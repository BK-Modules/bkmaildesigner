<?php
/**
 * Bloques del cuerpo de un correo diseñado: de la lista JSON que guarda el editor al HTML de
 * tablas que entienden todos los clientes de correo.
 *
 * Cada bloque es una fila de la tabla del cuerpo. El HTML sale limpio, con clases; el color y
 * la tipografía los aplica después el renderer con el CSS del layout pasado a estilos en línea.
 * Un bloque de tipo desconocido no se pinta: el editor solo produce los de esta lista.
 *
 * @author BK Modules
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class BkMailDesignerBlocks
{
    /** Tipos de bloque admitidos, en el orden en que los ofrece el editor */
    const TYPES = ['heading', 'text', 'button', 'box', 'divider', 'spacer', 'image', 'media', 'hero', 'social', 'columns', 'order', 'addresses', 'html'];

    /** Etiquetas HTML que un texto enriquecido puede llevar */
    /**
     * Lo que un texto puede llevar. No es la lista corta de un editor de comentarios: aquí se
     * escribe el cuerpo de un correo, y quien sepa HTML tiene que poder pegar una tabla o un
     * `div` con su estilo. Fuera se quedan solo las que un correo no puede ejecutar ni cargar.
     */
    const ALLOWED_TAGS = [
        'p', 'br', 'b', 'strong', 'i', 'em', 'u', 's', 'a', 'ul', 'ol', 'li', 'span', 'div',
        'h1', 'h2', 'h3', 'h4', 'small', 'sup', 'sub', 'blockquote', 'hr', 'img', 'font',
        'table', 'thead', 'tbody', 'tfoot', 'tr', 'td', 'th',
    ];

    /**
     * Las cinco líneas de totales con su variable y lo que hacen de serie.
     *
     * `auto` es lo razonable en las dos que suelen venir a cero: un pedido sin descuento no
     * enseña «Descuentos 0,00 €», y un catálogo con el impuesto incluido no enseña «Impuestos».
     */
    const TOTAL_ROWS = [
        'subtotal' => 'always',
        'shipping' => 'always',
        'discounts' => 'auto',
        'tax' => 'auto',
        'total_paid' => 'always',
    ];

    /** Variable de cada línea de totales */
    const TOTAL_VARS = [
        'subtotal' => '{total_products}',
        'shipping' => '{total_shipping}',
        'discounts' => '{total_discounts}',
        'tax' => '{total_tax_paid}',
        'total_paid' => '{total_paid}',
    ];

    /** Cuándo sale una línea de totales */
    const TOTAL_MODES = ['always', 'auto', 'never'];

    /** Qué direcciones enseña el bloque: las dos, solo la de facturación o solo la de entrega */
    const ADDRESS_SHOWS = ['both', 'invoice', 'delivery'];

    /** Atributos que sobreviven, por etiqueta; `*` vale para todas */
    const ALLOWED_ATTRS = [
        '*' => ['style', 'class', 'align', 'valign', 'width', 'height', 'dir', 'title'],
        'a' => ['href', 'target', 'rel'],
        'img' => ['src', 'alt', 'border'],
        'td' => ['colspan', 'rowspan', 'bgcolor'],
        'th' => ['colspan', 'rowspan', 'bgcolor'],
        'tr' => ['bgcolor'],
        'table' => ['border', 'cellpadding', 'cellspacing', 'bgcolor', 'role'],
        'font' => ['color', 'face', 'size'],
    ];

    /**
     * @param array $blocks Lista de bloques ya validada con sanitize()
     * @param array $ctx    ['align' => left|right, 'labels' => textos de la tabla de pedido]
     *
     * @return string Filas <tr> para la tabla del cuerpo
     */
    public static function render(array $blocks, array $ctx)
    {
        $out = '';
        foreach ($blocks as $index => $block) {
            $method = 'block' . ucfirst($block['type']);
            if (!method_exists(__CLASS__, $method)) {
                continue;
            }
            // La vista previa del editor marca cada fila con su posición: es lo que permite
            // seleccionar un bloque haciendo clic en el correo y no en la lista de la izquierda.
            self::$index = !empty($ctx['mark']) ? (int) $index : null;
            // Un bloque sin contenido no deja un hueco en el correo: no se pinta
            $out .= self::isEmpty($block) ? '' : self::$method($block, $ctx);
        }
        self::$index = null;

        return $out;
    }

    /** Posición del bloque que se está pintando, solo para la vista previa del editor */
    private static $index = null;

    /**
     * Si un bloque no tiene nada que enseñar.
     *
     * Un título sin texto, un botón sin rótulo o una imagen sin dirección no son un bloque vacío
     * en la pantalla: son un hueco, una raya o el icono de imagen rota en el correo del cliente.
     *
     * @param array $block
     *
     * @return bool
     */
    private static function isEmpty(array $block)
    {
        switch ($block['type']) {
            case 'heading':
            case 'button':
                return trim($block['text']) === '';
            case 'text':
            case 'box':
                return trim(strip_tags($block['html'], '<img><table><hr>')) === '';
            case 'image':
                return $block['src'] === '';
            case 'media':
                return $block['src'] === '' && trim(strip_tags($block['html'])) === '';
            case 'html':
                return trim($block['html']) === '';
            case 'columns':
                $all = $block['left'] . $block['right'] . ($block['count'] === '3' ? $block['third'] : '');

                return trim(strip_tags($all, '<img><table><hr>')) === '';
            default:
                return false;
        }
    }

    /**
     * Limpia una lista de bloques tal como llega del editor: tipos conocidos, campos con el tipo
     * esperado y HTML sin nada que un correo no deba llevar.
     *
     * @param mixed $list
     *
     * @return array
     */
    public static function sanitize($list)
    {
        $out = [];
        if (!is_array($list)) {
            return $out;
        }
        foreach ($list as $block) {
            if (!is_array($block) || !isset($block['type']) || !in_array($block['type'], self::TYPES, true)) {
                continue;
            }
            $clean = ['type' => $block['type']];
            // Ajustes que cualquier bloque acepta. Vacío o cero significa «lo que diga el tema»:
            // así un correo sigue cambiando de aspecto al cambiar de tema salvo donde se ha
            // decidido lo contrario a propósito.
            $clean['pad_top'] = self::spacing(self::get($block, 'pad_top'));
            $clean['pad_bottom'] = self::spacing(self::get($block, 'pad_bottom'));
            $clean['background'] = self::color(self::get($block, 'background'));
            // A sangre: el bloque se salta el margen lateral de la tarjeta
            $clean['bleed'] = self::get($block, 'bleed') ? 1 : 0;
            switch ($block['type']) {
                case 'heading':
                    $clean['text'] = self::plain(self::get($block, 'text'));
                    $clean['size'] = self::get($block, 'size') === 'h2' ? 'h2' : 'h1';
                    $clean['align'] = self::align(self::get($block, 'align'));
                    $clean['color'] = self::color(self::get($block, 'color'));
                    $clean['font_size'] = self::size(self::get($block, 'font_size'), 12, 44);
                    break;
                case 'text':
                    $clean['html'] = self::richText(self::get($block, 'html'));
                    $clean['align'] = self::align(self::get($block, 'align'));
                    $clean['tone'] = self::get($block, 'tone') === 'muted' ? 'muted' : 'normal';
                    $clean['color'] = self::color(self::get($block, 'color'));
                    $clean['font_size'] = self::size(self::get($block, 'font_size'), 10, 26);
                    break;
                case 'button':
                    $clean['text'] = self::plain(self::get($block, 'text'));
                    $clean['url'] = self::url(self::get($block, 'url'));
                    $clean['align'] = self::align(self::get($block, 'align'));
                    // Vacío significa «el del tema»: es lo que hace que cambiar de tema cambie
                    // también la forma del botón sin tocar una sola plantilla.
                    $clean['style'] = in_array(self::get($block, 'style'), ['solid', 'outline'], true)
                        ? self::get($block, 'style') : '';
                    $clean['color'] = self::color(self::get($block, 'color'));
                    $clean['size'] = in_array(self::get($block, 'size'), ['s', 'm', 'l'], true) ? self::get($block, 'size') : '';
                    $clean['full'] = !empty($block['full']);
                    break;
                case 'box':
                    $clean['html'] = self::richText(self::get($block, 'html'));
                    $clean['align'] = self::align(self::get($block, 'align'));
                    $clean['tone'] = in_array(self::get($block, 'tone'), ['neutral', 'primary', 'success', 'warning'], true)
                        ? self::get($block, 'tone') : 'neutral';
                    $clean['color'] = self::color(self::get($block, 'color'));
                    break;
                case 'divider':
                    $clean['style'] = in_array(self::get($block, 'style'), ['solid', 'dotted', 'thick'], true)
                        ? self::get($block, 'style') : '';
                    $clean['color'] = self::color(self::get($block, 'color'));
                    $clean['width'] = self::size(self::get($block, 'width'), 10, 100);
                    break;
                case 'spacer':
                    $clean['height'] = max(4, min(80, (int) self::get($block, 'height', 16)));
                    break;
                case 'image':
                    $clean['src'] = self::url(self::get($block, 'src'));
                    $clean['alt'] = self::plain(self::get($block, 'alt'));
                    $clean['url'] = self::url(self::get($block, 'url'));
                    $clean['width'] = max(40, min(640, (int) self::get($block, 'width', 536)));
                    $clean['align'] = self::align(self::get($block, 'align'));
                    $clean['radius'] = self::size(self::get($block, 'radius'), 0, 40);
                    break;
                case 'media':
                    $clean['src'] = self::url(self::get($block, 'src'));
                    $clean['alt'] = self::plain(self::get($block, 'alt'));
                    $clean['url'] = self::url(self::get($block, 'url'));
                    $clean['html'] = self::richText(self::get($block, 'html'));
                    $clean['side'] = self::get($block, 'side') === 'right' ? 'right' : 'left';
                    $clean['ratio'] = in_array((string) self::get($block, 'ratio'), ['30', '40', '50'], true)
                        ? (string) self::get($block, 'ratio') : '40';
                    $clean['radius'] = self::size(self::get($block, 'radius'), 0, 40);
                    $clean['valign'] = self::get($block, 'valign') === 'middle' ? 'middle' : 'top';
                    break;
                case 'social':
                    $clean['align'] = self::align(self::get($block, 'align'));
                    break;
                case 'columns':
                    $clean['left'] = self::richText(self::get($block, 'left'));
                    $clean['right'] = self::richText(self::get($block, 'right'));
                    $clean['third'] = self::richText(self::get($block, 'third'));
                    $clean['count'] = (string) self::get($block, 'count') === '3' ? '3' : '2';
                    $clean['ratio'] = in_array(self::get($block, 'ratio'), ['50', '60', '40'], true)
                        ? self::get($block, 'ratio') : '50';
                    $clean['align'] = self::align(self::get($block, 'align'));
                    $clean['valign'] = self::get($block, 'valign') === 'middle' ? 'middle' : 'top';
                    break;
                case 'hero':
                    $clean['src'] = self::url(self::get($block, 'src'));
                    $clean['url'] = self::url(self::get($block, 'url'));
                    $clean['heading'] = self::plain(self::get($block, 'heading'));
                    $clean['html'] = self::richText(self::get($block, 'html'));
                    $clean['btn_text'] = self::plain(self::get($block, 'btn_text'));
                    $clean['btn_url'] = self::url(self::get($block, 'btn_url'));
                    $clean['align'] = self::align(self::get($block, 'align'));
                    $clean['height'] = self::size(self::get($block, 'height'), 80, 520);
                    $clean['veil'] = self::size(self::get($block, 'veil'), 0, 90);
                    $clean['color'] = self::color(self::get($block, 'color'));
                    $clean['background'] = self::color(self::get($block, 'background'));
                    break;
                case 'order':
                    // La tabla de líneas no siempre llega en {products}: ps_emailalerts la manda en
                    // {items}, y un módulo puede traer la suya. La variable es parte del bloque.
                    $clean['source'] = self::varName(self::get($block, 'source'), 'products');
                    $clean['discount_source'] = self::varName(self::get($block, 'discount_source'), 'discounts');
                    $clean['totals'] = self::get($block, 'totals') === false ? false : true;
                    $clean['layout'] = in_array(self::get($block, 'layout'), ['stacked', 'table'], true)
                        ? self::get($block, 'layout') : '';
                    // Qué se enseña de cada línea. '' = lo que diga el tema.
                    foreach (['show_image', 'show_reference', 'show_options', 'show_unit'] as $flag) {
                        $value = self::get($block, $flag);
                        $clean[$flag] = ($value === '' || $value === null) ? '' : (int) (bool) $value;
                    }
                    $clean['image_size'] = self::get($block, 'image_size') ? (int) self::get($block, 'image_size') : 0;
                    // Cada línea de totales decide si sale siempre, solo cuando trae importe, o
                    // nunca: un pedido sin descuento no tiene por qué enseñar «Descuentos 0,00 €».
                    $modes = self::get($block, 'totals_mode');
                    $clean['totals_mode'] = [];
                    foreach (self::TOTAL_ROWS as $key => $default) {
                        $mode = is_array($modes) && isset($modes[$key]) ? $modes[$key] : $default;
                        $clean['totals_mode'][$key] = in_array($mode, self::TOTAL_MODES, true) ? $mode : $default;
                    }
                    // no break
                case 'addresses':
                    // Una tienda que vende descargas no entrega nada: enseñar una
                    // «dirección de entrega» en su correo sobra y confunde.
                    $shows = self::get($block, 'shows');
                    $clean['shows'] = in_array($shows, self::ADDRESS_SHOWS, true) ? $shows : 'both';
                    $labels = self::get($block, 'labels');
                    $clean['labels'] = [];
                    if (is_array($labels)) {
                        foreach ($labels as $key => $value) {
                            if (preg_match('/^[a-z_]+$/', (string) $key)) {
                                $clean['labels'][$key] = self::plain($value);
                            }
                        }
                    }
                    break;
                case 'html':
                    $clean['html'] = self::rawHtml(self::get($block, 'html'));
                    break;
            }
            $out[] = $clean;
        }

        return $out;
    }

    /**
     * Primer texto legible de la lista, para la línea de previsualización de la bandeja de entrada.
     *
     * @param array $blocks
     *
     * @return string
     */
    public static function preheader(array $blocks)
    {
        // Lo primero que se lee en la bandeja de entrada, y no todos los bloques tienen texto:
        // se recorre en orden y se coge lo primero que lo tenga, sea del tipo que sea.
        foreach ($blocks as $block) {
            foreach (['html', 'text', 'heading', 'left'] as $field) {
                if (!isset($block[$field])) {
                    continue;
                }
                $text = trim(preg_replace('/\s+/', ' ', strip_tags((string) $block[$field])));
                if ($text !== '') {
                    return Tools::substr($text, 0, 120);
                }
            }
        }

        return '';
    }

    // ---- bloques ------------------------------------------------------------------------------

    private static function blockHeading(array $b, array $ctx)
    {
        $tag = $b['size'] === 'h2' ? 'h2' : 'h1';
        $style = self::inline(['color' => $b['color'], 'font-size' => $b['font_size'] ? $b['font_size'] . 'px' : '']);

        return self::row('bk-heading', '<' . $tag . ' class="bk-' . $tag . '" align="' . self::alignAttr($b['align'], $ctx) . '"' . $style . '>' . self::esc($b['text']) . '</' . $tag . '>', $b);
    }

    private static function blockText(array $b, array $ctx)
    {
        $class = 'bk-text' . ($b['tone'] === 'muted' ? ' bk-muted' : '');
        $style = self::inline(['color' => $b['color'], 'font-size' => $b['font_size'] ? $b['font_size'] . 'px' : '']);

        return self::row($class, '<div class="bk-rich" align="' . self::alignAttr($b['align'], $ctx) . '"' . $style . '>' . self::paragraphs($b['html']) . '</div>', $b);
    }

    private static function blockButton(array $b, array $ctx)
    {
        $align = self::alignAttr($b['align'], $ctx);
        $style = $b['style'] !== '' ? $b['style'] : (isset($ctx['button_style']) ? $ctx['button_style'] : 'solid');
        $outline = $style === 'outline';
        $cellClass = $outline ? 'bk-btn-cell bk-btn-outline' : 'bk-btn-cell bk-btn-solid';
        $linkClass = $outline ? 'bk-btn bk-btn-outline-link' : 'bk-btn bk-btn-solid-link';

        // El color propio va al fondo si el botón es macizo y al borde y al texto si lleva borde
        $cellStyle = self::inline([
            'background-color' => $outline ? '' : $b['color'],
            'border-color' => $outline ? $b['color'] : '',
            'width' => $b['full'] ? '100%' : '',
        ]);
        $pad = ['s' => '9px 18px', 'm' => '13px 28px', 'l' => '17px 34px'];
        $font = ['s' => '13px', 'm' => '15px', 'l' => '16px'];
        $linkStyle = self::inline([
            'color' => $outline ? $b['color'] : '',
            'padding' => $b['size'] ? $pad[$b['size']] : '',
            'font-size' => $b['size'] ? $font[$b['size']] : '',
            'display' => $b['full'] ? 'block' : '',
            'text-align' => $b['full'] ? 'center' : '',
        ]);
        $tableStyle = self::inline(['width' => $b['full'] ? '100%' : '']);

        $html = '<table role="presentation" cellpadding="0" cellspacing="0" border="0" align="' . $align . '" class="bk-btn-table"' . $tableStyle . '>'
            . '<tr><td align="center" class="' . $cellClass . '"' . $cellStyle . '>'
            . '<a href="' . self::esc($b['url']) . '" class="' . $linkClass . '" target="_blank"' . $linkStyle . '>' . self::esc($b['text']) . '</a>'
            . '</td></tr></table>';

        return self::row('bk-button', $html, $b);
    }

    private static function blockBox(array $b, array $ctx)
    {
        $style = self::inline(['background-color' => $b['color']]);
        $html = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">'
            . '<tr><td class="bk-box bk-box-' . $b['tone'] . '" align="' . self::alignAttr($b['align'], $ctx) . '"' . $style . '>'
            . self::paragraphs($b['html']) . '</td></tr></table>';

        return self::row('bk-boxrow', $html, $b);
    }

    private static function blockDivider(array $b, array $ctx)
    {
        $width = ['solid' => 1, 'dotted' => 1, 'thick' => 3];
        $style = self::inline([
            'border-top-style' => $b['style'] === 'dotted' ? 'dotted' : ($b['style'] ? 'solid' : ''),
            'border-top-width' => $b['style'] ? $width[$b['style']] . 'px' : '',
            'border-top-color' => $b['color'],
            'width' => $b['width'] ? $b['width'] . '%' : '',
        ]);

        return self::row('bk-divider', '<div class="bk-rule"' . $style . '>&nbsp;</div>', $b, 'bk-divider');
    }

    private static function blockSpacer(array $b, array $ctx)
    {
        $mark = self::$index === null ? '' : ' data-bk-i="' . self::$index . '"';

        return '<tr' . $mark . '><td class="bk-spacer" height="' . (int) $b['height'] . '" style="height:' . (int) $b['height'] . 'px;line-height:' . (int) $b['height'] . 'px;font-size:1px;">&nbsp;</td></tr>';
    }

    private static function blockImage(array $b, array $ctx)
    {
        if ($b['src'] === '') {
            return '';
        }
        $radius = $b['radius'] ? 'border-radius:' . (int) $b['radius'] . 'px;' : '';
        $img = '<img src="' . self::esc($b['src']) . '" alt="' . self::esc($b['alt']) . '" width="' . (int) $b['width'] . '" class="bk-img" style="width:' . (int) $b['width'] . 'px;max-width:100%;height:auto;display:block;border:0;' . $radius . '">';
        if ($b['url'] !== '') {
            $img = '<a href="' . self::esc($b['url']) . '" target="_blank">' . $img . '</a>';
        }

        return self::row('bk-image', $img, $b, 'bk-image', ' align="' . self::alignAttr($b['align'], $ctx) . '"');
    }

    /**
     * Dos o tres columnas. En un móvil se apilan con la hoja del correo, así que el orden en que
     * se escriben es el orden en que se leen en pantalla estrecha.
     */
    private static function blockColumns(array $b, array $ctx)
    {
        $align = self::alignAttr($b['align'], $ctx);
        $valign = $b['valign'];
        $cell = function ($class, $width, $html) use ($align, $valign) {
            return '<td class="bk-col ' . $class . '" width="' . $width . '%" valign="' . $valign . '" align="' . $align . '">'
                . self::paragraphs($html) . '</td>';
        };

        if ($b['count'] === '3') {
            $cells = $cell('bk-col-first', 34, $b['left'])
                . $cell('bk-col-mid', 33, $b['right'])
                . $cell('bk-col-last', 33, $b['third']);
        } else {
            $left = (int) $b['ratio'];
            $cells = $cell('bk-col-first', $left, $b['left'])
                . $cell('bk-col-last', 100 - $left, $b['right']);
        }

        return self::row('bk-columns bk-columns--' . $b['count'], '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr>' . $cells . '</tr></table>', $b);
    }

    /**
     * Portada: una foto con el texto y el botón encima.
     *
     * El fondo de una celda no lo pinta Outlook, así que la misma imagen va dos veces: en el
     * atributo `background` para todos los demás y dentro de un `v:rect` para Outlook, que es la
     * única forma de que ahí también se vea. El velo oscurece la foto lo justo para que el texto
     * se lea encima; sin foto, el bloque es una banda de color y sigue teniendo sentido.
     */
    private static function blockHero(array $b, array $ctx)
    {
        $align = self::alignAttr($b['align'], $ctx);
        // El alto es un mínimo, y solo tiene sentido con foto: sin ella manda el contenido y
        // una banda de color no se queda medio vacía.
        $height = $b['src'] !== '' ? ($b['height'] ? (int) $b['height'] : 240) : (int) $b['height'];
        $fallback = $b['background'] !== '' ? $b['background'] : (isset($ctx['settings']['color_primary']) ? $ctx['settings']['color_primary'] : '#333333');
        $text = $b['color'] !== '' ? $b['color'] : '#ffffff';
        $veil = (int) $b['veil'];

        $inner = '';
        if ($b['heading'] !== '') {
            $inner .= '<div class="bk-hero__title" style="color:' . $text . ';">' . self::esc($b['heading']) . '</div>';
        }
        if (trim(strip_tags($b['html'])) !== '') {
            $inner .= '<div class="bk-hero__text" style="color:' . $text . ';">' . self::paragraphs($b['html']) . '</div>';
        }
        if ($b['btn_text'] !== '') {
            // Sobre el color de marca un botón del color de marca no se ve: aquí va en claro
            $inner .= '<table role="presentation" cellpadding="0" cellspacing="0" border="0" align="' . $align . '" class="bk-hero__btntable"><tr>'
                . '<td class="bk-btn-cell bk-hero__btn">'
                . '<a href="' . self::esc($b['btn_url']) . '" target="_blank" class="bk-btn bk-btn-solid-link bk-hero__btnlink">' . self::esc($b['btn_text']) . '</a>'
                . '</td></tr></table>';
        }

        $size = $height > 0 ? ' height="' . $height . '"' : '';
        $veilStyle = ($veil > 0 ? 'background-color:rgba(0,0,0,' . number_format($veil / 100, 2, '.', '') . ');' : '')
            . ($height > 0 ? 'height:' . $height . 'px;' : '');
        $veilCell = '<td class="bk-hero__veil" align="' . $align . '" valign="middle"' . $size
            . ($veilStyle !== '' ? ' style="' . $veilStyle . '"' : '') . '>' . $inner . '</td>';

        $image = $b['src'] !== '' ? ' background="' . self::esc($b['src']) . '"' : '';
        $mso = $b['src'] !== ''
            ? '<!--[if gte mso 9]><v:rect xmlns:v="urn:schemas-microsoft-com:vml" fill="true" stroke="false" style="width:100%;height:' . $height . 'px;">'
                . '<v:fill type="frame" src="' . self::esc($b['src']) . '" color="' . $fallback . '" /><v:textbox inset="0,0,0,0"><![endif]-->'
            : '';
        $msoEnd = $b['src'] !== '' ? '<!--[if gte mso 9]></v:textbox></v:rect><![endif]-->' : '';

        $html = $mso
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" class="bk-hero"' . $image
            . ' bgcolor="' . $fallback . '" style="background-color:' . $fallback . ';background-image:url(' . self::esc($b['src']) . ');background-size:cover;background-position:center;">'
            . '<tr>' . $veilCell . '</tr></table>' . $msoEnd;

        if ($b['url'] !== '') {
            $html = '<a href="' . self::esc($b['url']) . '" target="_blank" style="text-decoration:none;display:block;">' . $html . '</a>';
        }

        return self::row('bk-herorow', $html, $b);
    }

    /**
     * Tabla del pedido: las filas de producto y descuento las genera PrestaShop ({products} y
     * {discounts}) y llegan ya maquetadas; aquí se les pone cabecera y totales, y el CSS del
     * layout las re-estiliza al pasar a estilos en línea.
     */
    /**
     * Imagen y texto uno al lado del otro, la pareja que antes había que montar con dos columnas.
     * En un móvil las dos celdas se apilan con la hoja de estilos del correo.
     */
    private static function blockMedia(array $b, array $ctx)
    {
        $radius = $b['radius'] ? 'border-radius:' . (int) $b['radius'] . 'px;' : '';
        $img = '<img src="' . self::esc($b['src']) . '" alt="' . self::esc($b['alt'])
            . '" width="200" class="bk-media__img" style="width:100%;max-width:100%;height:auto;display:block;border:0;' . $radius . '">';
        if ($b['url'] !== '') {
            $img = '<a href="' . self::esc($b['url']) . '" target="_blank">' . $img . '</a>';
        }
        $media = '<td class="bk-stack bk-media__side" width="' . (int) $b['ratio'] . '%" valign="' . $b['valign'] . '">' . $img . '</td>';
        $text = '<td class="bk-stack bk-media__text" valign="' . $b['valign'] . '"><div class="bk-rich" align="' . self::alignAttr('', $ctx) . '">'
            . self::paragraphs($b['html']) . '</div></td>';

        $html = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr>'
            . ($b['side'] === 'right' ? $text . $media : $media . $text)
            . '</tr></table>';

        return self::row('bk-media bk-media--' . $b['side'], $html, $b);
    }

    /**
     * Las mismas redes del pie, donde se quieran dentro del correo.
     */
    private static function blockSocial(array $b, array $ctx)
    {
        $row = isset($ctx['settings']) ? BkMailDesignerRenderer::socialRow($ctx['settings'], $b['align']) : '';
        if ($row === '') {
            return '';
        }

        return self::row('bk-socialblock', $row, $b, '', ' align="' . self::alignAttr($b['align'], $ctx) . '"');
    }

    private static function blockOrder(array $b, array $ctx)
    {
        $l = array_merge($ctx['labels'], $b['labels']);
        $layout = $b['layout'] !== '' ? $b['layout'] : (isset($ctx['order_layout']) ? $ctx['order_layout'] : 'stacked');

        if ($layout === 'stacked') {
            // Las marcas delimitan las filas que se recomponen una vez sustituidas las variables:
            // aquí todavía son «{products}», no hay nada que leer. Las opciones viajan dentro.
            $on = function ($key, $fallback) use ($b, $ctx) {
                if ($b[$key] !== '') {
                    return (int) $b[$key];
                }

                return isset($ctx[$fallback]) ? (int) $ctx[$fallback] : 1;
            };
            $html = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" class="bk-lines">'
                . BkMailDesignerOrderTable::open([
                    'image' => $on('show_image', 'order_image'),
                    'size' => $b['image_size'] ? $b['image_size'] : (isset($ctx['order_image_size']) ? $ctx['order_image_size'] : 64),
                    'reference' => $on('show_reference', 'order_reference'),
                    'options' => $on('show_options', 'order_options'),
                    'unit' => $on('show_unit', 'order_unit'),
                    'style' => isset($ctx['line_style']) ? $ctx['line_style'] : 'plain',
                ])
                . '{' . $b['source'] . '}{' . $b['discount_source'] . '}'
                . BkMailDesignerOrderTable::CLOSE
                . '</table>';
        } else {
            $html = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" class="bk-order">'
                . '<thead><tr>'
                . '<th class="bk-th" align="' . $ctx['align'] . '">' . self::esc($l['reference']) . '</th>'
                . '<th class="bk-th" align="' . $ctx['align'] . '">' . self::esc($l['product']) . '</th>'
                . '<th class="bk-th bk-th-num" align="right">' . self::esc($l['unit_price']) . '</th>'
                . '<th class="bk-th bk-th-num" align="right">' . self::esc($l['quantity']) . '</th>'
                . '<th class="bk-th bk-th-num" align="right">' . self::esc($l['total']) . '</th>'
                . '</tr></thead><tbody>{' . $b['source'] . '}{' . $b['discount_source'] . '}</tbody></table>';
        }
        if (!empty($b['totals'])) {
            $rows = '';
            foreach (self::TOTAL_ROWS as $key => $default) {
                $mode = isset($b['totals_mode'][$key]) ? $b['totals_mode'][$key] : $default;
                // El envío a cero se puede anunciar como gratis en vez de esconderse
                $free = $key === 'shipping' && isset($l['free_shipping']) ? $l['free_shipping'] : '';
                $rows .= self::totalRow($l[$key], self::TOTAL_VARS[$key], $key === 'total_paid', $mode, $free);
            }
            $html .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" class="bk-totals">'
                . $rows . '</table>';
        }

        return self::row('bk-orderrow', $html, $b);
    }

    /**
     * Una línea de totales. Si no sale siempre, se marca para que se decida cuando el importe ya
     * sea un importe y no una variable.
     *
     * @param string $label
     * @param string $value
     * @param bool   $strong
     * @param string $mode  always | auto | never
     * @param string $free  Texto para un envío a cero; vacío, la línea se esconde
     *
     * @return string
     */
    private static function totalRow($label, $value, $strong = false, $mode = 'always', $free = '')
    {
        $class = $strong ? 'bk-total bk-total-strong' : 'bk-total';
        // Se marca siempre que haya algo que decidir cuando el importe ya sea un importe: o
        // porque la línea puede desaparecer, o porque el cero tiene texto propio.
        $mark = ($mode === 'always' && $free === '')
            ? ''
            : ' data-bk-total="' . $mode . '"' . ($free !== '' ? ' data-bk-free="' . self::esc($free) . '"' : '');

        return '<tr' . $mark . '><td class="' . $class . '">' . self::esc($label) . '</td>'
            . '<td class="' . $class . ' bk-total-num" align="right">' . $value . '</td></tr>';
    }

    private static function blockAddresses(array $b, array $ctx)
    {
        $l = array_merge($ctx['labels'], $b['labels']);
        $shows = isset($b['shows']) && in_array($b['shows'], self::ADDRESS_SHOWS, true) ? $b['shows'] : 'both';

        // Con una sola dirección la celda ocupa todo el ancho: media tabla vacía se lee
        // como un fallo de maquetación, no como una decisión.
        $celda = function ($etiqueta, $var, $clase, $ancho) {
            return '<td class="bk-col ' . $clase . '" width="' . $ancho . '" valign="top">'
                . '<p class="bk-label">' . self::esc($etiqueta) . '</p><p>' . $var . '</p></td>';
        };
        if ($shows === 'invoice') {
            $celdas = $celda($l['invoice'], '{invoice_block_html}', 'bk-col-first bk-col-last', '100%');
        } elseif ($shows === 'delivery') {
            $celdas = $celda($l['delivery'], '{delivery_block_html}', 'bk-col-first bk-col-last', '100%');
        } else {
            $celdas = $celda($l['delivery'], '{delivery_block_html}', 'bk-col-first', '50%')
                . $celda($l['invoice'], '{invoice_block_html}', 'bk-col-last', '50%');
        }

        return self::row('bk-columns', '<table role="presentation" width="100%" cellpadding="0" '
            . 'cellspacing="0" border="0"><tr>' . $celdas . '</tr></table>', $b);
    }

    private static function blockHtml(array $b, array $ctx)
    {
        return self::row('bk-html', $b['html'], $b);
    }

    // ---- utilidades ---------------------------------------------------------------------------

    /**
     * Fila del cuerpo. Los ajustes comunes del bloque —aire arriba y abajo, fondo— se aplican aquí
     * en línea, porque son de esa instancia y no de la clase.
     *
     * @param string $class
     * @param string $inner
     * @param array  $b     El bloque, para sus ajustes comunes
     * @param string $extraClass
     * @param string $attrs Atributos sueltos de la celda, como align
     *
     * @return string
     */
    private static function row($class, $inner, array $b = [], $extraClass = '', $attrs = '')
    {
        $style = self::inline([
            'padding-top' => !empty($b['pad_top']) ? (int) $b['pad_top'] . 'px' : '',
            'padding-bottom' => !empty($b['pad_bottom']) ? (int) $b['pad_bottom'] . 'px' : '',
            'background-color' => !empty($b['background']) ? $b['background'] : '',
        ]);

        $mark = self::$index === null ? '' : ' data-bk-i="' . self::$index . '"';
        $bleed = !empty($b['bleed']) ? ' bk-block--bleed' : '';

        return '<tr' . $mark . '><td class="bk-block' . $bleed . ' ' . $class . ($extraClass ? ' ' . $extraClass : '') . '"' . $attrs . $style . '>' . $inner . '</td></tr>';
    }

    /**
     * Atributo style con las propiedades que traen valor. Vacío significa «lo que diga el tema»,
     * así que no se escribe nada y el CSS del layout sigue mandando.
     *
     * @param array $properties
     *
     * @return string
     */
    private static function inline(array $properties)
    {
        $out = '';
        foreach ($properties as $name => $value) {
            if ($value !== '' && $value !== null) {
                $out .= $name . ':' . $value . ';';
            }
        }

        return $out === '' ? '' : ' style="' . $out . '"';
    }

    /**
     * @param mixed $value
     *
     * @return string '' o #rrggbb
     */
    private static function color($value)
    {
        $value = trim((string) $value);

        return preg_match('/^#[0-9a-f]{6}$/i', $value) ? Tools::strtolower($value) : '';
    }

    /**
     * @param mixed $value
     * @param int   $min
     * @param int   $max
     *
     * @return int 0 cuando no se ha fijado
     */
    private static function size($value, $min, $max)
    {
        $value = (int) $value;

        return $value === 0 ? 0 : max($min, min($max, $value));
    }

    /**
     * @param mixed $value
     *
     * @return int Píxeles de aire, 0 cuando no se ha fijado
     */
    private static function spacing($value)
    {
        return self::size($value, 0, 80);
    }

    /**
     * Un texto enriquecido sin párrafos se envuelve en uno para que herede los estilos.
     */
    private static function paragraphs($html)
    {
        $html = trim((string) $html);
        if ($html === '') {
            return '';
        }
        if (!preg_match('/^<(p|ul|ol)\b/i', $html)) {
            $html = '<p>' . $html . '</p>';
        }

        return $html;
    }

    private static function get(array $block, $key, $default = '')
    {
        return isset($block[$key]) ? $block[$key] : $default;
    }

    private static function plain($value)
    {
        return trim(strip_tags((string) $value));
    }

    /**
     * Nombre de variable de correo, sin llaves.
     */
    private static function varName($value, $fallback)
    {
        $value = trim((string) $value);

        return preg_match('/^[a-z0-9_]+$/i', $value) ? $value : $fallback;
    }

    private static function align($value)
    {
        return in_array($value, ['left', 'center', 'right'], true) ? $value : 'left';
    }

    /**
     * 'left' es "el lado de inicio": en un idioma RTL se convierte en right.
     */
    private static function alignAttr($align, array $ctx)
    {
        if ($align === 'center') {
            return 'center';
        }
        if ($ctx['align'] === 'right') {
            return $align === 'left' ? 'right' : 'left';
        }

        return $align;
    }

    /**
     * URL absoluta, variable del núcleo ({url}, {shop_url}...) o cadena vacía.
     */
    private static function url($value)
    {
        $value = trim((string) $value);
        if ($value === '' || preg_match('/^\{[a-z0-9_]+\}$/i', $value) || Validate::isAbsoluteUrl($value) || strpos($value, 'mailto:') === 0) {
            return $value;
        }

        return '';
    }

    private static function esc($value)
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8', false);
    }

    /**
     * Texto enriquecido: solo las etiquetas de la lista, y en los enlaces solo el destino.
     */
    public static function richText($html)
    {
        $html = trim((string) $html);
        if ($html === '') {
            return '';
        }
        $doc = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8"><div id="bk-root">' . $html . '</div>', LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $root = $doc->getElementById('bk-root');
        if (!$root) {
            return self::esc(strip_tags($html));
        }
        self::cleanNode($root);
        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $doc->saveHTML($child);
        }

        // `saveHTML` codifica las llaves dentro de un atributo y `{shop_url}` dejaría de ser una
        // variable; se devuelven tal como se escribieron.
        return trim(preg_replace('/%7B([a-z0-9_]+)%7D/i', '{$1}', $out));
    }

    private static function cleanNode(DOMNode $node)
    {
        $children = [];
        foreach ($node->childNodes as $child) {
            $children[] = $child;
        }
        foreach ($children as $child) {
            if ($child->nodeType === XML_COMMENT_NODE) {
                $node->removeChild($child);
                continue;
            }
            if ($child->nodeType !== XML_ELEMENT_NODE) {
                continue;
            }
            $tag = strtolower($child->nodeName);
            // De estas se va también lo que llevan dentro: su contenido no es texto del correo
            if (in_array($tag, ['script', 'style', 'iframe', 'object', 'embed', 'form', 'noscript'], true)) {
                $node->removeChild($child);
                continue;
            }
            if (!in_array($tag, self::ALLOWED_TAGS, true)) {
                // La etiqueta se va; su contenido se queda en su lugar
                self::cleanNode($child);
                while ($child->firstChild) {
                    $node->insertBefore($child->firstChild, $child);
                }
                $node->removeChild($child);
                continue;
            }
            $allowed = array_merge(
                self::ALLOWED_ATTRS['*'],
                isset(self::ALLOWED_ATTRS[$tag]) ? self::ALLOWED_ATTRS[$tag] : []
            );
            $attrs = [];
            foreach ($child->attributes as $attr) {
                $attrs[] = $attr->name;
            }
            foreach ($attrs as $name) {
                $lower = Tools::strtolower($name);
                if (!in_array($lower, $allowed, true)) {
                    $child->removeAttribute($name);
                    continue;
                }
                if ($lower === 'href' || $lower === 'src') {
                    $value = self::url($child->getAttribute($name));
                    if ($value === '') {
                        $child->removeAttribute($name);
                    } else {
                        $child->setAttribute($name, $value);
                    }
                    continue;
                }
                if ($lower === 'style') {
                    $style = self::styleAttr($child->getAttribute('style'));
                    if ($style === '') {
                        $child->removeAttribute('style');
                    } else {
                        $child->setAttribute('style', $style);
                    }
                }
            }
            if ($tag === 'a' && $child->getAttribute('href') !== '') {
                $child->setAttribute('target', '_blank');
            }
            self::cleanNode($child);
        }
    }

    /**
     * Un `style` sin lo que puede ejecutar código. Lo demás pasa: es lo que hace que pegar HTML
     * de verdad en un texto sirva de algo.
     *
     * @param string $style
     *
     * @return string
     */
    private static function styleAttr($style)
    {
        $style = (string) $style;
        if (preg_match('/expression\s*\(|behaviou?r\s*:|javascript\s*:|@import|<|>/i', $style)) {
            return '';
        }

        return trim(preg_replace('/\s+/', ' ', $style));
    }

    /**
     * HTML libre: se quita lo que un correo no puede ejecutar ni cargar; el resto es cosa del autor.
     */
    private static function rawHtml($html)
    {
        $html = (string) $html;
        $html = preg_replace('#<(script|iframe|object|embed|form)\b[^>]*>.*?</\1>#is', '', $html);
        $html = preg_replace('#<(script|iframe|object|embed|form|link|meta)\b[^>]*/?>#i', '', $html);
        $html = preg_replace('/\son[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html);

        return trim($html);
    }
}
