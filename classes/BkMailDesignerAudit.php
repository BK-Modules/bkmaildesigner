<?php
/**
 * Repaso de lo que, fuera de este módulo, puede cambiar el correo que sale de la tienda:
 * plantillas sobrescritas en el tema, un override del núcleo, otros módulos enganchados a los
 * mismos hooks y ajustes de PrestaShop que dejarían el diseño sin efecto.
 *
 * No arregla nada: enseña lo que hay para que el comerciante sepa por qué un correo no se ve como
 * espera antes de abrir un ticket.
 *
 * @author BK Modules
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class BkMailDesignerAudit
{
    const OK = 'ok';
    const WARN = 'warn';
    const INFO = 'info';

    /** Hooks del correo que otro módulo puede compartir con este */
    const MAIL_HOOKS = [
        'actionEmailSendBefore',
        'actionEmailAddBeforeContent',
        'actionEmailAddAfterContent',
        'actionGetExtraMailTemplateVars',
        'actionMailAlterMessageBeforeSend',
    ];

    /**
     * @param Shop   $shop
     * @param Module $module
     *
     * @return array [['level' => ok|warn|info, 'title' => ..., 'detail' => ...], ...]
     */
    public static function run(Shop $shop, Module $module)
    {
        return array_merge(
            self::mailType(),
            self::themeOverrides($shop),
            self::coreOverride(),
            self::hookNeighbours($shop, $module)
        );
    }

    /**
     * Con el correo en texto plano el diseño no llega a verse: es la causa más tonta y la más
     * difícil de encontrar desde dentro del módulo.
     */
    private static function mailType()
    {
        $type = (int) Configuration::get('PS_MAIL_TYPE');
        if ($type === Mail::TYPE_TEXT) {
            return [[
                'level' => self::WARN,
                'title' => 'PS_MAIL_TYPE_TEXT',
                'detail' => 'PS_MAIL_TYPE = ' . $type,
            ]];
        }

        return [[
            'level' => self::OK,
            'title' => 'PS_MAIL_TYPE_HTML',
            'detail' => '',
        ]];
    }

    /**
     * Plantillas sobrescritas en el tema activo o en su padre. Ganan a las del núcleo y a las de
     * los módulos en la resolución de rutas, así que el modo con marca envuelve ese fichero y no
     * el original.
     *
     * @param Shop $shop
     *
     * @return array
     */
    /**
     * Plantillas que el tema sobrescribe, y si de verdad importan.
     *
     * Importan **solo cuando el módulo lee el fichero original**, es decir, en «Cabecera y pie» y
     * con el correo apagado. En «Diseño completo» el cuerpo lo escriben los bloques y el fichero
     * del tema no se abre siquiera: avisar ahí es ruido, y el comerciante acaba borrando ficheros
     * que no le molestaban.
     */
    private static function themeOverrides(Shop $shop)
    {
        $found = [];
        $themes = [];
        if ($shop->theme) {
            $themes[] = $shop->theme->getName();
            $parent = $shop->theme->get('parent');
            if ($parent) {
                $themes[] = $parent;
            }
        }
        foreach (array_unique($themes) as $theme) {
            $base = _PS_ROOT_DIR_ . '/themes/' . $theme;
            foreach ((array) glob($base . '/mails/*/*.html') as $file) {
                $name = basename($file, '.html');
                $found['themes/' . $theme . '/mails/' . basename(dirname($file)) . '/' . basename($file)] = ['', $name];
            }
            foreach ((array) glob($base . '/modules/*/mails/*/*.html') as $file) {
                $parts = explode('/modules/', $file);
                $rest = end($parts);
                $module = strstr($rest, '/', true);
                $found['themes/' . $theme . '/modules/' . $rest] = [$module === false ? '' : $module, basename($file, '.html')];
            }
        }

        if (empty($found)) {
            return [['level' => self::OK, 'title' => 'THEME_NO_OVERRIDES', 'detail' => '']];
        }

        // Un fichero solo cuenta si su correo lee el original: en diseño completo no se abre
        $live = [];
        $ignored = 0;
        foreach ($found as $path => $pair) {
            $template = BkMailDesignerTemplate::find((int) $shop->id, $pair[0], $pair[1]);
            $reads = !$template->id
                || !$template->active
                || $template->mode !== BkMailDesignerConfig::MODE_DESIGNED;
            if ($reads) {
                $live[] = $path;
            } else {
                ++$ignored;
            }
        }

        if (empty($live)) {
            return [[
                'level' => self::INFO,
                'title' => 'THEME_OVERRIDES_IGNORED',
                'detail' => implode(', ', array_slice(array_keys($found), 0, 12)) . (count($found) > 12 ? '…' : ''),
                'count' => $ignored,
            ]];
        }

        return [[
            'level' => self::WARN,
            'title' => 'THEME_OVERRIDES',
            'detail' => implode(', ', array_slice($live, 0, 12)) . (count($live) > 12 ? '…' : ''),
            'count' => count($live),
        ]];
    }

    /**
     * Un override de la clase Mail puede saltarse los hooks del núcleo, y sin ellos este módulo
     * no llega a intervenir.
     *
     * @return array
     */
    private static function coreOverride()
    {
        $file = _PS_OVERRIDE_DIR_ . 'classes/Mail.php';
        if (!is_file($file)) {
            return [['level' => self::OK, 'title' => 'NO_MAIL_OVERRIDE', 'detail' => '']];
        }

        $content = (string) Tools::file_get_contents($file);
        $missing = [];
        foreach (['actionEmailSendBefore', 'actionEmailAddAfterContent'] as $hook) {
            if (strpos($content, $hook) === false) {
                $missing[] = $hook;
            }
        }

        return [[
            'level' => empty($missing) ? self::INFO : self::WARN,
            'title' => empty($missing) ? 'MAIL_OVERRIDE' : 'MAIL_OVERRIDE_NO_HOOKS',
            'detail' => 'override/classes/Mail.php' . (empty($missing) ? '' : ' — ' . implode(', ', $missing)),
        ]];
    }

    /**
     * Otros módulos en los hooks del correo. El orden importa: lo que añade un módulo colocado
     * antes que este acaba dentro del diseño, y lo que añade uno colocado después queda fuera.
     *
     * @param Shop   $shop
     * @param Module $module
     *
     * @return array
     */
    private static function hookNeighbours(Shop $shop, Module $module)
    {
        $rows = Db::getInstance()->executeS(
            'SELECT h.`name` AS hook, m.`name` AS module, hm.`position`
             FROM `' . _DB_PREFIX_ . 'hook_module` hm
             INNER JOIN `' . _DB_PREFIX_ . 'hook` h ON h.`id_hook` = hm.`id_hook`
             INNER JOIN `' . _DB_PREFIX_ . 'module` m ON m.`id_module` = hm.`id_module`
             WHERE h.`name` IN ("' . implode('", "', array_map('pSQL', self::MAIL_HOOKS)) . '")
               AND hm.`id_shop` = ' . (int) $shop->id . '
             ORDER BY h.`name`, hm.`position`'
        );

        $others = [];
        $behind = [];
        $mine = [];
        foreach ((array) $rows as $row) {
            if ($row['module'] === $module->name) {
                $mine[$row['hook']] = (int) $row['position'];
            }
        }
        foreach ((array) $rows as $row) {
            if ($row['module'] === $module->name) {
                continue;
            }
            $others[] = $row['module'] . ' (' . $row['hook'] . ')';
            if (isset($mine[$row['hook']]) && (int) $row['position'] < $mine[$row['hook']]) {
                $behind[] = $row['module'] . ' (' . $row['hook'] . ')';
            }
        }

        if (empty($others)) {
            return [['level' => self::OK, 'title' => 'HOOKS_ALONE', 'detail' => '']];
        }

        $out = [[
            'level' => self::INFO,
            'title' => 'HOOKS_SHARED',
            'detail' => implode(', ', array_slice(array_unique($others), 0, 10)),
        ]];
        if (!empty($behind)) {
            $out[] = [
                'level' => self::WARN,
                'title' => 'HOOKS_BEFORE',
                'detail' => implode(', ', array_unique($behind)),
            ];
        }

        return $out;
    }
}
