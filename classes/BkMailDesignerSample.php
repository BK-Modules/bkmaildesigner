<?php
/**
 * Datos con los que se previsualiza y se prueba un correo.
 *
 * Los correos de pedido se pintan con el último pedido real de la tienda, que es lo que enseña
 * de verdad cómo queda la tabla de productos; el resto de variables llevan un juego de valores
 * de ejemplo. Una variable que no está en ninguna de las dos listas se deja visible como
 * {variable}: en la vista previa es mejor verla que esconderla.
 *
 * @author BK Modules
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class BkMailDesignerSample
{
    /**
     * @param array   $names  Variables de la plantilla, sin llaves
     * @param Context $context
     *
     * @return array {clave} => valor, incluidas las globales que añade Mail::send
     */
    public static function vars(array $names, Context $context)
    {
        // Lo que el comerciante haya fijado manda sobre lo automático: lo puso a propósito, y el
        // campo llega relleno con el valor automático, así que dejarlo tal cual no cambia nada.
        $chosen = array_merge(
            BkMailDesignerConfig::getCustomVars((int) $context->shop->id),
            BkMailDesignerConfig::getSamples((int) $context->shop->id)
        );
        $link = $context->link;
        $idLang = (int) $context->language->id;
        $vars = [
            '{shop_logo}' => self::logoUrl(),
            '{shop_name}' => Configuration::get('PS_SHOP_NAME', null, null, (int) $context->shop->id),
            '{shop_url}' => $link->getPageLink('index', true, $idLang),
            '{my_account_url}' => $link->getPageLink('my-account', true, $idLang),
            '{guest_tracking_url}' => $link->getPageLink('guest-tracking', true, $idLang),
            '{history_url}' => $link->getPageLink('history', true, $idLang),
            '{order_slip_url}' => $link->getPageLink('order-slip', true, $idLang),
            '{color}' => (string) Configuration::get('PS_MAIL_COLOR'),
        ];

        $order = self::lastOrder($context);
        if ($order) {
            $vars = array_merge($vars, BkMailDesignerOrderVars::forOrder($order, $context));
            $vars['{voucher_num}'] = 'BIENVENIDA10';
            $vars['{voucher_amount}'] = BkMailDesignerOrderVars::price(10, 'EUR', $context);
        }

        foreach ($names as $name) {
            $key = '{' . $name . '}';
            if (!isset($vars[$key])) {
                $vars[$key] = self::generic($name, $context);
            }
        }

        foreach ($chosen as $name => $value) {
            $vars['{' . $name . '}'] = $value;
        }

        return $vars;
    }

    /**
     * URL del logo que el núcleo incrustará en el envío real.
     */
    public static function logoUrl()
    {
        $mail = (string) Configuration::get('PS_LOGO_MAIL');
        $file = $mail !== '' && is_file(_PS_IMG_DIR_ . $mail) ? $mail : (string) Configuration::get('PS_LOGO');

        return Tools::getShopProtocol() . Tools::getShopDomain() . __PS_BASE_URI__ . 'img/' . $file;
    }

    /**
     * @return Order|null
     */
    private static function lastOrder(Context $context)
    {
        $id = (int) Db::getInstance()->getValue(
            'SELECT `id_order` FROM `' . _DB_PREFIX_ . 'orders`
             WHERE `id_shop` = ' . (int) $context->shop->id . ' AND `valid` = 1
             ORDER BY `id_order` DESC'
        );
        if (!$id) {
            $id = (int) Db::getInstance()->getValue(
                'SELECT `id_order` FROM `' . _DB_PREFIX_ . 'orders`
                 WHERE `id_shop` = ' . (int) $context->shop->id . ' ORDER BY `id_order` DESC'
            );
        }
        if (!$id) {
            return null;
        }
        $order = new Order($id);

        return Validate::isLoadedObject($order) ? $order : null;
    }

    /**
     * Valor de ejemplo para una variable sin dato real, por familia de nombre.
     */
    private static function generic($name, Context $context)
    {
        $samples = [
            'firstname' => 'María', 'lastname' => 'García', 'email' => 'maria@ejemplo.com',
            'passwd' => '••••••••', 'url' => $context->link->getPageLink('my-account', true),
            'link' => $context->link->getPageLink('my-account', true),
            'order_name' => 'KHWLILZLL', 'id_order' => '1042', 'order_link' => $context->link->getPageLink('history', true),
            'date' => Tools::displayDate(date('Y-m-d H:i:s'), true),
            'message' => 'Hola, ¿podríais confirmarme la fecha de entrega? Gracias.',
            'reply' => 'Hola María, tu pedido sale mañana y te llegará en 48 horas. Un saludo.',
            'comment' => 'Cliente atendida por teléfono, pendiente de respuesta.',
            'messages' => 'Hola, ¿podríais confirmarme la fecha de entrega? Gracias.',
            'employee' => 'Ana López', 'attached_file' => '-', 'product_name' => 'Producto de ejemplo',
            'product' => 'Producto de ejemplo', 'qty' => '0', 'last_qty' => '3',
            'followup' => 'https://www.correos.es/seguimiento/PQ123456789ES',
            'meta_products' => 'Producto de ejemplo', 'filename' => 'productos.csv',
            'id_order_return' => '7', 'state_order_return' => 'Paquete recibido',
            'voucher_num' => 'BIENVENIDA10', 'voucher_amount' => '10 €',
            'bankwire_owner' => 'BK Modules SL', 'bankwire_details' => 'ES12 3456 7890 1234 5678 9012',
            'bankwire_address' => 'Banco Ejemplo, Santander', 'check_name' => 'BK Modules SL',
            'check_address_html' => 'Calle Ejemplo 1<br>39001 Santander',
            'carrier' => 'Correos Express', 'payment' => 'Tarjeta',
            'total_paid' => '148,99 €', 'total_products' => '148,99 €', 'total_discounts' => '0,00 €',
            'total_shipping' => '0,00 €', 'total_tax_paid' => '25,85 €',
            'delivery_block_html' => 'María García<br>Calle Ejemplo 1<br>39001 Santander<br>España',
            'invoice_block_html' => 'María García<br>Calle Ejemplo 1<br>39001 Santander<br>España',
            'products' => BkMailDesignerOrderVars::productRow('BKALT', 'Producto de ejemplo', '49,99 €', 1, '49,99 €'),
            'discounts' => '', 'recycled_packaging_label' => '',
            'status' => 'Aceptada', 'reason' => 'No es lo que esperaba',
            'comment' => 'Cliente atendida por teléfono, pendiente de respuesta.',
            'discount' => 'BIENVENIDA10', 'phone' => '+34 600 000 000',
            'post_title' => 'Artículo de ejemplo del blog', 'comment_content' => 'Muy útil, gracias por el artículo.',
            'current_coverage' => '3', 'warning_coverage' => '7',
        ];
        if (isset($samples[$name])) {
            return $samples[$name];
        }

        // Familias de nombre: un módulo de terceros llama a lo mismo de muchas maneras y la vista
        // previa tiene que enseñar algo creíble sin conocer ese módulo.
        $rows = BkMailDesignerOrderVars::productRow('BKALT', 'Producto de ejemplo', '49,99 €', 1, '49,99 €');
        $suffixes = [
            '_html' => $rows, '_url' => $context->link->getPageLink('index', true),
            '_link' => $context->link->getPageLink('index', true), '_id' => '1042',
            '_reference' => 'KHWLILZLL', '_date' => Tools::displayDate(date('Y-m-d H:i:s'), true),
            '_email' => 'maria@ejemplo.com', '_name' => 'María García', '_message' => $samples['message'],
        ];
        foreach ($suffixes as $suffix => $value) {
            if (substr($name, -Tools::strlen($suffix)) === $suffix) {
                return $value;
            }
        }
        if ($name === 'items') {
            return $rows;
        }
        if (strpos($name, 'html_') === 0 || strpos($name, 'status_') === 0) {
            return '<p>' . $samples['message'] . '</p>';
        }
        if (strpos($name, 'date_') === 0) {
            return Tools::displayDate(date('Y-m-d H:i:s'), true);
        }

        return '{' . $name . '}';
    }
}
