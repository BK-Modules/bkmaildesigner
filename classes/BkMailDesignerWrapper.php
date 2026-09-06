<?php
/**
 * Saca el cuerpo de una plantilla original —sin la cabecera con el logo ni el pie con el
 * "Powered by PrestaShop"— para meterlo dentro del layout de marca.
 *
 * Reconoce las tres familias de plantillas que hay en una tienda: el tema `modern` (bloques en
 * div con condicionales de Outlook), el tema `classic` (tabla con filas logo/contenido/pie) y
 * las plantillas heredadas de módulos (una tabla con el logo en la primera fila). Cuando no
 * reconoce ninguna estructura se queda con el cuerpo entero sin logo ni firma. Si no logra
 * nada, devuelve null y el correo sale como estaba: nunca se envía un correo roto.
 *
 * @author BK Modules
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class BkMailDesignerWrapper
{
    const MODERN = 'modern';
    const CLASSIC = 'classic';
    const GENERIC = 'generic';

    /**
     * @param string $html Plantilla original completa
     *
     * @return array|null ['html' => contenido, 'strategy' => modern|classic|generic]
     */
    public static function extract($html)
    {
        if (trim((string) $html) === '') {
            return null;
        }

        $doc = self::load($html);
        if ($doc === null) {
            return null;
        }
        $xpath = new DOMXPath($doc);

        $wrapper = $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " wrapper-container ")]')->item(0);
        if ($wrapper) {
            $result = self::modern($doc, $xpath, $wrapper);
            if ($result !== null) {
                return ['html' => $result, 'strategy' => self::MODERN];
            }
        }

        $classic = $xpath->query('//table[contains(concat(" ", normalize-space(@class), " "), " table-mail ")]//table[contains(concat(" ", normalize-space(@class), " "), " table ")]')->item(0);
        if ($classic) {
            $result = self::classic($doc, $xpath, $classic);
            if ($result !== null) {
                return ['html' => $result, 'strategy' => self::CLASSIC];
            }
        }

        $result = self::generic($doc, $xpath);

        return $result === null ? null : ['html' => $result, 'strategy' => self::GENERIC];
    }

    /**
     * Tema modern: la celda del contenedor lleva cabecera, bloques de contenido y, en los correos
     * de pedido, un pie propio. Se quita el bloque con el logo y se conservan los demás, con los
     * condicionales de Outlook que los encierran.
     */
    private static function modern(DOMDocument $doc, DOMXPath $xpath, DOMElement $wrapper)
    {
        $cell = $xpath->query('.//td', $wrapper)->item(0);
        if (!$cell) {
            return null;
        }

        foreach (self::logoImages($xpath, $cell) as $img) {
            $block = self::closestBlock($img, $cell);
            if ($block) {
                self::removeWithMsoComments($block);
            }
        }
        self::removeNodesWith($xpath, $cell, 'powered by', 'div');

        $inner = self::innerHtml($cell);

        return trim(strip_tags($inner)) === '' && strpos($inner, '{') === false ? null : $inner;
    }

    /**
     * Tema classic: una tabla blanca con la fila del logo, las de contenido y las del pie.
     */
    private static function classic(DOMDocument $doc, DOMXPath $xpath, DOMElement $table)
    {
        foreach ($xpath->query('./tr | ./tbody/tr', $table) as $row) {
            $class = ' ' . (string) $xpath->evaluate('string(./td/@class)', $row) . ' ';
            $text = strtolower(trim($row->textContent));
            if (self::logoImages($xpath, $row)->length
                || strpos($class, ' space_footer ') !== false
                || strpos($class, ' footer ') !== false
                || strpos($text, 'powered by') !== false
            ) {
                $row->parentNode->removeChild($row);
            }
        }
        $table->setAttribute('width', '100%');
        $table->removeAttribute('bgcolor');
        $table->setAttribute('style', 'width:100%;');

        $out = $doc->saveHTML($table);

        return trim(strip_tags($out)) === '' ? null : $out;
    }

    /**
     * Plantilla heredada: suele ser una tabla con el logo en la primera fila, filas separadoras
     * vacías y el nombre de la tienda en la última. Se quitan esas filas y se ensancha la tabla.
     */
    private static function generic(DOMDocument $doc, DOMXPath $xpath)
    {
        $body = $xpath->query('//body')->item(0);
        if (!$body) {
            return null;
        }

        foreach (self::logoImages($xpath, $body) as $img) {
            $row = self::closest($img, 'tr', $body);
            self::removeWithSpacers($row ?: $img);
        }
        self::removeNodesWith($xpath, $body, 'powered by', 'tr');

        // Fila cuyo único contenido es el nombre de la tienda: cabecera de color o pie de firma
        foreach ($xpath->query('.//tr', $body) as $row) {
            if (!$row->parentNode) {
                continue;
            }
            $text = trim(preg_replace('/\s+/', ' ', $row->textContent));
            if ($text === '{shop_name}' || $text === '') {
                if ($text === '' && !self::isSpacer($row)) {
                    continue;
                }
                self::removeWithSpacers($row);
            }
        }

        foreach ($xpath->query('./table', $body) as $table) {
            $table->setAttribute('width', '100%');
            $style = (string) $table->getAttribute('style');
            $style = preg_replace('/width\s*:\s*[^;]+;?/i', '', $style);
            $table->setAttribute('style', $style . 'width:100%;');
        }

        $inner = self::innerHtml($body);

        return trim(strip_tags($inner)) === '' ? null : $inner;
    }

    /**
     * @return DOMDocument|null
     */
    private static function load($html)
    {
        $doc = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $ok = $doc->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (!$ok) {
            return null;
        }
        // La declaración XML que fija la codificación no tiene que salir en el resultado
        foreach ($doc->childNodes as $node) {
            if ($node->nodeType === XML_PI_NODE) {
                $doc->removeChild($node);
                break;
            }
        }

        return $doc;
    }

    private static function logoImages(DOMXPath $xpath, DOMNode $context)
    {
        return $xpath->query('.//img[contains(@src, "shop_logo")]', $context);
    }

    /**
     * Bloque de contenido del tema modern que contiene un nodo: el div de ancho máximo que cuelga
     * directamente de la celda del contenedor.
     */
    private static function closestBlock(DOMNode $node, DOMNode $cell)
    {
        $current = $node;
        while ($current && $current->parentNode) {
            if ($current->parentNode->isSameNode($cell)) {
                return $current;
            }
            $current = $current->parentNode;
        }

        return null;
    }

    private static function closest(DOMNode $node, $tag, DOMNode $limit)
    {
        $current = $node->parentNode;
        while ($current && !$current->isSameNode($limit)) {
            if ($current->nodeType === XML_ELEMENT_NODE && strtolower($current->nodeName) === $tag) {
                return $current;
            }
            $current = $current->parentNode;
        }

        return null;
    }

    /**
     * Quita un bloque del tema modern con los comentarios condicionales de Outlook que lo
     * envuelven: sin ellos, Outlook pintaría una fila vacía donde estaba el logo.
     */
    private static function removeWithMsoComments(DOMNode $block)
    {
        foreach (['previousSibling', 'nextSibling'] as $direction) {
            $sibling = $block->$direction;
            while ($sibling && $sibling->nodeType === XML_TEXT_NODE && trim($sibling->textContent) === '') {
                $sibling = $sibling->$direction;
            }
            if ($sibling && $sibling->nodeType === XML_COMMENT_NODE && strpos($sibling->textContent, '[if mso') === 0) {
                $sibling->parentNode->removeChild($sibling);
            }
        }
        $block->parentNode->removeChild($block);
    }

    /**
     * Quita una fila y las filas separadoras vacías pegadas a ella.
     */
    private static function removeWithSpacers(DOMNode $row)
    {
        if (!$row->parentNode) {
            return;
        }
        foreach (['previousSibling', 'nextSibling'] as $direction) {
            $sibling = $row->$direction;
            while ($sibling && $sibling->nodeType === XML_TEXT_NODE && trim($sibling->textContent) === '') {
                $sibling = $sibling->$direction;
            }
            if ($sibling && $sibling->nodeType === XML_ELEMENT_NODE && self::isSpacer($sibling)) {
                $sibling->parentNode->removeChild($sibling);
            }
        }
        $row->parentNode->removeChild($row);
    }

    private static function isSpacer(DOMNode $row)
    {
        if (strtolower($row->nodeName) !== 'tr') {
            return false;
        }
        if (trim(str_replace("\xC2\xA0", '', $row->textContent)) !== '') {
            return false;
        }
        foreach ($row->getElementsByTagName('img') as $img) {
            return false;
        }

        return true;
    }

    /**
     * Quita el elemento (fila o bloque) más cercano a cada texto que contenga la firma.
     */
    private static function removeNodesWith(DOMXPath $xpath, DOMNode $context, $needle, $tag)
    {
        $nodes = $xpath->query('.//text()[contains(translate(., "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz"), "' . $needle . '")]', $context);
        foreach ($nodes as $text) {
            if (!$text->parentNode) {
                continue;
            }
            $target = $tag === 'div' ? self::closestBlock($text, $context) : self::closest($text, $tag, $context);
            if ($target && $target->parentNode) {
                if ($tag === 'div') {
                    self::removeWithMsoComments($target);
                } else {
                    self::removeWithSpacers($target);
                }
            }
        }
    }

    private static function innerHtml(DOMNode $node)
    {
        $out = '';
        foreach ($node->childNodes as $child) {
            $out .= $node->ownerDocument->saveHTML($child);
        }

        return $out;
    }
}
