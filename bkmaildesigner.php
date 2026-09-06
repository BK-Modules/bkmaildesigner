<?php
/**
 * BK Mail Designer — cabecera y pie de marca en todos los correos de la tienda, y un editor de
 * bloques para el cuerpo de los que importan.
 *
 * El módulo actúa en tiempo de envío: Mail::send() lee el fichero de siempre y, antes de
 * sustituir las variables, el módulo cambia ese HTML por el suyo (layout de marca + cuerpo
 * original envuelto, o + bloques diseñados). No escribe en mails/, no depende del Tema de email
 * del back office y al desinstalarlo todo vuelve al núcleo.
 *
 * Invariante: el módulo va el primero en actionEmailAddAfterContent. Lo que otros módulos
 * añadan al cuerpo (bkguarantee, por ejemplo) tiene que caer dentro del diseño, no fuera.
 *
 * @author    BK Modules
 * @copyright Cumsa
 * @license   MIT
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

class BkMailDesigner extends Module
{
    /** Pestaña padre en el menú del back office */
    const TAB_CLASS = 'AdminBkMailDesignerTab';
    /** Controlador de la pantalla del módulo */
    const CONTROLLER = 'AdminBkMailDesigner';

    /**
     * Los dos hooks del correo: el primero identifica la plantilla y el asunto; el segundo cambia
     * el HTML ya cargado del fichero por el diseñado.
     *
     * @var array
     */
    private $hooks = [
        'actionEmailSendBefore',
        'actionEmailAddAfterContent',
        // La ficha del pedido: 'displayAdminOrder' existe desde 1.7 y sigue en 9; 'Side' solo
        // desde 1.7.7, y donde existen los dos se pinta una vez.
        'displayAdminOrder',
        'displayAdminOrderSide',
    ];

    /**
     * Envío en curso, del hook de antes al de contenido: Mail::send() los dispara seguidos en la
     * misma llamada y el segundo no recibe ni la ruta ni las variables.
     *
     * @var array|null
     */
    private static $pending = null;

    public function __construct()
    {
        $this->name = 'bkmaildesigner';
        $this->tab = 'emailing';
        $this->version = '1.0.0';
        $this->author = 'BK Modules';
        $this->need_instance = 0;
        $this->bootstrap = true;
        $this->ps_versions_compliancy = ['min' => '1.7.6.0', 'max' => '9.99.99'];

        parent::__construct();

        BkMailDesignerTranslations::ensure();

        $this->displayName = $this->trans('BK Mail Designer', [], 'Modules.Bkmaildesigner.Admin');
        $this->description = $this->trans(
            'Branded header and footer on every email your shop sends, and a block editor for the ones that matter.',
            [],
            'Modules.Bkmaildesigner.Admin'
        );
    }

    public function isUsingNewTranslationSystem()
    {
        return true;
    }

    public function install()
    {
        if (!parent::install()) {
            return false;
        }

        foreach ($this->hooks as $hook) {
            $this->registerHook($hook);
        }
        $this->moveHooksFirst();

        if (!BkMailDesignerLayout::installTable() || !BkMailDesignerTemplate::installTable()) {
            return false;
        }

        BkMailDesignerConfig::installDefaults();

        if (!BkMailDesignerTabsInstaller::install($this->name)) {
            BkMailDesignerLogger::error('No se pudo crear la pestaña ' . self::TAB_CLASS);
        }

        // La tienda queda lista para trabajar: tema, cabecera, pie y el contenido de serie en
        // todas las plantillas. Abrir el módulo por primera vez y encontrarlo vacío no sirve.
        foreach (Shop::getShops(false) as $shopRow) {
            $seeded = BkMailDesignerSeed::run((int) $shopRow['id_shop']);
            BkMailDesignerLogger::confirmation(
                'Tienda ' . (int) $shopRow['id_shop'] . ': ' . $seeded['templates'] . ' plantillas listas, '
                . $seeded['designed'] . ' con contenido de serie'
            );
        }

        BkMailDesignerLogger::confirmation('Módulo instalado, versión ' . $this->version);

        \BkModules\Registry\V1\InstallReporter::report($this->name, $this->version, 'install');

        return true;
    }

    public function uninstall()
    {
        \BkModules\Registry\V1\InstallReporter::report($this->name, $this->version, 'uninstall');

        BkMailDesignerTabsInstaller::uninstall();
        BkMailDesignerTemplate::uninstallTable();
        BkMailDesignerLayout::uninstallTable();
        BkMailDesignerConfig::uninstallKeys();
        BkMailDesignerLogger::confirmation('Módulo desinstalado');

        return parent::uninstall();
    }

    /**
     * Primera posición en los dos hooks: el diseño se aplica antes de que otro módulo añada nada
     * al cuerpo, y así lo añadido queda dentro del diseño.
     */
    private function moveHooksFirst()
    {
        foreach ($this->hooks as $hookName) {
            $idHook = (int) Hook::getIdByName($hookName);
            if (!$idHook) {
                continue;
            }
            Db::getInstance()->execute(
                'UPDATE `' . _DB_PREFIX_ . 'hook_module` SET `position` = `position` + 1
                 WHERE `id_hook` = ' . $idHook . ' AND `id_module` <> ' . (int) $this->id
            );
            Db::getInstance()->execute(
                'UPDATE `' . _DB_PREFIX_ . 'hook_module` SET `position` = 1
                 WHERE `id_hook` = ' . $idHook . ' AND `id_module` = ' . (int) $this->id
            );
        }
    }

    /**
     * La pantalla del módulo es su controlador: tiene el ancho del back office y no el de la caja
     * de configuración de la lista de módulos.
     */
    public function getContent()
    {
        Tools::redirectAdmin($this->context->link->getAdminLink(self::CONTROLLER));
    }

    /**
     * Identifica la plantilla que va a salir, fija su asunto y guarda lo que el segundo hook
     * necesita. Los parámetros llegan por referencia: escribir sobre $params cambia el correo.
     */
    public function hookActionEmailSendBefore(&$params)
    {
        self::$pending = null;

        if (!BkMailDesignerConfig::isOn(BkMailDesignerConfig::ENABLED) || empty($params['template'])) {
            return true;
        }

        $name = (string) $params['template'];
        if (!preg_match('/^[a-z0-9_.-]+$/i', $name)) {
            return true;
        }
        $module = BkMailDesignerScanner::moduleFromPath(isset($params['templatePath']) ? $params['templatePath'] : '');
        $idShop = !empty($params['idShop']) ? (int) $params['idShop'] : (int) $this->context->shop->id;
        $idLang = !empty($params['idLang']) ? (int) $params['idLang'] : (int) $this->context->language->id;

        $template = BkMailDesignerTemplate::find($idShop, $module, $name);
        if (!$template->id) {
            // Una plantilla que se envía por primera vez entra en el listado con el modo de alta
            $template->add();
            BkMailDesignerLogger::debug('Plantilla nueva descubierta al enviar: ' . ($module !== '' ? $module . '/' : '') . $name);
        }

        if (!$template->active) {
            return true;
        }

        $vars = isset($params['templateVars']) && is_array($params['templateVars']) ? $params['templateVars'] : [];

        $subject = $template->subjectFor($idLang);
        if ($subject !== '') {
            // El asunto se resuelve aquí y no lo toca nadie después: el núcleo añade las variables
            // comunes más tarde y solo al cuerpo, así que un «{shop_name}» en el asunto llegaría
            // con las llaves puestas si no se sustituyera también con ellas.
            $params['subject'] = strtr(
                $subject,
                array_merge(BkMailDesignerVars::globals($idShop, $idLang), $this->scalarVars($vars))
            );
        }

        self::$pending = [
            'template' => $template,
            'id_lang' => $idLang,
            'id_shop' => $idShop,
            'vars' => $vars,
        ];

        return true;
    }

    /**
     * Cambia el HTML leído del fichero por el diseñado. Si el render no da nada (contenido
     * original irreconocible, plantilla diseñada sin bloques), el correo sale como estaba.
     */
    public function hookActionEmailAddAfterContent(&$params)
    {
        if (self::$pending === null || !isset($params['template_html'])) {
            return;
        }
        $pending = self::$pending;
        self::$pending = null;

        /** @var BkMailDesignerTemplate $template */
        $template = $pending['template'];
        if (!isset($params['template']) || (string) $params['template'] !== $template->name) {
            return;
        }

        $out = $this->render($template, $pending['id_lang'], $pending['id_shop'], $pending['vars'], (string) $params['template_html']);
        if ($out === null) {
            BkMailDesignerLogger::debug('Sale sin diseño: ' . $this->label($template) . ' (idioma ' . $pending['id_lang'] . ')');

            return;
        }

        $params['template_html'] = $out['html'];
        if (isset($params['template_txt'])) {
            $params['template_txt'] = $out['txt'];
        }
        BkMailDesignerLogger::debug('Enviado con diseño (' . $out['strategy'] . '): ' . $this->label($template) . ' (idioma ' . $pending['id_lang'] . ')');
    }

    public function hookDisplayAdminOrderSide(array $params)
    {
        return $this->renderOrderPanel($params);
    }

    public function hookDisplayAdminOrder(array $params)
    {
        return $this->renderOrderPanel($params);
    }

    /**
     * Botón para reenviar la confirmación de un pedido desde su propia ficha. Los dos hooks de la
     * ficha conviven en 9, así que se pinta una sola vez por petición.
     *
     * @param array $params
     *
     * @return string
     */
    private function renderOrderPanel(array $params)
    {
        static $painted = false;
        if ($painted || !BkMailDesignerConfig::isOn(BkMailDesignerConfig::ENABLED)) {
            return '';
        }

        $idOrder = 0;
        foreach (['id_order', 'order'] as $key) {
            if (isset($params[$key])) {
                $idOrder = is_object($params[$key]) ? (int) $params[$key]->id : (int) $params[$key];
            }
        }
        if (!$idOrder) {
            $idOrder = (int) Tools::getValue('id_order');
        }
        $order = new Order($idOrder);
        if (!Validate::isLoadedObject($order)) {
            return '';
        }
        $painted = true;

        $customer = new Customer((int) $order->id_customer);
        $language = new Language((int) $order->id_lang);

        $this->context->smarty->assign([
            'bkmd_order' => $idOrder,
            'bkmd_to' => $customer->email,
            'bkmd_lang' => Validate::isLoadedObject($language) ? $language->name : '',
            'bkmd_url' => $this->context->link->getAdminLink(self::CONTROLLER) . '&ajax=1',
        ]);

        return $this->fetch('module:bkmaildesigner/views/templates/hook/order-resend.tpl');
    }

    /**
     * Render compartido por el envío real, la vista previa y el correo de prueba.
     *
     * @param BkMailDesignerTemplate $template
     * @param int                    $idLang
     * @param int                    $idShop
     * @param array                  $vars     {clave} => valor
     * @param string                 $original HTML original de la plantilla (modo envuelto)
     *
     * @return array|null
     */
    public function render(BkMailDesignerTemplate $template, $idLang, $idShop, array $vars, $original)
    {
        $language = new Language((int) $idLang);
        if (!Validate::isLoadedObject($language)) {
            return null;
        }

        try {
            return BkMailDesignerRenderer::render([
                'layout' => BkMailDesignerLayout::forShop((int) $idShop),
                'template' => $template,
                'id_lang' => (int) $idLang,
                'iso' => $language->iso_code,
                'rtl' => (bool) $language->is_rtl,
                'original' => $original,
                'vars' => $vars,
                'labels' => $this->defaultLabels($idLang),
                'shop_name' => Configuration::get('PS_SHOP_NAME', null, null, (int) $idShop),
            ]);
        } catch (\Exception $e) {
            BkMailDesignerLogger::error('Fallo al montar ' . $this->label($template) . ': ' . $e->getMessage());

            return null;
        }
    }

    /**
     * Etiquetas de la tabla de pedido y de las direcciones cuando el bloque no trae las suyas.
     *
     * Se traducen al idioma del destinatario leyendo el catálogo del módulo, no con el traductor
     * del contexto: un correo se redacta en el idioma de quien lo recibe, que no tiene por qué ser
     * el del back office desde el que se dispara.
     *
     * @param int|null $idLang Idioma del correo; sin él, el del contexto
     *
     * @return array
     */
    public function defaultLabels($idLang = null)
    {
        return BkMailDesignerText::orderLabels($idLang ? (int) $idLang : (int) $this->context->language->id);
    }

    /**
     * Traducción accesible desde el controlador: Module::trans() es protected.
     *
     * @param string $id
     * @param array  $parameters
     * @param string $domain
     *
     * @return string
     */
    public function t($id, array $parameters = [], $domain = null)
    {
        return $this->trans($id, $parameters, $domain ?: 'Modules.Bkmaildesigner.Admin');
    }

    /**
     * URL de un asset del módulo firmada con la fecha del fichero: la versión del módulo no
     * cambia al retocar el CSS o el JS, y el navegador serviría la copia vieja.
     *
     * @param string $relative
     *
     * @return string
     */
    public function assetUrl($relative)
    {
        $path = _PS_MODULE_DIR_ . $this->name . '/' . $relative;
        $stamp = file_exists($path) ? filemtime($path) : $this->version;

        return $this->_path . $relative . '?v=' . $stamp;
    }

    private function label(BkMailDesignerTemplate $template)
    {
        return ($template->module !== '' ? $template->module . '/' : '') . $template->name;
    }

    private function scalarVars(array $vars)
    {
        $out = [];
        foreach ($vars as $key => $value) {
            if (is_scalar($value)) {
                $out[(string) $key] = strip_tags((string) $value);
            }
        }

        return $out;
    }
}
