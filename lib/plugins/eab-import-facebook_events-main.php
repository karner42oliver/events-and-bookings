<?php

if (!class_exists('PSource_Wp_Oauth')) require_once(EAB_PLUGIN_DIR . 'lib/class_wd_wpmu_oauth.php');
if (!class_exists('Eab_Importer')) require_once(EAB_PLUGIN_DIR . 'lib/class_eab_importer.php');
if( ! class_exists( 'Facebook\Facebook' ) ) require_once dirname( __FILE__ ) . '/lib/Facebook/autoload.php';

class Eab_Fbe_Oauth_FacebookEventsImporter extends Eab_FB_Plugin_Oauth_RO {
	public function get_data_key ($key) {
		return $this->_get_data_key("fbe_importer-{$key}");
	}
}

class Eab_Fbe_Importer_FacebookEventsImporter extends Eab_ScheduledImporter {

	private $_data;
	private $_oauth;
	private $_http_headers = array(
		'method' => 'GET',
		'timeout' => '5',
		'redirection' => '5',
		'blocking' => true,
		'compress' => false,
		'decompress' => true,
		'sslverify' => false,
	);

	protected function __construct () {
		$this->_data = Eab_Options::get_instance();
		$this->_oauth = new Eab_Fbe_Oauth_FacebookEventsImporter;
		parent::__construct();
	}

	public static function serve () { return new Eab_Fbe_Importer_FacebookEventsImporter; }

	public function check_schedule () {
		$last_run = (int)get_option($this->get_schedule_key());
		$run_each = $this->_data->get_option('fbe_importer-run_each');
		$run_each = $run_each ? $run_each : 3600;
		$next_run = $last_run + $run_each;
		return ($next_run < eab_current_time());
	}

	public function update_schedule () {
		return update_option($this->get_schedule_key(), eab_current_time());
	}


	public function import () {
		$sync_id = $this->_data->get_option('fbe_importer-fb_user');
		$this->import_events($sync_id);
	}

	public function map_to_raw_events_array ($id) {
		$items = $this->_get_request_items($id);
		return $items;
	}

	public function map_to_post_type ($source) {
 		$author = $this->_data->get_option('fbe_importer-calendar_author');
		$data = array(
			'post_type' => Eab_EventModel::POST_TYPE,
			'post_status' => 'publish',
			'post_title' => isset($source['name']) ? $source['name']  : '',
			'post_content' => isset($source['description']) ? $source['description']  : '',
			'post_date' => date('Y-m-d H:i:s', strtotime($source['updated_time'])),
			'post_author' => $author,
		);

                return $data;
	}

	public function map_to_post_meta ($source) {
		$meta = array();

		$meta['eab_fbe_event'] = $source['id'];
		$meta['psource_event_status'] = Eab_EventModel::STATUS_OPEN; // Open by default

		// Metadata - timestamps
		$start = isset($source['start_time']) ? strtotime($source['start_time']) : false;
		$end = isset($source['end_time']) ? strtotime($source['end_time']) : false;
		if ($start) $meta['psource_event_start'] = date('Y-m-d H:i:s', $start);
		if ($end) $meta['psource_event_end'] = date('Y-m-d H:i:s', $end);

		// Metadata - location
		$venue = isset($source['location']) ? $source['location'] : false;
		if ($venue) $meta['psource_event_venue'] = $venue;

		return $meta;
	}


	public function is_imported ($source) {
		global $wpdb;
		$id = esc_sql($source['id']);
                $res = $wpdb->get_var("SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key='eab_fbe_event' AND meta_value='{$id}'");

                return $res;
	}

	public function is_recurring ($source) {
		return false; // Facebook doesn't support recurring events atm.
	}

	private function _get_request_items ($id) {
		$token = $this->_oauth->is_authenticated();
		if ( $token ) {
			$api_key = $this->_data->get_option('fbe_importer-client_id');
			$api_secret = $this->_data->get_option('fbe_importer-client_secret');
			$sync_user = !empty($id)
					? $id
					: 'me'
			;

			$fb = new Facebook\Facebook(array(
					'app_id' => $api_key,
					'app_secret' => $api_secret,
			));

			$response = $fb->get('/' . $sync_user . '/events?fields=id,name,description,start_time,end_time,updated_time', $token);
			$items = $response->getDecodedBody();

			return !empty($items['data'])
				? $items['data']
				: array()
			;
		}
		return array();
	}

	private function get_schedule_key () {
		return 'last_' . __CLASS__ . '_run--';
	}
}


/**
 * Setup & auth handler.
 */
class Eab_Calendars_FacebookEventsImporter {

	private $_data;
	private $_oauth;

