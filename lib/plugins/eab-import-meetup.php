<?php
/*
Plugin Name: Import: Meetup.com
Description: Ermöglicht das Importieren von Ereignissen von meetup.com sowie das Erlernen der benutzerdefinierten Themen als Ereigniskategorien.
Plugin URI: https://n3rds.work/piestingtal-source-project/eventsps-das-eventmanagment-fuer-wordpress/
Version: 1.1
Author: DerN3rd
AddonType: Integration
*/

if (!class_exists('PSource_Wp_Meetup')) {
	class PSource_Wp_Meetup {

		private $_key;

		const SERVER = 'https://api.meetup.com';

		const ENDPOINT_EVENTS = '/2/events';
		const ENDPOINT_OPEN_EVENTS = '/2/open_events';
		const ENDPOINT_EVENT = '/2/event/:id:';
		const ENDPOINT_MEMBER = '/2/member/:id:';



		/**
		 * Fetches the meetup.com member JSON.
		 * @return mixed false on failure, JSON member hash on success.
		 */
		public function get_member ($user_id) {
			return $this->_read(self::ENDPOINT_MEMBER, array('id' => $user_id));
		}

		/**
		 * Fetch a list of user-organized events.
		 * @return mixed false on failure, array of event hashes on success
		 */
		public function get_events_for ($user_id) {
			return $this->_read(self::ENDPOINT_EVENTS, array('member_id' => $user_id));
		}

		/**
		 * Fetches a list of geolocated events near a city.
		 * @param string $city Valid city name.
		 * @param string $country Valid country code.
		 * @param float $radius (optional)Distance radius, in miles.
		 * @return mixed false on failure, array of event hashes on success.
		 */
		public function get_nearby_events_by_city ($city, $country, $radius=25) {
			return $this->_read(self::ENDPOINT_OPEN_EVENTS, array(
				'city' => $city,
				'country' => $country,
				'radius' => (float)$radius,
			));
		}

		/**
		 * Fetches a list of geolocated events by lat/lng pair.
		 * @param float $lat Latitude.
		 * @param float $lng Longitude.
		 * @param float $radius (optional)Distance radius, in miles.
		 * @return mixed false on failure, array of event hashes on success.
		 */
		public function get_nearby_events ($lat, $lng, $radius=25) {
			return $this->_read(self::ENDPOINT_OPEN_EVENTS, array(
				'lat' => (float)$lat,
				'lon' => (float)$lng,
				'radius' => (float)$radius,
			));
		}



		protected function _set_key ($key) { $this->_key = $key; }

		protected function _read ($what, $params=array()) {
			if (!is_array($params)) $params = array();
			
			$what = $this->_route($what);
			if (!empty($params['id'])) {
				$what = preg_replace('/:id:/', $params['id'], $what);
				unset($params['id']);
			}
			if (empty($what)) return false;

			$query = http_build_query($params);
			$url = self::SERVER . $what . '?key=' . $this->_key . (!empty($query) ? "&{$query}" : '');

			$request = wp_remote_get($url, array(
				'sslverify' => false
			));
			if (200 != wp_remote_retrieve_response_code($request)) return false; // Error
			$body = wp_remote_retrieve_body($request);

			return json_decode($body, true);
		}

		private function _route ($what) {
			if (preg_match('/\//', $what)) return $what;
			$what = strtoupper('endpoint_' . preg_replace('/[^a-z_0-9]/i', '', $what));
			$const = constant("PSource_Wp_Meetup::{$what}");
			
			return $const;
		}
	}
}

class Eab_Wp_Meetup extends PSource_Wp_Meetup {
	
	private $_data;

	public function __construct () {
		$this->_data = Eab_Options::get_instance();
		$key = $this->_data->get_option('meetup_importer-api_key');
		if (!empty($key)) $this->_set_key($key);
	}	

	/**
	 * Fetches the member topics.
	 * @return array Empty on failure, an array of topic hashes on success.
	 */
	public function get_topics_for ($user_id) {
		$member = $this->get_member($user_id);
		if (empty($member) || empty($member['topics'])) return array();

		return $member['topics'];
	}

}


class Eab_Calendars_MeetupImporter {
	
	private $_data;
	private $_meetup;

	private function __construct () {
		$this->_data = Eab_Options::get_instance();
		$this->_meetup = new Eab_Wp_Meetup;
	}

