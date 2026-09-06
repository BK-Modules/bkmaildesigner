<?php
/**
 * BK Modules - Estándar de reporte de instalación (revisión 1)
 *
 * Fichero compartido, idéntico en todos los módulos bk*. Avisa a bkmodules.com de que
 * el módulo se ha instalado, actualizado o desinstalado en una tienda, enviando solo el
 * dominio y las versiones en juego.
 *
 * CONTRATO CONGELADO. La versión vive en el namespace, no en el nombre de la clase: dos
 * módulos que traigan este mismo fichero definen la misma clase y gana la que cargue
 * primero el autoloader, así que las dos copias tienen que ser intercambiables. De ahí
 * las tres reglas del estándar:
 *
 *   1. Los ficheros de un namespace V* no se editan nunca una vez publicados.
 *   2. Un cambio que rompa la firma abre V2 en un directorio nuevo; V1 se queda como está.
 *   3. La clase no depende de nada del módulo que la trae: ni config, ni logger, ni clases
 *      hermanas. Solo PrestaShop y cURL.
 *
 * Uso desde cualquier módulo bk*, en install(), uninstall() y en cada script de upgrade:
 *
 *     \BkModules\Registry\V1\InstallReporter::report($this->name, $this->version, 'install');
 */

namespace BkModules\Registry\V1;

use Configuration;
use Tools;

final class InstallReporter
{
    /** Revisión del contrato; solo para diagnóstico, no cambia el comportamiento */
    const REVISION = 1;

    /**
     * Endpoint público de bkmodules.com.
     *
     * Lleva `ajax=1` en la propia URL porque una petición marcada como ajax no pasa por
     * las redirecciones de idioma y país del front: el aviso tiene que llegar de una sola
     * petición, sin saltos que se lleven por delante el cuerpo del POST.
     */
    const ENDPOINT = 'https://bkmodules.com/module/bkmodules/registry?ajax=1';

    /** El aviso ocurre durante una instalación: se espera poco y se sigue */
    const CONNECT_TIMEOUT = 2;
    const TIMEOUT = 4;

    /**
     * Avisa a bkmodules.com. No devuelve nada, no imprime nada y no lanza nada:
     * una instalación nunca puede fallar por esto.
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

            $ch = curl_init(self::ENDPOINT);
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
}
