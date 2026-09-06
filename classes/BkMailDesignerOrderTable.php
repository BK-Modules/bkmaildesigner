<?php
/**
 * Recompone las líneas del pedido para que se lean en un móvil.
 *
 * PrestaShop no manda los productos: manda HTML ya maquetado en `{products}` —o `{items}` en
 * ps_emailalerts— con cinco celdas por línea. A 360 px de pantalla, cuatro columnas fijas se
 * llevan 248 de los 328 útiles y al nombre del producto le quedan ochenta píxeles.
 *
 * Aquí esas filas se leen y se vuelven a emitir en dos columnas —tres con foto—: el producto a la
 * izquierda, con su referencia, su combinación, sus personalizaciones y su «2 × 49,99 €» debajo
 * del nombre, y el importe de la línea a la derecha. Aguanta a 320 px sin una sola media query.
 *
 * Qué se enseña de cada línea lo decide el comerciante bloque a bloque; las opciones viajan en la
 * propia marca de apertura, así que dos bloques de pedido del mismo correo pueden enseñar cosas
 * distintas.
 *
 * **Si la forma no se reconoce, las filas salen tal cual llegaron.** Un módulo con su propia
 * estructura sigue enviando su tabla de siempre y el correo no se rompe.
 *
 * @author BK Modules
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class BkMailDesignerOrderTable
{
    /** Cierre de las filas a recomponer; la apertura la construye open() con las opciones */
    const CLOSE = '<!--/bk-lines-->';

    /** Presentación de la línea */
    const STYLES = ['plain', 'zebra', 'boxed'];

    /** Lo que se enseña de cada línea si el bloque no dice otra cosa */
    const DEFAULTS = [
        'image' => 0,
        'size' => 64,
        'reference' => 1,
        'options' => 1,
        'unit' => 1,
        'style' => 'plain',
    ];

    /**
     * Marca de apertura con las opciones del bloque dentro.
     *
     * @param array $opt
     *
     * @return string
     */
    public static function open(array $opt)
    {
        $pairs = [];
        foreach (array_merge(self::DEFAULTS, array_intersect_key($opt, self::DEFAULTS)) as $key => $value) {
            $pairs[] = $key . '=' . preg_replace('/[^a-z0-9]/i', '', (string) $value);
        }

        return '<!--bk-lines ' . implode(';', $pairs) . '-->';
    }

    /**
     * Recompone lo que haya entre las marcas y las retira.
     *
     * @param string $html Correo con las variables ya sustituidas
     * @param array  $vars Variables del correo: de ellas sale el pedido del que leer las fotos
     *
     * @return string
     */
    public static function apply($html, array $vars = [])
    {
        // Se corta por bytes: los desplazamientos de preg_match lo son, y mezclarlos con un corte
        // por caracteres parte el HTML en cuanto el correo lleva un acento.
        while (preg_match('/<!--bk-lines ?([^>]*)-->/', $html, $match, PREG_OFFSET_CAPTURE)) {
            $open = $match[0][1];
            $from = $open + strlen($match[0][0]);
            $close = strpos($html, self::CLOSE, $from);
            if ($close === false) {
                break;
            }
            $rows = substr($html, $from, $close - $from);
            $opt = self::options($match[1][0]);
            $stacked = self::stack($rows, $opt, $opt['image'] ? self::photos($vars, $opt['size']) : []);
            $html = substr($html, 0, $open) . ($stacked === null ? $rows : $stacked)
                . substr($html, $close + strlen(self::CLOSE));
        }

        return self::applyTotals($html);
    }

    /**
     * Resuelve las líneas de totales que no salen siempre.
     *
     * Hasta aquí el importe era `{total_shipping}`: solo ahora se sabe si viene a cero. Un cero
     * con texto propio —«Gratis»— se queda con ese texto; sin texto, la línea desaparece si podía
     * («solo si tiene importe») y se queda con su cero si no. Una marcada `never` no sale nunca.
     *
     * @param string $html
     *
     * @return string
     */
    private static function applyTotals($html)
    {
        return preg_replace_callback(
            '/<tr data-bk-total="(always|auto|never)"(?: data-bk-free="([^"]*)")?>(.*?)<\/tr>/s',
            function ($m) {
                if ($m[1] === 'never') {
                    return '';
                }
                if (!self::isZero($m[3])) {
                    return '<tr>' . $m[3] . '</tr>';
                }
                if ($m[2] === '') {
                    // Sin texto para el cero: la línea se esconde solo si podía esconderse
                    return $m[1] === 'auto' ? '' : '<tr>' . $m[3] . '</tr>';
                }

                // El importe es la última celda de la fila
                $row = preg_replace('/(<td[^>]*bk-total-num[^>]*>).*?(<\/td>)/s', '$1' . $m[2] . '$2', $m[3], 1);

                return '<tr>' . $row . '</tr>';
            },
            $html
        );
    }

    /**
     * Si el importe de una fila de totales es cero, con el formato de cualquier moneda.
     *
     * @param string $row
     *
     * @return bool
     */
    private static function isZero($row)
    {
        if (!preg_match('/<td[^>]*bk-total-num[^>]*>(.*?)<\/td>/s', $row, $cell)) {
            return false;
        }
        $digits = preg_replace('/\D/', '', strip_tags($cell[1]));

        return $digits === '' || (int) $digits === 0;
    }

    /**
     * @param string $encoded «image=1;size=64;…» tal como lo escribió open()
     *
     * @return array
     */
    private static function options($encoded)
    {
        $opt = self::DEFAULTS;
        foreach (explode(';', $encoded) as $pair) {
            $parts = explode('=', $pair, 2);
            if (count($parts) === 2 && isset($opt[$parts[0]])) {
                $opt[$parts[0]] = $parts[1];
            }
        }
        $opt['size'] = min(120, max(32, (int) $opt['size']));
        if (!in_array($opt['style'], self::STYLES, true)) {
            $opt['style'] = 'plain';
        }
        foreach (['image', 'reference', 'options', 'unit'] as $flag) {
            $opt[$flag] = (bool) (int) $opt[$flag];
        }

        return $opt;
    }

    /**
     * Las filas leídas y vueltas a emitir.
     *
     * @param string $rows   Filas `<tr>` tal como las entrega PrestaShop
     * @param array  $opt
     * @param array  $photos URL de la foto de cada línea, en el orden en que van en el pedido
     *
     * @return string|null Null si la forma no se reconoce
     */
    public static function stack($rows, array $opt = [], array $photos = [])
    {
        $opt = array_merge(self::DEFAULTS, $opt);
        if (trim($rows) === '') {
            return '';
        }

        $doc = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $ok = $doc->loadHTML('<?xml encoding="UTF-8"><table>' . $rows . '</table>', LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (!$ok) {
            return null;
        }

        $lines = [];
        foreach (self::topRows($doc) as $tr) {
            $cells = [];
            foreach ($tr->childNodes as $child) {
                if ($child->nodeType === XML_ELEMENT_NODE && Tools::strtolower($child->nodeName) === 'td') {
                    $cells[] = $child;
                }
            }

            if (count($cells) === 5) {
                $lines[] = [
                    'type' => 'product',
                    'reference' => self::text($cells[0]),
                    'name' => self::name($cells[1]),
                    'extra' => self::rest($cells[1]),
                    'unit' => self::text($cells[2]),
                    'qty' => self::text($cells[3]),
                    'total' => self::text($cells[4]),
                ];
            } elseif (count($cells) === 2) {
                $lines[] = ['type' => 'discount', 'name' => self::text($cells[0]), 'total' => self::text($cells[1])];
            } elseif (count($cells) === 3 && !empty($lines)) {
                // Segunda personalización y siguientes: el núcleo las saca a una fila propia de
                // tres celdas que solo tiene sentido pegada a la línea de arriba.
                $last = count($lines) - 1;
                $text = self::text($cells[0]);
                if ($text !== '' && $lines[$last]['type'] === 'product') {
                    $lines[$last]['extra'] = trim($lines[$last]['extra'] . ' · ' . $text, ' ·');
                }
            } else {
                // Una fila que no encaja: no se toca ninguna, para no mezclar dos maquetaciones
                return null;
            }
        }

        if (empty($lines)) {
            return null;
        }

        $out = '';
        $index = 0;
        foreach ($lines as $position => $line) {
            // La presentación se marca celda a celda, no en el `tr`: Outlook no hereda el fondo
            // de una fila a sus celdas.
            $cell = 'bk-line-' . $opt['style']
                . ($opt['style'] === 'zebra' && $position % 2 === 1 ? ' bk-line--alt' : '') . ' bk-line';
            if ($line['type'] === 'discount') {
                $out .= self::discount($line, $opt, $cell);
                continue;
            }
            $photo = $opt['image'] ? (isset($photos[$index]) ? $photos[$index] : reset($photos)) : '';
            $out .= self::line($line, $opt, $cell, $photo === false ? '' : $photo);
            ++$index;
        }

        return $out;
    }

    /**
     * Las filas de la tabla de primer nivel. Las celdas del núcleo llevan dentro otra tabla con
     * sus propias filas, que no son líneas de pedido y no se leen.
     *
     * @param DOMDocument $doc
     *
     * @return array
     */
    private static function topRows(DOMDocument $doc)
    {
        $tables = $doc->getElementsByTagName('table');
        if ($tables->length === 0) {
            return [];
        }

        $rows = [];
        $walk = function (DOMNode $parent) use (&$walk, &$rows) {
            foreach ($parent->childNodes as $child) {
                if ($child->nodeType !== XML_ELEMENT_NODE) {
                    continue;
                }
                $tag = Tools::strtolower($child->nodeName);
                if ($tag === 'tr') {
                    $rows[] = $child;
                } elseif (in_array($tag, ['tbody', 'thead', 'tfoot'], true)) {
                    $walk($child);
                }
            }
        };
        $walk($tables->item(0));

        return $rows;
    }

    /**
     * @param array  $line
     * @param array  $opt
     * @param string $cell  Clases de las celdas de la fila
     * @param string $photo URL de la foto, vacía si no se enseña
     *
     * @return string
     */
    private static function line(array $line, array $opt, $cell, $photo)
    {
        $meta = [];
        if ($opt['options'] && trim($line['extra']) !== '') {
            $meta[] = $line['extra'];
        }
        if ($opt['reference'] && trim($line['reference']) !== '') {
            $meta[] = $line['reference'];
        }

        $left = '<span class="bk-line__name">' . self::esc($line['name']) . '</span>';
        if (!empty($meta)) {
            $left .= '<span class="bk-line__meta">' . self::esc(implode(' · ', $meta)) . '</span>';
        }
        if ($opt['unit'] && trim($line['qty']) !== '' && trim($line['unit']) !== '') {
            $left .= '<span class="bk-line__qty">' . self::esc($line['qty']) . ' × ' . self::esc($line['unit']) . '</span>';
        }

        $cells = '';
        if ($photo !== '') {
            $size = (int) $opt['size'];
            $cells .= '<td class="' . $cell . ' bk-line__photo" width="' . $size . '" valign="top">'
                . '<img src="' . self::esc($photo) . '" width="' . $size . '" height="' . $size
                . '" alt="" class="bk-line__img" style="width:' . $size . 'px;height:' . $size . 'px;display:block;border:0;">'
                . '</td>';
        }
        $cells .= '<td class="' . $cell . '">' . $left . '</td>'
            . '<td class="' . $cell . ' bk-line__amount" align="right">' . self::esc($line['total']) . '</td>';

        return self::wrap($cells, $opt, $photo !== '' ? 3 : 2);
    }

    /**
     * @param array  $line
     * @param array  $opt
     * @param string $cell
     *
     * @return string
     */
    private static function discount(array $line, array $opt, $cell)
    {
        $span = $opt['image'] ? ' colspan="2"' : '';

        return self::wrap(
            '<td class="' . $cell . ' bk-line--discount"' . $span . '><span class="bk-line__name">' . self::esc($line['name']) . '</span></td>'
                . '<td class="' . $cell . ' bk-line__amount bk-line--discount" align="right">' . self::esc($line['total']) . '</td>',
            $opt,
            $opt['image'] ? 3 : 2
        );
    }

    /**
     * La fila, con el hueco que separa una caja de la siguiente cuando así se presenta. El rayado
     * y la caja se pintan por color de fondo de celda, nunca con `nth-child`: la mitad de los
     * clientes de correo no lo aplica.
     *
     * @param string $cells
     * @param array  $opt
     * @param int    $columns
     *
     * @return string
     */
    private static function wrap($cells, array $opt, $columns)
    {
        $row = '<tr>' . $cells . '</tr>';
        if ($opt['style'] === 'boxed') {
            $row .= '<tr><td class="bk-line__gap" colspan="' . (int) $columns . '"></td></tr>';
        }

        return $row;
    }

    /**
     * La foto de cada producto del pedido, en el mismo orden en que se escribieron las filas
     * —el mismo en el que el núcleo y ps_emailalerts escriben las filas—.
     *
     * Sin pedido a mano —la vista previa del editor, un correo de prueba— se devuelve la imagen
     * «sin foto» de la tienda para todas: la maquetación se ve igual y no se inventa un producto.
     *
     * @param array $vars
     * @param int   $size
     *
     * @return array
     */
    private static function photos(array $vars, $size)
    {
        $context = Context::getContext();
        $link = $context->link;
        $iso = Tools::strtolower($context->language->iso_code);
        $none = [$link->getImageLink($iso, $iso . '-default', 'home_default')];

        $order = self::order($vars);
        if ($order === null) {
            return $none;
        }

        $out = [];
        foreach (self::productIds((int) $order->id) as $idProduct) {
            $cover = Image::getCover($idProduct);
            if (empty($cover['id_image'])) {
                $out[] = $none[0];
                continue;
            }
            // El nombre solo viste la URL; lo que la resuelve es «idProducto-idImagen»
            $name = Product::getProductName($idProduct);
            $out[] = $link->getImageLink(
                Tools::str2url($name ? $name : (string) $idProduct),
                $idProduct . '-' . (int) $cover['id_image'],
                'home_default'
            );
        }

        return empty($out) ? $none : $out;
    }

    /**
     * Los productos del pedido en el mismo orden en que el núcleo escribe las filas.
     *
     * Se leen de `order_detail` y no con `Order::getProducts()`: ese recalcula precios y para eso
     * necesita moneda en el contexto, que en un envío desde consola o desde un cron puede no
     * haber. Para una foto basta el identificador.
     *
     * @param int $idOrder
     *
     * @return array
     */
    private static function productIds($idOrder)
    {
        $rows = Db::getInstance()->executeS(
            'SELECT `product_id` FROM `' . _DB_PREFIX_ . 'order_detail`
             WHERE `id_order` = ' . (int) $idOrder . ' ORDER BY `id_order_detail` ASC'
        );

        $out = [];
        foreach ((array) $rows as $row) {
            $out[] = (int) $row['product_id'];
        }

        return $out;
    }

    /**
     * El pedido del correo: por id cuando el correo lo lleva —confirmación— y por referencia
     * cuando no —los avisos de ps_emailalerts solo mandan `{order_name}`—.
     *
     * @param array $vars
     *
     * @return Order|null
     */
    private static function order(array $vars)
    {
        if (!empty($vars['{id_order}'])) {
            $order = new Order((int) $vars['{id_order}']);
            if (Validate::isLoadedObject($order)) {
                return $order;
            }
        }

        if (!empty($vars['{order_name}'])) {
            $orders = Order::getByReference((string) $vars['{order_name}']);
            $first = $orders ? $orders->getFirst() : false;
            if ($first && Validate::isLoadedObject($first)) {
                return $first;
            }
        }

        return null;
    }

    /**
     * El nombre del producto: el del enlace si lo hay, el del `strong` si no, y el de la celda
     * entera como último recurso. Las dos plantillas conocidas caen en los dos primeros casos.
     *
     * @param DOMElement $cell
     *
     * @return string
     */
    private static function name(DOMElement $cell)
    {
        foreach (['a', 'strong'] as $tag) {
            $nodes = $cell->getElementsByTagName($tag);
            if ($nodes->length > 0 && trim($nodes->item(0)->textContent) !== '') {
                return self::clean(self::firstLine($nodes->item(0)));
            }
        }

        return self::clean(self::firstLine($cell));
    }

    /**
     * Lo que queda en la celda del producto una vez fuera el nombre: la combinación y las
     * personalizaciones, que van bajo el nombre y no en una columna propia. Cada `<br>` separa un
     * dato, y los `---` con que el núcleo separa personalizaciones no se enseñan.
     *
     * @param DOMElement $cell
     *
     * @return string
     */
    private static function rest(DOMElement $cell)
    {
        $name = self::name($cell);
        $out = [];
        foreach (self::lines($cell) as $line) {
            $line = self::clean($line);
            // El nombre y la combinación comparten renglón —«Camiseta (Talla: L)»—: fuera el
            // nombre, que ya va arriba, y queda la combinación sola.
            if ($name !== '' && strpos($line, $name) === 0) {
                $line = self::clean(Tools::substr($line, Tools::strlen($name)));
            }
            if ($line !== '' && $line !== $name && trim($line, '- ') !== '') {
                $out[] = $line;
            }
        }

        return implode(' · ', array_unique($out));
    }

    /**
     * El texto de un nodo partido por sus saltos de línea.
     *
     * @param DOMNode $node
     *
     * @return array
     */
    private static function lines(DOMNode $node)
    {
        $html = $node->ownerDocument->saveHTML($node);

        return preg_split('/<br\s*\/?>|<\/p>|<\/div>|<\/li>/i', $html);
    }

    /**
     * @param DOMNode $node
     *
     * @return string
     */
    private static function firstLine(DOMNode $node)
    {
        $lines = self::lines($node);

        return strip_tags(reset($lines));
    }

    private static function text(DOMNode $node)
    {
        return self::clean($node->textContent);
    }

    private static function clean($text)
    {
        $text = html_entity_decode(strip_tags((string) $text), ENT_QUOTES, 'UTF-8');

        // El guion no se recorta: se lo comería al importe negativo de un descuento
        return trim(preg_replace('/\s+/u', ' ', str_replace("\xC2\xA0", ' ', $text)), " \t\n\r\0\x0B·");
    }

    private static function esc($value)
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8', false);
    }
}
