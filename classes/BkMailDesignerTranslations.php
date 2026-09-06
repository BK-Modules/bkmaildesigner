<?php
/**
 * BK Mail Designer — acceso al catálogo de traducción propio del módulo.
 *
 * `ensure()` deja el dominio del módulo en el traductor en curso. Desde PrestaShop 1.7.6 el
 * traductor ya carga los XLF de cada módulo; antes solo miraba `app/Resources` y el tema, así que
 * en una tienda cuyo idioma no sea el de las cadenas origen —inglés— la página y la pantalla de
 * configuración salían sin traducir. Se comprueba el catálogo, no la versión de PrestaShop: donde
 * el core ya trae el dominio, no hace nada. Los mensajes se añaden al catálogo ya montado, sin
 * registrar recursos ni tocar la caché de traducciones: reconstruir el catálogo desde el módulo
 * dejaría fuera las cadenas del core.
 *
 * `trans()` traduce a un idioma concreto leyendo el XLF, al margen del traductor en curso. Es lo
 * que necesitan los correos, que se redactan en el idioma del destinatario y no en el del lado
 * —front o back office— desde el que se envían.
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class BkMailDesignerTranslations
{
    const DOMAIN_ADMIN = 'ModulesBkmaildesignerAdmin';
    const DOMAIN_SHOP = 'ModulesBkmaildesignerShop';

    /** @var array Dominios ya resueltos en esta petición */
    private static $done = [];

    /** @var array Catálogos ya leídos de disco, por dominio y locale */
    private static $catalogues = [];

    /**
     * Deja en el catálogo el dominio que corresponde al contexto: el de administración en el
     * back office y el de tienda en el front, igual que reparte el propio PrestaShop.
     */
    public static function ensure()
    {
        $domain = defined('_PS_ADMIN_DIR_') ? self::DOMAIN_ADMIN : self::DOMAIN_SHOP;

        // La marca se pone antes de pedir el traductor: montarlo puede instanciar módulos y
        // volver aquí, y sin la marca la llamada se perseguiría a sí misma.
        if (isset(self::$done[$domain])) {
            return;
        }
        self::$done[$domain] = true;

        $context = Context::getContext();
        if (!is_object($context) || !is_object($context->language) || !$context->language->locale) {
            unset(self::$done[$domain]);

            return;
        }

        $locale = $context->language->locale;
        $catalogue = $context->getTranslator()->getCatalogue($locale);
        if ($catalogue->all($domain)) {
            return;
        }

        $messages = self::load($domain, $locale);
        if ($messages) {
            $catalogue->add($messages, $domain);
        }
    }

    /**
     * Traduce con el catálogo del módulo, sin pasar por el traductor en curso.
     *
     * Un correo se redacta en el idioma de su destinatario, que no es el del front ni el del
     * back office desde el que se dispara, y el traductor del contexto solo trae el dominio de
     * su lado. Aquí se lee el XLF del idioma pedido y punto.
     *
     * @param string $domain
     * @param string $locale
     * @param string $string Cadena origen en inglés
     * @param array  $params Sustituciones tipo ['%id%' => 7]
     *
     * @return string
     */
    public static function trans($domain, $locale, $string, array $params = [])
    {
        $messages = self::load($domain, $locale);
        $translated = isset($messages[$string]) ? $messages[$string] : $string;

        return $params ? strtr($translated, $params) : $translated;
    }

    /**
     * Pares origen → traducción del XLF del dominio.
     *
     * @param string $domain
     * @param string $locale
     *
     * @return array
     */
    private static function load($domain, $locale)
    {
        $key = $domain . '|' . $locale;
        if (isset(self::$catalogues[$key])) {
            return self::$catalogues[$key];
        }
        self::$catalogues[$key] = [];

        $file = _PS_MODULE_DIR_ . 'bkmaildesigner/translations/' . $locale . '/' . $domain . '.' . $locale . '.xlf';
        if (!is_file($file)) {
            return self::$catalogues[$key];
        }

        $xml = @simplexml_load_file($file);
        if (!$xml || !isset($xml->file->body)) {
            return self::$catalogues[$key];
        }

        $messages = [];
        foreach ($xml->file->body->{'trans-unit'} as $unit) {
            $source = (string) $unit->source;
            $target = (string) $unit->target;
            if ($source !== '' && $target !== '') {
                $messages[$source] = $target;
            }
        }
        self::$catalogues[$key] = $messages;

        return $messages;
    }
}
