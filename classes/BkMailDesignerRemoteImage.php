<?php
/**
 * Copia local de las imágenes del catálogo de bkmodules.com.
 *
 * Las imágenes del panel "Más de BK Modules" viven en bkmodules.com, y una pantalla del back
 * office servida desde el dominio de la tienda que las pide es, para el navegador, una petición
 * a otro sitio. Los bloqueadores del lado del cliente —Shields de Brave, extensiones, filtros de
 * anuncios— retienen esas peticiones sin cerrarlas: la imagen no llega, no da error, y la pestaña
 * se queda cargando para siempre.
 *
 * Por eso el módulo se trae la imagen una vez y la sirve desde la propia tienda: mismo origen,
 * nada que bloquear. Si la descarga falla se conserva la URL original, que es mejor que un hueco.
 *
 * @author BK Modules
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class BkMailDesignerRemoteImage
{
    /** Carpeta bajo el directorio de imágenes de la tienda */
    const DIR = 'bkmaildesigner/';

    /** Solo se trae imágenes del hub; ninguna otra URL se descarga */
    const ALLOWED_HOST = 'bkmodules.com';

    /** Extensiones admitidas */
    const EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    /** Una copia sirve mientras el catálogo no cambie de imágenes */
    const TTL = 604800;

    /** Tope de tamaño: las portadas del blog rondan los 100 KB */
    const MAX_BYTES = 2097152;

    const CONNECT_TIMEOUT = 2;
    const TIMEOUT = 5;

    /** Presupuesto de la pasada completa: lo que no dé tiempo se trae en la siguiente carga */
    const BUDGET = 3;

    /**
     * Sustituye las URLs remotas del catálogo por las copias locales.
     *
     * @param array $remote Contenido devuelto por Catalog::fetch()
     *
     * @return array
     */
    public static function localize(array $remote)
    {
        // Traer las imágenes no puede alargar la pantalla: se copian las que quepan en el
        // presupuesto y las demás se quedan con su URL original hasta la siguiente carga.
        $deadline = microtime(true) + self::BUDGET;

        foreach (['catalog', 'posts'] as $section) {
            if (empty($remote[$section]) || !is_array($remote[$section])) {
                continue;
            }

            foreach ($remote[$section] as $index => $item) {
                if (empty($item['image'])) {
                    continue;
                }

                $local = self::cache($item['image'], $deadline);
                if ($local !== '') {
                    $remote[$section][$index]['image'] = $local;
                }
            }
        }

        return $remote;
    }

    /**
     * Devuelve la URL local de una imagen remota, trayéndola si hace falta.
     *
     * @param string $url
     * @param float|null $deadline Momento a partir del cual ya no se descarga nada
     *
     * @return string Cadena vacía si no se ha podido traer
     */
    public static function cache($url, $deadline = null)
    {
        $extension = self::resolveExtension($url);
        if ($extension === '') {
            return '';
        }

        $filename = sha1($url) . '.' . $extension;
        $path = self::directory() . $filename;

        if (file_exists($path) && filemtime($path) > time() - self::TTL) {
            return self::uri() . $filename;
        }

        if ($deadline !== null && microtime(true) > $deadline) {
            return '';
        }

        $body = self::download($url);
        if ($body === '' || strlen($body) > self::MAX_BYTES) {
            return file_exists($path) ? self::uri() . $filename : '';
        }

        if (!self::ensureDirectory() || @file_put_contents($path, $body) === false) {
            return '';
        }

        // Un cuerpo que no es una imagen (una página de error, un aviso del CDN) no se sirve.
        if (@getimagesize($path) === false) {
            @unlink($path);

            return '';
        }

        return self::uri() . $filename;
    }

    /**
     * @param string $url
     *
     * @return string Extensión admitida, o cadena vacía si la URL no vale
     */
    private static function resolveExtension($url)
    {
        $parts = @parse_url($url);

        if (empty($parts['scheme']) || $parts['scheme'] !== 'https' || empty($parts['host']) || empty($parts['path'])) {
            return '';
        }

        $host = strtolower($parts['host']);
        if ($host !== self::ALLOWED_HOST && substr($host, -strlen('.' . self::ALLOWED_HOST)) !== '.' . self::ALLOWED_HOST) {
            return '';
        }

        $extension = strtolower(pathinfo($parts['path'], PATHINFO_EXTENSION));

        return in_array($extension, self::EXTENSIONS) ? $extension : '';
    }

    /**
     * @param string $url
     *
     * @return string Cuerpo de la respuesta, o cadena vacía
     */
    private static function download($url)
    {
        if (!function_exists('curl_init')) {
            return '';
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, self::CONNECT_TIMEOUT);
        curl_setopt($ch, CURLOPT_TIMEOUT, self::TIMEOUT);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 2);
        curl_setopt($ch, CURLOPT_USERAGENT, 'BkVirtualCombinations/' . self::TTL);
        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return ($status === 200 && is_string($body)) ? $body : '';
    }

    /**
     * @return string
     */
    private static function directory()
    {
        return _PS_IMG_DIR_ . self::DIR;
    }

    /**
     * Borra las copias locales. Todo lo que hay en el directorio se puede volver a descargar,
     * así que desinstalar el módulo no deja rastro en `img/`.
     */
    public static function purge()
    {
        $dir = self::directory();
        if (!is_dir($dir)) {
            return;
        }

        foreach ((array) glob($dir . '*') as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }

        @rmdir($dir);
    }

    /**
     * @return string
     */
    private static function uri()
    {
        return _PS_IMG_ . self::DIR;
    }

    /**
     * @return bool
     */
    private static function ensureDirectory()
    {
        $dir = self::directory();

        return is_dir($dir) || @mkdir($dir, 0755, true);
    }
}
