<?php

if (!class_exists('RationalOptionPages')) {
    require_once('RationalOptionPages.php');
}

class Pixisoft_Connector_Core
{
    private array $px_options;
    private bool $disable_action_new_product = false;

    public function init()
    {
        // Gestion options et page de configuration du plugin
        $this->options();

        // Hook à la création/modification d'un produit
        add_action('woocommerce_new_product', [$this, 'px_woocommerce_new_product']);
        //add_action('woocommerce_update_product', [$this, 'px_woocommerce_new_product']);

        // Hook à la validation (paiement OK) d'une commande
        add_action('woocommerce_order_status_processing', [$this, 'px_woocommerce_order_status_processing']);

        // Hook de mise à jour du stock
        // Déclenché par CRON
        add_action('px_cron_update_stock', [$this, 'px_update_stock']);

        // Hook de mise à jour du plugin
        add_filter('site_transient_update_plugins', [$this, 'px_push_update']);
        add_action('upgrader_process_complete', 'px_after_update', 10, 2);
    }

    /**
     * Retourne le répertoire de dépôt des fichiers FTP
     * @param $flux
     * @return string
     * @throws Exception
     */
    static public function px_get_ftp_dir($flux)
    {
        if (!in_array($flux, [
            'articles',
            'stocks',
            'commandes',
            'livraisons',
        ])) {
            throw new Exception();
        }
        $dir = ABSPATH . '/pixisoft-connector/' . $flux;
        if (!file_exists($dir)) {
            wp_mkdir_p($dir);
        }
        return $dir;
    }

    /**
     * Paramétrage du plugin
     * https://github.com/jeremyHixon/RationalOptionPages
     */
    public function options()
    {
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
        $this->px_options = get_option('pixisoft-connector', []);
    }

    /**
     * HOOK à la création d'un produit
     * Génère le fichier de mise à jour chez Pixisoft
     */
    function px_woocommerce_new_product($product_id)
    {
        // Options
        $px_owner = strtoupper($this->px_options['owner']);

        // Pour pouvoir désactiver la création du fichier
        // (lors de l'ajout d'un produit depuis l'import pixisoft)
        if ($this->disable_action_new_product) return;

        $product = wc_get_product($product_id);

        // Pas d'export du produit s'il n'a pas de SKU
        if (empty($product->get_sku())) return;

        $dir = $this->px_get_ftp_dir('articles');
        $fname = $dir . "/" . "ART" . $px_owner . date('YmdHis') . ".txt";
        $f = fopen($fname, "a");

        fputcsv($f, [
            $px_owner,
            $product->get_sku(),
            $product->get_name(),
        ]);
        fclose($f);
    }

    /**
     * HOOK au paiement d'une commande
     * Génère le fichier de commande pour Pixisoft
     */
    function px_woocommerce_order_status_processing($order_id)
    {
        // Option
        $px_owner = strtoupper($this->px_options['owner']);
        $px_site = strtoupper($this->px_options['site']);

        $order = wc_get_order($order_id);

        $dir = $this->px_get_ftp_dir('commandes');
        $fname = $dir . "/" . "PREP" . $px_owner . date('YmdHis') . ".txt";
        $f = fopen($fname, "a");

        // Données fixes pour la commande
        $data = array_fill(1, 134, null);
        $data[1] = $px_owner;
        $data[2] = $px_site;
        $data[3] = $order->get_id(); //$order->get_order_key(); intérêt de l'order key ?
        $data[4] = $order->get_date_paid()->format("Ymd");
        $data[5] = $order->get_date_paid()->format("Ymd");
        $data[6] = $order->get_customer_id();
        $data[7] = $order->get_billing_last_name();
        $data[8] = $order->get_billing_address_1();
        $data[9] = $order->get_billing_address_2();
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

            /** @var WC_Product $product */
            //$product = $order->get_product_from_item();//deprecated
            $product = $item->get_product();

            $dataLine = $data;
            $dataLine[35] = $k;
            $dataLine[36] = $product->get_sku();
            $dataLine[37] = $item->get_quantity();
            fputcsv($f, $dataLine);
        }

        fclose($f);
    }

    /**
     * HOOK de mise à jour des stocks
     * (déclenché par cron)
     */
    function px_update_stock()
    {
        // On désactive le hook à la création d'un produit
        // Puisque c'est déjà un produit qui vient de Pixisoft
        $this->disable_action_new_product = true;

        $dir = $this->px_get_ftp_dir('stocks');
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
                } else {
                    $product = wc_get_product_object('simple');
                    $product->set_name('Nouveau produit en stock');
                    $product->set_sku($sku);
                    $product->set_status('draft');
                }

                //echo "<pre>";
                //var_dump($data, $sku, $qte, $product_id, $product);
                //echo "</pre>";

                // Mise à jour du stock
                $product->set_manage_stock(true);
                $product->set_stock_quantity($qte);
                $product->save();
            }
            fclose($f);

            // Suppression du fichier traité
            unlink($file);
        }

        //echo '<pre>'; print_r( _get_cron_array() ); echo '</pre>';
    }

    /**
     * Check d'une nouvelle version du plugin
     */

    function px_push_update($transient)
    {
        if (empty($transient->checked)) {
            return $transient;
        }

        // trying to get from cache first, to disable cache comment 10,20,21,22,24
        if (false == $remote = get_transient('px_upgrade_pixisoft_connector')) {

            // info.json is the file with the actual plugin information on your server
            $remote = wp_remote_get(PX_CONNECTOR_JSON_URL, [
                    'timeout' => 10,
                    'headers' => [
                        'Accept' => 'application/json'
                    ]]
            );

            if (!is_wp_error($remote)
                && isset($remote['response']['code'])
                && $remote['response']['code'] == 200
                && !empty($remote['body'])
            ) {
                set_transient('px_upgrade_pixisoft_connector', $remote, 3600);
            }
        }

        // Infos plugin local
        $pluginData = get_plugin_data(__DIR__ . '/../pixisoft-connector.php', false, false);

        if ($remote) {

            $remote = json_decode($remote['body']);

            // your installed plugin version should be on the line below! You can obtain it dynamically of course
            if ($remote
                && version_compare($pluginData['Version'] ?? 0, $remote->version, '<')
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


    function px_after_update($upgrader_object, $options)
    {
        if ($options['action'] == 'update' && $options['type'] === 'plugin') {
            // just clean the cache when new plugin version is installed
            delete_transient('px_upgrade_pixisoft_connector');
        }
    }

}
