<?php

/*
Plugin Name: Pixisoft Connector
Plugin URI: https://github.com/leahpar/pixisoft-connector
Description: Connecteur WP pour Pixisoft
Version: 0.2
Author: Raphaël Bacco
Author URI: https://github.com/leahpar
License: MIT
*/


/**
 * Paramétrage Plugin
 * https://github.com/jeremyHixon/RationalOptionPages
 */
if (!class_exists('RationalOptionPages')) {
    require_once('RationalOptionPages.php');
}
$pages = [
    'pixisoft-connector' => [
        'page_title' => "Pixisoft",
        'sections' => [
            'general' => [
                'title' => "Champs Pixisoft",
                'fields' => [
                    'owner' => [
                        'title' => "Owner",
                    ],
                    'site' => [
                        'title' => "Site",
                    ],
                ],
            ],
        ],
    ],
];

$px_options_page = new RationalOptionPages($pages);
$px_options = get_option('pixisoft-connector', []);
$disable_action_new_product = false;

function px_get_ftp_dir($flux)
{
    if (!in_array($flux, [
        'articles',
        'stocks',
        'commandes',
        'livraisons',
    ])) {
        throw new Exception();
    }
    //$upload_dir = wp_upload_dir();
    //$dir = $upload_dir['basedir'].'/pixisoft-connector';
    $dir = ABSPATH . '/pixisoft-connector/' . $flux;
    if (!file_exists($dir)) {
        wp_mkdir_p($dir);
    }
    return $dir;
}


add_action('woocommerce_new_product', 'px_woocommerce_new_product');
//add_action('woocommerce_update_product', 'px_woocommerce_new_product');
function px_woocommerce_new_product($product_id)
{
    global $px_options;
    $px_owner = strtoupper($px_options['owner']);

    global $disable_action_new_product;
    if ($disable_action_new_product) return;

    $product = wc_get_product($product_id);

    $dir = px_get_ftp_dir('articles');
    $fname = $dir . "/" . "ART" . $px_owner . date('YmdHis') . ".txt";
    $f = fopen($fname, "w");
    //fputs($f, print_r($product, true));
    fputcsv($f, [
        $px_owner,
        $product->get_sku(),
        $product->get_name(),
    ]);
    fclose($f);
}

add_action('woocommerce_order_status_processing', 'px_woocommerce_order_status_processing');
function px_woocommerce_order_status_processing($order_id)
{
    global $px_options;
    $px_owner = strtoupper($px_options['owner']);
    $px_site = strtoupper($px_options['site']);

    $order = wc_get_order($order_id);
    $dir = px_get_ftp_dir('commandes');
    $fname = $dir . "/" . "PREP" . $px_owner . date('YmdHis') . ".txt";
    $f = fopen($fname, "w");
    //fputs($f, print_r($order, true));

    // Données fixes pour la commande
    $data = array_fill(1, 134, null);
    $data[1]  = $px_owner;
    $data[2]  = $px_site;
    $data[3]  = $order->get_id(); //$order->get_order_key(); intérêt de l'order key ?
    $data[4]  = $order->get_date_paid()->format("Ymd");
    $data[5]  = $order->get_date_paid()->format("Ymd");
    $data[6]  = $order->get_customer_id();
    $data[7]  = $order->get_billing_last_name();
    $data[8]  = $order->get_billing_address_1();
    $data[9]  = $order->get_billing_address_2();
    $data[11] = $order->get_billing_postcode();
    $data[12] = $order->get_billing_city();
    $data[14] = $order->get_billing_country();
    $data[19] = $order->get_customer_id();
    $data[20] = $order->get_shipping_last_name();
    $data[21] = $order->get_shipping_address_1();
    $data[22] = $order->get_shipping_address_2();
    $data[24] = $order->get_shipping_postcode();
    $data[25] = $order->get_shipping_city();
    $data[27] = $order->get_shipping_country();
    //$data[29] = $order->get_billing_phone();
    //$data[31] = $order->get_billing_email();
    $data[32] = null; // TODO: transporteur
    $data[33] = null; // TODO: méthode de transport
    $data[39] = null; // TODO: owner
    $data[44] = null; // TODO: point relais

    // Parcours des lignes de commande
    foreach ($order->get_items('line_item') as $k => $item) {

        //$product = $order->get_product_from_item();//deprecated
        /** @var WC_Product $product */
        $product = $item->get_product();

        $dataLine = $data;
        $dataLine[35] = $k;
        $dataLine[36] = $product->get_sku();
        $dataLine[37] = $item->get_quantity();
        fputcsv($f, $dataLine);
    }

    fclose($f);
}



