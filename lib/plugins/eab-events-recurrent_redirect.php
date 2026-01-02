<?php
/*
Plugin Name: Umleitung wiederkehrender Ereignisse
Description: Leitet von der Stamminstanz zur aktuell aktiven Instanz um.
Plugin URI: https://n3rds.work/piestingtal-source-project/eventsps-das-eventmanagment-fuer-wordpress/
Version: 0.4
Author: DerN3rd
AddonType: Events
*/

class Eab_Events_RecurrentRootRedirect {

	private $_data;

	/**
	 * Constructor
	 */	
	private function __construct () {
		$this->_data = Eab_Options::get_instance();
	}

	/**
	 * Run the Addon
	 *
	 */	
	public static function serve () {
		$me = new Eab_Events_RecurrentRootRedirect;
		$me->_add_hooks();
	}

	/**
	 * Hooks to the main plugin Events+
	 *
	 */	
	private function _add_hooks () {
		add_action('template_redirect', array($this, 'redirect'));
	}
	
	function redirect() {
		global $post;
		if (!is_singular()) return false;
		if (!$post || !is_object($post) || !isset($post->post_type) || 'psource_event' != $post->post_type) return false;
		
		$event = ($post instanceof Eab_EventModel) ? $post : new Eab_EventModel($post);
		if (!$event->is_recurring()) return false;
		if ($event->is_recurring_child()) return false; // Already an instance - no need to keep going.

		$collection = new Eab_AllUpcomingRecurringChildrenCollection($event, $time);
		$children = $collection->to_collection();
		if (!$children) return false;

		$now = eab_current_time();
		$redirect_to = $active = false;
		$fallback = array();
		foreach ($children as $key => $child) {
			if ($child->get_end_timestamp() < $now) continue; // Already passed, move on
			if ($child->get_start_timestamp() <= $now) {
				$active = $child;
				break; // Currently ongoing event
			} else $fallback[$child->get_start_timestamp()] = $child;
		}

		if (!empty($active)) {
			$redirect_to = $active;
		} else if (!empty($fallback)) {
			sort($fallback);
			$redirect_to = reset($fallback);
		}
		if (!$redirect_to) return false;

		wp_redirect(get_permalink($redirect_to->get_id())); die;
	}
}


class Eab_AllUpcomingRecurringChildrenCollection extends Eab_UpcomingCollection {

	private $_event;

	public function __construct ($event, $timestamp=false, $args=array()) {
		$this->_event = $event;
		parent::__construct($timestamp, $args);
	}

	public function order_by_date ($q) {
		return "eab_meta.meta_value DESC";
	}
	
	public function build_query_args ($args) {
		$status = $this->_event->is_trashed() 
			? PSource_RecurringDatedItem::RECURRENCE_TRASH_STATUS
			: PSource_RecurringDatedItem::RECURRENCE_STATUS
		;
		$args = array (
			'post_type' => 'psource_event',
			'post_status' => $status,
			'post_parent' => $this->_event->get_id(),
			'posts_per_page' => -1,
			'meta_query' => array(
				array(
	    			'key' => 'psource_event_end',
	    			'value' => date("Y-m-d H:i:s", $this->get_timestamp()),
	    			'compare' => '>=',
	    			'type' => 'DATETIME'
				),
			),
		);
		return $args;
	}
}

if (!is_admin()) Eab_Events_RecurrentRootRedirect::serve();