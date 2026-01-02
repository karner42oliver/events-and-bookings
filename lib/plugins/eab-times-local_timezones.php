<?php
/*
Plugin Name: Lokale Zeitzonen
Description: Konvertiert Veranstaltungstermine und -zeiten automatisch für Ihre Besucher
Plugin URI: https://n3rds.work/piestingtal-source-project/eventsps-das-eventmanagment-fuer-wordpress/
Version: 1.1
Author: DerN3rd
AddonType: Events
*/

class Eab_Events_LocalTimezones {
	private function __construct () {}
	
	public static function serve () {
		$me = new Eab_Events_LocalTimezones;
		$me->_add_hooks();
	}
	
	private function _add_hooks () {
		add_action('eab-javascript-enqueue_scripts', array($this, 'include_scripts'));
	}

	public function include_scripts () {
		wp_enqueue_script('eab-events-local_timezones', EAB_PLUGIN_URL . "js/eab-events-local_timezones.js", array('jquery'), Eab_EventsHub::CURRENT_VERSION);
	}
}
Eab_Events_LocalTimezones::serve();