	private function __construct () {
		$this->_data = Eab_Options::get_instance();
		$this->_oauth = new Eab_Fbe_Oauth_FacebookEventsImporter;
	}

	public static function serve () {
		$me = new Eab_Calendars_FacebookEventsImporter;
		$me->_add_hooks();
	}

	private function _add_hooks () {
		add_action('eab-settings-after_api_settings', array($this, 'show_settings'));
		add_filter('eab-settings-before_save', array($this, 'save_settings'));

		add_action('wp_ajax_eab_fbe_import_authenticate', array($this, 'json_authenticate'));
		add_action('wp_ajax_eab_fbe_import_reset', array($this, 'json_reset'));
		add_action('wp_ajax_eab_fbe_import_resync_calendars', array($this, 'json_resync_calendars'));
	}

	function show_settings () {
		$tips = new PSource_HelpTooltips();
		$tips->set_icon_url(EAB_PLUGIN_URL . 'img/information.png' );

		$api_key = $this->_data->get_option('fbe_importer-client_id');
		$api_secret = $this->_data->get_option('fbe_importer-client_secret');
		$is_authenticated = $this->_oauth->is_authenticated();

		$fb_user = false;
		$sync_user = $this->_data->get_option('fbe_importer-fb_user');
		if (!$sync_user && $is_authenticated) {
			$fb_user = $this->_oauth->get_fb_user();
			$sync_user = !empty($fb_user['id'])
				? $fb_user['id']
				: false
			;
		}

		$runs = array(
			'3600' => __('Stunde', 'eab'),
			'7200' => __('Zwei Stunden', 'eab'),
			'10800' => __('Drei Stunden', 'eab'),
			'21600' => __('Sechs Stunden', 'eab'),
			'43200' => __('Zwölf Stunde', 'eab'),
			'86400' => __('Tag', 'eab'),
		);
		$run_each = $this->_data->get_option('fbe_importer-run_each');
		$run_each = $run_each ? $run_each : 3600;

		$user = wp_get_current_user();
		$calendar_author = $this->_data->get_option('fbe_importer-calendar_author', $user->ID);
		$raw_authors = get_users(array('who' => 'authors'));
		$possible_authors = array_combine(
			wp_list_pluck($raw_authors, 'ID'),
			wp_list_pluck($raw_authors, 'display_name')
		);
		?>
		<div id="eab-settings-fbe_importer" class="eab-metabox postbox">
			<h3 class="eab-hndle"><?php _e('Facebook Events Import Einstellungen', 'eab'); ?></h3>
				<div class="eab-inside">
					<div class="eab-settings-settings_item" style="line-height:1.8em">
                        <table cellpadding="5" cellspacing="5" width="100%">
                                <tr>
                                        <td valign="top" width="400">
                                                <label style="width: 100%" for="psource_event-fbe_importer-client_id" id="psource_event_label-fbe_importer-client_id"><?php _e('App ID', 'eab'); ?> <?php echo $tips->add_tip(__('Gib hier Deine App-ID ein.', 'eab')); ?></label>
                                        </td>
                                        <td valign="top">
                                                <input type="text" size="85" id="psource_event-fbe_importer-client_id" name="fbe_importer[client_id]" value="<?php print $api_key; ?>" />
                                        </td>
                                </tr>
                                <tr>
                                        <td valign="top">
                                                <label style="width: 100%" for="psource_event-fbe_importer-client_id" id="psource_event_label-fbe_importer-client_id"><?php _e('App secret', 'eab'); ?> <?php echo $tips->add_tip(__('Gib hier Deine App-Geheimnummer ein.', 'eab')); ?></label>
                                        </td>
                                        <td valign="top">
                                                <input type="text" size="85" id="psource_event-fbe_importer-client_id" name="fbe_importer[client_secret]" value="<?php print $api_secret; ?>" />
                                        </td>
                                </tr>
                        </table>
					<div class="fbe_importer-auth_actions">
				<?php if ($is_authenticated && $api_key && $api_secret) { ?>
					<a href="#reset" class="button" id="fbe_import-reset"><?php _e('Reset', 'eab'); ?></a>
					<span><?php echo $tips->add_tip(__('Denke daran, auch die App-Berechtigungen zu widerrufen <a href="http://www.facebook.com/settings?tab=applications" target="_blank">hier</a>.', 'eab')); ?></span>
				<?php } else if ($api_key && $api_secret) { ?>
					<a href="#authenticate" class="button" id="fbe_import-authenticate"><?php _e('Authentifizieren', 'eab'); ?></a>
				<?php } else { ?>
					<p><em><?php _e('Gib Deine API-Informationen ein und speichere zuerst die Einstellungen.', 'eab'); ?></em></p>
				<?php } ?>
			</div>
		</div>
		<?php if ($is_authenticated) { ?>
		<div class="eab-settings-settings_item">
			<label><?php _e('Importiere Ereignisse für diese Facebook-Benutzer-ID:', 'eab'); ?></label>
			<input type="text" id="psource_event-fbe_importer-fb_user" name="fbe_importer[fb_user]" value="<?php esc_attr_e($sync_user); ?>" />
			<small><em><?php _e('Ändere dieses Feld nur, wenn Du sicher bist, was Du tust', 'eab'); ?></em></small>
		</div>
		<div class="eab-settings-settings_item">
			<label><?php _e('Führe den Importer aus:', 'eab'); ?></label>
			<select name="fbe_importer[run_each]">
			<?php foreach ($runs as $interval => $ilabel) { ?>
				<option value="<?php echo (int)$interval; ?>" <?php echo selected($interval, $run_each); ?>><?php echo $ilabel; ?></option>
			<?php } ?>
			</select>
		</div>
		<div class="eab-settings-settings_item">
			<label><?php _e('Weise diesem Benutzer importierte Ereignisse zu:', 'eab'); ?></label>
			<select name="fbe_importer[calendar_author]">
			<?php foreach ($possible_authors as $aid => $alabel) { ?>
				<option value="<?php echo $aid; ?>" <?php echo selected($aid, $calendar_author); ?>><?php echo $alabel; ?>&nbsp;</option>
			<?php } ?>
			</select>
			<span><?php echo $tips->add_tip(__('Wähle den Benutzer aus, der als importierter Ereignis-Host angezeigt werden soll.', 'eab')); ?></span>
		</div>
		<?php if ($fb_user) { ?>
		<div class="eab-settings-settings_item">
			<input type="submit" value="<?php esc_attr_e(__('Einstellungen speichern', 'eab')); ?>" />
		</div>
		<?php } // end if fb user?>
		<?php } // end if authenticated ?>
	</div>
</div>
<script type="text/javascript">
(function ($) {

function authenticate () {
	var loginWindow = window.open('https://facebook.com', "oauth_login", "scrollbars=no,resizable=no,toolbar=no,location=no,directories=no,status=no,menubar=no,copyhistory=no,height=400,width=800");
	$.post(ajaxurl, {
		"action": "eab_fbe_import_authenticate",
		"url": window.location.href
	}, function (data) {
		var href = data.url;
		loginWindow.location = href;
		var gTimer = setInterval(function () {
			try {
				if (loginWindow.location.hostname == window.location.hostname) {
					// We're back!
					clearInterval(gTimer);
					loginWindow.close();
					window.location.reload();
				}
			} catch (e) {}
		}, 300);
	}, "json");
	return false;
}

$(function () {
	$("#fbe_import-authenticate").on("click", authenticate);
	$("#fbe_import-reset").on("click", function () {
		$.post(ajaxurl, {"action": "eab_fbe_import_reset"}, function() {
			window.location.reload();
		});
		return false;
	});
	$("#fbe_import-resync").on("click", function () {
		$.post(ajaxurl, {"action": "eab_fbe_import_resync_calendars"}, window.location.reload);
		return false;
	});
});
})(jQuery);
</script>
<?php
	}

