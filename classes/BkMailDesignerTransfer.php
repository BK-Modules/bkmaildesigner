<?php
/**
 * Exportar e importar toda la configuración del módulo en un único JSON: el layout de la tienda y
 * el contenido de cada plantilla en cada idioma.
 *
 * Es la vía para llevar de pruebas a producción sin repetir el trabajo, para guardar una copia
 * antes de tocar, y para pasar un diseño de una tienda a otra. Los idiomas viajan por su código
 * ISO, no por su identificador, porque el mismo idioma tiene otro id en cada tienda.
 *
 * @author BK Modules
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class BkMailDesignerTransfer
{
    /** Formato del fichero; sube cuando deje de poder leerse el anterior */
    const FORMAT = 1;

    /**
     * @param int $idShop
     *
     * @return array
     */
    public static function export($idShop)
    {
        $languages = Language::getLanguages(false);
        $layout = BkMailDesignerLayout::forShop((int) $idShop);

        $layoutLangs = [];
        foreach ($languages as $language) {
            $idLang = (int) $language['id_lang'];
            $layoutLangs[$language['iso_code']] = [
                'header_links' => $layout->links('header_links', $idLang),
                'footer_links' => $layout->links('footer_links', $idLang),
                'footer_legal' => $layout->text('footer_legal', $idLang),
                'footer_reason' => $layout->reasons($idLang),
            ];
        }

        $templates = [];
        $rows = Db::getInstance()->executeS(
            'SELECT `id_bk_maildesigner_template`, `module`, `name`, `mode`, `active`
             FROM `' . _DB_PREFIX_ . 'bk_maildesigner_template` WHERE `id_shop` = ' . (int) $idShop
        );
        foreach ((array) $rows as $row) {
            $template = new BkMailDesignerTemplate((int) $row['id_bk_maildesigner_template']);
            $langs = [];
            foreach ($languages as $language) {
                $idLang = (int) $language['id_lang'];
                $blocks = $template->decodeBlocks($idLang);
                $subject = $template->subjectFor($idLang);
                if (empty($blocks) && $subject === '') {
                    continue;
                }
                $langs[$language['iso_code']] = ['subject' => $subject, 'blocks' => $blocks];
            }
            $templates[] = [
                'module' => $row['module'],
                'name' => $row['name'],
                'mode' => $row['mode'],
                'active' => (bool) $row['active'],
                'langs' => $langs,
            ];
        }

        return [
            'format' => self::FORMAT,
            'module_version' => '1.0.0',
            'exported_at' => date('c'),
            'shop' => Configuration::get('PS_SHOP_NAME', null, null, (int) $idShop),
            'layout' => [
                'settings' => $layout->getSettings(),
                'langs' => $layoutLangs,
            ],
            'templates' => $templates,
        ];
    }

    /**
     * Vuelca un fichero exportado sobre la tienda. Lo que el fichero no traiga se queda como está:
     * un idioma que la tienda tiene y el fichero no, o una plantilla que aquí no existe, no se
     * borran ni se vacían.
     *
     * @param int   $idShop
     * @param array $data
     *
     * @return array ['ok' => bool, 'templates' => int, 'skipped' => int, 'error' => string]
     */
    public static function import($idShop, array $data)
    {
        if (!isset($data['format']) || (int) $data['format'] !== self::FORMAT || !isset($data['layout'])) {
            return ['ok' => false, 'templates' => 0, 'skipped' => 0, 'error' => 'format'];
        }

        $byIso = [];
        foreach (Language::getLanguages(false) as $language) {
            $byIso[$language['iso_code']] = (int) $language['id_lang'];
        }

        $layout = BkMailDesignerLayout::forShop((int) $idShop);
        if (isset($data['layout']['settings']) && is_array($data['layout']['settings'])) {
            $layout->settings = json_encode(BkMailDesignerLayout::sanitizeSettings($data['layout']['settings']));
        }
        if (isset($data['layout']['langs']) && is_array($data['layout']['langs'])) {
            foreach ($data['layout']['langs'] as $iso => $values) {
                if (!isset($byIso[$iso]) || !is_array($values)) {
                    continue;
                }
                $idLang = $byIso[$iso];
                $layout->header_links[$idLang] = json_encode(BkMailDesignerLayout::sanitizeLinks(
                    isset($values['header_links']) ? $values['header_links'] : []
                ));
                $layout->footer_links[$idLang] = json_encode(BkMailDesignerLayout::sanitizeLinks(
                    isset($values['footer_links']) ? $values['footer_links'] : []
                ));
                $layout->footer_legal[$idLang] = Tools::substr(strip_tags(isset($values['footer_legal']) ? $values['footer_legal'] : ''), 0, 1000);
                // Un fichero exportado antes de que hubiera tres motivos trae una sola cadena
                $reason = isset($values['footer_reason']) ? $values['footer_reason'] : [];
                $layout->footer_reason[$idLang] = BkMailDesignerLayout::encodeReasons(
                    is_array($reason) ? $reason : [BkMailDesignerScanner::AUDIENCE_RECIPIENT => $reason]
                );
            }
        }
        if (!($layout->id ? $layout->update() : $layout->add())) {
            return ['ok' => false, 'templates' => 0, 'skipped' => 0, 'error' => 'layout'];
        }

        $done = 0;
        $skipped = 0;
        foreach (isset($data['templates']) && is_array($data['templates']) ? $data['templates'] : [] as $item) {
            if (!is_array($item) || empty($item['name']) || !preg_match('/^[a-z0-9_.-]+$/i', $item['name'])) {
                ++$skipped;
                continue;
            }
            $module = isset($item['module']) && preg_match('/^[a-z0-9_-]*$/', $item['module']) ? $item['module'] : '';
            $template = BkMailDesignerTemplate::find((int) $idShop, $module, $item['name']);
            $template->mode = isset($item['mode']) && in_array($item['mode'], BkMailDesignerConfig::MODES, true)
                ? $item['mode'] : BkMailDesignerConfig::MODE_WRAPPED;
            $template->active = !empty($item['active']);

            if (isset($item['langs']) && is_array($item['langs'])) {
                foreach ($item['langs'] as $iso => $values) {
                    if (!isset($byIso[$iso]) || !is_array($values)) {
                        continue;
                    }
                    $idLang = $byIso[$iso];
                    $blocks = BkMailDesignerBlocks::sanitize(isset($values['blocks']) ? $values['blocks'] : []);
                    $template->blocks[$idLang] = empty($blocks) ? '' : json_encode($blocks, JSON_UNESCAPED_UNICODE);
                    $template->subject[$idLang] = Tools::substr(strip_tags(isset($values['subject']) ? $values['subject'] : ''), 0, 255);
                }
            }

            if ($template->id ? $template->update() : $template->add()) {
                ++$done;
            } else {
                ++$skipped;
            }
        }

        return ['ok' => true, 'templates' => $done, 'skipped' => $skipped, 'error' => ''];
    }
}
