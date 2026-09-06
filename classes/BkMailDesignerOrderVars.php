<?php
/**
 * Las variables del correo de confirmación de un pedido, reconstruidas desde el pedido guardado.
 *
 * PrestaShop las arma una sola vez, dentro de `PaymentModule::validateOrder()`, y no las guarda:
 * para volver a enviar la confirmación de un pedido de hace un mes hay que rehacerlas. Aquí se
 * rehacen con los mismos nombres y el mismo formato de tabla que usa el núcleo, así que sirven
 * tanto para reenviar de verdad como para la vista previa del editor.
 *
 * @author BK Modules
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class BkMailDesignerOrderVars
{
    /**
     * @param Order   $order
     * @param Context $context
     *
     * @return array {clave} => valor
     */
    public static function forOrder(Order $order, Context $context)
    {
        $customer = new Customer((int) $order->id_customer);
        $currency = new Currency((int) $order->id_currency);
        $delivery = new Address((int) $order->id_address_delivery);
        $invoice = new Address((int) $order->id_address_invoice);
        $carrier = new Carrier((int) $order->id_carrier, (int) $context->language->id);
        $iso = $currency->iso_code;

        $rows = '';
        foreach ($order->getProducts() as $product) {
            $rows .= self::productRow(
                $product['product_reference'],
                $product['product_name'],
                self::price($product['unit_price_tax_incl'], $iso, $context),
                (int) $product['product_quantity'],
                self::price($product['total_price_tax_incl'], $iso, $context)
            );
        }

        $discounts = '';
        foreach ($order->getCartRules() as $rule) {
            $discounts .= self::discountRow($rule['name'], '-' . self::price($rule['value'], $iso, $context));
        }

        $format = function (Address $address) {
            return AddressFormat::generateAddress($address, [], '<br />', ' ');
        };

        return [
            '{firstname}' => $customer->firstname,
            '{lastname}' => $customer->lastname,
            '{email}' => $customer->email,
            '{order_name}' => $order->getUniqReference(),
            '{id_order}' => (int) $order->id,
            '{date}' => Tools::displayDate($order->date_add, true),
            '{carrier}' => $carrier->name ? $carrier->name : '-',
            '{payment}' => $order->payment,
            '{products}' => $rows,
            '{discounts}' => $discounts,
            '{total_paid}' => self::price($order->total_paid, $iso, $context),
            '{total_products}' => self::price($order->total_products_wt, $iso, $context),
            '{total_discounts}' => self::price($order->total_discounts, $iso, $context),
            '{total_shipping}' => self::price($order->total_shipping, $iso, $context),
            '{total_tax_paid}' => self::price($order->total_paid_tax_incl - $order->total_paid_tax_excl, $iso, $context),
            '{delivery_block_html}' => Validate::isLoadedObject($delivery) ? $format($delivery) : '',
            '{invoice_block_html}' => Validate::isLoadedObject($invoice) ? $format($invoice) : '',
            '{recycled_packaging_label}' => '',
        ];
    }

    /**
     * Fila de producto con la estructura del partial `order_conf_product_list.tpl` del núcleo: el
     * bloque de tabla del editor la re-estiliza, y una plantilla sin diseñar la sigue entendiendo.
     *
     * @param string $reference
     * @param string $name
     * @param string $unit
     * @param int    $qty
     * @param string $total
     *
     * @return string
     */
    public static function productRow($reference, $name, $unit, $qty, $total)
    {
        $cell = function ($content, $align = 'left') {
            return '<td style="border:1px solid #D6D4D4;"><table class="table"><tr><td width="5">&nbsp;</td>'
                . '<td align="' . $align . '"><font size="2" face="Open-sans, sans-serif" color="#555454">' . $content . '</font></td>'
                . '<td width="5">&nbsp;</td></tr></table></td>';
        };

        return '<tr>' . $cell(htmlspecialchars($reference)) . $cell('<strong>' . htmlspecialchars($name) . '</strong>')
            . $cell($unit, 'right') . $cell((int) $qty, 'right') . $cell($total, 'right') . '</tr>';
    }

    /**
     * @param string $name
     * @param string $value
     *
     * @return string
     */
    public static function discountRow($name, $value)
    {
        $cell = function ($content) {
            return '<td bgcolor="#f8f8f8" colspan="4" style="border:1px solid #D6D4D4;color:#333;padding:7px 0">'
                . '<table class="table" style="width:100%;border-collapse:collapse"><tr><td width="5"></td>'
                . '<td align="right"><font size="2" face="Open-sans, sans-serif" color="#555454">' . $content . '</font></td>'
                . '<td width="5"></td></tr></table></td>';
        };

        return '<tr class="conf_body">' . $cell('<strong>' . htmlspecialchars($name) . '</strong>') . $cell($value) . '</tr>';
    }

    /**
     * El formateador de precios se pide al contexto, que lo trae desde 1.7.6, y al repositorio de
     * localización cuando el contexto aún no lo tiene. `Tools` no sirve: `getContextLocale()` es
     * protected en 1.7.6 y `displayPrice()` ya no existe en 9.
     *
     * @param float   $amount
     * @param string  $iso
     * @param Context $context
     *
     * @return string
     */
    public static function price($amount, $iso, Context $context)
    {
        $locale = isset($context->currentLocale) ? $context->currentLocale : null;
        if ($locale === null) {
            $container = \PrestaShop\PrestaShop\Adapter\SymfonyContainer::getInstance();
            if ($container !== null) {
                $locale = $container->get('prestashop.core.localization.locale.repository')->getLocale($context->language->locale);
            }
        }
        if ($locale !== null) {
            return $locale->formatPrice((float) $amount, $iso);
        }

        return number_format((float) $amount, 2, ',', '.') . ' ' . $iso;
    }
}
