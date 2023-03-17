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
        //add_action('woocommerce_new_product', [$this, 'px_woocommerce_new_product']);
        //add_action('woocommerce_update_product', [$this, 'px_woocommerce_new_product']);
        add_action('save_post', [$this, 'px_woocommerce_new_product']);

        // Hook à la validation (paiement OK) d'une commande
        add_action('woocommerce_order_status_processing', [$this, 'px_woocommerce_order_status_processing']);

        // Hook de mise à jour du stock
        // Déclenché par CRON
        add_action('px_cron_update_stock', [$this, 'px_update_stock']);
        add_action('px_cron_update_commandes', [$this, 'px_update_commandes']);

        // Hook de mise à jour du plugin
        add_filter('site_transient_update_plugins', [$this, 'px_push_update']);
        add_action('upgrader_process_complete', 'px_after_update', 10, 2);
    }

    static public function log($message)
    {
        $dir = self::px_get_ftp_dir('logs');
        $fname = $dir . "/" . date('Ymd') . ".log";
        $f = fopen($fname, "a");

        $date = date('[Y-m-d H:i:s]');
        fwrite($f, "$date $message\n");
        fclose($f);
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
            'archives',
            'articles',
            'stocks',
            'commandes',
            'livraisons',
            'logs',
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
                    'rouen-adresse' => [
                        'title' => "La Porte Ouverte - Rouen",
                        'fields' => [
                            'rouen_adresse1'   => [ 'id' => 'rouen_adresse1', 'title' => 'Nom'],
                            'rouen_adresse2'   => [ 'id' => 'rouen_adresse2', 'title' => 'Adresse'],
                            'rouen_codepostal' => [ 'id' => 'rouen_codepostal', 'title' => 'Code postal'],
                            'rouen_ville'      => [ 'id' => 'rouen_ville', 'title' => 'Ville'],
                        ]
                    ],
                    'dieppe-adresse' => [
                        'title' => "La Porte Ouverte - Dieppe",
                        'fields' => [
                            'dieppe_adresse1'   => [ 'id' => 'dieppe_adresse1', 'title' => 'Nom'],
                            'dieppe_adresse2'   => [ 'id' => 'dieppe_adresse2', 'title' => 'Adresse'],
                            'dieppe_codepostal' => [ 'id' => 'dieppe_codepostal', 'title' => 'Code postal'],
                            'dieppe_ville'      => [ 'id' => 'dieppe_ville', 'title' => 'Ville'],
                        ]
                    ],
                    'cergy-adresse' => [
                        'title' => "La Porte Ouverte - Cergy",
                        'fields' => [
                            'cergy_adresse1'   => [ 'id' => 'cergy_adresse1', 'title' => 'Nom'],
                            'cergy_adresse2'   => [ 'id' => 'cergy_adresse2', 'title' => 'Adresse'],
                            'cergy_codepostal' => [ 'id' => 'cergy_codepostal', 'title' => 'Code postal'],
                            'cergy_ville'      => [ 'id' => 'cergy_ville', 'title' => 'Ville'],
                        ]
                    ],
                    'voisins-adresse' => [
                        'title' => "La Porte Ouverte - Voisins",
                        'fields' => [
                            'voisins_adresse1'   => [ 'id' => 'voisins_adresse1', 'title' => 'Nom'],
                            'voisins_adresse2'   => [ 'id' => 'voisins_adresse2', 'title' => 'Adresse'],
                            'voisins_codepostal' => [ 'id' => 'voisins_codepostal', 'title' => 'Code postal'],
                            'voisins_ville'      => [ 'id' => 'voisins_ville', 'title' => 'Ville'],
                        ]
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

        // Pas un produit WooCommerce
        if (!$product) return;

        // Pas d'export du produit s'il n'a pas de SKU
        if (empty($product->get_sku())) return;

        // Pas d'export du produit s'il n'est pas publié
        if ($product->get_status() != 'publish') return;

        Pixisoft_Connector_Core::log("Update produit $product_id");

        $dir = $this->px_get_ftp_dir('articles');
        $fname = $dir . "/" . "ART" . $px_owner . date('YmdHi00') . ".txt";
        $f = fopen($fname, "a");

        $data = array_fill(1, 108, null);
        $data[1] = $px_owner;
        $data[2] = $product->get_sku();
        $data[3] = $product->get_name();
        $data[12] = 0; // Gestion par numéro de série
        $data[13] = 0; // Gestion par lot
        $data[14] = 1; // Gestion par date de peremption

        fputcsv($f, $data, ';', ' ');
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

        $fname = "PREP" . $px_owner . date('YmdHis') . "_" . $order_id . ".txt";

        Pixisoft_Connector_Core::log("Traitement commande #$order_id");

        // Données fixes pour la commande
        $data = array_fill(1, 134, null);
        $data[1] = $px_owner;
        $data[2] = $px_site;
        $data[3] = $order->get_id(); //$order->get_order_key(); intérêt de l'order key ?
        $data[4] = $order->get_date_paid()->format("Ymd");
        $data[5] = $order->get_date_paid()->format("Ymd");
        $data[6] = $order->get_customer_id();
        $data[7] = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
        $data[8] = $order->get_billing_address_1();
        $data[9] = $order->get_billing_address_2();
        $data[11] = $order->get_billing_postcode();
        $data[12] = $order->get_billing_city();
        $data[14] = $order->get_billing_country();
        $data[19] = $order->get_customer_id();
        $data[20] = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
        $data[21] = $order->get_shipping_address_1();
        $data[22] = $order->get_shipping_address_2();
        $data[24] = $order->get_shipping_postcode();
        $data[25] = $order->get_shipping_city();
        $data[27] = $order->get_shipping_country();
        $data[29] = $order->get_billing_phone();
        $data[31] = $order->get_billing_email();
        $data[39] = $px_owner;

        // Shipping
        foreach ($order->get_items('shipping') as $k => $item) {

            /** @var WC_Order_Item_Shipping $shipping */
            $shipping = $item;

            $IDT = null;
            $IDS = null;
            $IDP = null;

            switch ($shipping->get_method_id()) {
                case "chrono10":
                    $IDT = "CHRONOPOST";
                    $IDS = "CHRONO10";
                    break;
                case "chrono13":
                    $IDT = "CHRONOPOST";
                    $IDS = "CHRONO13";
                    break;
                case "chronorelais":
                    $IDT = "CHRONOPOST";
                    $IDS = "CHRONORELAIS";
                    // Récupération code du point relais
                    $chronopost = $order->get_meta('_shipping_method_chronorelais');
                    $IDP = $chronopost['id'] ?? "ERR";

                    // Cas particulier Chronorelais
                    // Nom du client dans le champ contact (28)
                    $data[28] = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
                    // Nom du point relais dans le champ desstinataire (20)
                    $data[20] = $chronopost['name'] ?? "ERR";
                    $data[22] = null;

                    break;
                case "gls_chezvous":
                case "gls_chezvousplus":
                    $IDT = "GLS";
                    $IDS = "GLS";
                    break;
                case "local_pickup":
                    // BAR :
                    $bars = [
                        4 => "rouen",
                        5 => "dieppe",
                        6 => "cergy",
                        7 => "voisins",
                    ];
                    $barId = $shipping->get_instance_id();
                    $IDT = "EUROP'EXPRESS";
                    $IDS = "BAR " . strtoupper($bars[$barId]);
                    $data[21] = $this->px_options[$bars[$barId].'_adresse1'];
                    $data[22] = $this->px_options[$bars[$barId].'_adresse2'];
                    $data[24] = $this->px_options[$bars[$barId].'_codepostal'];
                    $data[25] = $this->px_options[$bars[$barId].'_ville'];
                    $data[27] = null;
                    break;
                default:
                    $IDT = "XXXXX";
                    $IDS = "XXXXX";
                    break;
            }

            $data[32] = $IDT; // transporteur
            $data[33] = $IDS; // méthode de transport
            $data[44] = $IDP; // point relais

        }

        Pixisoft_Connector_Core::log("Fichier : $fname");
        $dir = $this->px_get_ftp_dir('commandes');
        $f = fopen($dir . "/" . $fname, "a");

        // Parcours des lignes de commande
        foreach ($order->get_items('line_item') as $k => $item) {

            /** @var WC_Product $product */
            //$product = $order->get_product_from_item();//deprecated
            $product = $item->get_product();

            $dataLine = $data;
            $dataLine[35] = $k;
            $dataLine[36] = $product->get_sku();
            $dataLine[37] = $item->get_quantity();
            // NB: 'espace' comme délimiteur car pixisoft veut pas de délimiteur
            //fputcsv($f, $dataLine, ';', ' ');
            $this->fputcsv2($f, $dataLine);

            Pixisoft_Connector_Core::log("\t#$k : ". $item->get_quantity()." x ".$product->get_sku());
        }

        fclose($f);
    }

    /**
     * Custom fputcsv
     * Ecriture d'une ligne dans un fichier csv
     * avec ';' comme séparateur, sans délimiteur
     */
    private function fputcsv2($handle, $fields)
    {
        $delimiter = ';';
        fwrite($handle, join($delimiter, array_map(
            fn ($field) => $this->cleanStr($field),
            $fields
            )));
        fwrite($handle, "\n");
    }
    private function cleanStr($str)
    {
        // On supprime le séparateur csv
        $str = str_replace(';', '', $str);
        // On supprime les espaces superflus
        $str = preg_replace('/\s+/', ' ', $str);
        // On tronque à 50 caractères pour Pixisoft
        $strLimit = 50;
        return substr($str, 0, $strLimit);
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
        $files = glob($dir . "/*.*");

        // Parcours des fichiers présents
        foreach ($files as $file) {

            Pixisoft_Connector_Core::log("Import fichier stock $file");

            $f = fopen($file, 'r');

            // 1 ligne par produit
            $cpt = 0;
            while (($data = fgetcsv($f, 0, ";")) !== FALSE) {

                list (
                    /* owner */,
                    /* site */,
                    $sku,
                    $qte,
                    /* unité */,
                    /* date export */,
                    /* heure export */,
                ) = $data;

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

                // Mise à jour du stock
                $product->set_manage_stock(true);
                $product->set_stock_quantity($qte);
                $product->save();
                $cpt++;
            }
            fclose($f);

            // Suppression du fichier traité
            unlink($file);

            Pixisoft_Connector_Core::log("$cpt lignes traitées");
        }
    }

    /**
     * HOOK de mise à jour des commandes
     * (déclenché par cron)
     */
    function px_update_commandes()
    {
        $dir = $this->px_get_ftp_dir('livraisons');
        $files = glob($dir . "/*.*");

        // Parcours des fichiers présents
        foreach ($files as $file) {

            Pixisoft_Connector_Core::log("Import fichier commandes $file");

            $f = fopen($file, 'r');

            $trackings = [];

            // 1 ligne par produit
            $cpt = 0;
            while (($data = fgetcsv($f, 0, ";")) !== false) {

                $orderId  = $data[2];   // Colonne 3  : OrderNumber
                $tracking = $data[35];  // Colonne 36 : TrackingTransporteur

                /** @var WC_Order $order */
                $order = wc_get_order($orderId);

                if (!$order) {
                    Pixisoft_Connector_Core::log("Commande $orderId non trouvée");
                    continue;
                }

                // Numéro de suivi déjà traité
                if (in_array($tracking, $trackings)) continue;

                // Shipping
                $shippings = $order->get_items('shipping');
                $shipping = reset($shippings);

                try {
                    // Import tracking
                    if (!Tracking_Service::hasTracking($order, $shipping, $tracking)) {
                        Tracking_Service::addTracking($order, $shipping, $tracking);
                    }
                    $trackings[] = $tracking;
                }
                catch (Exception $e) {
                    Pixisoft_Connector_Core::log("Commande $orderId : " . $e->getMessage());
                }

                // Mise à jour statut (se fera automatiquement par les crons des transporteurs)
                // https://woocommerce.com/document/managing-orders/#visual-diagram-to-illustrate-order-statuses
                // $order->update_status('completed');

                $cpt++;
            }
            fclose($f);

            // Archiver fichier traité
            //$archiveDir = $this->px_get_ftp_dir('archives');
            //rename($file, $archiveDir . '/' . basename($file));
            unlink($file);

            Pixisoft_Connector_Core::log("$cpt lignes traitées");
        }
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




