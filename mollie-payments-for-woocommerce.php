<?php
/**
 * Plugin Name: Mollie Payments for WooCommerce
 * Plugin URI: https://www.mollie.com
 * Description: Accept payments in WooCommerce with the official Mollie plugin
 * Version: 6.0
 * Author: Mollie
 * Author URI: https://www.mollie.com
 * Requires at least: 3.8
 * Tested up to: 5.6
 * Text Domain: mollie-payments-for-woocommerce
 * Domain Path: /languages
 * License: GPLv2 or later
 * WC requires at least: 2.2.0
 * WC tested up to: 4.8
 */

use Mollie\Api\CompatibilityChecker;

require_once(ABSPATH . 'wp-admin/includes/plugin.php');

define('M4W_FILE', __FILE__);
define('M4W_PLUGIN_DIR', dirname(M4W_FILE));

// Plugin folder URL.
if (!defined('M4W_PLUGIN_URL')) {
    define('M4W_PLUGIN_URL', plugin_dir_url(M4W_FILE));
}

/**
 * Called when plugin is activated
 */
function mollie_wc_plugin_activation_hook()
{
    require_once __DIR__ . '/inc/functions.php';
    require_once __DIR__ . '/src/subscriptions_status_check_functions.php';

    if (!mollie_wc_plugin_autoload()) {
        return;
    }

    if (!mollieIsWooCommerceCompatible()) {
        add_action('admin_notices', 'mollie_wc_plugin_inactive');
        return;
    }

    $status_helper = Mollie_WC_Plugin::getStatusHelper();

    if (!$status_helper->isCompatible()) {
        $title = 'Could not activate plugin ' . Mollie_WC_Plugin::PLUGIN_TITLE;
        $message = '<h1><strong>Could not activate plugin ' . Mollie_WC_Plugin::PLUGIN_TITLE . '</strong></h1><br/>'
            . implode('<br/>', $status_helper->getErrors());

        wp_die($message, $title, array('back_link' => true));
        return;
    }

    mollieDeleteWPTranslationFiles();
}


function mollieWcShowWcDroppingNotice()
{
    if (get_option('mollie-payments-for-woocommerce-wc-drop')) {
        return false;
    }
    $notice = new Mollie_WC_Notice_AdminNotice();
    $message = sprintf(
        esc_html__(
            '%1$sMollie Payments for WooCommerce is dropping WooCommerce support for version 2.x.%2$s Please %3$supdate WooCommerce to version 3.0 or newer &raquo;%4$s. %5$sContact our support team if any questions remain%6$s',
            'mollie-payments-for-woocommerce'
        ),
        '<strong>',
        '</strong>',
        '<a href="' . esc_url(admin_url('plugins.php')) . '">',
        '</a>',
        '<a href="' . esc_url('https://mollie.inpsyde.com/docs/how-to-request-support-via-website-widget/') . '">',
        '</a>'
    );
    if (version_compare(get_option('woocommerce_version'), '3.0', '<')) {
        $notice->addAdminNotice('notice-error is-dismissible', $message);
    }

    update_option('mollie-payments-for-woocommerce-wc-drop', 'yes', true);
    return false;
}


function mollieDeleteWPTranslationFiles()
{
    WP_Filesystem();
    global $wp_filesystem;

    $remote_destination = $wp_filesystem->find_folder(WP_LANG_DIR);
    if (!$wp_filesystem->exists($remote_destination)) {
        return;
    }
    $languageExtensions = [
        'de_DE',
        'de_DE_formal',
        'es_ES',
        'fr_FR',
        'it_IT',
        'nl_BE',
        'nl_NL',
        'nl_NL_formal'
    ];
    $translationExtensions = ['.mo', '.po'];
    $destination = WP_LANG_DIR
        . '/plugins/mollie-payments-for-woocommerce-';
    foreach ($languageExtensions as $languageExtension) {
        foreach ($translationExtensions as $translationExtension) {
            $file = $destination . $languageExtension
                . $translationExtension;
            $wp_filesystem->delete($file, false);
        }
    }
}

function mollieIsWooCommerceCompatible()
{
    $wooCommerceVersion = get_option('woocommerce_version');
    $isWooCommerceVersionCompatible = version_compare(
        $wooCommerceVersion,
        Mollie_WC_Helper_Status::MIN_WOOCOMMERCE_VERSION,
        '>='
    );

    return class_exists('WooCommerce') && $isWooCommerceVersionCompatible;
}

