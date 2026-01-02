<?php
/*
Plugin Name: Vergangene Ereignisse sofort ablaufen lassen
Description: Standardmäßig werden Deine vergangenen Ereignisse archiviert. Wenn Du dieses Add-On aktivierst, laufen alle Deine archivierten Ereignisse ab. Diese Aktion hängt vom Cron-Job ab. Du musst also bis zum nächsten Cron-Job-Lauf warten. Der Cron-Job wird von Deinem System in bestimmten Intervallen automatisch ausgeführt. Diese Erweiterung wird vorerst stündlich ausgeführt.
Plugin URI: https://n3rds.work/piestingtal-source-project/eventsps-das-eventmanagment-fuer-wordpress/
Version: 1.1
Author: DerN3rd
AddonType: Events
*/

/*
Detail: Deine <em>archivierten</em> Ereignisse werden in Archiven angezeigt, Besucher können jedoch nicht antworten. <br /> <em>Abgelaufene</em> Ereignisse werden aus Deinen Archiven entfernt.
*/

class Eab_Events_ExpirePastEvents {

	private function __construct () {}

	public static function serve () {
		$me = new Eab_Events_ExpirePastEvents;
		$me->_add_hooks();
	}

	private function _add_hooks () {
		add_action('admin_notices', array($this, 'show_nags'));
		add_action('eab_scheduled_jobs', array($this, 'expire_archived_events'), 99);
	}

	function show_nags () {
		if (!class_exists('Eab_Events_ExpireMonthOldEvents')) return false;
		if (defined('EAB_EXPIRY_CLASS_NAG_RENDERED')) return false;
		echo '<div class="error"><p>' .
			__("<b>Konfliktwarnung:</b> Du musst eines der Add-Ons für den Ablauf vergangener Ereignisse deaktivieren.", 'eab') .
		'</p></div>';
		define('EAB_EXPIRY_CLASS_NAG_RENDERED', true);
	}

	function expire_archived_events () {
		if (class_exists('Eab_Events_ExpireMonthOldEvents')) return false;
		$args = array();
		$collection = new Eab_ArchivedCollection($args);
		$events = $collection->to_collection();
		foreach ($events as $event) {
			$this->_expire_event($event);
		}
	}

	private function _expire_event ($event) {
		$event->set_status(Eab_EventModel::STATUS_EXPIRED);
	}
}

Eab_Events_ExpirePastEvents::serve();
