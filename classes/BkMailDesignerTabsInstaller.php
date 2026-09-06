<?php
/**
 * Pestañas del módulo en el back office.
 *
 * El nombre lleva el del módulo porque la clase es global: dos módulos con una clase `TabsInstaller`
 * definirían la misma y ganaría la que cargue primero el autoloader, cruzando la columna `module`
 * de sus pestañas.
 *
 * @author BK Modules
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class BkMailDesignerTabsInstaller
{
    private static $tabs_structure = [
        [
            'name' => [
                'en' => 'Mail Designer',
                'es' => 'Diseñador de correos',
                'fr' => 'Designer d\'e-mails',
                'de' => 'E-Mail-Designer',
                'it' => 'Designer e-mail',
                'pt' => 'Designer de e-mails',
                'nl' => 'E-mailontwerper',
                'pl' => 'Projektant e-maili',
            ],
            'class_name' => 'AdminBkMailDesignerTab',
            'parent_class_name' => 'CONFIGURE',
            'wording' => 'Mail Designer',
            'wording_domain' => 'Modules.Bkmaildesigner.Admin',
            'icon' => 'email',
        ],
        [
            'name' => [
                'en' => 'Emails',
                'es' => 'Correos',
                'fr' => 'E-mails',
                'de' => 'E-Mails',
                'it' => 'E-mail',
                'pt' => 'E-mails',
                'nl' => 'E-mails',
                'pl' => 'E-maile',
            ],
            'class_name' => 'AdminBkMailDesigner',
            'parent_class_name' => 'AdminBkMailDesignerTab',
            'wording' => 'Emails',
            'wording_domain' => 'Modules.Bkmaildesigner.Admin',
        ],
    ];

    public static function install($module_name)
    {
        $languages = Language::getLanguages(false);

        foreach (self::$tabs_structure as $tab_data) {
            // Si la pestaña ya existe de una instalación previa se reutiliza y se le fija el módulo:
            // así no hay duplicados ni una pestaña colgada de otro módulo dando "Page not found".
            $existingId = (int) Tab::getIdFromClassName($tab_data['class_name']);
            $tab = $existingId ? new Tab($existingId) : new Tab();

            $tab->active = 1;
            $tab->class_name = $tab_data['class_name'];
            $tab->module = $module_name;

            $parentId = Tab::getIdFromClassName($tab_data['parent_class_name']);
            if (!$parentId && $tab_data['parent_class_name'] === 'CONFIGURE') {
                $parentId = (int) Db::getInstance()->getValue(
                    'SELECT `id_tab` FROM `' . _DB_PREFIX_ . 'tab` WHERE `class_name` = \'CONFIGURE\''
                );
            }
            $tab->id_parent = $parentId;

            if (isset($tab_data['icon'])) {
                $tab->icon = $tab_data['icon'];
            }

            foreach ($languages as $language) {
                $iso = strtolower($language['iso_code']);
                $tab->name[$language['id_lang']] = isset($tab_data['name'][$iso])
                    ? $tab_data['name'][$iso]
                    : $tab_data['name']['en'];
            }

            $ok = $existingId ? $tab->update() : $tab->add();
            if (!$ok) {
                return false;
            }
        }

        return true;
    }

    public static function uninstall()
    {
        foreach (array_reverse(self::$tabs_structure) as $tab_data) {
            $id_tab = Tab::getIdFromClassName($tab_data['class_name']);
            if ($id_tab) {
                $tab = new Tab($id_tab);
                $tab->delete();
            }
        }

        return true;
    }
}
