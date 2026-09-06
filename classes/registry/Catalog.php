<?php
/**
 * BK Modules - Catálogo remoto de módulos y artículos (revisión 1)
 *
 * Fichero compartido, idéntico en todos los módulos bk*. Trae de bkmodules.com los módulos
 * a la venta y los últimos artículos del blog para pintarlos en el back office del cliente,
 * con caché en disco para no salir a la red en cada carga de página.
 *
 * Se rige por el mismo contrato congelado que InstallReporter: los ficheros de un
 * namespace V* no se editan una vez publicados y la clase no depende de nada del módulo
 * que la trae — quien llama pasa la ruta de la caché, el idioma y su propio nombre.
 */

namespace BkModules\Registry\V1;

final class Catalog
{
    /** Revisión del contrato; solo para diagnóstico */
    const REVISION = 1;

    /**
     * Endpoint público de bkmodules.com. `ajax=1` evita las redirecciones de idioma del
     * front y el prefijo bk_ mantiene los parámetros fuera de los nombres reservados del
     * despachador de PrestaShop.
     */
    const ENDPOINT = 'https://bkmodules.com/module/bkmodules/registry?ajax=1&bk_action=catalog';

    /** Un día: el catálogo cambia con cada módulo o artículo nuevo, no con cada visita */
    const TTL = 86400;

    const CONNECT_TIMEOUT = 2;
    const TIMEOUT = 4;

    /**
     * Devuelve el contenido, de la caché si está fresca y de la red si no.
     *
     * Ante cualquier problema devuelve las dos listas vacías para que quien llama pinte su
     * propio respaldo: el back office nunca se queda a medias por esto.
     *
     * @param string $cacheFile  Ruta del fichero de caché, propia de cada módulo
     * @param string $isoCode    Idioma en el que se quiere el contenido
     * @param string $moduleName Módulo que pregunta; no se le ofrece a sí mismo
     * @param int    $ttl        Segundos de vida de la caché
     *
     * @return array ['catalog' => [...], 'posts' => [...], 'links' => [...]]
     */
    public static function fetch($cacheFile, $isoCode = 'en', $moduleName = '', $ttl = self::TTL)
    {
        $cached = self::readCache($cacheFile, $ttl);

        if ($cached !== null) {
            return $cached;
        }

        $content = self::download($isoCode, $moduleName);

        if ($content === null) {
            // Sin red se sirve la caché aunque esté caducada; mejor vieja que nada.
            $stale = self::readCache($cacheFile, 0);

            return $stale === null ? ['catalog' => [], 'posts' => [], 'links' => []] : $stale;
        }

        self::writeCache($cacheFile, $content);

        return $content;
    }

    /**
     * @param string $cacheFile
     * @param int    $ttl
     *
     * @return array|null Null si no hay caché utilizable
     */
    private static function readCache($cacheFile, $ttl)
    {
        if (!is_file($cacheFile)) {
            return null;
        }

        if ($ttl > 0 && (time() - filemtime($cacheFile)) > $ttl) {
            return null;
        }

        $content = @file_get_contents($cacheFile);

        if ($content === false) {
            return null;
        }

        $decoded = json_decode($content, true);

        if (!is_array($decoded) || !isset($decoded['catalog']) || !isset($decoded['posts'])) {
            return null;
        }

        if (!isset($decoded['links'])) {
            $decoded['links'] = [];
        }

        return $decoded;
    }

    /**
     * @param string $cacheFile
     * @param array  $content
     */
    private static function writeCache($cacheFile, array $content)
    {
        $directory = dirname($cacheFile);

        if (!is_dir($directory)) {
            @mkdir($directory, 0755, true);
        }

        @file_put_contents($cacheFile, json_encode($content));
    }

    /**
     * @param string $isoCode
     * @param string $moduleName
     *
     * @return array|null Null si la descarga falla o la respuesta no es la esperada
     */
    private static function download($isoCode, $moduleName)
    {
        if (!function_exists('curl_init')) {
            return null;
        }

        $url = self::ENDPOINT
            . '&bk_lang=' . urlencode($isoCode)
            . '&bk_module=' . urlencode($moduleName);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, self::CONNECT_TIMEOUT);
        curl_setopt($ch, CURLOPT_TIMEOUT, self::TIMEOUT);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 2);
        curl_setopt($ch, CURLOPT_USERAGENT, 'BkModulesRegistry/' . self::REVISION);
        $response = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($status !== 200 || !$response) {
            return null;
        }

        $decoded = json_decode($response, true);

        if (!is_array($decoded) || empty($decoded['success'])) {
            return null;
        }

        return [
            'catalog' => isset($decoded['catalog']) && is_array($decoded['catalog']) ? $decoded['catalog'] : [],
            'posts' => isset($decoded['posts']) && is_array($decoded['posts']) ? $decoded['posts'] : [],
            'links' => isset($decoded['links']) && is_array($decoded['links']) ? $decoded['links'] : [],
        ];
    }
}
