<?php
/**
 * Monta el correo completo: layout de marca + cuerpo (bloques diseñados o contenido original
 * envuelto), sustituye las variables y pasa el CSS a estilos en línea.
 *
 * El mismo render sirve al envío real, a la vista previa y al correo de prueba: lo que se ve en
 * el editor es lo que sale. Las variables que Mail::send() añade después de este punto
 * ({shop_logo}, {shop_name}, {shop_url}…) se dejan sin tocar para que las resuelva el núcleo.
 *
 * @author BK Modules
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class BkMailDesignerRenderer
{
    /** Ancho por defecto; el tema puede estrecharlo o ensancharlo dentro de lo que Outlook pinta */
    const WIDTH = 600;

    /**
     * @param array $in [
     *   'layout' => BkMailDesignerLayout,
     *   'template' => BkMailDesignerTemplate,
     *   'id_lang' => int,
     *   'iso' => string,
     *   'rtl' => bool,
     *   'original' => string HTML original (para el modo envuelto),
     *   'vars' => array {clave} => valor, tal como las pasa Mail::send,
     *   'labels' => array etiquetas por defecto de la tabla de pedido y direcciones,
     *   'shop_name' => string,
     * ]
     *
     * @return array|null ['html' => ..., 'txt' => ..., 'strategy' => ...] o null si no hay nada que enviar
     */
    public static function render(array $in)
    {
        /** @var BkMailDesignerLayout $layout */
        $layout = $in['layout'];
        /** @var BkMailDesignerTemplate $template */
        $template = $in['template'];
        $idLang = (int) $in['id_lang'];
        $rtl = !empty($in['rtl']);
        $s = $layout->getSettings();
        $ctx = [
            'align' => $rtl ? 'right' : 'left',
            // Las etiquetas siempre completas: un bloque de pedido pide las suyas por nombre
            'labels' => array_merge(BkMailDesignerText::orderLabels($idLang), isset($in['labels']) ? $in['labels'] : []),
            'mark' => !empty($in['mark']),
            'settings' => $s,
            'order_layout' => isset($s['order_layout']) ? $s['order_layout'] : 'stacked',
            'order_image' => isset($s['order_image']) ? $s['order_image'] : 0,
            'order_image_size' => isset($s['order_image_size']) ? $s['order_image_size'] : 64,
            'order_reference' => isset($s['order_reference']) ? $s['order_reference'] : 1,
            'order_options' => isset($s['order_options']) ? $s['order_options'] : 1,
            'order_unit' => isset($s['order_unit']) ? $s['order_unit'] : 1,
            'line_style' => isset($s['line_style']) ? $s['line_style'] : 'plain',
            'button_style' => isset($s['button_style']) ? $s['button_style'] : 'solid',
        ];

        $preheader = '';
        $strategy = $template->mode;
        if ($template->mode === BkMailDesignerConfig::MODE_DESIGNED) {
            list($blocks) = $template->blocksFor($idLang);
            $blocks = BkMailDesignerBlocks::sanitize($blocks);
            if (empty($blocks)) {
                return null;
            }
            $rows = BkMailDesignerBlocks::render($blocks, $ctx);
            $preheader = BkMailDesignerBlocks::preheader($blocks);
            $bodyClass = 'bk-body';
        } else {
            $extracted = BkMailDesignerWrapper::extract(isset($in['original']) ? $in['original'] : '');
            if ($extracted === null) {
                return null;
            }
            $strategy = 'wrapped:' . $extracted['strategy'];
            $rows = '<tr><td class="bk-wrapped bk-wrapped-' . $extracted['strategy'] . '">' . $extracted['html'] . '</td></tr>';
            $bodyClass = 'bk-body bk-body-wrapped';
        }

        $subject = $template->subjectFor($idLang);
        $title = $subject !== '' ? $subject : (isset($in['shop_name']) ? $in['shop_name'] : '{shop_name}');

        $audience = BkMailDesignerScanner::audience($template->module, $template->name);
        $html = self::skeleton($s, $layout, $idLang, $in['iso'], $rtl, $title, $preheader, $bodyClass, $rows, $audience, !empty($in['mark']));

        $idShop = isset($in['id_shop']) ? (int) $in['id_shop'] : (int) Context::getContext()->shop->id;
        $html = strtr($html, BkMailDesignerVars::own($idShop, $idLang));

        if (!empty($in['vars']) && is_array($in['vars'])) {
            $html = strtr($html, self::stringVars($in['vars']));
        }

        // Las líneas del pedido se recomponen ahora: hasta aquí eran «{products}»
        $html = BkMailDesignerOrderTable::apply($html, isset($in['vars']) && is_array($in['vars']) ? $in['vars'] : []);

        $html = self::inline($html, self::css($s, $rtl) . self::extraCss($s));

        return [
            'html' => $html,
            'txt' => BkMailDesignerText::fromHtml($html),
            'strategy' => $strategy,
        ];
    }

    /**
     * Mail::send() acepta cualquier valor en las variables; strtr solo cadenas.
     */
    private static function stringVars(array $vars)
    {
        $out = [];
        foreach ($vars as $key => $value) {
            if (is_scalar($value) || $value === null) {
                $out[(string) $key] = (string) $value;
            }
        }

        return $out;
    }

    /**
     * Estructura del correo: tablas anidadas con los condicionales que Outlook necesita para
     * respetar el ancho, y una hoja de estilos mínima para el móvil que los clientes que la
     * entienden aplican encima de los estilos en línea.
     */
    private static function skeleton(array $s, BkMailDesignerLayout $layout, $idLang, $iso, $rtl, $title, $preheader, $bodyClass, $rows, $audience, $mark = false)
    {
        $w = isset($s['width']) ? (int) $s['width'] : self::WIDTH;
        // Vacío en un color de zona significa «el de marca»: una tienda que nunca los toque no
        // nota que existen.
        $headerBg = $s['color_header_bg'] !== '' ? $s['color_header_bg'] : $s['color_primary'];
        $headerText = $s['color_header_text'] !== '' ? $s['color_header_text'] : '#ffffff';
        $bandColor = $s['color_band'] !== '' ? $s['color_band'] : $s['color_primary'];
        $bandHeight = (int) $s['band_height'];
        // La zona se marca solo en la vista previa del back office: un clic en el correo abre la
        // pestaña que la gobierna.
        $zone = function ($name) use ($mark) {
            return $mark ? ' data-bk-zone="' . $name . '"' : '';
        };
        $dir = $rtl ? 'rtl' : 'ltr';
        $logoSrc = $s['logo_url'] !== '' ? $s['logo_url'] : '{shop_logo}';
        $logo = '<a href="{shop_url}" target="_blank"><img src="' . self::esc($logoSrc) . '" alt="{shop_name}" width="' . (int) $s['logo_width'] . '" class="bk-logo" style="width:' . (int) $s['logo_width'] . 'px;max-width:100%;height:auto;display:block;border:0;"></a>';

        $nav = self::nav($layout->links('header_links', $idLang));

        switch ($s['header_style']) {
            case 'left':
                $header = '<tr><td class="bk-header"' . $zone('header') . '><table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr>'
                    . '<td class="bk-stack" align="' . ($rtl ? 'right' : 'left') . '" valign="middle">' . $logo . '</td>'
                    . ($nav !== '' ? '<td class="bk-stack bk-stack-nav" align="' . ($rtl ? 'left' : 'right') . '" valign="middle">' . $nav . '</td>' : '')
                    . '</tr></table></td></tr>';
                break;
            case 'band':
                $header = '<tr><td class="bk-header bk-header-band" bgcolor="' . $headerBg . '" style="background-color:' . $headerBg . ';"' . $zone('header') . '><table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr>'
                    . '<td class="bk-stack" align="' . ($rtl ? 'right' : 'left') . '" valign="middle">' . $logo . '</td>'
                    . ($nav !== '' ? '<td class="bk-stack bk-stack-nav" align="' . ($rtl ? 'left' : 'right') . '" valign="middle">' . $nav . '</td>' : '')
                    . '</tr></table></td></tr>';
                break;
            default:
                $header = '<tr><td class="bk-header" align="center"' . $zone('header') . '>' . str_replace('display:block;', 'display:inline-block;', $logo)
                    . ($nav !== '' ? '<div class="bk-navrow">' . $nav . '</div>' : '')
                    . '</td></tr>';
        }

        $band = !empty($s['top_band']) && $s['header_style'] !== 'band'
            ? '<tr><td class="bk-band" height="' . $bandHeight . '" bgcolor="' . $bandColor . '" style="height:' . $bandHeight . 'px;line-height:' . $bandHeight . 'px;font-size:1px;background-color:' . $bandColor . ';"' . $zone('frame') . '>&nbsp;</td></tr>'
            : '';

        $footerParts = [];
        $footerNav = self::nav($layout->links('footer_links', $idLang));
        if ($footerNav !== '') {
            $footerParts[] = '<p class="bk-footer-links">' . $footerNav . '</p>';
        }
        $social = self::socialRow($s);
        if ($social !== '') {
            $footerParts[] = $social;
        }
        if (!empty($s['shop_name_in_footer'])) {
            $footerParts[] = '<p><a href="{shop_url}" class="bk-shop" target="_blank">{shop_name}</a></p>';
        }
        $legal = trim($layout->text('footer_legal', $idLang));
        if ($legal !== '') {
            $footerParts[] = '<p>' . nl2br(self::esc($legal)) . '</p>';
        }
        $reason = trim($layout->reason($idLang, $audience));
        if ($reason !== '') {
            $footerParts[] = '<p class="bk-reason">' . nl2br(self::esc($reason)) . '</p>';
        }
        $footer = '<tr><td class="bk-footer" bgcolor="' . $s['color_footer_bg'] . '" align="center"' . $zone('footer') . '>' . implode('', $footerParts) . '</td></tr>';

        $pre = $preheader !== ''
            ? '<div class="bk-pre" style="display:none;font-size:1px;line-height:1px;max-height:0;max-width:0;opacity:0;overflow:hidden;mso-hide:all;">' . self::esc($preheader) . str_repeat('&#847;&zwnj;&nbsp;', 40) . '</div>'
            : '';

        return '<!DOCTYPE html>' . "\n"
            . '<html xmlns="http://www.w3.org/1999/xhtml" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office" lang="' . self::esc($iso) . '" dir="' . $dir . '">' . "\n"
            . '<head>' . "\n"
            . '<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">' . "\n"
            . '<meta name="viewport" content="width=device-width, initial-scale=1">' . "\n"
            . '<meta http-equiv="X-UA-Compatible" content="IE=edge">' . "\n"
            . '<meta name="x-apple-disable-message-reformatting">' . "\n"
            . '<meta name="color-scheme" content="light">' . "\n"
            . '<meta name="supported-color-schemes" content="light">' . "\n"
            . '<title>' . self::esc($title) . '</title>' . "\n"
            . '<!--[if mso]><noscript><xml><o:OfficeDocumentSettings><o:PixelsPerInch>96</o:PixelsPerInch></o:OfficeDocumentSettings></xml></noscript><![endif]-->' . "\n"
            . '<style type="text/css">' . "\n" . self::mobileCss() . "\n" . '</style>' . "\n"
            . '</head>' . "\n"
            . '<body class="bk-page" bgcolor="' . $s['color_outer'] . '" style="margin:0;padding:0;width:100%;background-color:' . $s['color_outer'] . ';">' . "\n"
            . $pre
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="' . $s['color_outer'] . '" class="bk-outer"><tr><td class="bk-outer-cell" align="center">' . "\n"
            . '<!--[if mso]><table role="presentation" width="' . $w . '" cellpadding="0" cellspacing="0" border="0" align="center"><tr><td><![endif]-->' . "\n"
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" class="bk-card" bgcolor="' . $s['color_card'] . '" style="max-width:' . $w . 'px;margin:0 auto;">' . "\n"
            . $band . $header . "\n"
            . '<tr><td class="' . $bodyClass . '"' . $zone('body') . '><table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" class="bk-blocks">' . $rows . '</table></td></tr>' . "\n"
            . $footer . "\n"
            . '</table>' . "\n"
            . '<!--[if mso]></td></tr></table><![endif]-->' . "\n"
            . '</td></tr></table>' . "\n"
            . '</body></html>';
    }

    private static function nav(array $links)
    {
        if (empty($links)) {
            return '';
        }
        $out = [];
        foreach ($links as $link) {
            $out[] = '<a href="' . self::esc($link['url']) . '" class="bk-nav" target="_blank">' . self::esc($link['label']) . '</a>';
        }

        return implode('<span class="bk-nav-sep">&nbsp;&nbsp;&middot;&nbsp;&nbsp;</span>', $out);
    }

    /**
     * Hoja de estilos del layout. Va a estilos en línea, así que cada regla tiene que resolverse
     * por sí sola: sin variables ni herencia que un cliente de correo pueda no respetar.
     */
    /**
     * Hoja de estilos del layout. Va a estilos en línea, así que cada regla tiene que resolverse
     * por sí sola: sin variables ni herencia que un cliente de correo pueda no respetar.
     *
     * Aquí se decide la personalidad del correo —tipografía, tamaños, aire y forma—, no solo el
     * color: dos temas con la misma retícula y distinto tono se parecen demasiado.
     */
    private static function css(array $s, $rtl)
    {
        $body = BkMailDesignerLayout::FONTS[$s['font']];
        $head = $s['font_heading'] !== '' && isset(BkMailDesignerLayout::FONTS[$s['font_heading']])
            ? BkMailDesignerLayout::FONTS[$s['font_heading']]
            : $body;
        $p = $s['color_primary'];
        $t = $s['color_text'];
        $m = $s['color_muted'];
        $ft = $s['color_footer_text'];
        $r = (int) $s['card_radius'];
        $br = (int) $s['button_radius'];
        $line = self::mix($t, $s['color_card'], 0.14);
        $soft = self::mix($p, $s['color_card'], 0.06);
        $align = $rtl ? 'right' : 'left';
        $far = $rtl ? 'left' : 'right';
        $edge = $rtl ? 'right' : 'left';

        $space = BkMailDesignerLayout::SPACES[$s['space']];
        $padX = (int) $space['x'];
        $padY = (int) $space['y'];
        $block = (int) $space['block'];

        $hSize = (int) $s['heading_size'];
        $h2Size = max(14, (int) round($hSize * 0.76));
        $hWeight = $s['heading_weight'];
        $hCase = $s['heading_case'] === 'upper' ? 'uppercase' : 'none';
        $hSpacing = number_format($s['heading_spacing'] / 100, 3, '.', '');
        $bSize = (int) $s['body_size'];
        $bLead = number_format($s['body_leading'] / 100, 2, '.', '');
        $small = max(11, $bSize - 2);

        $btn = BkMailDesignerLayout::BUTTON_SIZES[$s['button_size']];
        $btnPad = $btn['pad'];
        $btnSize = (int) $btn['size'];
        $btnWeight = $s['button_weight'];
        $btnCase = $s['button_case'] === 'upper' ? 'uppercase' : 'none';
        $btnSpacing = $s['button_case'] === 'upper' ? '0.06em' : 'normal';
        // Un botón a todo ancho necesita que la tabla que lo envuelve también lo ocupe: el enlace
        // al 100% de una celda que se encoge al contenido no ensancha nada.
        $btnFull = !empty($s['button_full']);
        $btnWidth = $btnFull ? 'width:100%;box-sizing:border-box;text-align:center;' : '';
        $btnTable = $btnFull ? 'width: 100%;' : '';
        $btnOutlinePad = self::shrinkPadding($btnPad);

        $boxFill = $s['box_style'] === 'outline' ? $s['color_card'] : $soft;
        $boxBorder = $s['box_style'] === 'outline' ? '1px solid ' . $line : 'none';
        $boxBar = $s['box_style'] === 'bar' ? 'border-' . $edge . ': 3px solid ' . $p . ';' : '';

        $ruleWidth = $s['rule_style'] === 'thick' ? 3 : 1;
        $ruleStyle = $s['rule_style'] === 'dotted' ? 'dotted' : 'solid';
        $ruleColor = $s['rule_style'] === 'thick' ? $p : $line;

        // La línea del pedido hereda la personalidad del tema: el nombre en versales cuando los
        // titulares lo van, y la foto con el mismo radio que la tarjeta.
        $lineCase = $s['heading_case'] === 'upper' ? 'uppercase' : 'none';
        $lineSpacing = $s['heading_case'] === 'upper' ? '0.04em' : 'normal';
        $imgRadius = min(10, $r);

        $headerText = $s['color_header_text'] !== '' ? $s['color_header_text'] : '#ffffff';

        $headerRule = !empty($s['header_rule']) ? 'border-bottom: 1px solid ' . $line . ';' : '';
        $footerRule = !empty($s['footer_rule']) ? 'border-top: 1px solid ' . $line . ';' : '';

        return <<<CSS
.bk-page, .bk-outer { -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
.bk-outer-cell { padding: 28px 12px; }
.bk-card { background-color: {$s['color_card']}; border-radius: {$r}px; }
.bk-band { background-color: {$p}; }
.bk-header { padding: {$padY}px {$padX}px 10px; {$headerRule} }
.bk-header-band { background-color: {$p}; padding: 18px {$padX}px; border-bottom: 0; }
.bk-navrow { padding-top: 14px; }
.bk-nav { font-family: {$body}; font-size: {$small}px; color: {$m}; text-decoration: none; }
.bk-nav-sep { font-family: {$body}; font-size: {$small}px; color: {$m}; }
.bk-header-band .bk-nav, .bk-header-band .bk-nav-sep { color: {$headerText}; }
.bk-social { margin: 4px auto 12px; }
.bk-social__cell { line-height: 1; }
.bk-body { padding: 10px 0 {$padY}px; font-family: {$body}; color: {$t}; }
.bk-body-wrapped { padding: 6px 8px 14px; }
/* El relleno lateral de todo el cuerpo vive aquí: lo que venga después solo puede
   tocar el vertical, o el bloque se sale del margen de la tarjeta. */
.bk-block { padding: {$block}px {$padX}px; }
.bk-block--bleed { padding-left: 0; padding-right: 0; }
.bk-media__side { padding-{$far}: 16px; }
.bk-media--right .bk-media__side { padding-{$far}: 0; padding-{$align}: 16px; }
.bk-h1 { font-family: {$head}; font-size: {$hSize}px; line-height: 1.25; font-weight: {$hWeight}; text-transform: {$hCase}; letter-spacing: {$hSpacing}em; color: {$t}; margin: 0 0 10px; }
.bk-h2 { font-family: {$head}; font-size: {$h2Size}px; line-height: 1.3; font-weight: {$hWeight}; text-transform: {$hCase}; letter-spacing: {$hSpacing}em; color: {$t}; margin: 0 0 6px; }
.bk-rich, .bk-col, .bk-box { font-family: {$body}; font-size: {$bSize}px; line-height: {$bLead}; color: {$t}; }
.bk-rich p, .bk-col p, .bk-box p { font-family: {$body}; font-size: {$bSize}px; line-height: {$bLead}; color: {$t}; margin: 0 0 12px; }
.bk-rich ul, .bk-rich ol, .bk-col ul, .bk-col ol, .bk-box ul, .bk-box ol { margin: 0 0 12px; padding-{$align}: 22px; }
.bk-rich li, .bk-col li, .bk-box li { font-family: {$body}; font-size: {$bSize}px; line-height: {$bLead}; color: {$t}; margin: 0 0 4px; }
.bk-rich p:last-child, .bk-col p:last-child, .bk-box p:last-child { margin-bottom: 0; }
.bk-muted p, .bk-muted li { color: {$m}; font-size: {$small}px; }
.bk-rich a, .bk-col a, .bk-box a { color: {$p}; text-decoration: underline; }
.bk-block.bk-button { padding-top: 8px; padding-bottom: 12px; }
.bk-btn-table { {$btnTable} }
.bk-btn-cell { border-radius: {$br}px; {$btnTable} }
.bk-btn-solid { background-color: {$p}; }
.bk-btn-solid-link { display: inline-block; padding: {$btnPad}; font-family: {$body}; font-size: {$btnSize}px; line-height: 1.2; font-weight: {$btnWeight}; text-transform: {$btnCase}; letter-spacing: {$btnSpacing}; color: #ffffff; text-decoration: none; border-radius: {$br}px; {$btnWidth} }
.bk-btn-outline { border: 2px solid {$p}; }
.bk-btn-outline-link { display: inline-block; padding: {$btnOutlinePad}; font-family: {$body}; font-size: {$btnSize}px; line-height: 1.2; font-weight: {$btnWeight}; text-transform: {$btnCase}; letter-spacing: {$btnSpacing}; color: {$p}; text-decoration: none; border-radius: {$br}px; {$btnWidth} }
.bk-box { background-color: {$boxFill}; border: {$boxBorder}; {$boxBar} border-radius: {$r}px; padding: 14px 18px; }
.bk-box-primary { border-{$edge}: 3px solid {$p}; }
.bk-box-success { background-color: #eef7e6; border-{$edge}: 3px solid #4f792b; }
.bk-box-warning { background-color: #fff4e0; border-{$edge}: 3px solid #ab5700; }
.bk-block.bk-divider { padding-top: 10px; padding-bottom: 10px; }
.bk-rule { border-top: {$ruleWidth}px {$ruleStyle} {$ruleColor}; height: 1px; line-height: 1px; font-size: 1px; }
.bk-block.bk-image { padding-top: 6px; padding-bottom: 6px; }
.bk-col-first { padding: 0 10px 0 0; }
.bk-col-mid { padding: 0 10px; }
.bk-col-last { padding: 0 0 0 10px; }
.bk-hero { border-collapse: collapse; }
.bk-hero__veil { padding: 26px {$padX}px; }
.bk-hero__title { font-family: {$head}; font-size: {$hSize}px; line-height: 1.2; font-weight: {$hWeight}; text-transform: {$hCase}; letter-spacing: {$hSpacing}em; margin: 0 0 10px; }
.bk-hero__text { font-family: {$body}; font-size: {$bSize}px; line-height: {$bLead}; margin: 0 0 14px; }
.bk-hero__text p { margin: 0 0 8px; color: inherit; font-size: {$bSize}px; line-height: {$bLead}; }
.bk-hero__text p:last-child { margin-bottom: 0; }
.bk-hero__btntable { margin-top: 4px; }
.bk-hero__btn { background-color: #ffffff; border-radius: {$br}px; }
.bk-hero__btnlink { color: {$p}; background-color: transparent; }
.bk-label { font-family: {$head}; font-size: {$small}px; text-transform: uppercase; letter-spacing: 0.05em; color: {$m}; font-weight: bold; margin: 0 0 4px; }
.bk-order { border-collapse: collapse; margin: 4px 0 8px; }
.bk-th { font-family: {$head}; font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; color: {$m}; font-weight: bold; padding: 0 6px 8px 0; border-bottom: 2px solid {$line}; }
.bk-order > tbody > tr > td { border: 0 !important; border-bottom: 1px solid {$line} !important; padding: 9px 0 !important; background-color: transparent !important; vertical-align: top; }
.bk-order td table { border-collapse: collapse; width: 100%; }
.bk-order font { font-family: {$body} !important; font-size: {$small}px !important; color: {$t} !important; }
.bk-order a { color: {$p}; }
.bk-totals { margin: 6px 0 4px; }
.bk-total { font-family: {$body}; font-size: {$small}px; color: {$m}; padding: 4px 0; }
.bk-total-num { white-space: nowrap; }
.bk-total-strong { font-family: {$head}; font-size: {$bSize}px; color: {$t}; font-weight: bold; border-top: 2px solid {$line}; padding-top: 10px; }
.bk-footer { background-color: {$s['color_footer_bg']}; padding: 22px {$padX}px 24px; font-family: {$body}; font-size: {$small}px; line-height: 1.6; color: {$ft}; border-radius: 0 0 {$r}px {$r}px; {$footerRule} }
.bk-footer p { font-family: {$body}; font-size: 12px; line-height: 1.6; color: {$ft}; margin: 0 0 6px; }
.bk-footer a { color: {$p}; text-decoration: none; }
.bk-footer .bk-shop { font-family: {$head}; font-size: 14px; font-weight: bold; color: {$ft}; }
.bk-footer .bk-nav, .bk-footer .bk-nav-sep { color: {$ft}; }
.bk-footer .bk-reason { font-size: 11px; }
.bk-lines { border-collapse: collapse; margin: 4px 0 10px; }
.bk-line { font-family: {$body}; font-size: {$bSize}px; line-height: 1.4; color: {$t}; padding: 11px 0; vertical-align: top; }
.bk-line-plain { border-bottom: {$ruleWidth}px {$ruleStyle} {$ruleColor}; }
.bk-line-zebra { padding: 10px 12px; }
.bk-line-boxed { background-color: {$soft}; border-top: 1px {$ruleStyle} {$ruleColor}; border-bottom: 1px {$ruleStyle} {$ruleColor}; padding: 13px 15px; }
.bk-line--alt { background-color: {$soft}; }
.bk-line__gap { height: 6px; line-height: 6px; font-size: 1px; padding: 0; }
.bk-line__photo { width: 1%; padding-{$align}: 0; padding-{$far}: 14px; }
.bk-line__img { border-radius: {$imgRadius}px; }
.bk-line__name { display: block; font-family: {$head}; font-size: {$bSize}px; font-weight: bold; line-height: 1.35; text-transform: {$lineCase}; letter-spacing: {$lineSpacing}; color: {$t}; }
.bk-line__meta { display: block; font-family: {$body}; font-size: {$small}px; color: {$m}; margin-top: 3px; }
.bk-line__qty { display: block; font-family: {$body}; font-size: {$small}px; color: {$m}; margin-top: 5px; }
.bk-line__amount { font-family: {$body}; font-size: {$bSize}px; font-weight: bold; color: {$t}; text-align: right; white-space: nowrap; width: 1%; padding-{$align}: 14px; }
.bk-line--discount, .bk-line--discount .bk-line__name { color: {$p}; font-weight: bold; }
CSS;
    }

    /**
     * La fila de redes sociales del pie.
     *
     * Los iconos son imágenes y no una fuente ni un SVG, que ningún cliente de correo pinta. Van
     * en dos versiones: el trazo en blanco sobre una celda del color de la red o del de la
     * tienda, y el trazo a color cuando no se quiere fondo.
     *
     * @param array  $s
     * @param string $align
     *
     * @return string
     */
    public static function socialRow(array $s, $align = 'center')
    {
        if (empty($s['social'])) {
            return '';
        }

        $size = (int) $s['social_size'];
        $plain = $s['social_style'] === 'plain';
        $radius = $s['social_shape'] === 'square' ? max(2, (int) round($size * 0.22)) : (int) round($size / 2);
        $glyph = $plain ? $size : (int) round($size * 0.58);
        $base = Context::getContext()->shop->getBaseURL(true) . 'modules/bkmaildesigner/views/img/social/';

        $cells = [];
        foreach ($s['social'] as $row) {
            $network = $row['network'];
            $own = $network === 'custom';
            $label = $row['label'] !== ''
                ? $row['label']
                : ($own ? 'Web' : BkMailDesignerLayout::NETWORKS[$network]['name']);

            if (!$own) {
                $bg = $plain
                    ? ''
                    : ($s['social_style'] === 'color' ? BkMailDesignerLayout::NETWORKS[$network]['color'] : $s['color_primary']);
                $cells[] = self::socialCell(
                    $row['url'],
                    $base . $network . ($plain ? '-c' : '-w') . '.png',
                    $label,
                    $bg,
                    $size,
                    $radius,
                    'width:' . $glyph . 'px;height:' . $glyph . 'px;',
                    $size
                );
                continue;
            }

            // La imagen del comerciante no es un trazo blanco recortable ni tiene por qué ser
            // cuadrada: **se respeta su proporción** —solo se fija el alto— y no se recorta en
            // redondo, que a un logotipo apaisado le corta media palabra. De serie va suelta, a
            // la altura de los demás iconos; con color de fondo se mete dentro como los otros.
            $bg = $plain ? '' : $row['color'];
            // Con fondo cabe dentro del botón; suelta puede ser un logotipo apaisado y se le deja
            // el doble de ancho, nunca más: una fila de iconos no la puede romper una imagen.
            $tall = $bg === '' ? $size : (int) round($size * 0.62);
            $wide = $bg === '' ? $size * 2 : (int) round($size * 0.78);
            $cells[] = self::socialCell(
                $row['url'],
                $row['icon'],
                $label,
                $bg,
                $bg === '' ? 0 : $size,
                $radius,
                'height:' . $tall . 'px;width:auto;max-width:' . $wide . 'px;',
                $tall
            );
        }

        return '<table role="presentation" cellpadding="0" cellspacing="0" border="0" align="' . ($align === '' ? 'center' : $align) . '" class="bk-social"><tr>'
            . implode('<td class="bk-social__gap" width="10" style="width:10px;line-height:1px;font-size:1px;">&nbsp;</td>', $cells)
            . '</tr></table>';
    }

    /**
     * Una celda de la fila de redes.
     *
     * @param string $url
     * @param string $src
     * @param string $label
     * @param string $bg    Vacío, sin fondo
     * @param int    $box   Lado de la celda; 0 la deja crecer con la imagen
     * @param int    $radius
     * @param string $imgStyle
     * @param int    $height Alto de la imagen, para el atributo que Outlook necesita
     *
     * @return string
     */
    private static function socialCell($url, $src, $label, $bg, $box, $radius, $imgStyle, $height)
    {
        $style = 'text-align:center;vertical-align:middle;'
            . ($box > 0 ? 'width:' . $box . 'px;height:' . $box . 'px;border-radius:' . $radius . 'px;' : 'height:' . $height . 'px;')
            . ($bg !== '' ? 'background-color:' . $bg . ';' : '');

        return '<td class="bk-social__cell"' . ($box > 0 ? ' width="' . $box . '"' : '') . ' height="' . ($box > 0 ? $box : $height) . '"'
            . ' align="center" valign="middle"' . ($bg !== '' ? ' bgcolor="' . $bg . '"' : '') . ' style="' . $style . '">'
            . '<a href="' . self::esc($url) . '" target="_blank" style="text-decoration:none;display:inline-block;line-height:1;">'
            . '<img src="' . self::esc($src) . '" height="' . $height . '" alt="' . self::esc($label) . '"'
            . ' class="bk-social__img" style="' . $imgStyle . 'display:block;border:0;">'
            . '</a></td>';
    }

    /**
     * El CSS que el comerciante haya escrito, al final de la hoja para que gane al del tema.
     *
     * @param array $s
     *
     * @return string
     */
    private static function extraCss(array $s)
    {
        return empty($s['custom_css']) ? '' : "\n" . $s['custom_css'];
    }

    /**
     * Un tono intermedio entre dos colores, para filetes y fondos que tienen que sentarse sobre la
     * tarjeta sin fijar un gris que solo funcione en una paleta.
     *
     * @param string $from  #rrggbb
     * @param string $onto  #rrggbb
     * @param float  $ratio Parte de $from en la mezcla
     *
     * @return string
     */
    private static function mix($from, $onto, $ratio)
    {
        $a = self::rgb($from);
        $b = self::rgb($onto);
        $out = '#';
        for ($i = 0; $i < 3; ++$i) {
            $out .= str_pad(dechex((int) round($a[$i] * $ratio + $b[$i] * (1 - $ratio))), 2, '0', STR_PAD_LEFT);
        }

        return $out;
    }

    /**
     * @param string $hex
     *
     * @return array
     */
    private static function rgb($hex)
    {
        $hex = ltrim((string) $hex, '#');
        if (Tools::strlen($hex) !== 6) {
            return [0, 0, 0];
        }

        return [hexdec(Tools::substr($hex, 0, 2)), hexdec(Tools::substr($hex, 2, 2)), hexdec(Tools::substr($hex, 4, 2))];
    }

    /**
     * El botón con borde ocupa 2 px por lado con el propio borde: se le quitan del relleno para que
     * los dos estilos tengan la misma altura.
     *
     * @param string $padding
     *
     * @return string
     */
    private static function shrinkPadding($padding)
    {
        $parts = explode(' ', $padding);
        foreach ($parts as $i => $part) {
            $parts[$i] = max(0, (int) $part - 2) . 'px';
        }

        return implode(' ', $parts);
    }

    /**
     * Lo que se queda en la etiqueta <style>: solo reglas de móvil, que no pueden ir en línea.
     */
    private static function mobileCss()
    {
        return <<<CSS
#outlook a { padding: 0; }
table, td { border-collapse: collapse; mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
img { -ms-interpolation-mode: bicubic; }
@media only screen and (max-width: 620px) {
  .bk-outer-cell { padding: 0 !important; }
  .bk-card { width: 100% !important; max-width: 100% !important; border-radius: 0 !important; }
  .bk-header, .bk-footer, .bk-block { padding-left: 18px !important; padding-right: 18px !important; }
  .bk-block--bleed { padding-left: 0 !important; padding-right: 0 !important; }
  .bk-body-wrapped { padding-left: 0 !important; padding-right: 0 !important; }
  .bk-media__side { display: block !important; width: 100% !important; padding: 0 0 12px !important; }
  .bk-media__text { display: block !important; width: 100% !important; }
  .bk-stack { display: block !important; width: 100% !important; text-align: center !important; }
  .bk-stack img { margin: 0 auto !important; }
  .bk-stack-nav { padding-top: 12px !important; }
  .bk-col { display: block !important; width: 100% !important; padding: 0 0 14px !important; }
  .bk-hero__veil { padding-left: 18px !important; padding-right: 18px !important; }
  .bk-btn-table { width: 100% !important; }
  .bk-btn-cell { display: block !important; }
  .bk-btn-solid-link, .bk-btn-outline-link { display: block !important; text-align: center !important; }
  .bk-order .bk-th, .bk-order > tbody > tr > td { font-size: 12px !important; }
}
CSS;
    }

    /**
     * Estilos en línea con la librería que el núcleo trae desde 1.7.6; sin ella, la hoja viaja
     * en la cabecera, que Gmail y Apple Mail siguen respetando.
     */
    private static function inline($html, $css)
    {
        if (class_exists('TijsVerkoyen\CssToInlineStyles\CssToInlineStyles')) {
            try {
                $inliner = new \TijsVerkoyen\CssToInlineStyles\CssToInlineStyles();

                return self::keepVariables($inliner->convert($html, $css));
            } catch (\Exception $e) {
                BkMailDesignerLogger::warning('No se pudo pasar el CSS a estilos en línea: ' . $e->getMessage());
            }
        }

        return str_replace('</head>', '<style type="text/css">' . $css . '</style></head>', $html);
    }

    /**
     * Devuelve sus llaves a las variables que quedan dentro de un atributo.
     *
     * **Invariante**: una variable tiene que llegar a `Mail::send()` escrita `{asi}`. El inliner
     * de CSS reserializa el documento con DOMDocument, y DOMDocument codifica los atributos que
     * parecen una URI: `href="{shop_url}"` sale como `href="%7Bshop_url%7D"` y la sustitución
     * final del núcleo ya no la encuentra, así que el enlace llega al cliente sin resolver. Solo
     * se deshace la codificación de lo que tiene forma de variable, no de cualquier `%7B`.
     *
     * @param string $html
     *
     * @return string
     */
    private static function keepVariables($html)
    {
        return preg_replace('/%7B([a-z0-9_]+)%7D/i', '{$1}', $html);
    }

    private static function esc($value)
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8', false);
    }
}
