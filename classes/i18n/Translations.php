<?php
/**
 * BK Modules - Traducciones propias del módulo en cualquier versión y en cualquier idioma (revisión 1)
 *
 * Fichero compartido, idéntico en todos los módulos bk* que lo traigan. Lee los XLF del propio
 * módulo (`translations/<locale>/Modules<Nombre><Admin|Shop>.<locale>.xlf`) para cubrir los dos
 * huecos del traductor de PrestaShop:
 *
 *   - ensure(): hasta 1.7.5 el traductor solo mira `app/Resources` y el tema, así que en una tienda
 *     cuyo idioma no sea el de las cadenas origen —inglés— la página y el back office del módulo
 *     salen sin traducir. Se comprueba el catálogo, no la versión de PrestaShop: donde el núcleo ya
 *     trae el dominio del módulo, no hace nada, y lo que la tienda haya traducido en Internacional ›
 *     Traducciones sigue mandando. Los mensajes se añaden al catálogo ya montado, sin registrar
 *     recursos ni tocar la caché de traducciones: reconstruir el catálogo desde el módulo dejaría
 *     fuera las cadenas del núcleo.
 *   - trans(): un correo se redacta en el idioma de su destinatario, que no es el del front ni el
 *     del back office desde el que se envía, y el traductor del contexto solo trae el idioma y el
 *     lado en curso. Aquí se lee el XLF del idioma pedido y punto.
 *
 * Un locale sin XLF propio (de-AT en un módulo que trae de-DE) usa el XLF de su misma lengua: el
 * texto alemán vale en Austria, y el inglés no.
 *
 * CONTRATO CONGELADO, con las tres reglas del estándar de classes/registry/: la versión vive en el
 * namespace; los ficheros de un namespace V* no se editan nunca una vez publicados; un cambio que
 * rompa la firma abre V2 en un directorio nuevo; y la clase no depende de nada del módulo que la
 * trae —quien llama pasa su nombre técnico—. Dos módulos que traigan este fichero definen la misma
 * clase y gana la que cargue primero el autoloader, así que todo lo que guarda va por módulo.
 *
 * Uso desde cualquier módulo bk*:
 *
 *     // en el constructor, después de parent::__construct()
 *     \BkModules\I18n\V1\Translations::ensure($this->name);
 *
 *     // en un correo, en el idioma del destinatario
 *     \BkModules\I18n\V1\Translations::trans('bkguarantee', 'Modules.Bkguarantee.Shop', 'de-DE', 'Software updates');
 */

namespace BkModules\I18n\V1;

use Context;

final class Translations
{
    /** Revisión del contrato; solo para diagnóstico */
    const REVISION = 1;

    /** @var array Dominios ya resueltos en esta petición */
    private static $done = [];

    /** @var array Mensajes ya leídos de disco, por módulo, dominio y locale */
    private static $catalogues = [];

    /**
     * Deja en el catálogo en curso el dominio del módulo que corresponde al contexto: el de
     * administración en el back office y el de tienda en el front, igual que reparte PrestaShop.
     *
     * @param string $moduleName Nombre técnico del módulo (bkguarantee)
     *
     * @return void
     */
    public static function ensure($moduleName)
    {
        $domain = self::domain($moduleName, defined('_PS_ADMIN_DIR_') ? 'Admin' : 'Shop');

        // La marca se pone antes de pedir el traductor: montarlo puede instanciar módulos y volver
        // aquí, y sin la marca la llamada se perseguiría a sí misma.
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

        $messages = self::load($moduleName, $domain, $locale);
        if ($messages) {
            $catalogue->add($messages, $domain);
        }
    }

    /**
     * Traduce a un locale concreto con el XLF del módulo, al margen del traductor en curso.
     *
     * @param string $moduleName Nombre técnico del módulo
     * @param string $domain     Dominio tal como se pasa a trans(): Modules.Bkguarantee.Shop
     * @param string $locale     Locale de PrestaShop: de-DE
     * @param string $string     Cadena origen en inglés
     * @param array  $params     Sustituciones tipo ['%date%' => '31.12.2030']
     *
     * @return string
     */
    public static function trans($moduleName, $domain, $locale, $string, array $params = [])
    {
        $messages = self::load($moduleName, str_replace('.', '', (string) $domain), (string) $locale);
        $translated = isset($messages[$string]) ? $messages[$string] : $string;

        return $params ? strtr($translated, $params) : $translated;
    }

    /**
     * @param string $moduleName
     * @param string $side Admin|Shop
     *
     * @return string Dominio sin puntos, como lo nombran el catálogo y los XLF
     */
    private static function domain($moduleName, $side)
    {
        return 'Modules' . ucfirst((string) $moduleName) . $side;
    }

    /**
     * Pares origen → traducción del XLF del dominio en ese locale, o en otro de su misma lengua.
     *
     * @param string $moduleName
     * @param string $domain
     * @param string $locale
     *
     * @return array
     */
    private static function load($moduleName, $domain, $locale)
    {
        $key = $moduleName . '|' . $domain . '|' . $locale;
        if (isset(self::$catalogues[$key])) {
            return self::$catalogues[$key];
        }
        self::$catalogues[$key] = [];

        $dir = _PS_MODULE_DIR_ . basename((string) $moduleName) . '/translations/';
        $file = $dir . $locale . '/' . $domain . '.' . $locale . '.xlf';
        if (!is_file($file)) {
            $language = strtok($locale, '-_');
            $siblings = $language ? glob($dir . $language . '-*/' . $domain . '.' . $language . '-*.xlf') : [];
            if (empty($siblings)) {
                return self::$catalogues[$key];
            }
            sort($siblings);
            $file = $siblings[0];
        }

        $xml = @simplexml_load_file($file);
        if (!$xml) {
            return self::$catalogues[$key];
        }

        $messages = [];
        foreach ((array) $xml->xpath('//*[local-name()="trans-unit"]') as $unit) {
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
