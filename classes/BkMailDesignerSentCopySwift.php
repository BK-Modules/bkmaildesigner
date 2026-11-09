<?php
/**
 * El mensaje MIME tal como sale por SwiftMailer (PrestaShop 1.7.6 a 8.x), para guardarlo en Enviados.
 *
 * En estas versiones Mail::send() no sustituye las variables de la plantilla: deja `{firstname}`, `{shop_logo}`…
 * en el cuerpo y registra en el mailer un Swift_Plugins_DecoratorPlugin que las cambia justo antes de enviar y las
 * devuelve a su sitio justo después. El hook actionMailAlterMessageBeforeSend recibe el mensaje sin decorar y no da
 * acceso al mailer, así que la copia repite esa decoración sobre un clon, con el mismo plugin y las mismas variables.
 *
 * Las variables finales son la variable local `$templateVars` de Mail::send(): actionEmailSendBefore la pasa por
 * referencia y el núcleo la sigue completando después (logo, URLs de la tienda, actionGetExtraMailTemplateVars).
 * watch() guarda esa referencia; capture() la lee ya completa. Invariante: watch() se llama en cada
 * actionEmailSendBefore, antes de cualquier salida anticipada del hook, para que nunca quede la de un envío anterior.
 *
 * Swift fija Date y Message-ID al crear el mensaje (Mail::send() pone su propio Message-ID): el clon los conserva y
 * coinciden con los del enviado. Tras enviar, el transporte SMTP genera otro Message-ID en el original, por eso la copia
 * se serializa del clon tomado antes del envío y no del mensaje después.
 *
 * @author BK Modules
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class BkMailDesignerSentCopySwift
{
    /** @var array|null [0] es una referencia a $templateVars de Mail::send(); el envoltorio deja soltarla sin escribir en ella */
    private static $vars;

    /**
     * Mail::send() envía con SwiftMailer de 1.7.6 a 8.x; en 9 lo hace con Symfony Mailer.
     *
     * @return bool
     */
    public static function isSupported()
    {
        return version_compare(_PS_VERSION_, '1.7.6.0', '>=') && version_compare(_PS_VERSION_, '9.0.0', '<');
    }

    /**
     * Desde actionEmailSendBefore, con los $params que recibe el hook (por referencia).
     *
     * @param array $params
     */
    public static function watch(array &$params)
    {
        self::$vars = isset($params['templateVars']) ? [&$params['templateVars']] : null;
    }

    /**
     * Desde actionMailAlterMessageBeforeSend: el MIME que va a salir, con CRLF, o null si no es un mensaje de Swift.
     *
     * Se trabaja sobre un clon (Swift clona cabeceras y partes en profundidad, con el mismo Content-ID en las imágenes
     * en línea): decorarlo y quitarle Bcc no puede tocar el mensaje que envía el SMTP. Swift genera de nuevo los
     * separadores de las partes anidadas en cada serialización, así que la copia solo difiere del enviado en ellos.
     *
     * @param mixed $message
     *
     * @return string|null
     */
    public static function capture($message)
    {
        if (!$message instanceof Swift_Message) {
            return null;
        }
        $copy = clone $message;
        $to = array_keys((array) $copy->getTo());
        if ($to && self::$vars && is_array(self::$vars[0])) {
            $decorator = new Swift_Plugins_DecoratorPlugin([$to[0] => self::$vars[0]]);
            $decorator->beforeSendPerformed(new Swift_Events_SendEvent(new Swift_NullTransport(), $copy));
        }
        self::$vars = null;
        // El transporte quita Bcc antes de entregar: la copia tampoco lo lleva
        $copy->getHeaders()->removeAll('Bcc');

        return $copy->toString();
    }
}
