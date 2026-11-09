<?php
/**
 * Copia en Enviados: cada correo que sale por Mail::send() se guarda, por IMAP, en la carpeta de Enviados del buzón de
 * la tienda, como el mensaje MIME que recibió el cliente (mismas cabeceras, Message-ID, fecha, HTML e imágenes) y
 * marcado como leído. PrestaShop envía por SMTP, y el SMTP no deja copia en ningún buzón salvo que el servidor lo haga
 * por su cuenta (Gmail, Microsoft 365…).
 *
 * Dos pasos dentro del mismo Mail::send(), porque el núcleo no tiene hook después del envío:
 * - capture(), desde actionMailAlterMessageBeforeSend: el mensaje final, convertido ya en MIME. Cómo se obtiene depende
 *   de la librería de envío: Symfony Mailer (PS 9) aquí, en mimeOf(); SwiftMailer (1.7.6 a 8.x) en
 *   BkMailDesignerSentCopySwift::capture(). Lo que sigue es común.
 * - store(), con la señal de que el SMTP aceptó el mensaje: actionObjectMailAddAfter, en todas las versiones. Mail::send()
 *   no tiene hook tras el envío y lo único que corre después de un envío correcto y se puede escuchar es la fila del
 *   registro de correos, que el núcleo escribe solo entonces. Requisito: PS_LOG_EMAILS encendido (la pantalla avisa si
 *   no lo está). En PS 9 el transporte se crea sin despachador de eventos; en 1.7/8 el de Swift no es alcanzable desde
 *   ningún hook. Un envío que falla no llega a store() y su MIME lo reemplaza el siguiente capture().
 *
 * Invariante: nunca dos copias. Antes de guardar se busca en Enviados ese Message-ID (UID SEARCH HEADER); si ya está —el
 * servidor la guarda solo, u otro módulo la guardó— no se guarda otra. Por eso la opción se puede activar donde no hacía
 * falta sin que se note nada más que una búsqueda por envío.
 *
 * Una conexión IMAP por petición: la abre la primera copia (TLS, LOGIN, LIST, SELECT) y se cierra al acabar el script,
 * así un cron que manda cien correos hace un LOGIN y cien búsquedas y APPEND. Un fallo queda en el log, descarta la
 * conexión (la siguiente copia abre otra) y no toca el envío, que ya salió. Socket propio y no la extensión imap de PHP,
 * que falta en muchos alojamientos y desaparece en PHP 8.4.
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class BkMailDesignerSentCopy
{
    /** Hooks que la opción engancha al activarse y suelta al apagarse: apagada no cuesta nada en cada envío */
    const HOOKS = ['actionMailAlterMessageBeforeSend', 'actionObjectMailAddAfter'];

    /** Mail::METHOD_SMTP, que no existe como constante en todas las versiones */
    const METHOD_SMTP = 2;

    const SECURITIES = ['ssl', 'starttls', 'none'];

    /** @var string|null MIME del mensaje a punto de enviarse */
    private static $raw;

    /** @var resource|null */
    private static $socket;

    /** @var string carpeta \Sent tal como la nombra LIST */
    private static $folder;

    /** @var int */
    private static $tag = 0;

    /**
     * Solo con la opción encendida y envío por SMTP: con mail() del sistema o sin envío no hay buzón al que pertenezca
     * el correo.
     *
     * @return bool
     */
    public static function isActive()
    {
        return BkMailDesignerConfig::isOn(BkMailDesignerConfig::SENT_COPY)
            && (int) Configuration::get('PS_MAIL_METHOD') === self::METHOD_SMTP;
    }

    /**
     * @param mixed $message el de actionMailAlterMessageBeforeSend
     */
    public static function capture($message)
    {
        self::$raw = null;
        if (!self::isActive()) {
            return;
        }
        try {
            if ($message instanceof \Symfony\Component\Mime\Email) {
                self::$raw = self::mimeOf($message);
            } else {
                self::$raw = BkMailDesignerSentCopySwift::capture($message);
            }
        } catch (\Exception $e) {
            BkMailDesignerLogger::error('Copia en Enviados: no se pudo leer el mensaje: ' . $e->getMessage());
        }
    }

    /**
     * Una vez por envío: el registro de correos escribe una fila por destinatario.
     */
    public static function store()
    {
        $raw = self::$raw;
        self::$raw = null;
        if ($raw === null || $raw === '') {
            return;
        }
        $id = self::header($raw, 'Message-ID');
        $subject = self::header($raw, 'Subject');
        try {
            self::connect(self::settings());
            if ($id !== '' && self::exists($id)) {
                BkMailDesignerLogger::debug('Copia en Enviados: ya estaba, no se duplica «' . $subject . '» ' . $id);

                return;
            }
            $date = strtotime(self::header($raw, 'Date')) ?: time();
            self::append($raw, $date);
            BkMailDesignerLogger::debug('Copia en Enviados: guardado «' . $subject . '» ' . $id);
        } catch (\Exception $e) {
            self::disconnect();
            BkMailDesignerLogger::error('Copia en Enviados: no se pudo guardar «' . $subject . '»: ' . $e->getMessage());
        }
    }

    /**
     * «Probar conexión»: entra con los datos dados y devuelve el nombre de la carpeta de Enviados.
     *
     * @param array $settings host, port, security, user, password
     *
     * @return string
     *
     * @throws RuntimeException con el motivo, para enseñarlo tal cual
     */
    public static function test(array $settings)
    {
        self::disconnect();
        try {
            self::connect($settings);
            $folder = self::$folder;
        } finally {
            self::disconnect();
        }

        return self::folderName($folder);
    }

    /**
     * Lo guardado, con el usuario y la contraseña del SMTP de la tienda si no se han puesto otros.
     *
     * @return array
     */
    public static function settings()
    {
        $user = (string) Configuration::get(BkMailDesignerConfig::SENT_COPY_USER);

        return [
            'host' => (string) Configuration::get(BkMailDesignerConfig::SENT_COPY_HOST),
            'port' => (int) Configuration::get(BkMailDesignerConfig::SENT_COPY_PORT),
            'security' => (string) Configuration::get(BkMailDesignerConfig::SENT_COPY_SECURITY),
            'user' => $user !== '' ? $user : (string) Configuration::get('PS_MAIL_USER'),
            'password' => $user !== '' ? (string) Configuration::get(BkMailDesignerConfig::SENT_COPY_PASSWD) : (string) Configuration::get('PS_MAIL_PASSWD'),
        ];
    }

    /**
     * MIME de un Email de Symfony tal como lo serializa el transporte.
     *
     * Fija Date y Message-ID, que Symfony generaría al vuelo en cada serialización, para que el enviado y la copia
     * lleven los mismos (y el Message-ID sirva para no duplicar). Serializa una copia profunda: un Email no se serializa
     * dos veces igual —la primera renombra las imágenes en línea a su Content-ID y la segunda ya no las enlaza con su
     * `cid:`—, así que el SMTP serializa el original y la copia este.
     *
     * @return string
     */
    private static function mimeOf(\Symfony\Component\Mime\Email $message)
    {
        $headers = $message->getHeaders();
        if (!$headers->has('Date')) {
            $message->date(new \DateTimeImmutable());
        }
        if (!$headers->has('Message-ID')) {
            $headers->addIdHeader('Message-ID', $message->generateMessageId());
        }

        return unserialize(serialize($message))->toString();
    }

    /**
     * @return bool si la carpeta de Enviados ya tiene un mensaje con ese Message-ID
     */
    private static function exists($messageId)
    {
        foreach (self::command('UID SEARCH HEADER Message-ID ' . self::quote($messageId)) as $line) {
            if (preg_match('/^\* SEARCH \d/i', $line)) {
                return true;
            }
        }

        return false;
    }

    /**
     * APPEND (RFC 3501) con \Seen y la fecha del mensaje.
     *
     * @param string $raw mensaje MIME
     * @param int $date
     */
    private static function append($raw, $date)
    {
        $raw = preg_replace("/\r?\n/", "\r\n", $raw);
        $tag = 'C' . ++self::$tag;
        self::write($tag . ' APPEND ' . self::$folder . ' (\Seen) "' . date('d-M-Y H:i:s O', $date) . '" {' . strlen($raw) . "}\r\n");
        $reply = self::line();
        if ($reply === '' || $reply[0] !== '+') {
            throw new RuntimeException('APPEND rechazado: ' . $reply);
        }
        self::write($raw . "\r\n");
        self::result($tag);
    }

    /**
     * Abre la conexión de la petición si no lo está: entra, localiza la carpeta con el atributo \Sent (RFC 6154) y la
     * selecciona, que es lo que pide la búsqueda.
     */
    private static function connect(array $settings)
    {
        if (self::$socket) {
            return;
        }
        if ($settings['host'] === '' || $settings['user'] === '') {
            throw new RuntimeException('faltan el servidor IMAP o el usuario');
        }
        $security = in_array($settings['security'], self::SECURITIES, true) ? $settings['security'] : 'ssl';
        $port = $settings['port'] > 0 ? $settings['port'] : ($security === 'ssl' ? 993 : 143);
        $scheme = $security === 'ssl' ? 'ssl://' : 'tcp://';
        $socket = @stream_socket_client($scheme . $settings['host'] . ':' . $port, $errno, $error, 10);
        if (!$socket) {
            throw new RuntimeException('sin conexión con ' . $settings['host'] . ':' . $port . ' (' . $error . ')');
        }
        stream_set_timeout($socket, 20);
        self::$socket = $socket;
        register_shutdown_function([self::class, 'disconnect']);
        self::line();
        if ($security === 'starttls') {
            self::command('STARTTLS');
            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException('el servidor no aceptó STARTTLS');
            }
        }
        self::command('LOGIN ' . self::quote($settings['user']) . ' ' . self::quote($settings['password']));
        foreach (self::command('LIST "" "*"') as $line) {
            if (preg_match('/^\* LIST \(([^)]*)\) \S+ (.+)$/i', $line, $m) && preg_match('/\\\\Sent\b/i', $m[1])) {
                self::$folder = $m[2];
                self::command('SELECT ' . self::$folder);

                return;
            }
        }
        throw new RuntimeException('el buzón no tiene ninguna carpeta marcada como Enviados (\Sent)');
    }

    public static function disconnect()
    {
        if (!self::$socket) {
            return;
        }
        @fwrite(self::$socket, 'C' . ++self::$tag . " LOGOUT\r\n");
        @fclose(self::$socket);
        self::$socket = null;
        self::$folder = null;
    }

    /**
     * @return string[] respuestas sin etiquetar
     */
    private static function command($command)
    {
        $tag = 'C' . ++self::$tag;
        self::write($tag . ' ' . $command . "\r\n");

        return self::result($tag);
    }

    /**
     * @return string[] respuestas sin etiquetar hasta la final de $tag, que tiene que ser OK
     */
    private static function result($tag)
    {
        $lines = [];
        while (true) {
            $line = self::line();
            if (strpos($line, $tag . ' ') !== 0) {
                $lines[] = $line;
                continue;
            }
            if (stripos($line, $tag . ' OK') !== 0) {
                throw new RuntimeException(substr($line, strlen($tag) + 1));
            }

            return $lines;
        }
    }

    private static function write($data)
    {
        if (fwrite(self::$socket, $data) === false) {
            throw new RuntimeException('no se pudo escribir en la conexión IMAP');
        }
    }

    private static function line()
    {
        $line = fgets(self::$socket);
        if ($line === false) {
            throw new RuntimeException('el servidor IMAP cerró la conexión o no respondió');
        }

        return rtrim($line, "\r\n");
    }

    /**
     * Valor de una cabecera del MIME, con las líneas de continuación unidas.
     *
     * @return string
     */
    private static function header($raw, $name)
    {
        $end = strpos($raw, "\r\n\r\n");
        $head = $end === false ? $raw : substr($raw, 0, $end);
        if (preg_match('/^' . preg_quote($name, '/') . ':[ \t]*(.*(?:\r?\n[ \t].*)*)/mi', $head, $m)) {
            return trim(preg_replace('/\r?\n[ \t]+/', ' ', $m[1]));
        }

        return '';
    }

    /**
     * Nombre legible de la carpeta: sin comillas y decodificado de UTF-7 modificado (RFC 3501 5.1.3).
     *
     * @return string
     */
    private static function folderName($folder)
    {
        $name = stripcslashes(trim((string) $folder, '"'));

        return preg_replace_callback('/&([^-]*)-/', function ($m) {
            return $m[1] === '' ? '&' : mb_convert_encoding(base64_decode(strtr($m[1], ',', '/')), 'UTF-8', 'UTF-16BE');
        }, $name);
    }

    private static function quote($value)
    {
        return '"' . addcslashes((string) $value, '"\\') . '"';
    }
}
