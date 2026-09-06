<?php
/**
 * Pantalla del módulo: listado de plantillas, diseño del layout, ajustes y el editor de bloques.
 *
 * La plantilla es propia y no un HelperForm: la pantalla es una herramienta con vista previa en
 * vivo, no un formulario plano. Las acciones que el editor lanza por AJAX devuelven JSON y
 * terminan aquí; la vista previa devuelve el HTML del correo para pintarlo en un iframe.
 *
 * @author BK Modules
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class AdminBkMailDesignerController extends ModuleAdminController
{
    public function __construct()
    {
        $this->bootstrap = true;
        parent::__construct();
    }

    public function setMedia($isNewTheme = false)
    {
        parent::setMedia($isNewTheme);
        $this->addCSS($this->module->assetUrl('views/css/admin.css'), 'all', null, false);
        $this->addJqueryPlugin('chosen');
        $this->addJS($this->module->assetUrl('views/js/admin.js'), false);
    }

    public function initContent()
    {
        if (Tools::isSubmit('submitBkExport')) {
            $this->exportConfiguration();
        }
        if (Tools::isSubmit('submitBkLayout')) {
            $this->processLayout();
        } elseif (Tools::isSubmit('submitBkSettings')) {
            $this->processSettings();
        } elseif (Tools::isSubmit('submitBkImport')) {
            $this->importConfiguration();
        }

        $this->context->smarty->assign('bkmd_base', json_encode([
            'ajax' => self::$currentIndex . '&token=' . $this->token . '&ajax=1',
            'index' => self::$currentIndex . '&token=' . $this->token,
            'shop' => $this->shopId(),
        ]));

        if (Tools::getValue('bk_view') === 'edit') {
            $this->content .= $this->renderEditor();
        } else {
            $this->content .= $this->renderIndex();
        }

        parent::initContent();
    }

    // ---- pantalla principal --------------------------------------------------------------------

    private function renderIndex()
    {
        $shop = $this->shop();
        $languages = $this->languages();
        $rows = BkMailDesignerTemplate::allByKey($shop->id);
        $list = [];
        $counts = [
            'total' => 0, 'designed' => 0, 'wrapped' => 0, 'original' => 0,
            'core' => 0, 'modules' => 0, 'untranslated' => 0,
        ];

        foreach (BkMailDesignerScanner::discover($shop) as $found) {
            $key = $found['module'] . '/' . $found['name'];
            $defaults = BkMailDesignerDefaults::forTemplate($found['module'], $found['name']);
            $row = isset($rows[$key]) ? $rows[$key] : null;
            $active = $row ? $row['active'] : BkMailDesignerConfig::getNewMode() === 'wrapped';
            $mode = $row ? $row['mode'] : BkMailDesignerConfig::MODE_WRAPPED;
            $state = !$active ? 'original' : $mode;
            ++$counts['total'];
            ++$counts[$state];
            ++$counts[$found['module'] === '' ? 'core' : 'modules'];

            $langs = [];
            $inherited = false;
            foreach ($languages as $language) {
                $idLang = (int) $language['id_lang'];
                $own = $row && isset($row['langs'][$idLang]) && $row['langs'][$idLang]['has_blocks'];
                $langState = $state === 'designed'
                    ? ($own ? 'own' : 'inherited')
                    : (isset($found['langs'][$language['iso_code']]) ? 'file' : 'missing');
                $inherited = $inherited || $langState === 'inherited';
                $langs[] = [
                    'iso' => $language['iso_code'],
                    'name' => $language['name'],
                    'state' => $langState,
                ];
            }
            if ($inherited) {
                ++$counts['untranslated'];
            }

            $subject = $row && isset($row['langs'][(int) $this->context->language->id])
                ? $row['langs'][(int) $this->context->language->id]['subject'] : '';
            if ($subject === '') {
                $subject = $this->originalTitle($found, $this->context->language->iso_code);
            }

            $list[] = [
                'module' => $found['module'],
                'name' => $found['name'],
                'key' => $key,
                'subject' => $subject,
                'state' => $state,
                'mode' => $mode,
                'active' => $active,
                'langs' => $langs,
                'date_upd' => $row ? $row['date_upd'] : '',
                'audience' => BkMailDesignerScanner::audience($found['module'], $found['name']),
                'default_mode' => $defaults['mode'],
                'default_reason' => $defaults['reason'],
                'preset' => BkMailDesignerPresets::load($found['module'], $found['name']) !== null,
                'edit_url' => self::$currentIndex . '&token=' . $this->token . '&bk_view=edit&bk_module=' . urlencode($found['module']) . '&bk_name=' . urlencode($found['name']),
            ];
        }

        $layout = BkMailDesignerLayout::forShop($shop->id);
        // Los colores de zona guardados vacíos se enseñan con el de marca: el formulario siempre
        // manda un color concreto y en la pantalla no hay un campo de color en blanco.
        $layoutSettings = $layout->getSettings();
        foreach (['color_band', 'color_header_bg'] as $key) {
            if ($layoutSettings[$key] === '') {
                $layoutSettings[$key] = $layoutSettings['color_primary'];
            }
        }
        if ($layoutSettings['color_header_text'] === '') {
            $layoutSettings['color_header_text'] = '#ffffff';
        }

        $layoutLangs = [];
        foreach ($languages as $language) {
            $idLang = (int) $language['id_lang'];
            $layoutLangs[] = [
                'id' => $idLang,
                'iso' => $language['iso_code'],
                'name' => $language['name'],
                'header_links' => $layout->links('header_links', $idLang),
                'footer_links' => $layout->links('footer_links', $idLang),
                'footer_legal' => $layout->text('footer_legal', $idLang),
                'footer_reason' => $layout->reasons($idLang),
            ];
        }

        $values = [];
        foreach (array_keys(BkMailDesignerConfig::getDefaults()) as $key) {
            $values[$key] = Configuration::get($key);
        }
        $values[BkMailDesignerConfig::REF_LANG] = BkMailDesignerConfig::getRefLang();
        $values[BkMailDesignerConfig::NEW_MODE] = BkMailDesignerConfig::getNewMode();
        $values[BkMailDesignerConfig::TEST_EMAIL] = BkMailDesignerConfig::getTestEmail();

        $this->context->smarty->assign([
            'bkmd_list' => $list,
            'bkmd_counts' => $counts,
            'bkmd_languages' => $languages,
            'bkmd_layout' => $layoutSettings,
            'bkmd_networks' => BkMailDesignerLayout::NETWORKS,
            'bkmd_social_base' => $this->module->getPathUri() . 'views/img/social/',
            'bkmd_order_emails' => $this->orderEmails($list),
            'bkmd_sources' => $this->sources($list),
            'bkmd_layout_langs' => $layoutLangs,
            'bkmd_fonts' => BkMailDesignerLayout::FONTS,
            'bkmd_logo' => BkMailDesignerSample::logoUrl(),
            'bkmd_logo_link' => $this->context->link->getAdminLink('AdminThemes'),
            'bkmd_v' => $values,
            'bkmd_log' => BkMailDesignerLogger::tail(),
            'bkmd_action' => self::$currentIndex . '&token=' . $this->token,
            'bkmd_context_lang' => (int) $this->context->language->id,
            'bkmd_first_tpl' => $this->firstDesignable($list),
            'bkmd_ref_lang_name' => $this->refLangName($languages),
            'bkmd_themes' => BkMailDesignerTheme::all($this->context->language->iso_code),
            'bkmd_theme_current' => BkMailDesignerTheme::current($shop->id),
            'bkmd_audit' => $this->auditRows($shop),
            'bkmd_theme_tpl' => $this->firstDesignable($list),
            'bkmd_info_panel' => $this->renderInfoPanel(),
            'bkmd_update_notice' => $this->renderUpdateNotice(),
        ]);

        return $this->context->smarty->fetch($this->dir() . 'index.tpl');
    }

    /**
     * Traduce el resultado del repaso a lo que se enseña: cada aviso con su título y su
     * explicación en el idioma del back office.
     *
     * @param Shop $shop
     *
     * @return array
     */
    private function auditRows(Shop $shop)
    {
        $m = $this->module;
        $words = [
            'PS_MAIL_TYPE_HTML' => [
                $m->t('Emails go out in HTML'),
                $m->t('The design is visible. PrestaShop also sends the plain-text version alongside it.'),
            ],
            'PS_MAIL_TYPE_TEXT' => [
                $m->t('Emails go out as plain text only'),
                $m->t('Nobody sees the design. Change it in Advanced Parameters › Email.'),
            ],
            'THEME_NO_OVERRIDES' => [
                $m->t('No template is overridden by your theme'),
                $m->t('Every email is read from where PrestaShop or its module keeps it.'),
            ],
            'THEME_OVERRIDES' => [
                $m->t('Your theme overrides templates that are read from the file'),
                $m->t('Those emails go out in «Header and footer» or switched off, and in both the file is what is read: the theme copy wins over the PrestaShop one. Put them in «Full design» or delete the files if you no longer need them.'),
            ],
            'THEME_OVERRIDES_IGNORED' => [
                $m->t('Your theme overrides some templates, and it does not matter'),
                $m->t('Those emails go out in «Full design», and there the original file is never read: what is sent is written in the editor. The files can stay where they are.'),
            ],
            'NO_MAIL_OVERRIDE' => [
                $m->t('The Mail class of PrestaShop is untouched'),
                $m->t('The hooks this module needs are the ones PrestaShop ships.'),
            ],
            'MAIL_OVERRIDE' => [
                $m->t('There is an override of the Mail class'),
                $m->t('It keeps the email hooks, so this module still works. Worth knowing it is there.'),
            ],
            'MAIL_OVERRIDE_NO_HOOKS' => [
                $m->t('An override of the Mail class removes the email hooks'),
                $m->t('Without them this module cannot intervene and every email goes out as PrestaShop wrote it.'),
            ],
            'HOOKS_ALONE' => [
                $m->t('No other module touches the emails'),
                $m->t('Nothing else adds to or alters the body of what your shop sends.'),
            ],
            'HOOKS_SHARED' => [
                $m->t('Other modules also touch the emails'),
                $m->t('What they add lands inside your design as long as they come after this module.'),
            ],
            'HOOKS_BEFORE' => [
                $m->t('A module runs before this one'),
                $m->t('What it adds to the body may end up outside your design. Move this module to the first position in Design › Positions.'),
            ],
        ];

        $out = [];
        foreach (BkMailDesignerAudit::run($shop, $m) as $row) {
            $key = $row['title'];
            $out[] = [
                'level' => $row['level'],
                'title' => isset($words[$key]) ? $words[$key][0] : $key,
                'help' => isset($words[$key]) ? $words[$key][1] : '',
                'detail' => $row['detail'],
            ];
        }

        return $out;
    }

    /**
     * @param array $languages
     *
     * @return string Nombre del idioma de referencia
     */
    private function refLangName(array $languages)
    {
        $ref = BkMailDesignerConfig::getRefLang();
        foreach ($languages as $language) {
            if ((int) $language['id_lang'] === $ref) {
                return $language['name'];
            }
        }

        return '';
    }

    /**
     * Plantilla con la que se previsualiza el layout: la primera diseñada, o el pedido.
     */
    private function firstDesignable(array $list)
    {
        foreach ($list as $item) {
            if ($item['state'] === 'designed') {
                return ['module' => $item['module'], 'name' => $item['name']];
            }
        }
        foreach ($list as $item) {
            if ($item['module'] === '' && $item['name'] === 'order_conf') {
                return ['module' => '', 'name' => 'order_conf'];
            }
        }

        return empty($list) ? ['module' => '', 'name' => 'test'] : ['module' => $list[0]['module'], 'name' => $list[0]['name']];
    }

    /**
     * Título del fichero original, que en las plantillas del núcleo es el asunto que se ve en la
     * pantalla de traducciones.
     */
    private function originalTitle(array $found, $iso)
    {
        $path = isset($found['langs'][$iso]) ? $found['langs'][$iso] : (empty($found['langs']) ? null : reset($found['langs']));
        if (!$path) {
            return '';
        }
        $head = @file_get_contents($path, false, null, 0, 4096);
        if ($head && preg_match('#<title>(.*?)</title>#is', $head, $m)) {
            return trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES, 'UTF-8'));
        }

        return '';
    }

    /**
     * Guardado del diseño desde la propia pantalla, sin recargar: la vista previa se queda donde
     * está y el trabajo continúa.
     */
    public function ajaxProcessBkSaveLayout()
    {
        $this->json(['ok' => $this->saveLayout()]);
    }

    private function processLayout()
    {
        if ($this->saveLayout()) {
            $this->confirmations[] = $this->module->t('Design saved.');
        } else {
            $this->errors[] = $this->module->t('The design could not be saved.');
        }
    }

    /**
     * @return bool
     */
    private function saveLayout()
    {
        $layout = BkMailDesignerLayout::forShop($this->shopId());
        $layout->settings = json_encode(BkMailDesignerLayout::sanitizeSettings((array) Tools::getValue('layout')));
        foreach ($this->languages() as $language) {
            $idLang = (int) $language['id_lang'];
            $links = Tools::getValue('header_links');
            $layout->header_links[$idLang] = json_encode(BkMailDesignerLayout::sanitizeLinks(
                json_decode(isset($links[$idLang]) ? $links[$idLang] : '[]', true)
            ));
            $links = Tools::getValue('footer_links');
            $layout->footer_links[$idLang] = json_encode(BkMailDesignerLayout::sanitizeLinks(
                json_decode(isset($links[$idLang]) ? $links[$idLang] : '[]', true)
            ));
            $legal = Tools::getValue('footer_legal');
            $layout->footer_legal[$idLang] = Tools::substr(strip_tags(isset($legal[$idLang]) ? $legal[$idLang] : ''), 0, 1000);
            $reason = Tools::getValue('footer_reason');
            $layout->footer_reason[$idLang] = BkMailDesignerLayout::encodeReasons(
                isset($reason[$idLang]) && is_array($reason[$idLang]) ? $reason[$idLang] : []
            );
        }
        if (!($layout->id ? $layout->update() : $layout->add())) {
            BkMailDesignerLogger::error('No se pudo guardar el layout de la tienda ' . $this->shopId());

            return false;
        }
        BkMailDesignerLogger::confirmation('Layout guardado (tienda ' . $this->shopId() . ')');

        return true;
    }

    /**
     * Descarga de toda la configuración en un JSON. Sale por aquí y termina: la pantalla entera no
     * hace falta para entregar un fichero.
     */
    private function exportConfiguration()
    {
        $data = BkMailDesignerTransfer::export($this->shopId());
        $name = 'bkmaildesigner-' . Tools::str2url(Configuration::get('PS_SHOP_NAME')) . '-' . date('Y-m-d') . '.json';

        header('Content-Type: application/json; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $name . '"');
        exit(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
    }

    private function importConfiguration()
    {
        if (empty($_FILES['bk_import']['tmp_name']) || !is_uploaded_file($_FILES['bk_import']['tmp_name'])) {
            $this->errors[] = $this->module->t('Choose a file to import.');

            return;
        }
        $raw = Tools::file_get_contents($_FILES['bk_import']['tmp_name']);
        $data = json_decode((string) $raw, true);
        if (!is_array($data)) {
            $this->errors[] = $this->module->t('That file is not a valid export.');

            return;
        }

        $result = BkMailDesignerTransfer::import($this->shopId(), $data);
        if (!$result['ok']) {
            BkMailDesignerLogger::error('Importación rechazada: ' . $result['error']);
            $this->errors[] = $result['error'] === 'format'
                ? $this->module->t('That file was exported by another version of the module.')
                : $this->module->t('The import could not be applied.');

            return;
        }

        BkMailDesignerLogger::confirmation('Importadas ' . $result['templates'] . ' plantillas');
        $this->confirmations[] = $this->module->t('Imported: %count% emails.', ['%count%' => $result['templates']]);
    }

    private function processSettings()
    {
        Configuration::updateValue(BkMailDesignerConfig::ENABLED, (int) Tools::getValue(BkMailDesignerConfig::ENABLED));
        Configuration::updateValue(BkMailDesignerConfig::DEBUG, (int) Tools::getValue(BkMailDesignerConfig::DEBUG));
        $ref = (int) Tools::getValue(BkMailDesignerConfig::REF_LANG);
        Configuration::updateValue(BkMailDesignerConfig::REF_LANG, Language::getIsoById($ref) ? $ref : (int) Configuration::get('PS_LANG_DEFAULT'));
        $mode = Tools::getValue(BkMailDesignerConfig::NEW_MODE);
        Configuration::updateValue(BkMailDesignerConfig::NEW_MODE, in_array($mode, BkMailDesignerConfig::NEW_MODES, true) ? $mode : 'wrapped');
        $email = trim((string) Tools::getValue(BkMailDesignerConfig::TEST_EMAIL));
        Configuration::updateValue(BkMailDesignerConfig::TEST_EMAIL, Validate::isEmail($email) ? $email : '');
        BkMailDesignerLogger::confirmation('Ajustes guardados');
        $this->confirmations[] = $this->module->t('Settings updated.');
    }

    /**
     * De dónde sale cada correo, con cuántos trae: es la pregunta que se hace quien acaba de
     * instalar un módulo y quiere ver solo los suyos.
     *
     * @param array $list
     *
     * @return array
     */
    private function sources(array $list)
    {
        $out = [];
        foreach ($list as $row) {
            $key = $row['module'];
            if (!isset($out[$key])) {
                $out[$key] = ['module' => $key, 'name' => $this->sourceName($key), 'count' => 0];
            }
            ++$out[$key]['count'];
        }
        uasort($out, function ($a, $b) {
            if ($a['module'] === '') {
                return -1;
            }
            if ($b['module'] === '') {
                return 1;
            }

            return strcasecmp($a['name'], $b['name']);
        });

        return array_values($out);
    }

    /**
     * @param string $module
     *
     * @return string
     */
    private function sourceName($module)
    {
        if ($module === '') {
            return 'PrestaShop';
        }
        $instance = Module::getInstanceByName($module);

        return $instance && $instance->displayName ? $instance->displayName : $module;
    }

    /**
     * Los dos correos que llevan líneas de pedido, con su enlace al editor: desde los ajustes de
     * las líneas se salta a cambiarlas en uno solo.
     *
     * @param array $list
     *
     * @return array
     */
    private function orderEmails(array $list)
    {
        $wanted = [['', 'order_conf'], ['ps_emailalerts', 'new_order']];
        $out = [];
        foreach ($list as $row) {
            foreach ($wanted as $pair) {
                if ($row['module'] === $pair[0] && $row['name'] === $pair[1]) {
                    $out[] = ['name' => $row['name'], 'url' => $row['edit_url']];
                }
            }
        }

        return $out;
    }

    // ---- editor ---------------------------------------------------------------------------------

    private function renderEditor()
    {
        $shop = $this->shop();
        $module = $this->param('bk_module');
        $name = $this->param('bk_name');
        if ($name === '') {
            $this->errors[] = $this->module->t('Unknown template.');

            return '';
        }

        $template = BkMailDesignerTemplate::find($shop->id, $module, $name);
        $layoutSettings = BkMailDesignerLayout::forShop($shop->id)->getSettings();
        $groups = $this->varGroups($module, $name, $shop, $template);
        $vars = BkMailDesignerVars::flatten($groups);

        $languages = [];
        $ref = BkMailDesignerConfig::getRefLang();
        foreach ($this->languages() as $language) {
            $idLang = (int) $language['id_lang'];
            $languages[] = [
                'id' => $idLang,
                'iso' => $language['iso_code'],
                'name' => $language['name'],
                'is_ref' => $idLang === $ref,
                'subject' => $template->subjectFor($idLang),
                'blocks' => $template->decodeBlocks($idLang),
                'original_title' => $this->originalTitle(['langs' => [$language['iso_code'] => BkMailDesignerScanner::originalPath($module, $name, $language['iso_code'], $shop)]], $language['iso_code']),
                'has_file' => BkMailDesignerScanner::originalPath($module, $name, $language['iso_code'], $shop) !== null,
            ];
        }

        $this->context->smarty->assign([
            'bkmd_tpl' => [
                'module' => $module,
                'name' => $name,
                'mode' => $template->mode,
                'active' => (bool) $template->active,
                'is_new' => !$template->id,
                'preset' => BkMailDesignerPresets::load($module, $name) !== null,
            ],
            'bkmd_editor' => json_encode([
                'module' => $module,
                'name' => $name,
                'mode' => $template->mode,
                'active' => (bool) $template->active,
                'languages' => $languages,
                'vars' => $vars,
                'groups' => $groups,
                'group_names' => $this->groupNames(),
                'globals' => BkMailDesignerScanner::GLOBAL_VARS,
                'labels' => $this->module->defaultLabels(),
                // Presentación del tema: las etiquetas de columna solo se piden si se va a las
                // cinco columnas, y el editor las esconde si no
                'order_layout' => $layoutSettings['order_layout'],
                'context_lang' => (int) $this->context->language->id,
                'test_email' => BkMailDesignerConfig::getTestEmail(),
                'back' => self::$currentIndex . '&token=' . $this->token,
                'i18n' => $this->editorStrings(),
            ]),
            'bkmd_languages' => $languages,
            'bkmd_var_groups' => $groups,
            'bkmd_group_names' => $this->groupNames(),
            'bkmd_back' => self::$currentIndex . '&token=' . $this->token,
            'bkmd_test_email' => BkMailDesignerConfig::getTestEmail(),
        ]);

        return $this->context->smarty->fetch($this->dir() . 'editor.tpl');
    }

    /**
     * Las variables de una plantilla, agrupadas y con el valor que toman hoy. Es la única
     * implementación: la usan el editor y la ventana de prueba del listado.
     *
     * @param string                 $module
     * @param string                 $name
     * @param Shop                   $shop
     * @param BkMailDesignerTemplate $template
     *
     * @return array
     */
    private function varGroups($module, $name, Shop $shop, BkMailDesignerTemplate $template)
    {
        $names = array_values(array_unique(array_merge(
            BkMailDesignerScanner::variables($module, $name, $shop),
            array_keys(BkMailDesignerConfig::getCustomVars($shop->id)),
            BkMailDesignerScanner::GLOBAL_VARS
        )));

        return BkMailDesignerVars::grouped(
            $module,
            $name,
            $shop,
            $template,
            BkMailDesignerSample::vars($names, $this->context),
            BkMailDesignerConfig::getSamples($this->shopId())
        );
    }

    /**
     * @return array Nombre de cada grupo en el idioma del back office
     */
    private function groupNames()
    {
        $m = $this->module;

        return [
            'own' => $m->t('Of this email'),
            'lines' => $m->t('Order lines'),
            'totals' => $m->t('Amounts'),
            'address' => $m->t('Addresses'),
            'customer' => $m->t('Customer'),
            'order' => $m->t('Order'),
            'custom' => $m->t('Added by you'),
            'shop' => $m->t('Your shop'),
        ];
    }

    /**
     * Las variables de una plantilla para la ventana de prueba del listado.
     */
    public function ajaxProcessBkSamples()
    {
        $shop = $this->shop();
        $module = $this->param('bk_module');
        $name = $this->param('bk_name');
        $template = BkMailDesignerTemplate::find($shop->id, $module, $name);

        $this->json([
            'ok' => true,
            'groups' => $this->varGroups($module, $name, $shop, $template),
            'names' => $this->groupNames(),
        ]);
    }

    /**
     * Cadenas que el editor pinta desde JavaScript.
     */
    private function editorStrings()
    {
        $m = $this->module;

        return [
            'heading' => $m->t('Heading'), 'text' => $m->t('Text'), 'button' => $m->t('Button'),
            'box' => $m->t('Highlight box'), 'divider' => $m->t('Divider'), 'spacer' => $m->t('Spacer'),
            'image' => $m->t('Image'), 'columns' => $m->t('Columns'), 'order' => $m->t('Order lines'),
            'media' => $m->t('Image and text'), 'social' => $m->t('Social networks'), 'hero' => $m->t('Cover'),
            'label_columns_count' => $m->t('Columns'), 'label_third' => $m->t('Third column'),
            'label_valign' => $m->t('Vertical alignment'), 'valign_top' => $m->t('Top'), 'valign_middle' => $m->t('Middle'),
            'label_heading' => $m->t('Headline'), 'label_btn_text' => $m->t('Button text'), 'label_btn_url' => $m->t('Button link'),
            'label_veil' => $m->t('Darken the photo'), 'label_hero_height' => $m->t('Height'),
            'label_text_color' => $m->t('Text colour'),
            'hero_hint' => $m->t('The photo goes behind and the text on top. Without a photo it is a band of colour. Darken it until the text reads.'),
            'label_side' => $m->t('Image side'), 'side_left' => $m->t('Left'), 'side_right' => $m->t('Right'),
            'label_bleed' => $m->t('Full width, ignoring the side margin'),
            'social_hint' => $m->t('The networks and their look are set once in Design › Footer.'),
            'addresses' => $m->t('Addresses'), 'html' => $m->t('HTML'),
            'new_heading' => $m->t('New heading'), 'new_text' => $m->t('Write your text here.'),
            'new_button' => $m->t('Open my account'),
            'saved' => $m->t('Saved.'), 'save_error' => $m->t('Could not save. Check the log.'),
            'sent' => $m->t('Test email sent to %s.'), 'send_error' => $m->t('The test email could not be sent.'),
            'confirm_reset' => $m->t('Remove the content of every language and start again?'),
            'confirm_preset' => $m->t('Replace the current content with the built-in one? Languages without a built-in version keep what they have.'),
            'inherits' => $m->t('No own content: inherits from %s.'),
            'unsaved' => $m->t('You have unsaved changes.'),
            'empty' => $m->t('Add a block from the left, or load the built-in content.'),
            'var_hint' => $m->t('Click a variable to insert it at the cursor.'),
            'remove' => $m->t('Remove'), 'duplicate' => $m->t('Duplicate'), 'up' => $m->t('Move up'), 'down' => $m->t('Move down'),
            'label_text' => $m->t('Text'), 'label_url' => $m->t('Link'), 'label_align' => $m->t('Alignment'),
            'label_style' => $m->t('Style'), 'label_size' => $m->t('Size'), 'label_tone' => $m->t('Tone'),
            'label_height' => $m->t('Height (px)'), 'label_width' => $m->t('Width (px)'), 'label_alt' => $m->t('Alternative text'),
            'label_src' => $m->t('Image URL'), 'label_left' => $m->t('Left column'), 'label_right' => $m->t('Right column'),
            'label_labels' => $m->t('Labels'), 'label_html' => $m->t('HTML code'),
            'label_source' => $m->t('Line table variable'), 'label_totals' => $m->t('Show totals'),
            'total_always' => $m->t('Always'), 'total_auto' => $m->t('Only if it has an amount'),
            'total_never' => $m->t('Never'),
            'label_words' => $m->t('The words of this block'),
            'label_advanced' => $m->t('Advanced'),
            'source_hint' => $m->t('PrestaShop sends the products in {products}. Some modules use another name — ps_emailalerts uses {items}.'),
            'label_free_shipping' => $m->t('Text when shipping is free'),
            'totals_hint' => $m->t('«Only if it has an amount» hides the line when it comes to zero. Shipping at zero shows the free shipping text instead of hiding.'),
            'label_columns' => $m->t('Column headings'),
            'yes' => $m->t('Yes'), 'no' => $m->t('No'),
            'label_order_layout' => $m->t('Presentation'), 'label_line_photo' => $m->t('Product photo'),
            'label_line_size' => $m->t('Photo size'), 'label_line_reference' => $m->t('Reference'),
            'label_line_options' => $m->t('Combination and customisations'), 'label_line_unit' => $m->t('Quantity and unit price'),
            'opt_theme' => $m->t('From the theme'), 'opt_stacked' => $m->t('One per line'), 'opt_table' => $m->t('Five columns'),
            'label_color' => $m->t('Own colour'), 'label_font_size' => $m->t('Text size'),
            'label_button_size' => $m->t('Button size'), 'label_full' => $m->t('Across the full width'),
            'label_width_pct' => $m->t('Width'), 'label_radius' => $m->t('Rounded corners'),
            'label_ratio' => $m->t('Column split'), 'label_pad_top' => $m->t('Space above'),
            'label_pad_bottom' => $m->t('Space below'), 'label_background' => $m->t('Background colour'),
            'label_common' => $m->t('More options for this block'),
            'group_content' => $m->t('Content'), 'group_look' => $m->t('How it looks'),
            'label_source_code' => $m->t('Edit the HTML'), 'label_wysiwyg' => $m->t('Back to the text'),
            'underline' => $m->t('Underline'), 'numbered' => $m->t('Numbered list'),
            'unlink' => $m->t('Remove the link'), 'clear_format' => $m->t('Clear formatting'), 'auto' => $m->t('From the theme'),
            'rule_solid' => $m->t('Hairline'), 'rule_dotted' => $m->t('Dotted'),
            'rule_thick' => $m->t('Thick, in the brand colour'),
            'align_left' => $m->t('Left'), 'align_center' => $m->t('Centred'), 'align_right' => $m->t('Right'),
            'solid' => $m->t('Filled'), 'outline' => $m->t('Outlined'), 'h1' => $m->t('Large'), 'h2' => $m->t('Small'),
            'normal' => $m->t('Normal'), 'muted' => $m->t('Small print'), 'neutral' => $m->t('Neutral'), 'primary' => $m->t('Brand colour'),
            'success' => $m->t('Success'), 'warning' => $m->t('Warning'),
            'bold' => $m->t('Bold'), 'italic' => $m->t('Italic'), 'link' => $m->t('Link'), 'list' => $m->t('List'),
            'link_prompt' => $m->t('Link URL (or a variable such as {url}):'),
            'preview_fail' => $m->t('The preview could not be rendered.'),
            'no_blocks' => $m->t('A full design needs at least one block. Add one, or switch this email to header and footer.'),
        ];
    }

    // ---- AJAX -----------------------------------------------------------------------------------

    /**
     * HTML del correo con datos de ejemplo. Admite bloques, asunto y layout sin guardar, para que
     * la vista previa siga a lo que se está editando.
     */
    public function ajaxProcessBkPreview()
    {
        $shop = $this->shop();
        $module = $this->param('bk_module');
        $name = $this->param('bk_name');
        $idLang = (int) Tools::getValue('id_lang') ?: (int) $this->context->language->id;

        $template = BkMailDesignerTemplate::find($shop->id, $module, $name);
        $draftMode = Tools::getValue('mode');
        // «Sin cambios» no es un modo guardado: es el correo tal como lo manda PrestaShop, y la
        // vista previa tiene que enseñar eso mismo y no la versión envuelta.
        if ($draftMode === 'original') {
            $this->previewOriginal($module, $name, (int) $idLang, $shop);
        }
        if (in_array($draftMode, BkMailDesignerConfig::MODES, true)) {
            $template->mode = $draftMode;
        }
        $draftBlocks = Tools::getValue('blocks');
        if ($draftBlocks !== false && $draftBlocks !== null) {
            $decoded = json_decode((string) $draftBlocks, true);
            if (!is_array($template->blocks)) {
                $template->blocks = [];
            }
            $template->blocks[$idLang] = json_encode(BkMailDesignerBlocks::sanitize($decoded));
        }
        $draftSubject = Tools::getValue('subject');
        if ($draftSubject !== false && $draftSubject !== null) {
            if (!is_array($template->subject)) {
                $template->subject = [];
            }
            $template->subject[$idLang] = (string) $draftSubject;
        }

        $layout = BkMailDesignerLayout::forShop($shop->id);
        // La galería pide la vista previa de un tema que todavía no está aplicado
        $themeKey = (string) Tools::getValue('bk_theme');
        if ($themeKey !== '') {
            $theme = BkMailDesignerTheme::load($themeKey, $this->context->language->iso_code);
            if ($theme !== null) {
                // Lo mismo que al aplicarlo de verdad: el logo y el CSS propio son de la tienda
                $settings = $theme['settings'];
                $current = $layout->getSettings();
                foreach (['logo_url', 'logo_width', 'custom_css', 'social', 'social_style', 'social_shape', 'social_size'] as $keep) {
                    $settings[$keep] = $current[$keep];
                }
                $layout->settings = json_encode($settings);
            }
        }
        $draftLayout = Tools::getValue('layout');
        if (is_array($draftLayout)) {
            $layout->settings = json_encode(BkMailDesignerLayout::sanitizeSettings($draftLayout));
            foreach (['header_links', 'footer_links', 'footer_legal', 'footer_reason'] as $field) {
                $value = Tools::getValue($field);
                if (!is_array($value) || !isset($value[$idLang])) {
                    continue;
                }
                if (!is_array($layout->$field)) {
                    $layout->$field = [];
                }
                // El motivo del pie llega partido por audiencia y se guarda como JSON, igual que
                // al enviar el formulario: la vista previa tiene que verlo como lo verá el correo.
                $layout->{$field}[$idLang] = $field === 'footer_reason'
                    ? BkMailDesignerLayout::encodeReasons(is_array($value[$idLang]) ? $value[$idLang] : [])
                    : (string) $value[$idLang];
            }
        }

        $out = $this->renderWith($template, $layout, $idLang, $shop, (bool) Tools::getValue('mark'));

        header('Content-Type: text/html; charset=UTF-8');
        if ($out === null) {
            exit('<!DOCTYPE html><html><body style="font-family:sans-serif;padding:24px;color:#6b7a86;">' . htmlspecialchars($this->module->t('Nothing to show yet: the original template could not be read, or the design has no blocks.')) . '</body></html>');
        }
        exit(Tools::getValue('type') === 'txt' ? '<pre style="white-space:pre-wrap;font:13px/1.5 monospace;padding:16px;">' . htmlspecialchars($out['txt']) . '</pre>' : $out['html']);
    }

    /**
     * El correo original, sin pasar por el módulo: es lo que se envía cuando la plantilla está
     * apagada, y lo único honesto que se puede enseñar en el modo «sin cambios».
     *
     * @param string $module
     * @param string $name
     * @param int    $idLang
     * @param Shop   $shop
     */
    private function previewOriginal($module, $name, $idLang, Shop $shop)
    {
        $language = new Language($idLang);
        $path = BkMailDesignerScanner::originalPath($module, $name, $language->iso_code, $shop);
        header('Content-Type: text/html; charset=UTF-8');
        if ($path === null) {
            exit('<!DOCTYPE html><html><body style="font-family:sans-serif;padding:24px;color:#6b7a86;">'
                . htmlspecialchars($this->module->t('There is no original file for this language.')) . '</body></html>');
        }

        $html = (string) Tools::file_get_contents($path);
        $names = array_unique(array_merge(
            BkMailDesignerScanner::variables($module, $name, $shop),
            array_keys(BkMailDesignerConfig::getCustomVars($shop->id))
        ));
        $vars = array_merge(
            BkMailDesignerVars::globals((int) $shop->id, $idLang),
            BkMailDesignerSample::vars($names, $this->context)
        );
        $strings = [];
        foreach ($vars as $key => $value) {
            if (is_scalar($value) || $value === null) {
                $strings[(string) $key] = (string) $value;
            }
        }

        if (Tools::getValue('type') === 'txt') {
            exit('<pre style="white-space:pre-wrap;font:13px/1.5 monospace;padding:16px;">'
                . htmlspecialchars(BkMailDesignerText::fromHtml(strtr($html, $strings))) . '</pre>');
        }

        exit(strtr($html, $strings));
    }

    private function renderWith(BkMailDesignerTemplate $template, BkMailDesignerLayout $layout, $idLang, Shop $shop, $mark = false)
    {
        $language = new Language((int) $idLang);
        $iso = $language->iso_code;
        $original = '';
        $path = BkMailDesignerScanner::originalPath($template->module, $template->name, $iso, $shop);
        if ($path) {
            $original = (string) Tools::file_get_contents($path);
        }
        $names = array_unique(array_merge(
            BkMailDesignerScanner::variables($template->module, $template->name, $shop),
            array_keys(BkMailDesignerConfig::getCustomVars($shop->id)),
            $this->varsInBlocks($template, $idLang)
        ));
        $vars = BkMailDesignerSample::vars($names, $this->context);

        return BkMailDesignerRenderer::render([
            'layout' => $layout,
            'template' => $template,
            'id_lang' => (int) $idLang,
            'iso' => $iso,
            'rtl' => (bool) $language->is_rtl,
            'original' => $original,
            'vars' => $vars,
            'labels' => $this->module->defaultLabels((int) $idLang),
            'shop_name' => Configuration::get('PS_SHOP_NAME', null, null, (int) $shop->id),
            'mark' => $mark,
        ]);
    }

    /**
     * Variables que el autor ha escrito en los bloques, para que la vista previa las resuelva
     * aunque el fichero original no las use.
     */
    private function varsInBlocks(BkMailDesignerTemplate $template, $idLang)
    {
        list($blocks) = $template->blocksFor($idLang);
        $json = json_encode($blocks);
        if ($json && preg_match_all('/\{([a-z0-9_]+)\}/i', $json, $m)) {
            return array_unique($m[1]);
        }

        return [];
    }

    public function ajaxProcessBkSaveTemplate()
    {
        $shop = $this->shop();
        $template = BkMailDesignerTemplate::find($shop->id, $this->param('bk_module'), $this->param('bk_name'));
        $mode = Tools::getValue('mode');
        $template->mode = in_array($mode, BkMailDesignerConfig::MODES, true) ? $mode : BkMailDesignerConfig::MODE_WRAPPED;
        $template->active = (bool) (int) Tools::getValue('active');

        $subjects = Tools::getValue('subject');
        $blocks = Tools::getValue('blocks');
        if (!is_array($template->subject)) {
            $template->subject = [];
        }
        if (!is_array($template->blocks)) {
            $template->blocks = [];
        }
        foreach ($this->languages() as $language) {
            $idLang = (int) $language['id_lang'];
            if (is_array($subjects) && array_key_exists($idLang, $subjects)) {
                $template->subject[$idLang] = Tools::substr(strip_tags((string) $subjects[$idLang]), 0, 255);
            }
            if (is_array($blocks) && array_key_exists($idLang, $blocks)) {
                $clean = BkMailDesignerBlocks::sanitize(json_decode((string) $blocks[$idLang], true));
                $template->blocks[$idLang] = empty($clean) ? '' : json_encode($clean, JSON_UNESCAPED_UNICODE);
            }
        }

        $ok = $template->id ? $template->update() : $template->add();
        BkMailDesignerLogger::confirmation(($ok ? 'Guardada' : 'No se pudo guardar') . ' la plantilla ' . $this->param('bk_module') . '/' . $this->param('bk_name'));
        $this->json(['ok' => (bool) $ok]);
    }

    /**
     * Cambios rápidos desde el listado: encender o apagar, cambiar de modo.
     */
    public function ajaxProcessBkToggle()
    {
        $shop = $this->shop();
        $template = BkMailDesignerTemplate::find($shop->id, $this->param('bk_module'), $this->param('bk_name'));
        $active = Tools::getValue('active');
        if ($active !== false && $active !== null) {
            $template->active = (bool) (int) $active;
        }
        $mode = Tools::getValue('mode');
        if (in_array($mode, BkMailDesignerConfig::MODES, true)) {
            $template->mode = $mode;
            $template->active = true;
        }
        $ok = $template->id ? $template->update() : $template->add();
        $this->json(['ok' => (bool) $ok, 'active' => (bool) $template->active, 'mode' => $template->mode]);
    }

    /**
     * Vuelca el contenido de serie en todas las plantillas que lo tienen y las deja diseñadas. Es
     * la vuelta al punto de partida: sustituye lo escrito, por eso la pantalla lo confirma antes.
     */
    public function ajaxProcessBkBulk()
    {
        $shop = $this->shop();
        $n = 0;
        foreach (BkMailDesignerScanner::discover($shop) as $found) {
            $preset = BkMailDesignerPresets::load($found['module'], $found['name']);
            if ($preset === null) {
                continue;
            }
            $template = BkMailDesignerTemplate::find($shop->id, $found['module'], $found['name']);
            BkMailDesignerPresets::apply($template, $preset);
            $template->mode = BkMailDesignerConfig::MODE_DESIGNED;
            $template->active = true;
            if ($template->id ? $template->update() : $template->add()) {
                ++$n;
            }
        }
        BkMailDesignerLogger::confirmation('Contenido de serie recargado en ' . $n . ' plantillas');
        $this->json(['ok' => true, 'count' => $n]);
    }

    /**
     * Reenvía la confirmación de un pedido a su cliente, en el idioma del pedido y con sus datos
     * reales. Sale por `Mail::send()`, así que recorre los mismos hooks que el envío original y el
     * cliente recibe exactamente lo que recibiría hoy.
     */
    /**
     * Guarda con qué se rellena cada variable al previsualizar y al enviar una prueba. Un valor
     * vacío vuelve a lo automático.
     */
    public function ajaxProcessBkSaveSamples()
    {
        $input = Tools::getValue('samples');
        $samples = BkMailDesignerConfig::getSamples($this->shopId());
        if (is_array($input)) {
            foreach ($input as $name => $value) {
                if (!preg_match('/^[a-z0-9_]+$/i', (string) $name)) {
                    continue;
                }
                if (trim((string) $value) === '') {
                    unset($samples[$name]);
                } else {
                    $samples[$name] = (string) $value;
                }
            }
        }

        $ok = BkMailDesignerConfig::setSamples($samples, $this->shopId());
        BkMailDesignerLogger::confirmation('Valores de prueba guardados: ' . count($samples));
        $this->json(['ok' => $ok, 'count' => count($samples)]);
    }

    /**
     * Añade o quita una variable declarada a mano. Sirve para las que un módulo pasa sin que
     * aparezcan en su plantilla: leyendo ficheros no hay forma de descubrirlas.
     */
    public function ajaxProcessBkCustomVar()
    {
        $name = trim((string) Tools::getValue('name'));
        $name = ltrim(rtrim($name, '}'), '{');
        if (!preg_match('/^[a-z0-9_]+$/i', $name)) {
            $this->json(['ok' => false, 'error' => 'name']);
        }

        $custom = BkMailDesignerConfig::getCustomVars($this->shopId());
        if (Tools::getValue('remove')) {
            unset($custom[$name]);
        } else {
            $custom[$name] = (string) Tools::getValue('value');
        }
        $ok = BkMailDesignerConfig::setCustomVars($custom, $this->shopId());
        BkMailDesignerLogger::confirmation('Variables añadidas a mano: ' . count($custom));

        $this->json(['ok' => $ok, 'name' => $name]);
    }

    public function ajaxProcessBkResendOrder()
    {
        $order = new Order((int) Tools::getValue('id_order'));
        if (!Validate::isLoadedObject($order)) {
            $this->json(['ok' => false, 'error' => 'order']);
        }

        $customer = new Customer((int) $order->id_customer);
        if (!Validate::isLoadedObject($customer) || !Validate::isEmail($customer->email)) {
            $this->json(['ok' => false, 'error' => 'customer']);
        }

        // El correo se redacta en el idioma del pedido, no en el del back office
        $idLang = (int) $order->id_lang;
        $context = Context::getContext();
        $before = $context->language;
        $context->language = new Language($idLang);
        $vars = BkMailDesignerOrderVars::forOrder($order, $context);
        $context->language = $before;

        // Las globales las pone Mail::send(); pasarlas aquí pisaría el logo incrustado
        foreach (BkMailDesignerScanner::GLOBAL_VARS as $global) {
            unset($vars['{' . $global . '}']);
        }

        $sent = Mail::send(
            $idLang,
            'order_conf',
            $this->module->t('Order confirmation'),
            $vars,
            $customer->email,
            $customer->firstname . ' ' . $customer->lastname,
            null,
            null,
            null,
            null,
            _PS_MAIL_DIR_,
            false,
            (int) $order->id_shop
        );

        BkMailDesignerLogger::confirmation(
            'Confirmación del pedido ' . $order->getUniqReference() . ' reenviada a ' . $customer->email . ': ' . ($sent ? 'enviada' : 'fallo')
        );

        $this->json(['ok' => (bool) $sent]);
    }

    /**
     * Aplica un tema al layout de la tienda.
     *
     * El tema es el aspecto, no el contenido: el logo de la tienda y el CSS que haya escrito el
     * comerciante no son suyos y se conservan. Los textos de cabecera y pie tampoco se tocan.
     */
    /**
     * Sube una imagen y devuelve su dirección.
     *
     * Va a `img/bkmaildesigner/` y no a la carpeta del módulo: lo que vive dentro del módulo
     * desaparece al actualizarlo, y un icono del pie tiene que sobrevivir a eso.
     */
    public function ajaxProcessBkUpload()
    {
        if (empty($_FILES['file']['tmp_name'])) {
            $this->json(['ok' => false, 'error' => $this->module->t('No file arrived.')]);
        }

        $file = $_FILES['file'];
        $error = ImageManager::validateUpload($file, Tools::getMaxUploadSize());
        if ($error !== false) {
            $this->json(['ok' => false, 'error' => $error]);
        }

        $dir = _PS_IMG_DIR_ . 'bkmaildesigner/';
        if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
            BkMailDesignerLogger::error('No se pudo crear ' . $dir);
            $this->json(['ok' => false, 'error' => $this->module->t('The folder could not be created.')]);
        }

        $extension = Tools::strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, ['png', 'jpg', 'jpeg', 'gif', 'webp'], true)) {
            $this->json(['ok' => false, 'error' => $this->module->t('Only images are accepted.')]);
        }
        $name = Tools::str2url(pathinfo($file['name'], PATHINFO_FILENAME)) . '-' . Tools::substr(md5(uniqid('', true)), 0, 8) . '.' . $extension;

        if (!move_uploaded_file($file['tmp_name'], $dir . $name)) {
            BkMailDesignerLogger::error('No se pudo guardar la imagen ' . $name);
            $this->json(['ok' => false, 'error' => $this->module->t('The image could not be saved.')]);
        }
        @chmod($dir . $name, 0644);
        BkMailDesignerLogger::confirmation('Imagen subida: ' . $name);

        $this->json(['ok' => true, 'url' => $this->context->shop->getBaseURL(true) . 'img/bkmaildesigner/' . $name]);
    }

    public function ajaxProcessBkApplyTheme()
    {
        $shop = $this->shop();
        $theme = BkMailDesignerTheme::load((string) Tools::getValue('bk_theme'), $this->context->language->iso_code);
        if ($theme === null) {
            $this->json(['ok' => false]);
        }

        $layout = BkMailDesignerLayout::forShop($shop->id);
        $current = $layout->getSettings();
        $settings = $theme['settings'];
        foreach (['logo_url', 'logo_width', 'custom_css', 'social', 'social_style', 'social_shape', 'social_size'] as $keep) {
            $settings[$keep] = $current[$keep];
        }
        $layout->settings = json_encode(BkMailDesignerLayout::sanitizeSettings($settings));
        $ok = $layout->id ? $layout->update() : $layout->add();
        if ($ok) {
            Configuration::updateValue(BkMailDesignerConfig::THEME, $theme['key']);
            BkMailDesignerLogger::confirmation('Tema ' . $theme['key'] . ' aplicado a la tienda ' . (int) $shop->id);
        }

        $this->json(['ok' => (bool) $ok]);
    }

    public function ajaxProcessBkApplyPreset()
    {
        $shop = $this->shop();
        $module = $this->param('bk_module');
        $name = $this->param('bk_name');
        $preset = BkMailDesignerPresets::load($module, $name);
        if ($preset === null) {
            $this->json(['ok' => false]);
        }
        $template = BkMailDesignerTemplate::find($shop->id, $module, $name);
        $onlyLang = (int) Tools::getValue('id_lang') ?: null;
        $filled = BkMailDesignerPresets::apply($template, $preset, $onlyLang);
        $template->mode = BkMailDesignerConfig::MODE_DESIGNED;
        $template->active = true;
        $ok = $template->id ? $template->update() : $template->add();
        BkMailDesignerLogger::confirmation('Contenido de serie aplicado a ' . ($module !== '' ? $module . '/' : '') . $name . ' en ' . $filled . ' idiomas');
        $this->json(['ok' => (bool) $ok, 'filled' => $filled]);
    }

    public function ajaxProcessBkResetTemplate()
    {
        $shop = $this->shop();
        $template = BkMailDesignerTemplate::find($shop->id, $this->param('bk_module'), $this->param('bk_name'));
        if ($template->id) {
            foreach ($this->languages() as $language) {
                $template->subject[(int) $language['id_lang']] = '';
                $template->blocks[(int) $language['id_lang']] = '';
            }
            $template->mode = BkMailDesignerConfig::MODE_WRAPPED;
            $template->update();
        }
        $this->json(['ok' => true]);
    }

    /**
     * El correo de prueba sale por Mail::send() con la ruta de plantillas de su dueño: pasa por
     * los mismos hooks que un envío real, así que lo que llega es exactamente lo que recibiría
     * un cliente.
     */
    public function ajaxProcessBkSendTest()
    {
        $shop = $this->shop();
        $module = $this->param('bk_module');
        $name = $this->param('bk_name');
        $idLang = (int) Tools::getValue('id_lang') ?: (int) $this->context->language->id;
        $to = trim((string) Tools::getValue('email'));
        if (!Validate::isEmail($to)) {
            $this->json(['ok' => false, 'error' => 'email']);
        }

        $template = BkMailDesignerTemplate::find($shop->id, $module, $name);
        $names = array_unique(array_merge(
            BkMailDesignerScanner::variables($module, $name, $shop),
            array_keys(BkMailDesignerConfig::getCustomVars($shop->id)),
            $this->varsInBlocks($template, $idLang)
        ));
        $vars = BkMailDesignerSample::vars($names, $this->context);
        // Las globales las pone Mail::send(); pasarlas aquí pisaría el logo incrustado
        foreach (BkMailDesignerScanner::GLOBAL_VARS as $global) {
            unset($vars['{' . $global . '}']);
        }

        $path = $module !== '' ? _PS_MODULE_DIR_ . $module . '/mails/' : _PS_MAIL_DIR_;
        $subject = '[' . $this->module->t('Test') . '] ' . $name;
        $sent = Mail::send(
            $idLang,
            $name,
            $subject,
            $vars,
            $to,
            null,
            null,
            null,
            null,
            null,
            $path,
            false,
            (int) $shop->id
        );
        BkMailDesignerLogger::confirmation('Correo de prueba ' . ($module !== '' ? $module . '/' : '') . $name . ' a ' . $to . ': ' . ($sent ? 'enviado' : 'fallo'));
        $this->json(['ok' => (bool) $sent]);
    }

    // ---- utilidades -----------------------------------------------------------------------------

    private function json(array $data)
    {
        header('Content-Type: application/json');
        exit(json_encode($data));
    }

    /**
     * @return Shop La tienda del contexto; en "todas las tiendas", la principal
     */
    private function shop()
    {
        return new Shop($this->shopId());
    }

    private function shopId()
    {
        $id = (int) Shop::getContextShopID(true);

        return $id ?: (int) Configuration::get('PS_SHOP_DEFAULT');
    }

    private function languages()
    {
        return Language::getLanguages(true, $this->shopId());
    }

    /**
     * Nombre de módulo o de plantilla recibido por la URL, limpio.
     */
    private function param($key)
    {
        $value = (string) Tools::getValue($key);

        return preg_match('/^[a-z0-9_.-]*$/i', $value) ? $value : '';
    }

    /** @var array|null Catálogo remoto, pedido una vez por carga */
    private $remote = null;

    private function dir()
    {
        return _PS_MODULE_DIR_ . $this->module->name . '/views/templates/admin/';
    }

    /**
     * Módulos, artículos y última versión publicada, con caché de un día. Sin red se pinta la
     * lista que viaja con el módulo: el panel nunca se queda vacío.
     *
     * @return array
     */
    private function remote()
    {
        if ($this->remote !== null) {
            return $this->remote;
        }

        $remote = \BkModules\Registry\V2\Catalog::fetch(
            _PS_CACHE_DIR_ . 'bkmaildesigner_catalog.json',
            $this->context->language->iso_code,
            $this->module->name
        );

        if (empty($remote['catalog'])) {
            $remote['catalog'] = $this->getFallbackCatalog();
        }

        $remote = BkMailDesignerRemoteImage::localize($remote);

        $remote['links'] = array_merge(
            [
                'licenses' => 'https://bkmodules.com',
                'contact' => 'https://bkmodules.com',
                'blog' => 'https://bkmodules.com',
            ],
            array_filter(isset($remote['links']) ? $remote['links'] : [])
        );

        $this->remote = $remote;

        return $this->remote;
    }

    /**
     * Versión instalada frente a la última publicada.
     *
     * `known` distingue «estás al día» de «hoy no se ha podido preguntar»: sin red, o con un
     * módulo que todavía no tiene ninguna release, no se afirma ninguna de las dos cosas.
     *
     * @return array
     */
    private function versionState()
    {
        $remote = $this->remote();
        $latest = isset($remote['latest']['version']) ? (string) $remote['latest']['version'] : '';

        return [
            'installed' => $this->module->version,
            'latest' => $latest,
            'known' => $latest !== '',
            'outdated' => $latest !== '' && version_compare($this->module->version, $latest, '<'),
            'url' => !empty($remote['latest']['url']) ? $remote['latest']['url'] : $remote['links']['licenses'],
        ];
    }

    /**
     * Aviso de versión nueva, fuera de las pestañas: una actualización pendiente tiene que verse
     * al abrir la pantalla, no solo si al comerciante le da por mirar la pestaña de BK Modules.
     *
     * @return string
     */
    private function renderUpdateNotice()
    {
        $version = $this->versionState();
        if (!$version['outdated']) {
            return '';
        }

        $this->context->smarty->assign('bkmd_version', $version);

        return $this->context->smarty->fetch($this->dir() . 'update-notice.tpl');
    }

    private function renderInfoPanel()
    {
        $this->context->smarty->assign([
            'bk_remote' => $this->remote(),
            'bkmd_version' => $this->versionState(),
            'bkmd_display_name' => $this->module->displayName,
        ]);

        return $this->context->smarty->fetch($this->dir() . 'info-panel.tpl');
    }

    /**
     * Lista mínima para cuando bkmodules.com no contesta.
     */
    private function getFallbackCatalog()
    {
        return [
            [
                'name' => 'B2B & VIES Validation',
                'description' => 'Professional registration validated against the EU VIES database.',
                'url' => 'https://bkmodules.com',
                'image' => '',
            ],
            [
                'name' => 'Right of Withdrawal',
                'description' => 'The online withdrawal function required by EU Directive 2023/2673.',
                'url' => 'https://bkmodules.com',
                'image' => '',
            ],
            [
                'name' => 'EU Guarantee Notice',
                'description' => 'The harmonised legal guarantee notice required from September 2026.',
                'url' => 'https://bkmodules.com',
                'image' => '',
            ],
        ];
    }
}