	public static function serve () {
		$me = new self;
		$me->_add_hooks();
	}

	private function _add_hooks () {
		add_action('eab-settings-after_api_settings', array($this, 'show_settings'));
		add_filter('eab-settings-before_save', array($this, 'save_settings'));
		
		add_action('wp_ajax_eab_meetup-import_user_events', array($this, 'json_import_user_events'));
		add_action('wp_ajax_eab_meetup-import_nearby_events', array($this, 'json_import_nearby_events'));
		add_action('wp_ajax_eab_meetup-import_user_topics', array($this, 'json_import_user_topics'));
	}

	public function json_import_user_topics () {
		$user_id = $this->_data->get_option('meetup_importer-user_id');
		if (empty($user_id)) wp_send_json(array(
			'status' => 0,
			'msg' => __('Bitte konfiguriere die API-Informationen und speichere zuerst die Einstellungen', 'eab'),
		));
		$topics = $this->_meetup->get_topics_for($user_id);
		if (empty($topics)) wp_send_json(array(
			'status' => 1,
			'msg' => __('Keine zu importierenden Themen', 'eab'),
		));
		$imported = get_option('eab-meetup_importer-imported_topics', array());
		$results = array();
		foreach ($topics as $topic) {
			if (empty($topic['id']) || in_array($topic['id'], $imported)) continue;
			$term = !empty($topic['name']) ? $topic['name'] : false;
			$slug = !empty($topic['urlkey']) ? $topic['urlkey'] : false;
			if (empty($term) || empty($slug)) continue;
			$data = array(
				'description' => $term,
				'slug' => $slug,
			);
			$result = wp_insert_term($term, 'eab_events_category', $data);
			if (is_wp_error($result) || empty($result['term_id'])) continue;

			$imported[] = $topic['id'];
			$results[] = "<a href='" . admin_url('/edit-tags.php?action=edit&taxonomy=eab_events_category&tag_ID=' . $result['term_id'] . '&post_type=' . Eab_EventModel::POST_TYPE) . "'>{$term}</a>";
		}
		update_option('eab-meetup_importer-imported_topics', $imported);
		$results[] = sprintf(__('%s Themen erfolgreich als Ereigniskategorien importiert', 'eab'), count($results));
		wp_send_json(array(
			'status' => 1,
			'msg' => join('<br />', $results),
		));
	}

	public function json_import_user_events () {
		$user_id = $this->_data->get_option('meetup_importer-user_id');
		if (empty($user_id)) wp_send_json(array(
			'status' => 0,
			'msg' => __('Bitte konfiguriere die API-Informationen und speichere zuerst die Einstellungen', 'eab'),
		));
		$events = $this->_meetup->get_events_for($user_id);
		if (empty($events) || empty($events['results'])) wp_send_json(array(
			'status' => 1,
			'msg' => __('Es wurden keine Ereignisse importiert', 'eab'),
		));

		$imported = $this->_import_events($events);
		if (empty($imported)) wp_send_json(array(
			'status' => 0,
			'msg' => __('Keine Ereignisse importiert.', 'eab'),
		));

		$result = array();
		foreach ($imported as $event_id) {
			if (empty($event_id)) continue;
			$event = new Eab_EventModel(get_post($event_id));
			$result[] = '<a href="' . admin_url('post.php?action=edit&post=' . $event->get_id()) . '">' . $event->get_title() . '</a>';
		}
		$result[] = sprintf(__('%s Ereignisse erfolgreich importiert', 'eab'), count($result));
		wp_send_json(array(
			'status' => 1,
			'msg' => join('<br />', $result),
		));
	}

