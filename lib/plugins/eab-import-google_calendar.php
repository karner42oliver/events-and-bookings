<?php
/*
Plugin Name: Import: Google Calendar
Description: Synchronisiere Ereignisse aus Deinen Google-Kalendern. Derzeit werden nur Deine regulären Ereignisse importiert (keine wiederkehrenden Ereignisse)..
Plugin URI: https://n3rds.work/piestingtal-source-project/eventsps-das-eventmanagment-fuer-wordpress/
Version: 1.1
Author: DerN3rd
AddonType: Integration
*/

if (!class_exists('PSource_Wp_Oauth')) require_once(EAB_PLUGIN_DIR . 'lib/class_wd_wpmu_oauth.php');
if (!class_exists('Eab_Importer')) require_once(EAB_PLUGIN_DIR . 'lib/class_eab_importer.php');



/**
 * Concrete oAuth login interface implementation.
 */
class Eab_Gcal_Oauth_GoogleImporter extends Eab_Gcal_Plugin_Oauth_RO {
	
	public function get_data_key ($key) {
		return $this->_get_data_key("gcal_importer-{$key}");
	}

}


/**
 * Concrete gCalendar helper implementation.
 */
class Eab_Gcal_Calendar_GoogleImporter extends PSource_Gcal_Helper {

	public function initialize () {
		$oauth = new Eab_Gcal_Oauth_GoogleImporter;
		$token = $oauth->is_authenticated();
		if ($token) return $this->_token = $token;

		return false;
	}
}

/**
 * Concrete importer implementation.
 */
class Eab_Gcal_Importer_GoogleImporter extends Eab_ScheduledImporter {

	private $_helper;
	private $_data;

	protected function __construct () {
		$this->_helper = new Eab_Gcal_Calendar_GoogleImporter;
		$this->_data = Eab_Options::get_instance();
		parent::__construct();
	}

	public static function serve () { return new Eab_Gcal_Importer_GoogleImporter; }

	public function check_schedule () {	
		$last_run = (int)get_option($this->get_schedule_key());
		$run_each = $this->_data->get_option('gcal_importer-run_each');
		$run_each = $run_each ? $run_each : 3600;
		$next_run = $last_run + $run_each;
		return ($next_run < eab_current_time());
	}

	public function update_schedule () {
		return update_option($this->get_schedule_key(), eab_current_time());
	}

	public function import () {
		$sync_calendars = $this->_data->get_option('gcal_importer-sync_calendars', array());
		foreach ($sync_calendars as $calendar_id) $this->import_events($calendar_id);
	}

	public function map_to_raw_events_array ($calendar_id) {
		return $this->_helper->get_calendar_events($calendar_id);
	}

	public function is_imported ($gevent) {
		global $wpdb;
		$id = esc_sql($gevent['id']);
		return $wpdb->get_var("SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key='eab_gcal_event' AND meta_value='{$id}'");
	}

	public function is_recurring ($source) {
		return !empty($source['recurrence']);
	}

	public function map_to_post_type ($gevent) {
		// Basic post info
		$author = $this->_data->get_option('gcal_importer-calendar_author');

		return array(
			'post_type' => Eab_EventModel::POST_TYPE,
			'post_status' => 'publish',
			'post_title' => isset($gevent['summary']) ? $gevent['summary']  : '',
			'post_content' => isset($gevent['description']) ? $gevent['description']  : '',
			'post_date' => date("Y-m-d H:i:s", strtotime($gevent['created'])),
			'post_author' => $author,
			// category ...
		);
	}

	public function map_to_post_meta ($gevent) {
		$meta = array();

		$meta['eab_gcal_event'] = $gevent['id'];
		$meta['psource_event_status'] = Eab_EventModel::STATUS_OPEN; // Open by default

		// Metadata - timestamps
                $startDateTime = isset($gevent['start']['dateTime']) ? $gevent['start']['dateTime'] : $gevent['start']['date'];
                $endDateTime = isset($gevent['end']['dateTime']) ? $gevent['end']['dateTime'] : $gevent['end']['date'];
                
		$start = isset($startDateTime) ? $this->_to_local_time($startDateTime) : false;
		$end = isset($endDateTime) ? $this->_to_local_time($endDateTime) : false;
		if ($start) $meta['psource_event_start'] = date('Y-m-d H:i:s', $start);
		if ($end) $meta['psource_event_end'] = date('Y-m-d H:i:s', $end);

		// Metadata - location
		$venue = isset($gevent['location']) ? $gevent['location'] : false;
		if ($venue) $meta['psource_event_venue'] = $venue;

		return $meta;
	}

