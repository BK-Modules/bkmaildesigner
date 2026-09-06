<?php
/**
* El modo con el que cada correo viene de fábrica.
 *
 * La regla es la misma que aplicaría una persona mirando la lista: si el módulo trae un texto
 * escrito y traducido para ese correo, se usa; si no lo trae, el texto original se conserva dentro
 * del diseño; y si el cuerpo no se puede leer, el correo se deja como está. Nada de adivinar.
 *
 * @author BK Modules
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class BkMailDesignerDefaults
{
    /** Motivos, para poder explicar en la pantalla por qué le toca ese modo */
    const HAS_PRESET = 'has_preset';
    const NO_PRESET = 'no_preset';
    const UNREADABLE = 'unreadable';

    /**
     * @param string $module
     * @param string $name
     *
     * @return array ['mode' => designed|wrapped|original, 'reason' => ...]
     */
    public static function forTemplate($module, $name)
    {
        if (BkMailDesignerPresets::load($module, $name) !== null) {
            return ['mode' => BkMailDesignerConfig::MODE_DESIGNED, 'reason' => self::HAS_PRESET];
        }

        return ['mode' => BkMailDesignerConfig::MODE_WRAPPED, 'reason' => self::NO_PRESET];
    }

    /**
     * Igual que forTemplate(), pero cayendo a 'original' cuando el cuerpo no se puede extraer y
     * envolverlo no llevaría a ningún sitio.
     *
     * @param string $module
     * @param string $name
     * @param string $strategy Lectura detectada por BkMailDesignerWrapper, si se conoce
     *
     * @return array
     */
    public static function withStrategy($module, $name, $strategy)
    {
        if ($strategy === 'none') {
            return ['mode' => 'original', 'reason' => self::UNREADABLE];
        }

        return self::forTemplate($module, $name);
    }
}