	public function json_import_nearby_events () {
		
		$data = stripslashes_deep($_POST);
		$lat = (float)$data['lat'];
		$lng = (float)$data['lng'];

		if (empty($lat) || empty($lng)) wp_send_json(array(
			'status' => 0,
			'msg' => __('Ungültiger Standort', 'eab'),
		));
		$events = $this->_meetup->get_nearby_events($lat, $lng, 100);
		if (empty($events) || empty($events['results'])) wp_send_json(array(
			'status' => 1,
			'msg' => __('Es wurden keine Ereignisse importiert', 'eab'),
		));

		$imported = $this->_import_events($events);
		if (empty($imported)) wp_send_json(array(
			'status' => 0,
			'msg' => __('Keine Ereignisse importiert.', 'eab'),
		));

		$result = array();
		foreach ($imported as $event_id) {
			if (empty($event_id)) continue;
			$event = new Eab_EventModel(get_post($event_id));
			$result[] = '<a target="_blank" href="' . admin_url('post.php?action=edit&post=' . $event->get_id()) . '">' . $event->get_title() . '</a>';
		}
		$result[] = sprintf(__('%s Ereignisse erfolgreich importiert', 'eab'), count($result));
		wp_send_json(array(
			'status' => 1,
			'msg' => join('<br />', $result),
		));
	}

	private function _import_events ($events) {
		if (empty($events) || empty($events['results'])) return false;
		$results = array();
		foreach ($events['results'] as $event) {
			$post_id = $this->_import_event($event);
			if (!empty($post_id)) $results[] = $post_id;
		}
		return $results;
	}

	private function _import_event ($event) {
		$time = !empty($event['time']) && (int)$event['time']
			? (int)$event['time']/1000
			: false
		;
		if (!$time) return false;
                $offset = apply_filters( 'eab_meetup_offset_adjust', get_option( 'gmt_offset' ) );
                $time = $time + ( $offset * 60 * 60 );
                
		if (empty($event['status']) || 'upcoming' != $event['status']) return false;

		// Is the event already imported?
		global $wpdb;
		$id = esc_sql($event['id']);
		$is_imported = $wpdb->get_var("SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key='_eab_meetup_id' AND meta_value='{$id}'");
		//if (!empty($is_imported)) return false;

		$post = array(
			'post_type' => Eab_EventModel::POST_TYPE,
			'post_status' => 'publish',
			'post_title' => isset($event['name']) ? $event['name']  : '',
			'post_content' => wp_kses_post( (isset($event['description']) ? $event['description']  : '') ),
			'post_date' => date('Y-m-d H:i:s'),
			'post_author' => get_current_user_id(),
		);
		$meta = array(
			'_eab_meetup_id' => $event['id'],
			'_eab_meetup_original' => $event,
			'psource_event_status' => Eab_EventModel::STATUS_OPEN, // Open by default
			'psource_event_start' => date('Y-m-d H:i:s', $time),
			'psource_event_end' => date('Y-m-d 23:59:00', $time),
			'psource_event_no_end' => 1,
		);

		// Import event
		$post_id = wp_insert_post($post);
		if (!$post_id) return false; // Something went very wrong
		foreach ($meta as $key => $value) {
			update_post_meta($post_id, $key, $value);
		}

		// Geolocate event
		if (!empty($event['venue'])) {
			$venue = !empty($event['venue']['name']) ? $event['venue']['name'] : false;
			if (class_exists('AgmMapModel') && !empty($event['venue']['lat']) && !empty($event['venue']['lon'])) {
				$model = new AgmMapModel;
				$map_id = $model->autocreate_map($post_id, $event['venue']['lat'], $event['venue']['lon'], false);
				//if ($map_id) $venue = "[map id='{$map_id}']";
				if ($map_id) $venue = false;
			}
			if (!empty($venue)) update_post_meta($post_id, 'psource_event_venue', $venue);
		}

		return $post_id;
	}

	function save_settings ($options) {
		$data = !empty($_POST['event_default']) ? stripslashes_deep($_POST['event_default']) : array();
		$options['meetup_importer-api_key'] = !empty($data['meetup_importer-api_key']) ? trim($data['meetup_importer-api_key']) : false;
		$options['meetup_importer-user_id'] = !empty($data['meetup_importer-user_id']) ? trim($data['meetup_importer-user_id']) : false;
		return $options;
	}