	private function get_schedule_key () {
		return 'last_' . __CLASS__ . '_run';
	}

	/**
	 * Convert the GCal event times into locally valid ones.
	 *
	 * @param string $str_stamp String timestamp (e.g. 2013-05-30T10:49:26+02:00)
	 *
	 * @return int Parsed UNIX timestamp
	 */
	private function _to_local_time ($str_stamp) {
		$stamp = strtotime($str_stamp);

		$convert = $this->_data->get_option('gcal_importer-convert_times');
		if (empty($convert)) return $stamp;

		$gmt_offset = (float)get_option('gmt_offset');
		$tdiff = $gmt_offset * 3600;

		return $stamp + $tdiff;
	}

}

/**
 * Setup & auth handler.
 */
class Eab_Calendars_GoogleImporter {

	private $_data;
	private $_oauth;
	private $_gcal;

	private function __construct () {
		$this->_data = Eab_Options::get_instance();
		$this->_oauth = new Eab_Gcal_Oauth_GoogleImporter;
		$this->_gcal = new Eab_Gcal_Calendar_GoogleImporter;
	}

	public static function serve () {
		$me = new Eab_Calendars_GoogleImporter;
		$me->_add_hooks();
	}

	private function _add_hooks () {
		add_action('eab-settings-after_api_settings', array($this, 'show_settings'));
		add_filter('eab-settings-before_save', array($this, 'save_settings'));

		add_action('wp_ajax_eab_gcal_import_authenticate', array($this, 'json_authenticate'));
		add_action('wp_ajax_eab_gcal_import_reset', array($this, 'json_reset'));
		add_action('wp_ajax_eab_gcal_import_resync_calendars', array($this, 'json_resync_calendars'));
	}

