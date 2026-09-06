<?php
/**
 * Contenido de serie: asunto y bloques ya escritos y traducidos, en
 * data/presets/<módulo>/<plantilla>.json, con `core` como carpeta de las plantillas de PrestaShop.
 * Es lo que hace que una plantilla en modo diseñado tenga algo que enseñar antes de que nadie
 * escriba una línea.
 *
 * Formato: {"subject": {"es": "...", "en": "..."}, "blocks": {"es": [...], "en": [...]}}.
 * Los idiomas que no tengan preset heredan del idioma de referencia por el camino normal.
 *
 * @author BK Modules
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class BkMailDesignerPresets
{
    /**
     * @param string $module Módulo dueño de la plantilla, vacío para el núcleo
     * @param string $name   Nombre de plantilla
     *
     * @return array|null
     */
    public static function load($module, $name)
    {
        if (!preg_match('/^[a-z0-9_]+$/', $name) || !preg_match('/^[a-z0-9_-]*$/', $module)) {
            return null;
        }
        $file = self::dir() . ($module === '' ? 'core' : $module) . '/' . $name . '.json';
        if (!is_file($file)) {
            return null;
        }
        $data = json_decode(Tools::file_get_contents($file), true);

        return is_array($data) && isset($data['blocks']) ? $data : null;
    }

    /**
     * @return array Claves "módulo/plantilla" con preset, el núcleo con módulo vacío
     */
    public static function keys()
    {
        $out = [];
        foreach ((array) glob(self::dir() . '*/*.json') as $file) {
            $module = basename(dirname($file));
            $out[] = ($module === 'core' ? '' : $module) . '/' . basename($file, '.json');
        }

        return $out;
    }

    /**
     * @return string
     */
    private static function dir()
    {
        return _PS_MODULE_DIR_ . 'bkmaildesigner/data/presets/';
    }

    /**
     * Vuelca el preset en la plantilla, idioma a idioma, para los idiomas que el preset traiga.
     * Los idiomas de la tienda sin preset se dejan como estaban.
     *
     * @param BkMailDesignerTemplate $template
     * @param array                  $preset
     * @param int|null               $onlyLang Solo ese idioma, o todos
     *
     * @return int Idiomas rellenados
     */
    public static function apply(BkMailDesignerTemplate $template, array $preset, $onlyLang = null)
    {
        $filled = 0;
        foreach (Language::getLanguages(false) as $language) {
            $idLang = (int) $language['id_lang'];
            if ($onlyLang !== null && $idLang !== (int) $onlyLang) {
                continue;
            }
            $iso = self::presetIso($preset, $language['iso_code']);
            if ($iso === null) {
                continue;
            }
            $blocks = BkMailDesignerBlocks::sanitize($preset['blocks'][$iso]);
            if (empty($blocks)) {
                continue;
            }
            if (!is_array($template->blocks)) {
                $template->blocks = [];
            }
            if (!is_array($template->subject)) {
                $template->subject = [];
            }
            $template->blocks[$idLang] = json_encode($blocks, JSON_UNESCAPED_UNICODE);
            $template->subject[$idLang] = isset($preset['subject'][$iso]) ? Tools::substr((string) $preset['subject'][$iso], 0, 255) : '';
            ++$filled;
        }

        return $filled;
    }

    /**
     * El preset se busca por el código ISO de dos letras de la tienda.
     */
    private static function presetIso(array $preset, $iso)
    {
        $iso = strtolower(substr((string) $iso, 0, 2));

        return isset($preset['blocks'][$iso]) && is_array($preset['blocks'][$iso]) ? $iso : null;
    }
}
