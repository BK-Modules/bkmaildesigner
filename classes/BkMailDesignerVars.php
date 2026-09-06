<?php
/**
 * Las variables de un correo, agrupadas por lo que significan.
 *
 * Cada plantilla tiene las suyas y solo las suyas: las que aparecen en su fichero original, las
 * que su autor haya escrito en los bloques y las que se hayan visto llegar en un envío real. Las
 * conocidas del núcleo se reparten en grupos —líneas, totales, direcciones, cliente, pedido— para
 * que un correo de pedido, que trae treinta, se lea de un vistazo en vez de como una lista plana.
 *
 * La clasificación se calcula aquí, en PHP, y viaja ya hecha: el JavaScript solo la pinta.
 *
 * @author BK Modules
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class BkMailDesignerVars
{
    /** Grupos, en el orden en que se enseñan */
    const GROUPS = ['own', 'lines', 'totals', 'address', 'customer', 'order', 'custom', 'shop'];

    /**
     * Variables del núcleo con grupo conocido. Lo que no está aquí es propio del correo, que es
     * justo lo que traen los módulos.
     */
    const KNOWN = [
        // Las que llegan como HTML ya maquetado por PrestaShop
        'products' => 'lines',
        'products_txt' => 'lines',
        'items' => 'lines',
        'discounts' => 'lines',
        'discounts_txt' => 'lines',
        'products_html' => 'lines',
        // Importes
        'total_paid' => 'totals',
        'total_products' => 'totals',
        'total_shipping' => 'totals',
        'total_discounts' => 'totals',
        'total_tax_paid' => 'totals',
        'total_wrapping' => 'totals',
        'total_paid_tax_excl' => 'totals',
        'total_shipping_tax_excl' => 'totals',
        'total_shipping_tax_incl' => 'totals',
        // Direcciones
        'delivery_block_html' => 'address',
        'delivery_block_txt' => 'address',
        'invoice_block_html' => 'address',
        'invoice_block_txt' => 'address',
        // Cliente
        'firstname' => 'customer',
        'lastname' => 'customer',
        'email' => 'customer',
        'phone' => 'customer',
        // Pedido
        'order_name' => 'order',
        'id_order' => 'order',
        'order_reference' => 'order',
        'date' => 'order',
        'payment' => 'order',
        'carrier' => 'order',
        'followup' => 'order',
        'recycled_packaging_label' => 'order',
        'order_link' => 'order',
    ];

    /**
     * @param string                 $module
     * @param string                 $name
     * @param Shop                   $shop
     * @param BkMailDesignerTemplate $template
     * @param array                  $samples Con qué se rellena cada una hoy, ['nombre' => valor]
     * @param array                  $fixed   Nombres con valor fijado a mano
     *
     * @return array [['key' => 'own', 'rows' => [['name','value','html','unknown','fixed'], ...]], ...]
     */
    public static function grouped($module, $name, Shop $shop, BkMailDesignerTemplate $template, array $samples, array $fixed)
    {
        // Solo lo que esta plantilla usa de verdad: lo que trae su fichero y lo que su autor haya
        // escrito en los bloques. Ni una variable de más.
        $own = array_values(array_unique(array_merge(
            BkMailDesignerScanner::variables($module, $name, $shop),
            self::fromBlocks($template)
        )));

        $rows = [];
        foreach ($own as $variable) {
            $rows[$variable] = isset(self::KNOWN[$variable]) ? self::KNOWN[$variable] : 'own';
        }
        foreach (BkMailDesignerConfig::getCustomVars((int) $shop->id) as $variable => $value) {
            if (!isset($rows[$variable])) {
                $rows[$variable] = 'custom';
            }
        }
        // Una global que la plantilla usa va al grupo de la tienda, no al suyo. Y solo esa: no se
        // ofrece ninguna que el correo no use, que es lo que llenaba la lista de ruido.
        foreach (BkMailDesignerScanner::GLOBAL_VARS as $variable) {
            if (isset($rows[$variable])) {
                $rows[$variable] = 'shop';
            }
        }

        $out = [];
        foreach (self::GROUPS as $group) {
            $names = array_keys($rows, $group, true);
            if (empty($names)) {
                continue;
            }
            sort($names);
            $out[] = ['key' => $group, 'rows' => self::describe($names, $samples, $fixed)];
        }

        return $out;
    }

    /**
     * Las variables que `Mail::send()` añade a toda plantilla, con su valor real.
     *
     * El núcleo las calcula **después** de los hooks del correo, así que un asunto escrito en el
     * módulo —«Bienvenido a {shop_name}»— no tiene con qué resolverlas cuando llega su turno.
     * Aquí se calculan igual que en `classes/Mail.php`, para la tienda y el idioma del envío.
     *
     * `{shop_logo}` se queda fuera a propósito: el núcleo lo incrusta como adjunto y no es texto.
     *
     * @param int $idShop
     * @param int $idLang
     *
     * @return array {clave} => valor
     */
    public static function globals($idShop, $idLang)
    {
        $context = Context::getContext();
        if (!($context->link instanceof Link)) {
            $context->link = new Link();
        }
        ShopUrl::cacheMainDomainForShop((int) $idShop);
        $link = $context->link;

        return [
            '{shop_name}' => Tools::safeOutput(Configuration::get('PS_SHOP_NAME', null, null, (int) $idShop)),
            '{shop_url}' => $link->getPageLink('index', null, (int) $idLang, null, false, (int) $idShop),
            '{my_account_url}' => $link->getPageLink('my-account', null, (int) $idLang, null, false, (int) $idShop),
            '{guest_tracking_url}' => $link->getPageLink('guest-tracking', null, (int) $idLang, null, false, (int) $idShop),
            '{history_url}' => $link->getPageLink('history', null, (int) $idLang, null, false, (int) $idShop),
            '{order_slip_url}' => $link->getPageLink('order-slip', null, (int) $idLang, null, false, (int) $idShop),
            '{color}' => Tools::safeOutput(Configuration::get('PS_MAIL_COLOR', null, null, (int) $idShop)),
            // La única que no pone el núcleo: hace falta para el enlace «Escribir a la tienda»
            // del pie, que si no habría que teclear a mano en cada idioma.
            '{contact_url}' => $link->getPageLink('contact', null, (int) $idLang, null, false, (int) $idShop),
        ];
    }

    /**
     * Las que pone el módulo y el núcleo no conoce.
     *
     * `Mail::send()` sustituye sus variables comunes después de los hooks, pero solo las suyas:
     * una que añada el módulo tiene que sustituirla el módulo o llegaría con las llaves puestas.
     *
     * @param int $idShop
     * @param int $idLang
     *
     * @return array
     */
    public static function own($idShop, $idLang)
    {
        $all = self::globals($idShop, $idLang);

        return ['{contact_url}' => $all['{contact_url}']];
    }

    /**
     * Lista plana de nombres, para lo que no necesita grupos.
     *
     * @param array $groups
     *
     * @return array
     */
    public static function flatten(array $groups)
    {
        $out = [];
        foreach ($groups as $group) {
            foreach ($group['rows'] as $row) {
                $out[] = $row['name'];
            }
        }

        return $out;
    }

    /**
     * @param array $names
     * @param array $samples
     * @param array $fixed
     *
     * @return array
     */
    private static function describe(array $names, array $samples, array $fixed)
    {
        $out = [];
        foreach ($names as $name) {
            $value = isset($samples['{' . $name . '}']) ? (string) $samples['{' . $name . '}'] : '';
            $out[] = [
                'name' => $name,
                'value' => isset($fixed[$name]) ? $fixed[$name] : $value,
                'fixed' => isset($fixed[$name]),
                // Una variable que se queda con sus llaves es justo la que nadie puede adivinar
                'unknown' => $value === '{' . $name . '}',
                'html' => strpos($value, '<') !== false,
            ];
        }

        return $out;
    }

    /**
     * Variables que el autor ha escrito en los bloques de cualquier idioma: son suyas aunque el
     * fichero original no las traiga.
     *
     * @param BkMailDesignerTemplate $template
     *
     * @return array
     */
    private static function fromBlocks(BkMailDesignerTemplate $template)
    {
        $json = '';
        foreach (Language::getLanguages(false) as $language) {
            $json .= json_encode($template->decodeBlocks((int) $language['id_lang']));
        }
        if ($json !== '' && preg_match_all('/\{([a-z0-9_]+)\}/i', $json, $m)) {
            return array_values(array_unique($m[1]));
        }

        return [];
    }
}
