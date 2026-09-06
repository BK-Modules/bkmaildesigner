<?php
/**
 * Traza del módulo en fichero propio. Es la única vía de logging: nada de error_log ni de
 * PrestaShopLogger, que mezclarían estas líneas con las del núcleo.
 *
 * @author BK Modules
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class BkMailDesignerLogger
{
    const FILE = 'bkmaildesigner.log';
    /** Tamaño a partir del cual el fichero se rota */
    const MAX_BYTES = 1048576;

    public static function error($message)
    {
        self::write('error', $message);
    }

    public static function warning($message)
    {
        self::write('warning', $message);
    }

    public static function confirmation($message)
    {
        self::write('confirmation', $message);
    }

    /**
     * Solo escribe con la traza encendida: en marcha normal el fichero no crece.
     */
    public static function debug($message)
    {
        if (BkMailDesignerConfig::isOn(BkMailDesignerConfig::DEBUG)) {
            self::write('debug', $message);
        }
    }

    /**
     * Últimas líneas del fichero, para el visor de la pantalla de ajustes.
     *
     * @param int $lines
     *
     * @return string
     */
    public static function tail($lines = 40)
    {
        $file = self::path();
        if (!is_file($file)) {
            return '';
        }
        $all = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        return implode(PHP_EOL, array_slice((array) $all, -$lines));
    }

    private static function path()
    {
        return _PS_MODULE_DIR_ . 'bkmaildesigner/log/' . self::FILE;
    }

    private static function write($level, $message)
    {
        $dir = dirname(self::path()) . '/';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        $file = self::path();
        if (is_file($file) && filesize($file) > self::MAX_BYTES) {
            @rename($file, $file . '.1');
        }

        $line = sprintf('[%s] %s: %s%s', date('Y-m-d H:i:s'), strtoupper($level), $message, PHP_EOL);
        @file_put_contents($file, $line, FILE_APPEND);
    }
}