	function show_settings () {
		$tips = new PSource_HelpTooltips();
		$tips->set_icon_url(EAB_PLUGIN_URL . 'img/information.png' );

		$api_key = $this->_data->get_option('meetup_importer-api_key');
		$member_id = $this->_data->get_option('meetup_importer-user_id');
?>
<div id="eab-settings-meetup_importer" class="eab-metabox postbox">
	<h3 class="eab-hndle"><?php _e('Meetup.com import Einstellungen', 'eab'); ?></h3>
	<div class="eab-inside">
	    <div class="eab-settings-settings_item">
			<b><?php _e('API Einstellungen', 'eab'); ?></b>
			<div style="line-height:1.5em; padding-bottom:.5em;">
				<label for="eab-meetup_importer-api_key">
					<?php _e('API Schlüssel', 'eab'); ?>
					<input type="text" class="widefat" id="eab-meetup_importer-api_key" name="event_default[meetup_importer-api_key]" value="<?php echo esc_attr($api_key); ?>" />
				</label>
				<br />
				<label for="eab-meetup_importer-user_id">
					<?php _e('Meetup.com Member ID', 'eab'); ?>
					<input type="text" class="widefat" id="eab-meetup_importer-user_id" name="event_default[meetup_importer-user_id]" value="<?php echo esc_attr($member_id); ?>" />
				</label>
			</div>
	    </div>
		<div class="eab-settings-settings_item" id="eab-meetup_importer-actions">
		<?php if (!empty($api_key) && !empty($member_id)) { ?>
	    	<button type="button" class="button" id="eab-meetup_importer-import_user_events"><?php echo esc_html(__('Importiere meine Events', 'eab')); ?></button>
	    	<button type="button" class="button" id="eab-meetup_importer-import_location_events"><?php echo esc_html(__('Importiere Ereignisse in der Nähe meines aktuellen Standorts', 'eab')); ?></button>
	    	<button type="button" class="button" id="eab-meetup_importer-import_user_topics"><?php echo esc_html(__('Importiere meine Themen als Ereigniskategorien', 'eab')); ?></button>
                <?php if( ! is_ssl() ) : ?>
                <br>
                <em><?php _e( 'Wichtig: Um Ereignisse in der Nähe Deines aktuellen Standorts zu importieren, benötigst Du SSL', 'eab' ); ?></em>
                <br>
                <?php endif; ?>
	    	<div id="eab-meetup_importer-status" style="display:none"></div>
	    <?php } else { ?>
			<p class="note"><?php _e('Fülle zunächst die obigen API-Informationen aus und speichere Deine Einstellungen', 'eab'); ?></p>
	    <?php } ?>
	    </div>
	</div>
</div>
<script>
(function ($) {
$(function () {
	if (!navigator.geolocation) {
		$("#eab-meetup_importer-import_location_events").remove();
		return false;
	}
	var $target = $("#eab-meetup_importer-status");
	function write_status (msg) {
		$target.empty().html(msg).show();
	}
	// Import my events
	$("#eab-meetup_importer-import_user_events").on("click", function (e) {
		e.preventDefault();
		write_status("<?php echo esc_js(__('Bitte einen Augenblick...', 'eab')); ?>");
		$.post(ajaxurl, {
			action: 'eab_meetup-import_user_events'
		}, function (data) {
			if (data && "msg" in data && data.msg) write_status(data.msg);
			else write_status("<?php echo esc_js(__('Error', 'eab')); ?>");
		}, 'json');
		return false;
	});
	// Geolocated import
	$("#eab-meetup_importer-import_location_events").on("click", function (e) {
		e.preventDefault();
		write_status("<?php echo esc_js(__('Bitte einen Augenblick...', 'eab')); ?>");
                
		navigator.geolocation.getCurrentPosition(function (resp) {
			$.post(ajaxurl, {
				action: 'eab_meetup-import_nearby_events',
				lat: resp.coords.latitude,
				lng: resp.coords.longitude
			}, function (data) {
				if (data && "msg" in data && data.msg) write_status(data.msg);
				else write_status("<?php echo esc_js(__('Error', 'eab')); ?>");
			}, 'json')
		});
		return false;
	});
	// Import my topics
	$("#eab-meetup_importer-import_user_topics").on("click", function (e) {
		e.preventDefault();
		write_status("<?php echo esc_js(__('Bitte einen Augenblick...', 'eab')); ?>");
		$.post(ajaxurl, {
			action: 'eab_meetup-import_user_topics'
		}, function (data) {
			if (data && "msg" in data && data.msg) write_status(data.msg);
			else write_status("<?php echo esc_js(__('Fehler', 'eab')); ?>");
		}, 'json');
		return false;
	});
});
})(jQuery);
</script>
<?php
	}
}
Eab_Calendars_MeetupImporter::serve();