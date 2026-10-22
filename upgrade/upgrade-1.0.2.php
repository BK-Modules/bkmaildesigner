<?php
/**
 * BK Mail Designer — actualización a 1.0.2.
 *
 * El pie de cada correo admite líneas de otros módulos por el hook `actionBkMailDesignerFooter`
 * (`BkMailDesignerRenderer::FOOTER_HOOK`), y las traducciones van por el estándar `BkModules\I18n\V1\Translations`.
 * Aquí se da de alta ese hook con su título.
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_0_2($module)
{
    if (!BkMailDesigner::declareFooterHook()) {
        return false;
    }

    \BkModules\Registry\V2\InstallReporter::report($module->name, $module->version, 'upgrade');

    return true;
}
