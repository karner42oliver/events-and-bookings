<?php
/*
Plugin Name: Import: Facebook Events
Description: Synchronisiere lokale und Facebook-Events.
Plugin URI: https://n3rds.work/piestingtal-source-project/eventsps-das-eventmanagment-fuer-wordpress/
Version: 1.1
Author: DerN3rd
AddonType: Integration
*/

if( strnatcmp( phpversion(), '5.4.0' ) >= 0 )
{
        require_once( dirname( __FILE__ ) . '/eab-import-facebook_events-main.php');
}
else
{
        add_action( 'admin_notices', 'eab_show_php_notice' );
        function eab_show_php_notice() {
                ?>
                <div class="notice notice-error is-dismissible">
                        <p><?php _e( 'Du benötigst PHP 5.4 oder höher, um die Erweiterung <b>Import: Facebook Events</b> verwenden zu können.', 'eab' ); ?></p>
                </div>
                <?php
        }
}