	function show_settings () {
		$tips = new PSource_HelpTooltips();
		$tips->set_icon_url(EAB_PLUGIN_URL . 'img/information.png' );

		$api_key = $this->_data->get_option('gcal_importer-client_id');
		$api_secret = $this->_data->get_option('gcal_importer-client_secret');
		$is_authenticated = $this->_oauth->is_authenticated();

		$runs = array(
			'3600' => __('Stunde', 'eab'),
			'7200' => __('Zwei Stunden', 'eab'),
			'10800' => __('Drei Stunden', 'eab'),
			'21600' => __('Sechs Stunden', 'eab'),
			'43200' => __('Zwölf Stunden', 'eab'),
			'86400' => __('Tag', 'eab'),
		);
		$run_each = $this->_data->get_option('gcal_importer-run_each');
		$run_each = $run_each ? $run_each : 3600;

		$calendars_cache = $this->_get_cached_calendars();
		$sync_calendars = $this->_data->get_option('gcal_importer-sync_calendars', array());

		$user = wp_get_current_user();
		$calendar_author = $this->_data->get_option('gcal_importer-calendar_author', $user->ID);
		$raw_authors = get_users(array('who' => 'authors'));
		$possible_authors = array_combine(
			wp_list_pluck($raw_authors, 'ID'),
			wp_list_pluck($raw_authors, 'display_name') 
		);
?>
<div id="eab-settings-gcal_importer" class="eab-metabox postbox">
	<h3 class="eab-hndle"><?php _e('Google Calendar Import Einstellungen', 'eab'); ?></h3>
	<div class="eab-inside">
		<p>
			<ol>
			    <li><a href="https://code.google.com/apis/console/" target="_blank"><?php _e('Erstelle ein Projekt in der Google API Console', 'eab'); ?></a></li>
			    <li><?php _e('Aktiviere auf der Registerkarte "Dienste" die Option "Kalender-API"', 'eab'); ?></li>
			    <li><?php printf(__('Klicke unter "API-Zugriff" auf "oAuth-Clientzugriff erstellen". Gib Deine Daten ein und verwende diese als "Authorized Redirect URIs".: <code>%s</code>', 'eab'), $this->_oauth->get_login_response_endpoint()); ?></li>
			    <li><?php _e('Kopiere Deine Client-ID und geheime Client-ID und füge sie in die folgenden Felder ein', 'eab'); ?></li>
			    <li><?php _e('Speichere Deine Plugin-Einstellungen und klicke auf die Schaltfläche "Authentifizieren"', 'eab'); ?></li>
			</ol>
		</p>
		<div class="eab-settings-settings_item" style="line-height:1.8em">
                        <div>
			<label for="psource_event-gcal_importer-app_id" id="psource_event_label-gcal_importer-app_id"><?php _e('Client ID', 'eab'); ?></label><br />
			<input type="text" size="90" id="psource_event-gcal_importer-app_id" name="gcal_importer[client_id]" value="<?php print $api_key; ?>" />
			<span><?php echo $tips->add_tip(__('Gib hier Deine Client-ID ein.', 'eab')); ?></span>
                        </div>
                        <div>
			<label for="psource_event-gcal_importer-app_id" id="psource_event_label-gcal_importer-app_id"><?php _e('Client secret', 'eab'); ?></label><br />
			<input type="text" size="85" id="psource_event-gcal_importer-app_id" name="gcal_importer[client_secret]" value="<?php print $api_secret; ?>" />
			<span><?php echo $tips->add_tip(__('Gib hier Deine Client-Geheimnummer ein.', 'eab')); ?></span>
                        </div>
			<div class="gcal_importer-auth_actions">
		<?php if ($is_authenticated && $api_key && $api_secret) { ?>
				<a href="#reset" class="button" id="gcal_import-reset"><?php _e('Reset', 'eab'); ?></a>
				<span><?php echo $tips->add_tip(__('Denke daran, auch das Offline-Zugriffstoken zu widerrufen <a href="https://accounts.google.com/IssuedAuthSubTokens" target="_blank">hier</a>.', 'eab')); ?></span>
		<?php } else if ($api_key && $api_secret) { ?>
				<a href="#authenticate" class="button" id="gcal_import-authenticate"><?php _e('Authentifizieren', 'eab'); ?></a>
		<?php } else { ?>
				<p><em><?php _e('Gib Deine API-Informationen ein und speichere zuerst die Einstellungen.', 'eab'); ?></em></p>
		<?php } ?>
			</div>
		</div>
		<?php if ($is_authenticated) { ?>
		<div class="eab-settings-settings_item">
			<label><?php _e('Ich möchte Ereignisse aus diesen Kalendern importieren:', 'eab'); ?></label>
			<span><a href="#resync" id="gcal_import-resync" class="button"><?php _e('Kalenderliste aktualisieren', 'eab'); ?></a></span>
			<span><?php echo $tips->add_tip(__('WähleKalender aus, die Du importieren möchtest.', 'eab')); ?></span>
			<?php if ($calendars_cache) { ?>
			<br />
			<select name="gcal_importer[sync_calendars][]" multiple="multiple">
			<?php foreach ($calendars_cache as $id => $label) { ?>
				<?php $selected = in_array($id, $sync_calendars) ? 'selected="selected"' : ''; ?>
				<option value="<?php esc_attr_e($id); ?>" <?php echo $selected; ?>><?php echo $label; ?>&nbsp;</option>
			<?php } ?>
			</select>
			<?php } // end if cache ?>
		</div>
		<div class="eab-settings-settings_item">
			<label><?php _e('Importer ausführen alle:', 'eab'); ?></label>
			<select name="gcal_importer[run_each]">
			<?php foreach ($runs as $interval => $ilabel) { ?>
				<option value="<?php echo (int)$interval; ?>" <?php echo selected($interval, $run_each); ?>><?php echo $ilabel; ?></option>
			<?php } ?>
			</select>
		</div>
		<div class="eab-settings-settings_item">
			<label><?php _e('Weise diesem Benutzer importierte Ereignisse zu:', 'eab'); ?></label>
			<select name="gcal_importer[calendar_author]">
			<?php foreach ($possible_authors as $aid => $alabel) { ?>
				<option value="<?php echo $aid; ?>" <?php echo selected($aid, $calendar_author); ?>><?php echo $alabel; ?>&nbsp;</option>
			<?php } ?>
			</select>
			<span><?php echo $tips->add_tip(__('Wähle den Benutzer aus, der als importierter Ereignis-Host angezeigt werden soll.', 'eab')); ?></span>
		</div>
		<div class="eab-settings-settings_item">
			<input type="hidden" name="gcal_importer[convert_times]" value="" />
			<input type="checkbox" id="psource_event-gcal_importer-convert_times" name="gcal_importer[convert_times]" value="1" <?php checked($this->_data->get_option('gcal_importer-convert_times'), 1); ?> />
			<label for="psource_event-gcal_importer-convert_times"><?php _e('Versuche Ereigniszeiten in lokale WordPress-Zeit umzuwandeln', 'eab'); ?></label>
		</div>
		<?php } // end if authenticated ?>
	</div>
</div>
<script type="text/javascript">
(function ($) {

function authenticate () {
	var googleLogin = window.open('https://www.google.com/accounts', "google_login", "scrollbars=no,resizable=no,toolbar=no,location=no,directories=no,status=no,menubar=no,copyhistory=no,height=400,width=800");
	$.post(ajaxurl, {
		"action": "eab_gcal_import_authenticate",
		"url": window.location.href
	}, function (data) {
		var href = data.url;
		googleLogin.location = href;
		var gTimer = setInterval(function () {
			try {
				if (googleLogin.location.hostname == window.location.hostname) {
					// We're back!
					clearInterval(gTimer);
					googleLogin.close();
					window.location.reload();
				}
			} catch (e) {}
		}, 300);
	}, "json");
	return false;
}

$(function () {
	$("#gcal_import-authenticate").on("click", authenticate);
	$("#gcal_import-reset").on("click", function () {
		$.post(ajaxurl, {"action": "eab_gcal_import_reset"}, window.location.reload);
		return false;
	});
	$("#gcal_import-resync").on("click", function () {
		$.post(ajaxurl, {"action": "eab_gcal_import_resync_calendars"}, window.location.reload);
		return false;
	});
});
})(jQuery);
</script>
<?php
	}

