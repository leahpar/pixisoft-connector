<?php


//if (!class_exists('WC_Gls_label')) {
//    require_once ( GLS_PLUGIN_PATH . 'classes/class-gls-label.php' );
//}

class Tracking_Service
{

    public static function addTracking(
        WC_Order      $order,
        WC_Order_Item $shipping,
        string        $tracking
    ) {
        $method = $shipping->get_method_id();

        // Chronopost
        if (self::isChronopost($method)) {
            $parcel = new stdClass();
            $parcel->skybillNumber = $tracking;
            $parcel->imported = true;
            // plugins/chronopost_1.2.3_for_woocommerce_3.x/includes/class-chronopost-order.php
            WC_Chronopost_Order::add_tracking_numbers($order, [$parcel]);
        }

        // GLS
        elseif (self::isGls($method)) {
            throw new Exception("Méthode '$method' désactivée");
            // plugins/woocommerce-gls/classes/class-gls-label.php
            // plugins/woocommerce-gls/classes/class-gls-admin-order-label.php:856
            $gls_product = WC_Gls::get_gls_product($method, "FR"); // = 18
            $glsLabel = new WC_Gls_label();
            $glsLabel->create(array(
                'order_id' => $order->get_id(),
                'shipping_number' => $tracking,
                //'weight' => 0,
                'gls_product' => $gls_product,
                //'delivery_date' => TODO ?,
                'reference1' => $order->get_id(),
                //'reference2' => '',
            ));
        }

        else {
            throw new Exception("Méthode de livraison '$method' inconnue");
        }
    }

    public static function hasTracking(
        WC_Order      $order,
        WC_Order_Item $shipping,
        string        $tracking
    ) {
        $method = $shipping->get_method_id();

        // Chronopost
        if (self::isChronopost($method)) {
            $meta = get_post_meta($order->get_id(), '_shipment_datas');
            $metaRaw = json_encode($meta);
            return strpos($metaRaw, $tracking) !== false;
        }
        // GLS
        elseif (self::isGls($method)) {
            global $wpdb;
            $row = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}woocommerce_gls_label WHERE shipping_number=%s",
                $tracking));
            return $row !== null;
        }
        else {
            throw new Exception("Méthode de livraison '$method' inconnue");
        }
    }


    public static function isChronopost(string $method)
    {
        return substr($method, 0, 6) == 'chrono' || $method == 'local_pickup';
    }

    public static function isGls(string $method)
    {
        return substr($method, 0, 3) == 'gls';

}








}
