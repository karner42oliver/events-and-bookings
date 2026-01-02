<?php
/*
Plugin Name: Ereignisse des Vormonats verfallen
Description: Standardmäßig werden Deine vergangenen Ereignisse archiviert. Wenn Du dieses Add-On aktivierst, laufen Deine monatelangen archivierten Ereignisse sofort ab.
Plugin URI: https://n3rds.work/piestingtal-source-project/eventsps-das-eventmanagment-fuer-wordpress/
Version: 1.1
Author: DerN3rd
AddonType: Events
*/

/*
Detail: Deine <em>archivierten</em> Ereignisse werden in Archiven angezeigt, Besucher können jedoch nicht antworten. <br /><em>Abgelaufene</em> Ereignisse werden aus Ihren Archiven entfernt.
*/

class Eab_Events_ExpireMonthOldEvents {
	
	private function __construct () {}
	
	public static function serve () {
		$me = new Eab_Events_ExpireMonthOldEvents;
		$me->_add_hooks();
	}
	
	private function _add_hooks () {
		add_action('admin_notices', array($this, 'show_nags'));
		add_action('eab_scheduled_jobs', array($this, 'expire_archived_events'), 99);
	}
	
	function show_nags () {
		if (!class_exists('Eab_Events_ExpirePastEvents')) return false;
		if (defined('EAB_EXPIRY_CLASS_NAG_RENDERED')) return false;
		echo '<div class="error"><p>' .
			__("<b>Konfliktwarnung:</b> Du musst eines der Add-Ons für den Ablauf vergangener Ereignisse deaktivieren.", 'eab') .
		'</p></div>';
		define('EAB_EXPIRY_CLASS_NAG_RENDERED', true);
	}
	
	function expire_archived_events () {
		if (class_exists('Eab_Events_ExpirePastEvents')) return false;
		$args = array();
		$collection = new Eab_LastMonthArchivedCollection(eab_current_time(), $args);
		$events = $collection->to_collection();
		foreach ($events as $event) {
			$event->set_status(Eab_EventModel::STATUS_EXPIRED);
		}
	}
}



/**
 * Month-old archived events
 */
class Eab_LastMonthArchivedCollection extends Eab_TimedCollection {
	
	public function build_query_args ($args) {
		$time = $this->get_timestamp();
		
		$args = array_merge(
			$args,
			array(
			 	'post_type' => 'psource_event',
				'suppress_filters' => false, 
				'posts_per_page' => EAB_OLD_EVENTS_EXPIRY_LIMIT,
				'meta_query' => array(
					array(
		    			'key' => 'psource_event_status',
		    			'value' => Eab_EventModel::STATUS_ARCHIVED,
					),
					array(
		    			'key' => 'psource_event_end',
		    			'value' => date("Y-m-01 00:00:01", $time),
		    			'compare' => '<',
		    			'type' => 'DATETIME'
					),
				)
			)
		);
		return $args;
	}
}

Eab_Events_ExpireMonthOldEvents::serve();