	function save_settings ($options) {
		$options['gcal_importer-client_id'] 		= isset( $_POST['gcal_importer']['client_id'] ) ? $_POST['gcal_importer']['client_id'] : '';
		$options['gcal_importer-client_secret'] 	= isset( $_POST['gcal_importer']['client_secret'] ) ? $_POST['gcal_importer']['client_secret'] : '';
		$options['gcal_importer-sync_calendars'] 	= isset( $_POST['gcal_importer']['sync_calendars'] ) ? $_POST['gcal_importer']['sync_calendars'] : 0;
		$options['gcal_importer-calendar_author'] 	= isset( $_POST['gcal_importer']['calendar_author'] ) ? $_POST['gcal_importer']['calendar_author'] : '';
		$options['gcal_importer-run_each'] 			= isset( $_POST['gcal_importer']['run_each'] ) ? $_POST['gcal_importer']['run_each'] : '';
		$options['gcal_importer-convert_times'] 	= isset( $_POST['gcal_importer']['convert_times'] ) ? 1 : 0;
		return $options;
	}

	function json_reset () {
		$this->_oauth->reset_token();
		die;
	}

	function json_authenticate () {
		die(json_encode(array(
			"url" => $this->_oauth->get_authentication(),
		)));
	}

	function json_resync_calendars () {
		$calendars = array();
		$raw_calendars = $this->_gcal->get_calendars();
		foreach ($raw_calendars as $calendar) {
			$calendars[$calendar['id']] = $calendar['summary'];
		}
		$this->_data->set_option('gcal_importer-cached_calendars', $calendars);
		$this->_data->update();
		die;
	}

	private function _get_cached_calendars () {
		$calendars = $this->_data->get_option('gcal_importer-cached_calendars', array());
		return $calendars
			? $calendars
			: array()
		;
	}
}


Eab_Calendars_GoogleImporter::serve();
Eab_Gcal_Importer_GoogleImporter::serve();