<?php
/**
 * Temas predefinidos: un juego de colores, tipografía y forma listos para aplicar de una vez,
 * en data/themes/<clave>.json. Un tema solo toca la presentación del layout; los textos vienen
 * del contenido de serie de cada plantilla, que se carga aparte.
 *
 * @author BK Modules
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class BkMailDesignerTheme
{
    /**
     * @param string $iso Idioma en el que se quieren el nombre y la descripción
     *
     * @return array [['key' => ..., 'name' => ..., 'description' => ..., 'settings' => [...]], ...]
     */
    public static function all($iso)
    {
        $out = [];
        foreach ((array) glob(self::dir() . '*.json') as $file) {
            $theme = self::load(basename($file, '.json'), $iso);
            if ($theme !== null) {
                $out[] = $theme;
            }
        }
        // El orden es del autor, no del alfabeto: la galería empieza por el tema más seguro
        usort($out, function ($a, $b) {
            return $a['order'] === $b['order'] ? strcmp($a['key'], $b['key']) : ($a['order'] < $b['order'] ? -1 : 1);
        });

        return $out;
    }

    /**
     * @param string $key
     * @param string $iso
     *
     * @return array|null
     */
    public static function load($key, $iso)
    {
        if (!preg_match('/^[a-z0-9_-]+$/', $key)) {
            return null;
        }
        $file = self::dir() . $key . '.json';
        if (!is_file($file)) {
            return null;
        }
        $data = json_decode(Tools::file_get_contents($file), true);
        if (!is_array($data) || !isset($data['settings'])) {
            return null;
        }

        return [
            'key' => $key,
            'order' => isset($data['order']) ? (int) $data['order'] : 99,
            'name' => self::text($data, 'name', $iso, $key),
            'description' => self::text($data, 'description', $iso, ''),
            'settings' => BkMailDesignerLayout::sanitizeSettings($data['settings']),
        ];
    }

    /**
     * Aplica el tema al layout de la tienda. El logo y los textos del pie no se tocan: son de la
     * tienda, no del tema, y perderlos al probar un tema sería peor que no poder probarlo.
     *
     * @param int    $idShop
     * @param string $key
     * @param string $iso
     *
     * @return bool
     */
    public static function apply($idShop, $key, $iso)
    {
        $theme = self::load($key, $iso);
        if ($theme === null) {
            return false;
        }

        $layout = BkMailDesignerLayout::forShop((int) $idShop);
        $current = $layout->getSettings();
        $settings = $theme['settings'];
        $settings['logo_url'] = $current['logo_url'];
        $settings['logo_width'] = $current['logo_width'];
        $layout->settings = json_encode($settings);

        $saved = $layout->id ? (bool) $layout->update() : (bool) $layout->add();
        if ($saved) {
            Configuration::updateValue(BkMailDesignerConfig::THEME, $key);
        }

        return $saved;
    }

    /**
     * Tema aplicado por última vez, o cadena vacía si el layout se ha tocado a mano después.
     *
     * @param int $idShop
     *
     * @return string
     */
    public static function current($idShop)
    {
        $key = (string) Configuration::get(BkMailDesignerConfig::THEME);
        $theme = self::load($key, 'en');
        if ($theme === null) {
            return '';
        }

        // Editar un color a mano deja de ser el tema: se compara lo que de verdad hay guardado.
        $settings = BkMailDesignerLayout::forShop((int) $idShop)->getSettings();
        foreach ($theme['settings'] as $field => $value) {
            // El logo, el CSS propio, las redes y los colores de zona son de la tienda, no del
            // tema: no cuentan para decidir si el tema sigue puesto.
            if (in_array($field, ['logo_url', 'logo_width', 'custom_css', 'social', 'social_style', 'social_shape', 'social_size', 'color_band', 'color_header_bg', 'color_header_text'], true)) {
                continue;
            }
            if (!isset($settings[$field]) || $settings[$field] !== $value) {
                return '';
            }
        }

        return $key;
    }

    /**
     * @param array  $data
     * @param string $field
     * @param string $iso
     * @param string $fallback
     *
     * @return string
     */
    private static function text(array $data, $field, $iso, $fallback)
    {
        if (!isset($data[$field]) || !is_array($data[$field])) {
            return $fallback;
        }
        $iso = Tools::strtolower(Tools::substr((string) $iso, 0, 2));
        if (isset($data[$field][$iso])) {
            return (string) $data[$field][$iso];
        }

        return isset($data[$field]['en']) ? (string) $data[$field]['en'] : $fallback;
    }

    private static function dir()
    {
        return _PS_MODULE_DIR_ . 'bkmaildesigner/data/themes/';
    }
}
