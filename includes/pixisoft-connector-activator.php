<?php

class Pixisoft_Connector_Activator
{
    public static function activate()
    {
        Pixisoft_Connector_Core::log("Activation plugin...");

        // Création des répertoires FTP
        Pixisoft_Connector_Core::px_get_ftp_dir('articles');
        Pixisoft_Connector_Core::px_get_ftp_dir('stocks');
        Pixisoft_Connector_Core::px_get_ftp_dir('commandes');
        Pixisoft_Connector_Core::px_get_ftp_dir('livraisons');
        Pixisoft_Connector_Core::px_get_ftp_dir('logs');


        if (!wp_next_scheduled('px_cron_update_stock')) {
            $time = strtotime('now');
            $error = wp_schedule_event($time, 'hourly', 'px_cron_update_stock', [], true);
            Pixisoft_Connector_Core::log(print_r($error, true));
        }
        if (!wp_next_scheduled('px_cron_update_commandes')) {
            $time = strtotime('now');
            $error = wp_schedule_event($time, 'hourly', 'px_cron_update_commandes', [], true);
            Pixisoft_Connector_Core::log(print_r($error, true));
        }

        Pixisoft_Connector_Core::log("Done");
    }

    public static function deactivate()
    {
        Pixisoft_Connector_Core::log("Désactivation plugin...");
        wp_clear_scheduled_hook('px_cron_update_stock');
        wp_clear_scheduled_hook('px_cron_update_commandes');
        Pixisoft_Connector_Core::log("Done");
    }

}
