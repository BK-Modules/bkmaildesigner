<?php
/**
 * BK Modules - Estándar de reporte de instalación (revisión 2)
 *
 * Fichero compartido, idéntico en todos los módulos bk*. Avisa al registro de instalaciones de
 * bkmodules.com de que el módulo se ha instalado, actualizado o desinstalado en una tienda,
 * enviando solo el dominio y las versiones en juego.
 *
 * Se rige por el contrato congelado del estándar: los ficheros de un namespace V* no se editan
 * una vez publicados y la clase no depende de nada del módulo que la trae, solo de PrestaShop y
 * cURL. Dos módulos que traigan este fichero definen la misma clase, así que las copias tienen
 * que ser idénticas.
 *
 * Frente a la revisión 1, el servidor del registro se puede cambiar: `BK_REGISTRY_SERVER` en la
 * configuración de la tienda (la URL base, `https://pre.bkmodules.com`) manda los avisos a otro
 * bkmodules; sin ella van a bkmodules.com. Las tiendas de prueba lo apuntan a pre para no anotar
 * instalaciones falsas en el registro de producción.
 *
 * Uso desde cualquier módulo bk*, en install(), uninstall() y en cada script de upgrade:
 *
 *     \BkModules\Registry\V2\InstallReporter::report($this->name, $this->version, 'install');
 */

namespace BkModules\Registry\V2;

use Configuration;
use Tools;

final class InstallReporter
{
    /** Revisión del contrato; solo para diagnóstico, no cambia el comportamiento */
    const REVISION = 2;

    /** Servidor del registro cuando la tienda no pone otro en `BK_REGISTRY_SERVER` */
    const SERVER = 'https://bkmodules.com';

    /** Clave de configuración con la URL base de otro servidor del registro */
    const SERVER_KEY = 'BK_REGISTRY_SERVER';

    /**
     * Ruta del endpoint en el servidor. Lleva `ajax=1` porque una petición marcada como ajax no
     * pasa por las redirecciones de idioma y país del front: el aviso tiene que llegar de una sola
     * petición, sin saltos que se lleven por delante el cuerpo del POST.
     */
    const PATH = '/module/bkmodules/registry?ajax=1';

    /** El aviso ocurre durante una instalación: se espera poco y se sigue */
    const CONNECT_TIMEOUT = 2;
    const TIMEOUT = 4;

    /**
     * Avisa al registro. No devuelve nada, no imprime nada y no lanza nada: una instalación nunca
     * puede fallar por esto.
     *
     * @param string $moduleName    Nombre técnico del módulo
     * @param string $moduleVersion Versión que queda instalada
     * @param string $event         install|upgrade|uninstall
     *
     * @return void
     */
    public static function report($moduleName, $moduleVersion, $event)
    {
        try {
            if (!function_exists('curl_init')) {
                return;
            }

            $domain = Configuration::get('PS_SHOP_DOMAIN_SSL');

            if (!$domain) {
                $domain = Configuration::get('PS_SHOP_DOMAIN');
            }

            if (!$domain) {
                return;
            }

            // Todos los campos van con prefijo bk_: `module`, `controller` o `action` son
            // parámetros reservados del despachador de PrestaShop y, al leerse antes el
            // POST que la URL, un campo con ese nombre le haría creer que la petición va
            // a otro sitio.
            $payload = [
                'bk_module' => (string) $moduleName,
                'bk_version' => (string) $moduleVersion,
                'bk_event' => (string) $event,
                'bk_domain' => (string) $domain,
                'bk_shop_url' => Tools::getShopDomainSsl(true),
                'bk_ps_version' => _PS_VERSION_,
                'bk_php_version' => PHP_VERSION,
            ];

            $ch = curl_init(self::endpoint());
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($payload));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, self::CONNECT_TIMEOUT);
            curl_setopt($ch, CURLOPT_TIMEOUT, self::TIMEOUT);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_MAXREDIRS, 2);
            curl_setopt($ch, CURLOPT_USERAGENT, 'BkModulesRegistry/' . self::REVISION);
            curl_exec($ch);
            curl_close($ch);
        } catch (\Exception $e) {
            // Silencioso por diseño: el aviso es informativo y nunca bloquea al módulo.
        } catch (\Throwable $e) {
            // Idem para errores de PHP 7+.
        }
    }

    /**
     * URL del endpoint: la del servidor de `BK_REGISTRY_SERVER` si es una URL http(s), y si no la
     * de bkmodules.com.
     *
     * @return string
     */
    public static function endpoint()
    {
        $server = rtrim((string) Configuration::getGlobalValue(self::SERVER_KEY), '/');

        return (preg_match('#^https?://[^/?\#]+(/[^?\#]*)?$#i', $server) ? $server : self::SERVER) . self::PATH;
    }
}