//add_filter('cron_schedules', 'px_add_cron_interval');
//function px_add_cron_interval($schedules) {
//    $schedules['five_seconds'] = array(
//        'interval' => 5,
//        'display'  => esc_html__( 'Every Five Seconds' ), );
//    return $schedules;
//}
//if (!wp_next_scheduled('px_cron_update_stock')) {
//    wp_schedule_event(time(), 'every_minute', 'px_cron_update_stock');
//}

add_action('px_cron_update_stock', 'px_update_stock');
function px_update_stock()
{
    // On désactive le hook à la création d'un produit
    // Puisque c'est déjà un produit qui vient de Pixisoft
    global $disable_action_new_product;
    $disable_action_new_product = true;

    $dir = px_get_ftp_dir('stocks');
    $files = glob($dir . "/*.txt");

    // Parcours des fichiers présents
    foreach ($files as $file) {
        $f = fopen($file, 'r');

        // 1 ligne par produit
        while (($data = fgetcsv($f, 0, ",")) !== FALSE) {

            //$sku = $data[2];
            //$qte = $data[3];
            list (/* owner */, /* site */, $sku, $qte,) = $data;

            $product_id = wc_get_product_id_by_sku($sku);

            // Récupération produit
            // Ou création si nouveau produit
            if ($product_id) {
                $product = wc_get_product($product_id);
            }
            else {
                $product = wc_get_product_object('simple');
                $product->set_name('Nouveau produit en stock');
                $product->set_sku($sku);
                $product->set_manage_stock(true);
                $product->set_status('draft');
            }

            //echo "<pre>";
            //var_dump($data, $sku, $qte, $product_id, $product);
            //echo "</pre>";

            // Mise à jour du stock
            $product->set_stock_quantity($qte);
            $product->save();
        }
        fclose($f);

        // Suppression du fichier traité
        unlink($file);
    }

}


//echo '<pre>'; print_r( _get_cron_array() ); echo '</pre>';




add_filter('site_transient_update_plugins', 'px_push_update');
function px_push_update($transient)
{

    if (empty($transient->checked)) {
        return $transient;
    }

    // trying to get from cache first, to disable cache comment 10,20,21,22,24
    //if(false == $remote = get_transient('px_upgrade_pixisoft_connector')) {

        // info.json is the file with the actual plugin information on your server
        $remote = wp_remote_get('https://raw.githubusercontent.com/leahpar/pixisoft-connector/master/info.json', [
            'timeout' => 10,
            'headers' => [
                'Accept' => 'application/json'
            ]]
        );

    //    if ( !is_wp_error( $remote ) && isset( $remote['response']['code'] ) && $remote['response']['code'] == 200 && !empty( $remote['body'] ) ) {
    //        set_transient( 'misha_upgrade_YOUR_PLUGIN_SLUG', $remote, 43200 ); // 12 hours cache
    //    }

    //}

    if ($remote) {

        $remote = json_decode($remote['body']);

        $pluginData = get_plugin_data("pixisoft-connector/pixisoft-connector.php", false, false);

        // your installed plugin version should be on the line below! You can obtain it dynamically of course
        if ($remote
            && version_compare($pluginData['Version']??0, $remote->version, '<')
            && version_compare($remote->requires, get_bloginfo('version'), '<')) {
            $res = new stdClass();
            $res->slug = 'pixisoft-connector';
            $res->plugin = 'pixisoft-connector/pixisoft-connector.php';
            $res->new_version = $remote->version;
            $res->tested = $remote->tested;
            $res->package = $remote->download_url;
            $transient->response[$res->plugin] = $res;
            //$transient->checked[$res->plugin] = $remote->version;
        }

    }
    return $transient;
}

//add_action( 'upgrader_process_complete', 'px_after_update', 10, 2);
//function px_after_update($upgrader_object, $options)
//{
//    if ($options['action'] == 'update' && $options['type'] === 'plugin') {
//        // just clean the cache when new plugin version is installed
//        delete_transient('px_upgrade_pixisoft_connector');
//    }
//}
