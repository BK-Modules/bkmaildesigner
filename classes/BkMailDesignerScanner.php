<?php
/**
 * Descubre las plantillas de correo que la tienda puede enviar y localiza el fichero original de
 * cada una con el mismo orden de búsqueda que Mail::send(): tema activo, tema padre y raíz; y
 * dentro de cada base, la carpeta mails/ del módulo o la del núcleo.
 *
 * Nada se da de alta a mano: una plantilla nueva de un módulo instalado mañana aparece sola.
 *
 * @author BK Modules
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class BkMailDesignerScanner
{
    /**
     * Variables que Mail::send() añade siempre, más las que pone el módulo; no se ofrecen como
     * propias de la plantilla porque están en todas.
     */
    const GLOBAL_VARS = [
        'shop_logo', 'shop_name', 'shop_url', 'my_account_url', 'guest_tracking_url',
        'history_url', 'order_slip_url', 'color', 'contact_url',
    ];

    /** Ficheros que no son plantillas aunque terminen en .html */
    const SKIP = ['index'];

    /**
     * Plantillas del núcleo que van al comerciante y no al cliente. Saberlo cambia el orden de
     * trabajo: lo primero que hay que dejar bonito es lo que ve quien compra.
     */
    const CORE_MERCHANT = [
        'backoffice_order', 'contact', 'employee_password', 'forward_msg', 'import',
        'log_alert', 'outofstock', 'productoutofstock', 'test',
    ];

    /** Lo mismo para las plantillas de módulo, por nombre de fichero */
    const MODULE_MERCHANT = [
        'new_order', 'order_changed', 'productcoverage', 'productoutofstock', 'return_slip',
        'admin_notification', 'new_comment', 'new_customer_message',
    ];

    /**
     * Dos audiencias y no más: quien recibe el correo y la propia tienda.
     *
     * Repartir a los de fuera entre «tiene cuenta» y «no tiene cuenta» sería adivinar —un correo
     * de desistimiento o una factura pueden ir a un pedido de invitado—, así que no se hace.
     */
    const AUDIENCE_RECIPIENT = 'recipient';
    const AUDIENCE_MERCHANT = 'merchant';

    /**
     * Plantillas del núcleo y de los módulos, con los idiomas en que existe cada una.
     *
     * @param Shop $shop
     *
     * @return array [['module' => '', 'name' => 'account', 'langs' => ['es' => '/ruta/es/account.html', ...]], ...]
     */
    public static function discover(Shop $shop)
    {
        $isos = self::isoCodes();
        $out = [];

        foreach (self::templateNames('', $shop, $isos) as $name) {
            $out[] = ['module' => '', 'name' => $name, 'langs' => self::langPaths('', $name, $shop, $isos)];
        }

        foreach (self::moduleNames($shop) as $module) {
            foreach (self::templateNames($module, $shop, $isos) as $name) {
                $out[] = ['module' => $module, 'name' => $name, 'langs' => self::langPaths($module, $name, $shop, $isos)];
            }
        }

        return $out;
    }

    /**
     * A quién va dirigida una plantilla.
     *
     * @param string $module
     * @param string $name
     *
     * @return string customer | merchant
     */
    public static function audience($module, $name)
    {
        $merchant = $module === '' ? self::CORE_MERCHANT : self::MODULE_MERCHANT;

        return in_array($name, $merchant, true) ? self::AUDIENCE_MERCHANT : self::AUDIENCE_RECIPIENT;
    }

    /**
     * Fichero HTML original de una plantilla para un idioma, con la cascada de idioma del núcleo:
     * el pedido, el de la tienda y el inglés.
     *
     * @param string $module
     * @param string $name
     * @param string $iso
     * @param Shop   $shop
     *
     * @return string|null
     */
    public static function originalPath($module, $name, $iso, Shop $shop)
    {
        $tries = [$iso];
        $default = Language::getIsoById((int) Configuration::get('PS_LANG_DEFAULT'));
        if ($default && $default !== $iso) {
            $tries[] = $default;
        }
        if (!in_array('en', $tries, true)) {
            $tries[] = 'en';
        }

        foreach ($tries as $try) {
            foreach (self::bases($shop) as $base) {
                $file = $base . self::relative($module) . $try . '/' . $name . '.html';
                if (is_file($file)) {
                    return $file;
                }
            }
        }

        return null;
    }

    /**
     * Variables que usa la plantilla en cualquiera de sus idiomas.
     *
     * Se leen del **cuerpo**, no del fichero entero: la cabecera y el pie originales traen
     * `{shop_logo}` y `{shop_url}`, que en un correo de este módulo los pone el layout y no son
     * del contenido. Si el cuerpo no se puede extraer se lee el fichero completo, que es peor
     * pero nunca deja una variable fuera.
     *
     * @param string $module
     * @param string $name
     * @param Shop   $shop
     *
     * @return array
     */
    public static function variables($module, $name, Shop $shop)
    {
        $vars = [];
        foreach (self::langPaths($module, $name, $shop, self::isoCodes()) as $path) {
            $html = (string) Tools::file_get_contents($path);
            if ($html === '') {
                continue;
            }
            $body = BkMailDesignerWrapper::extract($html);
            $source = $body === null ? $html : $body['html'];
            if (preg_match_all('/\{([a-z0-9_]+)\}/i', $source, $m)) {
                $vars = array_merge($vars, $m[1]);
            }
        }
        $vars = array_values(array_unique($vars));
        sort($vars);

        return $vars;
    }

    /**
     * Nombre de módulo que Mail::send() deduce de la ruta de plantillas, o '' para el núcleo.
     *
     * @param string $templatePath
     *
     * @return string
     */
    public static function moduleFromPath($templatePath)
    {
        $path = str_replace(DIRECTORY_SEPARATOR, '/', (string) $templatePath);
        if (preg_match('#/modules/([a-z0-9_-]+)/#i', $path, $m)) {
            return $m[1];
        }

        return '';
    }

    /**
     * @return array Códigos ISO de los idiomas activos
     */
    public static function isoCodes()
    {
        $isos = [];
        foreach (Language::getLanguages(true) as $language) {
            $isos[] = $language['iso_code'];
        }

        return $isos;
    }

    /**
     * Bases de búsqueda en el orden del núcleo.
     *
     * @param Shop $shop
     *
     * @return array
     */
    private static function bases(Shop $shop)
    {
        $bases = [];
        $theme = $shop->theme;
        if ($theme) {
            $bases[] = _PS_ROOT_DIR_ . '/themes/' . $theme->getName() . '/';
            $parent = $theme->get('parent');
            if ($parent) {
                $bases[] = _PS_ROOT_DIR_ . '/themes/' . $parent . '/';
            }
        }
        $bases[] = _PS_ROOT_DIR_ . '/';

        return $bases;
    }

    /**
     * @param string $module
     *
     * @return string
     */
    private static function relative($module)
    {
        return $module !== '' ? 'modules/' . $module . '/mails/' : 'mails/';
    }

    /**
     * @param string $module
     * @param Shop   $shop
     * @param array  $isos
     *
     * @return array
     */
    private static function templateNames($module, Shop $shop, array $isos)
    {
        $names = [];
        foreach (self::bases($shop) as $base) {
            foreach ($isos as $iso) {
                $dir = $base . self::relative($module) . $iso;
                if (!is_dir($dir)) {
                    continue;
                }
                foreach ((array) glob($dir . '/*.html') as $file) {
                    $name = basename($file, '.html');
                    if (!in_array($name, self::SKIP, true) && preg_match('/^[a-z0-9_.-]+$/i', $name)) {
                        $names[$name] = true;
                    }
                }
            }
        }
        $names = array_keys($names);
        sort($names);

        return $names;
    }

    /**
     * @param string $module
     * @param string $name
     * @param Shop   $shop
     * @param array  $isos
     *
     * @return array iso => ruta del fichero (solo idiomas con fichero propio)
     */
    private static function langPaths($module, $name, Shop $shop, array $isos)
    {
        $out = [];
        foreach ($isos as $iso) {
            foreach (self::bases($shop) as $base) {
                $file = $base . self::relative($module) . $iso . '/' . $name . '.html';
                if (is_file($file)) {
                    $out[$iso] = $file;
                    break;
                }
            }
        }

        return $out;
    }

    /**
     * Módulos instalados y activos con carpeta de correos, en la raíz o en el tema.
     *
     * @param Shop $shop
     *
     * @return array
     */
    private static function moduleNames(Shop $shop)
    {
        $names = [];
        foreach (self::bases($shop) as $base) {
            foreach ((array) glob($base . 'modules/*/mails', GLOB_ONLYDIR) as $dir) {
                $names[basename(dirname($dir))] = true;
            }
        }
        $out = [];
        foreach (array_keys($names) as $module) {
            if (Module::isInstalled($module) && Module::isEnabled($module)) {
                $out[] = $module;
            }
        }
        sort($out);

        return $out;
    }
}