function mollie_wc_plugin_inactive_json_extension()
{
    $nextScheduledTime = wp_next_scheduled('pending_payment_confirmation_check');
    if ($nextScheduledTime) {
        wp_unschedule_event($nextScheduledTime, 'pending_payment_confirmation_check');
    }

    if (!is_admin()) {
        return false;
    }

    echo '<div class="error"><p>';
    echo esc_html__(
        'Mollie Payments for WooCommerce requires the JSON extension for PHP. Enable it in your server or ask your webhoster to enable it for you.',
        'mollie-payments-for-woocommerce'
    );
    echo '</p></div>';

    return false;
}

function mollie_wc_plugin_inactive_php()
{
    $nextScheduledTime = wp_next_scheduled('pending_payment_confirmation_check');
    if ($nextScheduledTime) {
        wp_unschedule_event($nextScheduledTime, 'pending_payment_confirmation_check');
    }

    if (!is_admin()) {
        return false;
    }

    echo '<div class="error"><p>';
    echo sprintf(
        esc_html__(
            'Mollie Payments for WooCommerce 4.0 requires PHP 5.6 or higher. Your PHP version is outdated. Upgrade your PHP version and view %sthis FAQ%s.',
            'mollie-payments-for-woocommerce'
        ),
        '<a href="https://github.com/mollie/WooCommerce/wiki/PHP-&-Mollie-API-v2" target="_blank">',
        '</a>'
    );
    echo '</p></div>';

    return false;
}

function mollie_wc_plugin_inactive()
{
    $nextScheduledTime = wp_next_scheduled('pending_payment_confirmation_check');
    if ($nextScheduledTime) {
        wp_unschedule_event($nextScheduledTime, 'pending_payment_confirmation_check');
    }

    if (!is_admin()) {
        return false;
    }

    if (!is_plugin_active('woocommerce/woocommerce.php')) {
        echo '<div class="error"><p>';
        echo sprintf(
            esc_html__(
                '%1$sMollie Payments for WooCommerce is inactive.%2$s The %3$sWooCommerce plugin%4$s must be active for it to work. Please %5$sinstall & activate WooCommerce &raquo;%6$s',
                'mollie-payments-for-woocommerce'
            ),
            '<strong>',
            '</strong>',
            '<a href="https://wordpress.org/plugins/woocommerce/">',
            '</a>',
            '<a href="' . esc_url(admin_url('plugins.php')) . '">',
            '</a>'
        );
        echo '</p></div>';
        return false;
    }

    if (version_compare(get_option('woocommerce_version'), '2.2', '<')) {
        echo '<div class="error"><p>';
        echo sprintf(
            esc_html__(
                '%1$sMollie Payments for WooCommerce is inactive.%2$s This version requires WooCommerce 2.2 or newer. Please %3$supdate WooCommerce to version 2.2 or newer &raquo;%4$s',
                'mollie-payments-for-woocommerce'
            ),
            '<strong>',
            '</strong>',
            '<a href="' . esc_url(admin_url('plugins.php')) . '">',
            '</a>'
        );
        echo '</p></div>';
        return false;
    }
}

function mollieWcNoticeApiKeyMissing(){
    //if test/live keys are in db return
    $liveKeySet = get_option('mollie-payments-for-woocommerce_live_api_key');
    $testKeySet = get_option('mollie-payments-for-woocommerce_test_api_key');
    $apiKeysSetted = $liveKeySet || $testKeySet;
    if ($apiKeysSetted) {
        return;
    }

    $notice = new Mollie_WC_Notice_AdminNotice();
    $message = sprintf(
        esc_html__(
            '%1$sMollie Payments for WooCommerce: API keys missing%2$s Please%3$s set your API keys here%4$s.',
            'mollie-payments-for-woocommerce'
        ),
        '<strong>',
        '</strong>',
        '<a href="' . esc_url(admin_url('admin.php?page=wc-settings&tab=mollie_settings')) . '">',
        '</a>'
    );

    $notice->addAdminNotice('notice-error is-dismissible', $message);
}

function mollie_wc_plugin_autoload()
{
    $autoloader = __DIR__ . '/vendor/autoload.php';
    if (file_exists($autoloader)) {
        /** @noinspection PhpIncludeInspection */
        require $autoloader;
    }

    return class_exists(Mollie_WC_Plugin::class);
}

