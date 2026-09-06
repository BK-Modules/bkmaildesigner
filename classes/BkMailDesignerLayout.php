<?php
/**
 * Layout de marca: cabecera, pie y estilo comunes a todos los correos de una tienda.
 *
 * Una fila por tienda. Los ajustes visuales viajan en un JSON y los textos que cambian con el
 * idioma (enlaces, aviso legal, motivo de recepción) en la tabla de idioma.
 *
 * @author BK Modules
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class BkMailDesignerLayout extends ObjectModel
{
    public $id_shop;
    /** @var string JSON con los ajustes visuales, ver defaults() */
    public $settings;
    /** @var string|array JSON [{label, url}] por idioma */
    public $header_links;
    /** @var string|array JSON [{label, url}] por idioma */
    public $footer_links;
    /** @var string|array */
    public $footer_legal;
    /** @var string|array */
    public $footer_reason;
    public $date_add;
    public $date_upd;

    public static $definition = [
        'table' => 'bk_maildesigner_layout',
        'primary' => 'id_bk_maildesigner_layout',
        'multilang' => true,
        'fields' => [
            'id_shop' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'required' => true],
            'settings' => ['type' => self::TYPE_HTML, 'validate' => 'isString'],
            'date_add' => ['type' => self::TYPE_DATE, 'validate' => 'isDate'],
            'date_upd' => ['type' => self::TYPE_DATE, 'validate' => 'isDate'],
            'header_links' => ['type' => self::TYPE_HTML, 'lang' => true, 'validate' => 'isString'],
            'footer_links' => ['type' => self::TYPE_HTML, 'lang' => true, 'validate' => 'isString'],
            'footer_legal' => ['type' => self::TYPE_HTML, 'lang' => true, 'validate' => 'isString'],
            'footer_reason' => ['type' => self::TYPE_HTML, 'lang' => true, 'validate' => 'isString'],
        ],
    ];

    /** Estilos de cabecera admitidos */
    const HEADER_STYLES = ['center', 'left', 'band'];
    /** Estilos de botón admitidos */
    const BUTTON_STYLES = ['solid', 'outline'];
    /**
     * Fuentes seguras: las tiene instaladas cualquier cliente de correo, sin depender de descargas.
     * No se ofrecen fuentes web a propósito — Outlook y Gmail las ignoran y el correo cambiaría de
     * aspecto según quién lo abra.
     */
    const FONTS = [
        'arial' => 'Arial, Helvetica, sans-serif',
        'helvetica' => '\'Helvetica Neue\', Helvetica, Arial, sans-serif',
        'verdana' => 'Verdana, Geneva, sans-serif',
        'tahoma' => 'Tahoma, Verdana, sans-serif',
        'trebuchet' => '\'Trebuchet MS\', Helvetica, sans-serif',
        'georgia' => 'Georgia, \'Times New Roman\', serif',
        'palatino' => '\'Palatino Linotype\', \'Book Antiqua\', Palatino, Georgia, serif',
        'times' => '\'Times New Roman\', Times, serif',
        'courier' => '\'Courier New\', Courier, monospace',
    ];

    /** Tamaños de botón: relleno y cuerpo */
    const BUTTON_SIZES = [
        's' => ['pad' => '9px 18px', 'size' => 13],
        'm' => ['pad' => '13px 28px', 'size' => 15],
        'l' => ['pad' => '17px 34px', 'size' => 16],
    ];

    /** Aire del correo: relleno de la tarjeta y separación entre bloques */
    const SPACES = [
        'tight' => ['x' => 22, 'y' => 18, 'block' => 4],
        'normal' => ['x' => 32, 'y' => 26, 'block' => 6],
        'roomy' => ['x' => 44, 'y' => 36, 'block' => 10],
    ];

    /** Presentación de la caja destacada */
    const BOX_STYLES = ['fill', 'bar', 'outline'];
    /** Presentación del separador */
    const RULE_STYLES = ['solid', 'dotted', 'thick'];
    /** Presentación de la línea de pedido */
    const LINE_STYLES = ['plain', 'zebra', 'boxed'];
    /**
     * Redes con marca propia y su color oficial. El icono se sirve desde el propio módulo en dos
     * versiones —blanca sobre el color de la red y a color sobre el pie—, porque un correo no
     * puede pintar un SVG ni una fuente de iconos.
     */
    const NETWORKS = [
        'facebook' => ['name' => 'Facebook', 'color' => '#1877f2'],
        'instagram' => ['name' => 'Instagram', 'color' => '#e4405f'],
        'x' => ['name' => 'X', 'color' => '#000000'],
        'youtube' => ['name' => 'YouTube', 'color' => '#ff0000'],
        'tiktok' => ['name' => 'TikTok', 'color' => '#000000'],
        'linkedin' => ['name' => 'LinkedIn', 'color' => '#0a66c2'],
        'pinterest' => ['name' => 'Pinterest', 'color' => '#bd081c'],
        'whatsapp' => ['name' => 'WhatsApp', 'color' => '#25d366'],
    ];
    /** Presentación de los iconos de redes */
    const SOCIAL_STYLES = ['color', 'brand', 'plain'];
    /** Forma del fondo del icono */
    const SOCIAL_SHAPES = ['circle', 'square'];
    /** Presentación de las líneas del pedido: apiladas (se leen en el móvil) o en cinco columnas */
    const ORDER_LAYOUTS = ['stacked', 'table'];
    /** Transformación de un texto */
    const CASES = ['none', 'upper'];
    /** Pesos admitidos */
    const WEIGHTS = ['normal', 'bold'];

    /**
     * Valores con los que arranca una tienda. El logo es el de correo de PrestaShop, que el núcleo
     * incrusta como adjunto y por eso se ve en Outlook sin pedir permiso para descargar imágenes.
     *
     * @return array
     */
    public static function defaults()
    {
        return [
            'width' => 600,
            'logo_width' => 150,
            'logo_url' => '',
            'color_primary' => '#00769e',
            'color_outer' => '#f3f6f8',
            'color_card' => '#ffffff',
            'color_text' => '#363a41',
            'color_muted' => '#6b7a86',
            'color_band' => '',
            'band_height' => 6,
            'color_header_bg' => '',
            'color_header_text' => '',
            'color_footer_bg' => '#f3f6f8',
            'color_footer_text' => '#6b7a86',
            'font' => 'arial',
            'font_heading' => '',
            'heading_size' => 22,
            'heading_weight' => 'bold',
            'heading_case' => 'none',
            'heading_spacing' => 0,
            'body_size' => 15,
            'body_leading' => 155,
            'space' => 'normal',
            'header_style' => 'center',
            'header_rule' => 0,
            'footer_rule' => 0,
            'top_band' => 1,
            'card_radius' => 4,
            'box_style' => 'fill',
            'order_layout' => 'stacked',
            'line_style' => 'plain',
            'order_image' => 0,
            'order_image_size' => 64,
            'order_reference' => 1,
            'order_options' => 1,
            'order_unit' => 1,
            'custom_css' => '',
            'rule_style' => 'solid',
            'button_style' => 'solid',
            'button_radius' => 4,
            'button_size' => 'm',
            'button_case' => 'none',
            'button_weight' => 'bold',
            'button_full' => 0,
            'shop_name_in_footer' => 1,
            'social' => [],
            'social_style' => 'color',
            'social_shape' => 'circle',
            'social_size' => 32,
        ];
    }

    /**
     * Layout de una tienda; si aún no tiene, uno nuevo con los valores por defecto sin guardar.
     *
     * @param int $idShop
     *
     * @return BkMailDesignerLayout
     */
    public static function forShop($idShop)
    {
        $id = (int) Db::getInstance()->getValue(
            'SELECT `id_bk_maildesigner_layout` FROM `' . _DB_PREFIX_ . 'bk_maildesigner_layout`
             WHERE `id_shop` = ' . (int) $idShop
        );
        $layout = new self($id ?: null);
        if (!$id) {
            $layout->id_shop = (int) $idShop;
            $layout->settings = json_encode(self::defaults());
            $layout->header_links = [];
            $layout->footer_links = [];
            $layout->footer_legal = [];
            $layout->footer_reason = [];
            foreach (Language::getLanguages(false) as $language) {
                $idLang = (int) $language['id_lang'];
                $layout->header_links[$idLang] = '[]';
                $layout->footer_links[$idLang] = '[]';
                $layout->footer_legal[$idLang] = '';
                $layout->footer_reason[$idLang] = '';
            }
        }

        return $layout;
    }

    /**
     * Ajustes completos: lo guardado sobre los valores por defecto, validado campo a campo para que
     * un valor escrito a mano en la base de datos no pueda dejar un correo sin estilo.
     *
     * @return array
     */
    public function getSettings()
    {
        $saved = json_decode((string) $this->settings, true);

        return self::sanitizeSettings(is_array($saved) ? $saved : []);
    }

    /**
     * @param array $input
     *
     * @return array
     */
    public static function sanitizeSettings(array $input)
    {
        $out = self::defaults();
        foreach (['color_primary', 'color_outer', 'color_card', 'color_text', 'color_muted', 'color_footer_bg', 'color_footer_text'] as $key) {
            if (isset($input[$key]) && preg_match('/^#[0-9a-f]{6}$/i', trim($input[$key]))) {
                $out[$key] = strtolower(trim($input[$key]));
            }
        }
        // Vacío significa «el color de marca»: así una tienda que nunca los toque no nota que
        // existen, y quien los toque separa la banda y la cabecera del color de los botones.
        foreach (['color_band', 'color_header_bg', 'color_header_text'] as $key) {
            if (!isset($input[$key])) {
                continue;
            }
            $value = trim((string) $input[$key]);
            if ($value === '' || preg_match('/^#[0-9a-f]{6}$/i', $value)) {
                $out[$key] = strtolower($value);
            }
        }
        if (isset($input['band_height'])) {
            $out['band_height'] = max(2, min(16, (int) $input['band_height']));
        }
        if (isset($input['social'])) {
            $out['social'] = self::sanitizeSocial($input['social']);
        }
        if (isset($input['social_style']) && in_array($input['social_style'], self::SOCIAL_STYLES, true)) {
            $out['social_style'] = $input['social_style'];
        }
        if (isset($input['social_shape']) && in_array($input['social_shape'], self::SOCIAL_SHAPES, true)) {
            $out['social_shape'] = $input['social_shape'];
        }
        if (isset($input['social_size'])) {
            $out['social_size'] = max(20, min(48, (int) $input['social_size']));
        }
        if (isset($input['logo_width'])) {
            $out['logo_width'] = max(60, min(400, (int) $input['logo_width']));
        }
        if (isset($input['logo_url']) && Validate::isAbsoluteUrl(trim($input['logo_url']))) {
            $out['logo_url'] = trim($input['logo_url']);
        }
        if (isset($input['font']) && isset(self::FONTS[$input['font']])) {
            $out['font'] = $input['font'];
        }
        // Vacío significa «la misma del cuerpo»: es lo que hace que un tema con una sola fuente
        // no tenga que repetirla en dos campos.
        if (isset($input['font_heading']) && ($input['font_heading'] === '' || isset(self::FONTS[$input['font_heading']]))) {
            $out['font_heading'] = $input['font_heading'];
        }
        foreach (['heading_size' => [15, 40], 'body_size' => [12, 18], 'heading_spacing' => [0, 12], 'body_leading' => [130, 200], 'width' => [480, 680]] as $key => $range) {
            if (isset($input[$key])) {
                $out[$key] = max($range[0], min($range[1], (int) $input[$key]));
            }
        }
        foreach (['heading_weight' => self::WEIGHTS, 'button_weight' => self::WEIGHTS, 'heading_case' => self::CASES, 'button_case' => self::CASES, 'box_style' => self::BOX_STYLES, 'rule_style' => self::RULE_STYLES] as $key => $allowed) {
            if (isset($input[$key]) && in_array($input[$key], $allowed, true)) {
                $out[$key] = $input[$key];
            }
        }
        if (isset($input['order_layout']) && in_array($input['order_layout'], self::ORDER_LAYOUTS, true)) {
            $out['order_layout'] = $input['order_layout'];
        }
        if (isset($input['line_style']) && in_array($input['line_style'], self::LINE_STYLES, true)) {
            $out['line_style'] = $input['line_style'];
        }
        if (isset($input['order_image_size'])) {
            $out['order_image_size'] = min(120, max(32, (int) $input['order_image_size']));
        }
        if (isset($input['custom_css'])) {
            // CSS del comerciante: se limpia lo que no es CSS y se recorta, no se interpreta
            $css = str_replace(['</style', '<script', '</script'], '', (string) $input['custom_css']);
            $out['custom_css'] = Tools::substr(trim($css), 0, 8000);
        }
        if (isset($input['space']) && isset(self::SPACES[$input['space']])) {
            $out['space'] = $input['space'];
        }
        if (isset($input['button_size']) && isset(self::BUTTON_SIZES[$input['button_size']])) {
            $out['button_size'] = $input['button_size'];
        }
        if (isset($input['header_style']) && in_array($input['header_style'], self::HEADER_STYLES, true)) {
            $out['header_style'] = $input['header_style'];
        }
        if (isset($input['button_style']) && in_array($input['button_style'], self::BUTTON_STYLES, true)) {
            $out['button_style'] = $input['button_style'];
        }
        // Las casillas del formulario van precedidas de un campo oculto a 0: sin él, desmarcar
        // una casilla no llega al servidor y el ajuste se quedaría encendido para siempre.
        foreach (['top_band', 'shop_name_in_footer', 'header_rule', 'footer_rule', 'button_full', 'order_image', 'order_reference', 'order_options', 'order_unit'] as $key) {
            if (isset($input[$key])) {
                $out[$key] = (int) (bool) $input[$key];
            }
        }
        foreach (['card_radius', 'button_radius'] as $key) {
            if (isset($input[$key])) {
                $out[$key] = max(0, min(30, (int) $input[$key]));
            }
        }

        return $out;
    }

    /**
     * Lista de enlaces de un campo de idioma, ya limpia: etiqueta y URL absoluta o variable.
     *
     * @param string|array $field  header_links | footer_links
     * @param int          $idLang
     *
     * @return array [['label' => ..., 'url' => ...], ...]
     */
    public function links($field, $idLang)
    {
        $raw = is_array($this->$field) ? (isset($this->$field[$idLang]) ? $this->$field[$idLang] : '') : $this->$field;

        return self::sanitizeLinks(json_decode((string) $raw, true));
    }

    /**
     * @param mixed $list
     *
     * @return array
     */
    public static function sanitizeLinks($list)
    {
        $out = [];
        if (!is_array($list)) {
            return $out;
        }
        foreach ($list as $item) {
            if (!is_array($item)) {
                continue;
            }
            $label = trim(isset($item['label']) ? (string) $item['label'] : '');
            $url = trim(isset($item['url']) ? (string) $item['url'] : '');
            // Se admite una variable del núcleo ({shop_url}, {my_account_url}...) como destino
            if ($label === '' || ($url !== '' && !Validate::isAbsoluteUrl($url) && !preg_match('/^\{[a-z_]+\}$/', $url))) {
                continue;
            }
            $out[] = ['label' => Tools::substr($label, 0, 60), 'url' => $url === '' ? '{shop_url}' : $url];
            if (count($out) >= 6) {
                break;
            }
        }

        return $out;
    }

    /**
     * @param string $field footer_legal | footer_reason
     * @param int    $idLang
     *
     * @return string
     */
    /**
     * Redes limpias: solo las conocidas o una propia con su imagen, y solo con URL absoluta.
     *
     * @param mixed $input
     *
     * @return array [['network' => 'facebook', 'url' => '…', 'icon' => '', 'label' => ''], ...]
     */
    public static function sanitizeSocial($input)
    {
        if (is_string($input)) {
            $input = json_decode($input, true);
        }
        if (!is_array($input)) {
            return [];
        }

        $out = [];
        foreach ($input as $row) {
            if (!is_array($row)) {
                continue;
            }
            $network = isset($row['network']) ? (string) $row['network'] : '';
            $url = isset($row['url']) ? trim((string) $row['url']) : '';
            if ($url === '' || !Validate::isAbsoluteUrl($url)) {
                continue;
            }
            if ($network !== 'custom' && !isset(self::NETWORKS[$network])) {
                continue;
            }
            $icon = isset($row['icon']) ? trim((string) $row['icon']) : '';
            $color = isset($row['color']) ? trim((string) $row['color']) : '';
            $entry = [
                'network' => $network,
                'url' => $url,
                'icon' => Validate::isAbsoluteUrl($icon) ? $icon : '',
                'label' => Tools::substr(strip_tags(isset($row['label']) ? (string) $row['label'] : ''), 0, 40),
                // Vacío en una red propia significa «sin fondo»: la imagen es el icono
                'color' => preg_match('/^#[0-9a-f]{6}$/i', $color) ? Tools::strtolower($color) : '',
            ];
            // Una red propia sin imagen no se puede pintar
            if ($network === 'custom' && $entry['icon'] === '') {
                continue;
            }
            $out[] = $entry;
            if (count($out) >= 10) {
                break;
            }
        }

        return $out;
    }

    /**
     * El motivo del pie para quien recibe el correo.
     *
     * No hay uno solo: a quien está fuera de la tienda se le explica por qué le llega, y a la
     * propia tienda no se le explica nada. Los dos viajan como JSON en el mismo campo de idioma,
     * igual que los enlaces; un valor antiguo en texto plano se lee como el de quien recibe.
     *
     * @param int    $idLang
     * @param string $audience BkMailDesignerScanner::AUDIENCE_*
     *
     * @return string
     */
    public function reason($idLang, $audience = BkMailDesignerScanner::AUDIENCE_RECIPIENT)
    {
        $all = $this->reasons($idLang);

        return isset($all[$audience]) ? $all[$audience] : '';
    }

    /**
     * Los dos motivos de un idioma, siempre las dos claves.
     *
     * @param int $idLang
     *
     * @return array
     */
    public function reasons($idLang)
    {
        $raw = trim($this->text('footer_reason', $idLang));
        $out = [
            BkMailDesignerScanner::AUDIENCE_RECIPIENT => '',
            BkMailDesignerScanner::AUDIENCE_MERCHANT => '',
        ];
        if ($raw === '') {
            return $out;
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            $out[BkMailDesignerScanner::AUDIENCE_RECIPIENT] = $raw;

            return $out;
        }

        // Un valor guardado cuando había tres audiencias: la de cliente pasa a ser la de todos
        if (!isset($decoded[BkMailDesignerScanner::AUDIENCE_RECIPIENT]) && isset($decoded['customer'])) {
            $decoded[BkMailDesignerScanner::AUDIENCE_RECIPIENT] = $decoded['customer'];
        }
        foreach ($out as $key => $ignored) {
            if (isset($decoded[$key])) {
                $out[$key] = Tools::substr(strip_tags((string) $decoded[$key]), 0, 500);
            }
        }

        return $out;
    }

    /**
     * @param array $reasons
     *
     * @return string JSON listo para guardar en el campo de idioma
     */
    public static function encodeReasons(array $reasons)
    {
        $out = [];
        foreach ([BkMailDesignerScanner::AUDIENCE_RECIPIENT, BkMailDesignerScanner::AUDIENCE_MERCHANT] as $key) {
            $out[$key] = Tools::substr(strip_tags(isset($reasons[$key]) ? (string) $reasons[$key] : ''), 0, 500);
        }

        return json_encode($out);
    }

    public function text($field, $idLang)
    {
        $value = is_array($this->$field) ? (isset($this->$field[$idLang]) ? $this->$field[$idLang] : '') : $this->$field;

        return (string) $value;
    }

    public static function installTable()
    {
        $db = Db::getInstance();
        $ok = $db->execute(
            'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'bk_maildesigner_layout` (
                `id_bk_maildesigner_layout` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `id_shop` INT UNSIGNED NOT NULL,
                `settings` TEXT,
                `date_add` DATETIME NOT NULL,
                `date_upd` DATETIME NOT NULL,
                PRIMARY KEY (`id_bk_maildesigner_layout`),
                UNIQUE KEY `shop` (`id_shop`)
            ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4'
        );
        $ok = $ok && $db->execute(
            'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'bk_maildesigner_layout_lang` (
                `id_bk_maildesigner_layout` INT UNSIGNED NOT NULL,
                `id_lang` INT UNSIGNED NOT NULL,
                `header_links` TEXT,
                `footer_links` TEXT,
                `footer_legal` TEXT,
                `footer_reason` TEXT,
                PRIMARY KEY (`id_bk_maildesigner_layout`, `id_lang`)
            ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4'
        );

        return $ok;
    }

    public static function uninstallTable()
    {
        $db = Db::getInstance();

        return $db->execute('DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'bk_maildesigner_layout_lang`')
            && $db->execute('DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'bk_maildesigner_layout`');
    }
}
