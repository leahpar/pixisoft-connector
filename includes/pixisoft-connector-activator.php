<?php

class Pixisoft_Connector_Activator
{
    public static function activate()
    {
        //add_filter('cron_schedules', 'px_add_cron_interval');
        //function px_add_cron_interval($schedules) {
        //    $schedules['five_seconds'] = array(
        //        'interval' => 5,
        //        'display'  => esc_html__( 'Every Five Seconds' ), );
        //    return $schedules;
        //}

        if (!wp_next_scheduled('px_cron_update_stock')) {
            wp_schedule_event(time(), 'daily', 'px_cron_update_stock');
        }

        // Création des répertoires FTP
        Pixisoft_Connector_Core::px_get_ftp_dir('articles');
        Pixisoft_Connector_Core::px_get_ftp_dir('stocks');
        Pixisoft_Connector_Core::px_get_ftp_dir('commandes');
        Pixisoft_Connector_Core::px_get_ftp_dir('livraisons');
    }

    public static function deactivate()
    {
        wp_clear_scheduled_hook('px_cron_update_stock');
    }

}