$bootstrap = Closure::bind(
    function () {
        add_action(
            'plugins_loaded',
            function () {
                require_once __DIR__ . '/inc/functions.php';
                require_once __DIR__ . '/src/subscriptions_status_check_functions.php';

                if (!mollie_wc_plugin_autoload()) {
                    return;
                }

                if (function_exists('extension_loaded') && !extension_loaded('json')) {
                    add_action('admin_notices', 'mollie_wc_plugin_inactive_json_extension');
                    return;
                }

                if (version_compare(PHP_VERSION, CompatibilityChecker::MIN_PHP_VERSION, '<')) {
                    add_action('admin_notices', 'mollie_wc_plugin_inactive_php');
                    return;
                }

                if (!mollieIsWooCommerceCompatible()) {
                    add_action('admin_notices', 'mollie_wc_plugin_inactive');
                    return;
                }
                mollieWcShowWcDroppingNotice();

                add_action(
                    'init',
                    function () {
                        load_plugin_textdomain('mollie-payments-for-woocommerce', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/');
                        Mollie_WC_Plugin::init();
                    }
                );
                
                add_action( 'core_upgrade_preamble', 'mollieDeleteWPTranslationFiles' );
                add_filter(
                    'site_transient_update_plugins',
                    function ($value) {
                        if (isset($value->translations)) {
                            $i = 0;
                            foreach ($value->translations as $translation) {
                                if ($translation["slug"]
                                    == "mollie-payments-for-woocommerce"
                                ) {
                                    unset($value->translations[$i]);
                                }
                                $i++;
                            }
                        }

                        return $value;
                    }
                );
                mollieWcNoticeApiKeyMissing();
                add_filter(
                    'query_vars',
                    function ($query_vars) {
                        $query_vars[] = 'appleparam';
                        return $query_vars;
                    }
                );
                add_action(
                    'template_include',
                    function ($template) {
                        if (get_query_var('appleparam') == false
                            || get_query_var('appleparam') == '') {
                            return $template;
                        }
                        echo('7B227073704964223A2244394337463730314338433646324336463344363536433039393434453332323030423137364631353245353844393134304331433533414138323436453630222C2276657273696F6E223A312C22637265617465644F6E223A313535373438323935353137362C227369676E6174757265223A22333038303036303932613836343838366637306430313037303261303830333038303032303130313331306633303064303630393630383634383031363530333034303230313035303033303830303630393261383634383836663730643031303730313030303061303830333038323033653633303832303338626130303330323031303230323038363836306636393964396363613730663330306130363038326138363438636533643034303330323330376133313265333032633036303335353034303330633235343137303730366336353230343137303730366336393633363137343639366636653230343936653734363536373732363137343639366636653230343334313230326432303437333333313236333032343036303335353034306230633164343137303730366336353230343336353732373436393636363936333631373436393666366532303431373537343638366637323639373437393331313333303131303630333535303430613063306134313730373036633635323034393665363332653331306233303039303630333535303430363133303235353533333031653137306433313336333033363330333333313338333133363334333035613137306433323331333033363330333233313338333133363334333035613330363233313238333032363036303335353034303330633166363536333633326437333664373032643632373236663662363537323264373336393637366535663535343333343264353334313465343434323466353833313134333031323036303335353034306230633062363934663533323035333739373337343635366437333331313333303131303630333535303430613063306134313730373036633635323034393665363332653331306233303039303630333535303430363133303235353533333035393330313330363037326138363438636533643032303130363038326138363438636533643033303130373033343230303034383233306664616263333963663735653230326335306439396234353132653633376532613930316464366362336530623163643462353236373938663863663465626465383161323561386332316534633333646463653865326139366332663661666131393330333435633465383761343432366365393531623132393561333832303231313330383230323064333034353036303832623036303130353035303730313031303433393330333733303335303630383262303630313035303530373330303138363239363837343734373033613266326636663633373337303265363137303730366336353265363336663664326636663633373337303330333432643631373037303663363536313639363336313333333033323330316430363033353531643065303431363034313430323234333030623961656565643436333139376134613635613239396534323731383231633435333030633036303335353164313330313031666630343032333030303330316630363033353531643233303431383330313638303134323366323439633434663933653465663237653663346636323836633366613262626664326534623330383230313164303630333535316432303034383230313134333038323031313033303832303130633036303932613836343838366637363336343035303133303831666533303831633330363038326230363031303530353037303230323330383162363063383162333532363536633639363136653633363532303666366532303734363836393733323036333635373237343639363636393633363137343635323036323739323036313665373932303730363137323734373932303631373337333735366436353733323036313633363336353730373436313665363336353230366636363230373436383635323037343638363536653230363137303730366336393633363136323663363532303733373436313665363436313732363432303734363537323664373332303631366536343230363336663665363436393734363936663665373332303666363632303735373336353263323036333635373237343639363636393633363137343635323037303666366336393633373932303631366536343230363336353732373436393636363936333631373436393666366532303730373236313633373436393633363532303733373436313734363536643635366537343733326533303336303630383262303630313035303530373032303131363261363837343734373033613266326637373737373732653631373037303663363532653633366636643266363336353732373436393636363936333631373436353631373537343638366637323639373437393266333033343036303335353164316630343264333032623330323961303237613032353836323336383734373437303361326632663633373236633265363137303730366336353265363336663664326636313730373036633635363136393633363133333265363337323663333030653036303335353164306630313031666630343034303330323037383033303066303630393261383634383836663736333634303631643034303230353030333030613036303832613836343863653364303430333032303334393030333034363032323130306461316336336165386265356636346638653131653836353639333762396236396334373262653933656163333233336131363739333665346138643565383330323231303062643561666266383639663363306361323734623266646465346637313731353963623362643731393962326361306666343039646536353961383262323464333038323032656533303832303237356130303330323031303230323038343936643266626633613938646139373330306130363038326138363438636533643034303330323330363733313162333031393036303335353034303330633132343137303730366336353230353236663666373432303433343132303264323034373333333132363330323430363033353530343062306331643431373037303663363532303433363537323734363936363639363336313734363936663665323034313735373436383666373236393734373933313133333031313036303335353034306130633061343137303730366336353230343936653633326533313062333030393036303335353034303631333032353535333330316531373064333133343330333533303336333233333334333633333330356131373064333233393330333533303336333233333334333633333330356133303761333132653330326330363033353530343033306332353431373037303663363532303431373037303663363936333631373436393666366532303439366537343635363737323631373436393666366532303433343132303264323034373333333132363330323430363033353530343062306331643431373037303663363532303433363537323734363936363639363336313734363936663665323034313735373436383666373236393734373933313133333031313036303335353034306130633061343137303730366336353230343936653633326533313062333030393036303335353034303631333032353535333330353933303133303630373261383634386365336430323031303630383261383634386365336430333031303730333432303030346630313731313834313964373634383564353161356532353831303737366538383061326566646537626165346465303864666334623933653133333536643536363562333561653232643039373736306432323465376262613038666437363137636538386362373662623636373062656338653832393834666635343435613338316637333038316634333034363036303832623036303130353035303730313031303433613330333833303336303630383262303630313035303530373330303138363261363837343734373033613266326636663633373337303265363137303730366336353265363336663664326636663633373337303330333432643631373037303663363537323666366637343633363136373333333031643036303335353164306530343136303431343233663234396334346639336534656632376536633466363238366333666132626266643265346233303066303630333535316431333031303166663034303533303033303130316666333031663036303335353164323330343138333031363830313462626230646561313538333338383961613438613939646562656264656261666461636232346162333033373036303335353164316630343330333032653330326361303261613032383836323636383734373437303361326632663633373236633265363137303730366336353265363336663664326636313730373036633635373236663666373436333631363733333265363337323663333030653036303335353164306630313031666630343034303330323031303633303130303630613261383634383836663736333634303630323065303430323035303033303061303630383261383634386365336430343033303230333637303033303634303233303361636637323833353131363939623138366662333563333536636136326266663431376564643930663735346461323865626566313963383135653432623738396638393866373962353939663938643534313064386639646539633266653032333033323264643534343231623061333035373736633564663333383362393036376664313737633263323136643936346663363732363938323132366635346638376137643162393963623962303938393231363130363939306630393932316430303030333138323031386233303832303138373032303130313330383138363330376133313265333032633036303335353034303330633235343137303730366336353230343137303730366336393633363137343639366636653230343936653734363536373732363137343639366636653230343334313230326432303437333333313236333032343036303335353034306230633164343137303730366336353230343336353732373436393636363936333631373436393666366532303431373537343638366637323639373437393331313333303131303630333535303430613063306134313730373036633635323034393665363332653331306233303039303630333535303430363133303235353533303230383638363066363939643963636137306633303064303630393630383634383031363530333034303230313035303061303831393533303138303630393261383634383836663730643031303930333331306230363039326138363438383666373064303130373031333031633036303932613836343838366637306430313039303533313066313730643331333933303335333133303331333033303339333133353561333032613036303932613836343838366637306430313039333433313164333031623330306430363039363038363438303136353033303430323031303530306131306130363038326138363438636533643034303330323330326630363039326138363438383666373064303130393034333132323034323035613437363366643264396534366338346162356331346462383563633833663831303934316536323838306363663138636536376131613630656633356661333030613036303832613836343863653364303430333032303434363330343430323230363436636338323861383361333062353136313731323266633462333532386432373762373937646264333861633064396263643439393864633832303634383032323030366663656534646432316661313165653665353834346561393565643465643034323939636666363333656437623233343461383835613433636431613662303030303030303030303030227D');
                        exit;
                    },
                    PHP_INT_MIN,
                    1
                );
                add_action(
                    'init',
                    function () {
                        add_rewrite_rule(
                            '^.well-known/apple-developer-merchantid-domain-association$',
                            'index.php?appleparam=applepaydirect',
                            'top'
                        );
                    }
                );
            }
        );
    },
    null
);

$bootstrap();

register_activation_hook(M4W_FILE, 'mollie_wc_plugin_activation_hook');
