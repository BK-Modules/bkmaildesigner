<?php
/**
 * Estado de una plantilla de correo en una tienda: si el módulo la toca, cómo (envuelta o
 * diseñada) y, por idioma, el asunto y los bloques del cuerpo.
 *
 * Una plantilla se identifica por módulo ('' para las del núcleo) y nombre de fichero: así
 * `admin_notification` de dos módulos distintos son dos plantillas distintas.
 *
 * @author BK Modules
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class BkMailDesignerTemplate extends ObjectModel
{
    public $id_shop;
    /** @var string Módulo dueño de la plantilla, vacío para el núcleo */
    public $module;
    /** @var string Nombre del fichero sin extensión */
    public $name;
    /** @var string wrapped | designed */
    public $mode;
    /** @var bool Apagada, el correo sale tal cual lo genera PrestaShop */
    public $active;
    /** @var string|array Asunto por idioma; vacío conserva el de PrestaShop */
    public $subject;
    /** @var string|array JSON con los bloques del cuerpo por idioma */
    public $blocks;
    public $date_add;
    public $date_upd;

    public static $definition = [
        'table' => 'bk_maildesigner_template',
        'primary' => 'id_bk_maildesigner_template',
        'multilang' => true,
        'fields' => [
            'id_shop' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'required' => true],
            'module' => ['type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'size' => 64],
            'name' => ['type' => self::TYPE_STRING, 'validate' => 'isTplName', 'required' => true, 'size' => 64],
            'mode' => ['type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'size' => 16],
            'active' => ['type' => self::TYPE_BOOL, 'validate' => 'isBool'],
            'date_add' => ['type' => self::TYPE_DATE, 'validate' => 'isDate'],
            'date_upd' => ['type' => self::TYPE_DATE, 'validate' => 'isDate'],
            'subject' => ['type' => self::TYPE_STRING, 'lang' => true, 'validate' => 'isString', 'size' => 255],
            'blocks' => ['type' => self::TYPE_HTML, 'lang' => true, 'validate' => 'isString'],
        ],
    ];

    /**
     * Fila de una plantilla; si aún no existe, una nueva sin guardar con el modo de alta configurado.
     *
     * @param int    $idShop
     * @param string $module
     * @param string $name
     *
     * @return BkMailDesignerTemplate
     */
    public static function find($idShop, $module, $name)
    {
        $id = (int) Db::getInstance()->getValue(
            'SELECT `id_bk_maildesigner_template` FROM `' . _DB_PREFIX_ . 'bk_maildesigner_template`
             WHERE `id_shop` = ' . (int) $idShop . '
               AND `module` = \'' . pSQL($module) . '\'
               AND `name` = \'' . pSQL($name) . '\''
        );
        $template = new self($id ?: null);
        if (!$id) {
            $template->id_shop = (int) $idShop;
            $template->module = (string) $module;
            $template->name = (string) $name;
            $template->mode = BkMailDesignerConfig::MODE_WRAPPED;
            $template->active = BkMailDesignerConfig::getNewMode() === 'wrapped';
            $template->subject = [];
            $template->blocks = [];
            foreach (Language::getLanguages(false) as $language) {
                $template->subject[(int) $language['id_lang']] = '';
                $template->blocks[(int) $language['id_lang']] = '';
            }
        }

        return $template;
    }

    /**
     * Todas las filas de una tienda indexadas por "módulo/nombre", para el listado.
     *
     * @param int $idShop
     *
     * @return array
     */
    public static function allByKey($idShop)
    {
        $rows = Db::getInstance()->executeS(
            'SELECT t.*, tl.`id_lang`, tl.`subject`, LENGTH(tl.`blocks`) AS `blocks_len`
             FROM `' . _DB_PREFIX_ . 'bk_maildesigner_template` t
             LEFT JOIN `' . _DB_PREFIX_ . 'bk_maildesigner_template_lang` tl
                ON tl.`id_bk_maildesigner_template` = t.`id_bk_maildesigner_template`
             WHERE t.`id_shop` = ' . (int) $idShop
        );
        $out = [];
        foreach ((array) $rows as $row) {
            $key = $row['module'] . '/' . $row['name'];
            if (!isset($out[$key])) {
                $out[$key] = [
                    'id' => (int) $row['id_bk_maildesigner_template'],
                    'mode' => $row['mode'],
                    'active' => (bool) $row['active'],
                    'date_upd' => $row['date_upd'],
                    'langs' => [],
                ];
            }
            if ($row['id_lang']) {
                $out[$key]['langs'][(int) $row['id_lang']] = [
                    'subject' => (string) $row['subject'],
                    'has_blocks' => (int) $row['blocks_len'] > 2,
                ];
            }
        }

        return $out;
    }

    /**
     * @param int $idLang
     *
     * @return string
     */
    public function subjectFor($idLang)
    {
        if (is_array($this->subject)) {
            return isset($this->subject[$idLang]) ? (string) $this->subject[$idLang] : '';
        }

        return (string) $this->subject;
    }

    /**
     * Bloques del idioma pedido; sin contenido propio, los del idioma de referencia.
     *
     * @param int $idLang
     *
     * @return array [bloques, id_lang del que salen]
     */
    public function blocksFor($idLang)
    {
        $own = $this->decodeBlocks($idLang);
        if (!empty($own)) {
            return [$own, (int) $idLang];
        }
        $ref = BkMailDesignerConfig::getRefLang();
        if ($ref !== (int) $idLang) {
            $inherited = $this->decodeBlocks($ref);
            if (!empty($inherited)) {
                return [$inherited, $ref];
            }
        }

        return [[], (int) $idLang];
    }

    /**
     * @param int $idLang
     *
     * @return array
     */
    public function decodeBlocks($idLang)
    {
        $raw = is_array($this->blocks)
            ? (isset($this->blocks[$idLang]) ? $this->blocks[$idLang] : '')
            : $this->blocks;
        $list = json_decode((string) $raw, true);

        return is_array($list) ? $list : [];
    }

    public static function installTable()
    {
        $db = Db::getInstance();
        $ok = $db->execute(
            'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'bk_maildesigner_template` (
                `id_bk_maildesigner_template` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `id_shop` INT UNSIGNED NOT NULL,
                `module` VARCHAR(64) NOT NULL DEFAULT \'\',
                `name` VARCHAR(64) NOT NULL,
                `mode` VARCHAR(16) NOT NULL DEFAULT \'wrapped\',
                `active` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
                `date_add` DATETIME NOT NULL,
                `date_upd` DATETIME NOT NULL,
                PRIMARY KEY (`id_bk_maildesigner_template`),
                UNIQUE KEY `tpl` (`id_shop`, `module`, `name`)
            ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4'
        );
        $ok = $ok && $db->execute(
            'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'bk_maildesigner_template_lang` (
                `id_bk_maildesigner_template` INT UNSIGNED NOT NULL,
                `id_lang` INT UNSIGNED NOT NULL,
                `subject` VARCHAR(255) NOT NULL DEFAULT \'\',
                `blocks` MEDIUMTEXT,
                PRIMARY KEY (`id_bk_maildesigner_template`, `id_lang`)
            ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4'
        );

        return $ok;
    }

    public static function uninstallTable()
    {
        $db = Db::getInstance();

        return $db->execute('DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'bk_maildesigner_template_lang`')
            && $db->execute('DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'bk_maildesigner_template`');
    }
}
