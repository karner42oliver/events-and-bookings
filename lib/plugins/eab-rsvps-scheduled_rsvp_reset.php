<?php
/*
Plugin Name: RSVP-Status automatisch zurückgesetzen
Description: Setzt den RSVP-Status Ihrer bezahlten Ereignisse nach einer vorkonfigurierten Zeit automatisch zurück, wenn der Benutzer noch nicht bezahlt hat.
Plugin URI: https://n3rds.work/piestingtal-source-project/eventsps-das-eventmanagment-fuer-wordpress/
Version: 1.1
Author: DerN3rd
AddonType: Events, RSVP
*/

class Eab_Rsvps_RsvpAutoReset {

	const SCHEDULE_KEY = '_eab-rsvps-rsvp_auto_reset-run_lock';

	private $_data;

	private function __construct () {
		$this->_data = Eab_Options::get_instance();
	}

	public static function serve () {
		$me = new Eab_Rsvps_RsvpAutoReset;
		$me->_add_hooks();
	}

	private function _add_hooks () {
		add_action('eab-settings-after_plugin_settings', array($this, 'show_settings'));
		add_filter('eab-settings-before_save', array($this, 'save_settings'));

		add_action('eab_scheduled_jobs', array($this, 'check_schedule'));
	}

	public function check_schedule () {
		$last_run = (int)get_option(self::SCHEDULE_KEY);
		$run_each = $this->_data->get_option('rsvp_auto_reset-run_each');
		$run_each = $run_each ? $run_each : 3600;
		$next_run = $last_run + $run_each;
		if ($next_run < eab_current_time()) {
			$this->_reset_expired_bookings($last_run);
			update_option(self::SCHEDULE_KEY, eab_current_time());
		}
	}

	private function _reset_expired_bookings ($since) {
		//$rsvps = Eab_EventModel::get_bookings(Eab_EventModel::BOOKING_YES, $since);
		$rsvps = Eab_EventModel::get_bookings(Eab_EventModel::BOOKING_YES); // Just reset all the expired bookings.
		$now = eab_current_time();
		
		$cutoff_limit = $this->_data->get_option('rsvp_auto_reset-cutoff');
		$cutoff_limit = $cutoff_limit ? $cutoff_limit : 3600;
		
		$callback = (int)$this->_data->get_option('rsvp_auto_reset-remove_attendance')
			? 'delete_attendance'
			: 'cancel_attendance'
		;
		$events = array(); // Events cache
		foreach ($rsvps as $rsvp) {
			// Check time difference
			$time_diff = $now - strtotime($rsvp->timestamp);
			if ($time_diff < $cutoff_limit) continue; // This one still has time to pay
			
			// Check event premium status
			if (empty($events[$rsvp->event_id])) $events[$rsvp->event_id] = new Eab_EventModel(get_post($rsvp->event_id));
			if (!$events[$rsvp->event_id]->is_premium()) continue; // Not a paid event, carry on

			// Check user payment
			if ($events[$rsvp->event_id]->user_paid($rsvp->user_id)) continue; // User paid for event, we're good here.

			// If we got here, we should reset the users RSVP
			if (is_callable(array($events[$rsvp->event_id], $callback))) $events[$rsvp->event_id]->$callback($rsvp->user_id);
		}
	}

	function show_settings () {
		$tips = new PSource_HelpTooltips();
		$tips->set_icon_url(EAB_PLUGIN_URL . 'img/information.png' );
		$runs = array(
			'3600' => __('Stunde', 'eab'),
			'7200' => __('Zwei Stunden', 'eab'),
			'10800' => __('Drei Stunden', 'eab'),
			'21600' => __('Sechs Stunden', 'eab'),
			'43200' => __('Zwölf Stunden', 'eab'),
			'86400' => __('Tag', 'eab'),
		);
		$runs = apply_filters( 'eab_rsvp_scheduled_rsvp_reset_cron_times', $runs );
		$run_each = $this->_data->get_option('rsvp_auto_reset-run_each');
		$run_each = $run_each ? $run_each : 3600;

		$cutoff = $this->_data->get_option('rsvp_auto_reset-cutoff');
		$cutoff = $cutoff ? $cutoff : 3600;

		$remove_attendance = $this->_data->get_option('rsvp_auto_reset-remove_attendance')
			? 'checked="checked"'
			: ''
		;
?>
<div id="eab-settings-rsvp_status_auto_reset" class="eab-metabox postbox">
	<h3 class="eab-hndle"><?php _e('Einstellungen für das automatische Zurücksetzen des RSVP-Status', 'eab'); ?></h3>
	<div class="eab-inside">
		<div class="eab-settings-settings_item">
	    	<label><?php _e('Plane Überprüfungen alle:', 'eab'); ?></label>
			<select name="eab_rsvps[rsvp_auto_reset-run_each]">
			<?php foreach ($runs as $sinterval => $slabel) { ?>
				<option value="<?php echo (int)$sinterval; ?>" <?php echo selected($sinterval, $run_each); ?>><?php echo $slabel; ?></option>
			<?php } ?>
			</select>
			<span><?php echo $tips->add_tip(__('Plane Überprüfungen alle', 'eab')); ?></span>
	    </div>
	    <div class="eab-settings-settings_item">
	    	<label><?php _e('Unbezahlte RSVPs, die älter als diese Zeit sind, automatisch zurücksetzen:', 'eab'); ?></label>
			<select name="eab_rsvps[rsvp_auto_reset-cutoff]">
			<?php foreach ($runs as $cinterval => $clabel) { ?>
				<option value="<?php echo (int)$cinterval; ?>" <?php echo selected($cinterval, $cutoff); ?>><?php echo $clabel; ?></option>
			<?php } ?>
			</select>
			<span><?php echo $tips->add_tip(__('Unbezahlte positive RSVP-Sperrzeit', 'eab')); ?></span>
	    </div>
		<div class="eab-settings-settings_item">
	    	<label for="eab_event-eab_rsvps-rsvp_auto_reset-remove_attendance"><?php _e('Anwesenheit vollständig entfernen', 'eab'); ?>?</label>
			<input type="checkbox" id="eab_event-eab_rsvps-rsvp_auto_reset-remove_attendance" name="eab_rsvps[rsvp_auto_reset-remove_attendance]" value="1" <?php print $remove_attendance; ?> />
			<span><?php echo $tips->add_tip(__('Standardmäßig setzt das Plugin die Benutzeranwesenheit auf "Nein" zurück. Wähle diese Option, wenn Du stattdessen die Anwesenheitslisten vollständig entfernen möchtest.', 'eab')); ?></span>
	    </div>
	</div>
</div>
<?php
	}

	function save_settings ($options) {
		$options['rsvp_auto_reset-run_each'] 			= isset( $_POST['eab_rsvps']['rsvp_auto_reset-run_each'] ) ? $_POST['eab_rsvps']['rsvp_auto_reset-run_each'] : false;
		$options['rsvp_auto_reset-cutoff'] 				= isset( $_POST['eab_rsvps']['rsvp_auto_reset-cutoff'] ) ? $_POST['eab_rsvps']['rsvp_auto_reset-cutoff'] : '';
		$options['rsvp_auto_reset-remove_attendance'] 	= isset( $_POST['eab_rsvps']['rsvp_auto_reset-remove_attendance'] ) ? $_POST['eab_rsvps']['rsvp_auto_reset-remove_attendance'] : '';
		return $options;
	}

}
Eab_Rsvps_RsvpAutoReset::serve();