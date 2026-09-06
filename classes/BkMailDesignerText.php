<?php
/**
 * Versión de texto plano de un correo a partir de su HTML.
 *
 * Es propia porque el conversor del núcleo cambia de librería entre versiones (Html2Text en 1.7,
 * Soundasleep en 9) y el resultado tiene que ser el mismo en todas.
 *
 * @author BK Modules
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class BkMailDesignerText
{
    /**
     * @param string $html
     *
     * @return string
     */
    public static function fromHtml($html)
    {
        $text = (string) $html;
        // Cabecera, estilos y el texto oculto de previsualización no forman parte del mensaje
        $text = preg_replace('#<head\b[^>]*>.*?</head>#is', '', $text);
        $text = preg_replace('#<(script|style)\b[^>]*>.*?</\1>#is', '', $text);
        $text = preg_replace('#<!--.*?-->#s', '', $text);
        $text = preg_replace('#<div[^>]*class="[^"]*bk-pre[^"]*"[^>]*>.*?</div>#is', '', $text);
        // Un enlace conserva su destino: en texto plano es lo único que lo hace útil. El que solo
        // envuelve una imagen —el logo— no aporta nada y desaparece con ella.
        $text = preg_replace_callback(
            '#<a\b[^>]*href="([^"]+)"[^>]*>(.*?)</a>#is',
            function ($m) {
                $label = trim(strip_tags($m[2]));
                $url = html_entity_decode($m[1], ENT_QUOTES, 'UTF-8');
                if ($label === '') {
                    return stripos($m[2], '<img') !== false ? '' : $url;
                }
                if ($label === $url || strpos($url, 'mailto:') === 0) {
                    return $label;
                }

                return $label . ' (' . $url . ')';
            },
            $text
        );
        $text = preg_replace('#<br\s*/?>#i', "\n", $text);
        $text = preg_replace('#</(p|div|tr|li|h[1-6]|table|ul|ol|blockquote)>#i', "\n", $text);
        $text = preg_replace('#</t[dh]>#i', "\t", $text);
        $text = preg_replace('#<li\b[^>]*>#i', '- ', $text);
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_replace("\xC2\xA0", ' ', $text);
        $lines = [];
        foreach (explode("\n", $text) as $line) {
            $line = trim(preg_replace('/[ \t]+/', ' ', $line));
            $lines[] = $line;
        }
        $text = implode("\n", $lines);
        $text = preg_replace("/\n{3,}/", "\n\n", $text);

        return trim($text);
    }

    /**
     * Etiquetas de la tabla de pedido y de las direcciones en el idioma del destinatario.
     *
     * Se leen del XLF del idioma pedido y no del traductor del contexto: un correo se redacta en
     * el idioma de quien lo recibe, que no tiene por qué ser el del back office que lo dispara.
     *
     * @param int $idLang
     *
     * @return array
     */
    public static function orderLabels($idLang)
    {
        $language = new Language((int) $idLang);
        $locale = Validate::isLoadedObject($language) ? $language->locale : 'en-US';

        $labels = [
            'reference' => 'Reference',
            'product' => 'Product',
            'unit_price' => 'Unit price',
            'quantity' => 'Qty',
            'total' => 'Total',
            'subtotal' => 'Products',
            'shipping' => 'Shipping',
            'discounts' => 'Discounts',
            'tax' => 'Taxes',
            'total_paid' => 'Total paid',
            'free_shipping' => 'Free',
            'delivery' => 'Delivery address',
            'invoice' => 'Billing address',
        ];
        foreach ($labels as $key => $source) {
            $labels[$key] = BkMailDesignerTranslations::trans(BkMailDesignerTranslations::DOMAIN_SHOP, $locale, $source);
        }

        return $labels;
    }
}
