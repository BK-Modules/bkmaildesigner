<?php
/**
 * Deja la tienda lista para trabajar en el momento de instalar: tema aplicado, cabecera y pie con
 * los enlaces y los textos legales de la propia tienda, y el contenido de serie cargado en todas
 * las plantillas que lo tienen.
 *
 * Nadie configura un módulo de correo desde cero: al abrirlo por primera vez ya tiene que haber
 * un correo presentable que enviar. Lo que se siembra aquí se puede cambiar entero después.
 *
 * @author BK Modules
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class BkMailDesignerSeed
{
    /** Tema con el que arranca una tienda: el más neutro de los que trae el módulo */
    const DEFAULT_THEME = 'clean';

    /**
     * @param int $idShop
     *
     * @return array ['templates' => int, 'designed' => int]
     */
    public static function run($idShop)
    {
        $shop = new Shop((int) $idShop);
        if (!Validate::isLoadedObject($shop)) {
            return ['templates' => 0, 'designed' => 0];
        }

        self::layout($idShop);

        $templates = 0;
        $designed = 0;
        foreach (BkMailDesignerScanner::discover($shop) as $found) {
            $template = BkMailDesignerTemplate::find((int) $idShop, $found['module'], $found['name']);
            $defaults = BkMailDesignerDefaults::forTemplate($found['module'], $found['name']);
            if ($defaults['mode'] === BkMailDesignerConfig::MODE_DESIGNED) {
                BkMailDesignerPresets::apply($template, BkMailDesignerPresets::load($found['module'], $found['name']));
                ++$designed;
            }
            $template->mode = $defaults['mode'];
            $template->active = true;
            if ($template->id ? $template->update() : $template->add()) {
                ++$templates;
            }
        }

        return ['templates' => $templates, 'designed' => $designed];
    }

    /**
     * Tema por defecto, enlaces de cabecera y de pie, y los dos textos del pie con los datos que
     * la propia tienda ya tiene configurados.
     *
     * @param int $idShop
     */
    private static function layout($idShop)
    {
        BkMailDesignerTheme::apply($idShop, self::DEFAULT_THEME, 'en');

        $layout = BkMailDesignerLayout::forShop((int) $idShop);
        $domain = BkMailDesignerTranslations::DOMAIN_SHOP;

        foreach (Language::getLanguages(false) as $language) {
            $idLang = (int) $language['id_lang'];
            $locale = $language['locale'];

            $layout->header_links[$idLang] = json_encode([
                ['label' => BkMailDesignerTranslations::trans($domain, $locale, 'Shop'), 'url' => '{shop_url}'],
                ['label' => BkMailDesignerTranslations::trans($domain, $locale, 'My account'), 'url' => '{my_account_url}'],
                ['label' => BkMailDesignerTranslations::trans($domain, $locale, 'Contact'), 'url' => '{contact_url}'],
            ]);
            // Tres destinos que el módulo sabe resolver en cualquier tienda e idioma
            $layout->footer_links[$idLang] = json_encode([
                ['label' => BkMailDesignerTranslations::trans($domain, $locale, 'My orders'), 'url' => '{history_url}'],
                ['label' => BkMailDesignerTranslations::trans($domain, $locale, 'Track my order'), 'url' => '{guest_tracking_url}'],
                ['label' => BkMailDesignerTranslations::trans($domain, $locale, 'Write to us'), 'url' => '{contact_url}'],
            ]);
            $layout->footer_legal[$idLang] = self::legalLine($idShop, $idLang);
            // A la tienda no se le explica por qué recibe sus propios correos: va vacío
            $layout->footer_reason[$idLang] = BkMailDesignerLayout::encodeReasons([
                BkMailDesignerScanner::AUDIENCE_RECIPIENT => BkMailDesignerTranslations::trans(
                    $domain,
                    $locale,
                    'You receive this email because this address was used at {shop_name}.'
                ),
                BkMailDesignerScanner::AUDIENCE_MERCHANT => '',
            ]);
        }

        $layout->id ? $layout->update() : $layout->add();
    }

    /**
     * Nombre y dirección de la tienda tal como están en Parámetros de la tienda: es lo que el
     * comerciante ya ha escrito una vez y no tiene por qué repetir aquí.
     *
     * @param int $idShop
     *
     * @return string
     */
    private static function legalLine($idShop, $idLang)
    {
        $parts = [];
        foreach (['PS_SHOP_NAME', 'PS_SHOP_ADDR1', 'PS_SHOP_CITY'] as $key) {
            $value = trim((string) Configuration::get($key, null, null, (int) $idShop));
            if ($value !== '') {
                $parts[] = $value;
            }
        }

        // `PS_SHOP_COUNTRY` guarda el **nombre** del país en el idioma que estuviera activo al
        // guardar la dirección de la tienda, y no cambia con el idioma del destinatario: la misma
        // línea saldría «Estados Unidos» en los cinco. El nombre se pide por su identificador.
        $idCountry = (int) Configuration::get('PS_SHOP_COUNTRY_ID', null, null, (int) $idShop);
        $country = $idCountry ? Country::getNameById((int) $idLang, $idCountry) : '';
        if ($country === '' || $country === false) {
            $country = trim((string) Configuration::get('PS_SHOP_COUNTRY', null, null, (int) $idShop));
        }
        if ($country !== '') {
            $parts[] = $country;
        }

        return implode(' · ', $parts);
    }
}