	function save_settings ( $options ) {
		$options['fbe_importer-client_id'] 			= $_POST['fbe_importer']['client_id'];
		$options['fbe_importer-client_secret'] 		= $_POST['fbe_importer']['client_secret'];
		$options['fbe_importer-fb_user'] 			= isset( $_POST['fbe_importer']['fb_user'] ) ? $_POST['fbe_importer']['fb_user'] : '';
		$options['fbe_importer-run_each'] 			= isset( $_POST['fbe_importer']['run_each'] ) ? $_POST['fbe_importer']['run_each'] : '';
		$options['fbe_importer-sync_calendars'] 	= isset( $_POST['fbe_importer']['sync_calendars'] ) ? $_POST['fbe_importer']['sync_calendars'] : '';
		$options['fbe_importer-calendar_author'] 	= isset( $_POST['fbe_importer']['calendar_author'] ) ? $_POST['fbe_importer']['calendar_author'] : '';
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
		$raw_calendars = $this->_fbe->get_calendars();
		foreach ($raw_calendars as $calendar) {
			$calendars[$calendar['id']] = $calendar['summary'];
		}
		$this->_data->set_option('fbe_importer-cached_calendars', $calendars);
		$this->_data->update();
		die;
	}

	private function _get_cached_calendars () {
		$calendars = $this->_data->get_option('fbe_importer-cached_calendars', array());
		return $calendars
			? $calendars
			: array()
		;
	}
}

Eab_Calendars_FacebookEventsImporter::serve();
Eab_Fbe_Importer_FacebookEventsImporter::serve();
