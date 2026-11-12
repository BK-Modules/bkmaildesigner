<?php
/**
 * BK Mail Designer — actualización a 1.0.4.
 *
 * `{admin_order_url}` es la ficha del pedido en el back office (`BkMailDesignerVars::adminOrderUrl()`), y el botón
 * «Abrir el pedido» del correo `new_order` de ps_emailalerts lleva a ella. Las tiendas que ya tenían esa plantilla
 * guardan el botón de serie con `{shop_url}`: aquí pasa a `{admin_order_url}`. Solo se toca el botón de serie —el
 * rótulo de serie de algún idioma y todavía `{shop_url}`—; un botón que el comerciante haya añadido o llevado a otra
 * dirección se queda como está.
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_0_4($module)
{
    $labels = [];
    $preset = BkMailDesignerPresets::load('ps_emailalerts', 'new_order');
    foreach ((array) ($preset ? $preset['blocks'] : []) as $blocks) {
        foreach ((array) $blocks as $block) {
            if (isset($block['type'], $block['text']) && $block['type'] === 'button') {
                $labels[] = (string) $block['text'];
            }
        }
    }

    $ids = Db::getInstance()->executeS(
        'SELECT `id_bk_maildesigner_template` FROM `' . _DB_PREFIX_ . 'bk_maildesigner_template`
         WHERE `module` = \'ps_emailalerts\' AND `name` = \'new_order\''
    );
    foreach ((array) $ids as $row) {
        $template = new BkMailDesignerTemplate((int) $row['id_bk_maildesigner_template']);
        $changed = false;
        foreach (array_keys((array) $template->blocks) as $idLang) {
            $blocks = $template->decodeBlocks($idLang);
            foreach ($blocks as $i => $block) {
                if (isset($block['type'], $block['url']) && $block['type'] === 'button' && $block['url'] === '{shop_url}'
                    && isset($block['text']) && in_array((string) $block['text'], $labels, true)) {
                    $blocks[$i]['url'] = '{admin_order_url}';
                    $template->blocks[$idLang] = json_encode($blocks, JSON_UNESCAPED_UNICODE);
                    $changed = true;
                }
            }
        }
        if ($changed && !$template->update()) {
            return false;
        }
    }

    \BkModules\Registry\V2\InstallReporter::report($module->name, $module->version, 'upgrade');

    return true;
}
