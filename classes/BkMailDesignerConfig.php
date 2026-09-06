<?php
/**
 * Única fuente de configuración del módulo: toda clave nueva entra aquí, no en el controlador.
 *
 * @author BK Modules
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class BkMailDesignerConfig
{
    /** Interruptor maestro: apagado, todos los correos salen como los genera PrestaShop */
    const ENABLED = 'BK_MD_ON';
    /** Idioma del que heredan los demás mientras no tengan contenido propio (id_lang) */
    const REF_LANG = 'BK_MD_REF_LANG';
    /** Modo con el que entra una plantilla descubierta después de instalar: wrapped | original */
    const NEW_MODE = 'BK_MD_NEW_MODE';
    /** Destinatario por defecto de los envíos de prueba */
    const TEST_EMAIL = 'BK_MD_TEST_EMAIL';
    /** Último tema predefinido aplicado; solo sirve para marcarlo en la galería */
    const THEME = 'BK_MD_THEME';
    /** Valores con los que se rellenan las variables al previsualizar y al enviar una prueba */
    const SAMPLES = 'BK_MD_SAMPLES';
    /** Variables añadidas a mano: las que un módulo pasa y no aparecen en su plantilla */
    const CUSTOM_VARS = 'BK_MD_CUSTOM_VARS';
    /** Traza de depuración */
    const DEBUG = 'BK_MD_DEBUG';

    /** Modos de plantilla admitidos */
    const MODE_WRAPPED = 'wrapped';
    const MODE_DESIGNED = 'designed';
    const MODES = [self::MODE_WRAPPED, self::MODE_DESIGNED];

    /** Modos de alta de plantillas nuevas */
    const NEW_MODES = ['wrapped', 'original'];

    /**
     * @return array Claves con su valor por defecto
     */
    public static function getDefaults()
    {
        return [
            self::ENABLED => '1',
            self::REF_LANG => (string) (int) Configuration::get('PS_LANG_DEFAULT'),
            self::NEW_MODE => 'wrapped',
            self::TEST_EMAIL => (string) Configuration::get('PS_SHOP_EMAIL'),
            self::THEME => 'clean',
            self::SAMPLES => '{}',
            self::CUSTOM_VARS => '{}',
            self::DEBUG => '0',
        ];
    }

    public static function installDefaults()
    {
        foreach (self::getDefaults() as $key => $value) {
            if (Configuration::get($key) === false) {
                Configuration::updateValue($key, $value);
            }
        }
    }

    public static function uninstallKeys()
    {
        foreach (array_keys(self::getDefaults()) as $key) {
            Configuration::deleteByName($key);
        }
    }

    /**
     * @param string $key
     *
     * @return bool
     */
    public static function isOn($key)
    {
        return (bool) Configuration::get($key);
    }

    /**
     * El idioma de referencia se valida al leerlo: si el configurado ya no existe se cae al de la
     * tienda, así una plantilla nunca se queda sin texto del que heredar.
     *
     * @return int
     */
    public static function getRefLang()
    {
        $id = (int) Configuration::get(self::REF_LANG);
        if ($id > 0 && Language::getIsoById($id)) {
            return $id;
        }

        return (int) Configuration::get('PS_LANG_DEFAULT');
    }

    /**
     * @return string wrapped | original
     */
    public static function getNewMode()
    {
        $value = (string) Configuration::get(self::NEW_MODE);

        return in_array($value, self::NEW_MODES, true) ? $value : 'wrapped';
    }

    /**
     * Valores de prueba de la tienda: nombre de variable, sin llaves, y con qué rellenarla.
     *
     * Los correos de un módulo llevan variables que este módulo no puede adivinar —un número de
     * solicitud, un estado propio—; aquí el comerciante decide con qué se rellenan mientras
     * prueba. No tocan nada del envío real.
     *
     * @param int|null $idShop
     *
     * @return array
     */
    public static function getSamples($idShop = null)
    {
        $raw = Configuration::get(self::SAMPLES, null, null, $idShop);
        $list = json_decode((string) $raw, true);
        if (!is_array($list)) {
            return [];
        }

        $out = [];
        foreach ($list as $name => $value) {
            if (preg_match('/^[a-z0-9_]+$/i', (string) $name) && is_scalar($value) && trim((string) $value) !== '') {
                $out[(string) $name] = (string) $value;
            }
        }

        return $out;
    }

    /**
     * @param array    $samples
     * @param int|null $idShop
     *
     * @return bool
     */
    public static function setSamples(array $samples, $idShop = null)
    {
        $clean = [];
        foreach ($samples as $name => $value) {
            if (preg_match('/^[a-z0-9_]+$/i', (string) $name) && is_scalar($value) && trim((string) $value) !== '') {
                $clean[(string) $name] = Tools::substr(trim((string) $value), 0, 500);
            }
        }

        return (bool) Configuration::updateValue(self::SAMPLES, json_encode($clean), true, null, (int) $idShop);
    }

    /**
     * Variables que el comerciante ha añadido a mano, con el valor con el que se previsualizan.
     *
     * Un módulo puede pasar una variable que no aparece en su plantilla: nadie puede descubrirla
     * leyendo ficheros, así que se declara aquí y pasa a estar disponible en el editor.
     *
     * @param int|null $idShop
     *
     * @return array
     */
    public static function getCustomVars($idShop = null)
    {
        $list = json_decode((string) Configuration::get(self::CUSTOM_VARS, null, null, $idShop), true);
        if (!is_array($list)) {
            return [];
        }

        $out = [];
        foreach ($list as $name => $value) {
            if (preg_match('/^[a-z0-9_]+$/i', (string) $name)) {
                $out[(string) $name] = is_scalar($value) ? (string) $value : '';
            }
        }

        return $out;
    }

    /**
     * @param array    $vars
     * @param int|null $idShop
     *
     * @return bool
     */
    public static function setCustomVars(array $vars, $idShop = null)
    {
        $clean = [];
        foreach ($vars as $name => $value) {
            if (preg_match('/^[a-z0-9_]+$/i', (string) $name)) {
                $clean[(string) $name] = Tools::substr(is_scalar($value) ? (string) $value : '', 0, 500);
            }
        }

        return (bool) Configuration::updateValue(self::CUSTOM_VARS, json_encode($clean), true, null, (int) $idShop);
    }

    /**
     * @return string
     */
    public static function getTestEmail()
    {
        $value = trim((string) Configuration::get(self::TEST_EMAIL));

        return Validate::isEmail($value) ? $value : (string) Configuration::get('PS_SHOP_EMAIL');
    }
}
