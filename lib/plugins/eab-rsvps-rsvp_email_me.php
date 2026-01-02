<?php
/*
Plugin Name: Benachrichtigung per RSVP senden
Description: Sende automatisch eine Benachrichtigung an Dich selbst und/oder den Ereignisautor, wenn sich ein Benutzer meldet
Plugin URI: https://n3rds.work/piestingtal-source-project/eventsps-das-eventmanagment-fuer-wordpress/
Version: 1.1
Author: DerN3rd
AddonType: Email, RSVP
*/


class Eab_Events_RsvpEmailMe_Codec extends Eab_Macro_Codec {

	public function __construct ($event_id=false, $user_id=false) {
		parent::__construct($event_id, $user_id);
		$this->_macros[] = 'HAS_PAID';
		$this->_macros = apply_filters('eab-events-rsvp_email_me-codec-macros', $this->_macros);
	}

	public function expand ($str, $filter=false) {
		return apply_filters('eab-events-rsvp_email_me-codec-expand', parent::expand($str, $filter), $this->_event);
	}

	public function replace_has_paid () {
		if (!$this->_event->is_premium()) return '';

		$has_paid = apply_filters('eab-events-rsvp_email_me-codec-message-has_paid', __('Der Benutzer hat für die Veranstaltung bezahlt.', 'eab'));
		$not_paid = apply_filters('eab-events-rsvp_email_me-codec-message-not_paid', __('Der Benutzer hat die Veranstaltung nicht bezahlt.', 'eab'));

		return $this->_event->user_paid($this->_user->ID)
			? $has_paid
			: $not_paid
		;
	}

}

class Eab_Events_RsvpEmailMe {

	private $_data;

	function __construct () {
		$this->_data = Eab_Options::get_instance();
	}

	public static function serve () {
		$me = new Eab_Events_RsvpEmailMe;
		$me->_add_hooks();
	}

	private function _add_hooks () {
		add_action('eab-settings-after_plugin_settings', array($this, 'show_settings'));
		add_filter('eab-settings-before_save', array($this, 'save_settings'));

		add_action('wp_ajax_eab_rsvp_email_me-preview_email', array($this, 'ajax_preview_email'));

		if ($this->_data->get_option('eab_rsvps-email_me-positive_rsvp')) {
			add_action('psource_event_booking_yes', array($this, 'dispatch_positive_rsvp_update'), 10, 2);
		}
		if ($this->_data->get_option('eab_rsvps-email_me-paid_rsvp')) {
			add_action('eab-ipn-event_paid', array($this, 'dispatch_paid_rsvp_update'), 10, 3);
		}
	}

	function dispatch_positive_rsvp_update ($event_id, $user_id) {
		$this->_send_notification_email($event_id, $user_id);
	}

	function dispatch_paid_rsvp_update ($event_id, $amount, $booking_id) {
		$booking = Eab_EventModel::get_booking($booking_id);
		if (!is_object($booking) || empty($booking->event_id) || empty($booking->user_id) || $booking->event_id != $event_id) return false;

		$this->_send_notification_email($event_id, $booking->user_id);
	}

	private function _send_notification_email ($event_id, $user_id) {
		$user = get_user_by('id', $user_id);
		$subject = $this->_data->get_option('eab_rsvps-email_me-subject');
		$body = $this->_data->get_option('eab_rsvps-email_me-body');
		$admin_email = get_option('admin_email');
                
        $from = $this->_data->get_option('eab_rsvps-email_me-from');
		$from = $from ? $from : get_option('admin_email');
		
		$from_name = $this->_data->get_option('eab_rsvps-email_me-from-name');
		$from_name = ! empty( $from_name ) ? $from_name : get_bloginfo( 'name' );

		if (empty($subject) || empty($body)) return false;
		$headers = array(
			'From: ' . $from_name . ' <' . $from . '>',
            'From: ' . $from,
			'Content-Type: ' . $this->email_charset() . '; charset="' . get_option('blog_charset') . '"'
		);

		$codec = new Eab_Events_RsvpEmailMe_Codec($event_id, $user_id);
		add_filter('wp_mail_content_type', array($this, 'email_charset'));
		
		if ($this->_data->get_option('eab_rsvps-email_me-notify_admin')) {
			wp_mail(
				$admin_email,
				$codec->expand($subject, Eab_Macro_Codec::FILTER_TITLE),
				$codec->expand($body, Eab_Macro_Codec::FILTER_BODY),
				$headers
			);
		}
		if ($this->_data->get_option('eab_rsvps-email_me-notify_author')) {
			$event = new Eab_EventModel(get_post($event_id));
			$author_id = $event->get_author();
			if ($author_id) {
				$author = get_user_by('id', $author_id);
				if ($author->user_email != $admin_email) {
					wp_mail(
						$author->user_email,
						$codec->expand($subject, Eab_Macro_Codec::FILTER_TITLE),
						$codec->expand($body, Eab_Macro_Codec::FILTER_BODY),
						$headers
					);
				}
			}
		}
		remove_filter('wp_mail_content_type', array($this, 'email_charset'));
	}

	function email_charset () { return 'text/html'; }

	function ajax_preview_email () {
		$data = stripslashes_deep($_POST);
		$event_id = !empty($data['event_id']) ? $data['event_id'] : false;
		if (!$event_id) die;
		$user = wp_get_current_user();
		$codec = new Eab_Events_RsvpEmailMe_Codec($event_id, $user->ID);
		$body = !empty($data['body'])
			? $data['body']
			: $this->_data->get_option('eab_rsvps-email_me-body')
		;
		die(
			'<strong>' . $codec->expand($data['subject'], Eab_Macro_Codec::FILTER_TITLE) . '</strong>' .
			'<div>' . $codec->expand($body, Eab_Macro_Codec::FILTER_BODY) . '</div>'
		);
	}

	function save_settings ($options) {
		$data = stripslashes_deep($_POST);
		$options['eab_rsvps-email_me-positive_rsvp'] = @$data['eab_rsvps_me']['email-positive_rsvp'];
		$options['eab_rsvps-email_me-paid_rsvp'] = @$data['eab_rsvps_me']['email-paid_rsvp'];
		$options['eab_rsvps-email_me-notify_admin'] = @$data['eab_rsvps_me']['email-notify_admin'];
		$options['eab_rsvps-email_me-notify_author'] = @$data['eab_rsvps_me']['email-notify_author'];
		$options['eab_rsvps-email_me-subject'] = @$data['eab_rsvps_me']['email-subject'];
        $options['eab_rsvps-email_me-from'] = @$data['eab_rsvps_me']['email-from'];
		$options['eab_rsvps-email_me-from-name'] = @$data['eab_rsvps_me']['email-from-name'];
		$options['eab_rsvps-email_me-body'] = @$data['eab_rsvps-email_me-body'];
		return $options;
	}

	function show_settings () {
		$tips = new PSource_HelpTooltips();
		$tips->set_icon_url(EAB_PLUGIN_URL . 'img/information.png' );
		
		$positive_rsvp = $this->_data->get_option('eab_rsvps-email_me-positive_rsvp') ? 'checked="checked"' : '';
		$paid_rsvp = $this->_data->get_option('eab_rsvps-email_me-paid_rsvp') ? 'checked="checked"' : '';
		$notify_admin = $this->_data->get_option('eab_rsvps-email_me-notify_admin') ? 'checked="checked"' : '';
		$notify_author = $this->_data->get_option('eab_rsvps-email_me-notify_author') ? 'checked="checked"' : '';
		$subject = $this->_data->get_option('eab_rsvps-email_me-subject');
        $from = $this->_data->get_option('eab_rsvps-email_me-from');
		$from_name = $this->_data->get_option('eab_rsvps-email_me-from-name');
		$body = $this->_data->get_option('eab_rsvps-email_me-body');
		
		$codec = new Eab_Events_RsvpEmailMe_Codec;
		$macros = join('</code>, <code>', $codec->get_macros());

		$events = Eab_CollectionFactory::get_upcoming_events(eab_current_time(), array('posts_per_page' => 10));
		?>
<div id="eab-settings-eab_rsvps_me" class="eab-metabox postbox">
	<h3 class="eab-hndle"><?php _e('Einstellungen für die RSVP-Benachrichtigung per E-Mail', 'eab'); ?></h3>
	<div class="eab-inside">
		<div class="eab-settings-settings_item">
	    	<label><?php _e('Sende ein Update', 'eab'); ?></label>
			<br />
			<label for="eab_event-eab_rsvps-me-positive_rsvp" style="display:block; line-height:1.8em">
				<input type="hidden" name="eab_rsvps_me[email-positive_rsvp]" value="" />
				<input type="checkbox" id="eab_event-eab_rsvps-me-positive_rsvp" name="eab_rsvps_me[email-positive_rsvp]" value="1" <?php echo $positive_rsvp; ?> />
				<?php _e('Auf alle positiven RSVPs', 'eab'); ?>
			</label>
			<label for="eab_event-eab_rsvps-me-paid_rsvp" style="display:block; line-height:1.8em">
				<input type="hidden" name="eab_rsvps_me[email-paid_rsvp]" value="" />
				<input type="checkbox" id="eab_event-eab_rsvps-me-paid_rsvp" name="eab_rsvps_me[email-paid_rsvp]" value="1" <?php echo $paid_rsvp; ?> />
				<?php _e('Wenn der Benutzer für eine bezahlte Veranstaltung bezahlt', 'eab'); ?>
			</label>
	    </div>
	    <div class="eab-settings-settings_item">
	    	<label><?php _e('Benachrichtigen', 'eab'); ?></label>
			<br />
			<label for="eab_event-eab_rsvps-me-notify_admin" style="display:block; line-height:1.8em">
				<input type="hidden" name="eab_rsvps_me[email-notify_admin]" value="" />
				<input type="checkbox" id="eab_event-eab_rsvps-me-notify_admin" name="eab_rsvps_me[email-notify_admin]" value="1" <?php echo $notify_admin; ?> />
				<?php _e('Seitenadministrator', 'eab'); ?>
			</label>
			<label for="eab_event-eab_rsvps-me-notify_author" style="display:block; line-height:1.8em">
				<input type="hidden" name="eab_rsvps_me[email-notify_author]" value="" />
				<input type="checkbox" id="eab_event-eab_rsvps-me-notify_author" name="eab_rsvps_me[email-notify_author]" value="1" <?php echo $notify_author; ?> />
				<?php _e('Eventautor', 'eab'); ?>
			</label>
	    </div>
        <div class="eab-settings-settings_item">
	    	<label for="eab_event-eab_rsvps-me-from-name"><?php _e('E-Mail von Namen', 'eab'); ?></label>
			<span><?php echo $tips->add_tip('Dies ist Dein E-Mail von Name'); ?></span>
			<input type="text" class="widefat" id="eab_event-eab_rsvps-me-from-name" name="eab_rsvps_me[email-from-name]" value="<?php esc_attr_e($from_name); ?>" />
	    </div>
		<div class="eab-settings-settings_item">
	    	<label for="eab_event-eab_rsvps-me-from"><?php _e('Email von', 'eab'); ?></label>
			<span><?php echo $tips->add_tip('Dies ist Deine E-Mail-Adresse'); ?></span>
			<input type="text" class="widefat" id="eab_event-eab_rsvps-me-from" name="eab_rsvps_me[email-from]" value="<?php esc_attr_e($from); ?>" />
	    </div>
	    <div class="eab-settings-settings_item">
	    	<label for="eab_event-eab_rsvps-me-subject"><?php _e('E-Mail Betreff', 'eab'); ?></label>
			<span><?php echo $tips->add_tip(sprintf(__('Dies ist der E-Mail-Betreff. Du kannst diese Makros verwenden: <code>%s</code>', 'eab'), $macros)); ?></span>
			<input type="text" class="widefat" id="eab_event-eab_rsvps-me-subject" name="eab_rsvps_me[email-subject]" value="<?php esc_attr_e($subject); ?>" />
	    </div>
	    <div class="eab-settings-settings_item">
	    	<label for="eab_event-eab_rsvps-me-body"><?php _e('Nachrichtentext', 'eab'); ?></label>
			<span><?php echo $tips->add_tip(sprintf(__('Dies ist der E-Mail-Text. Du kannst diese Makros verwenden: <code>%s</code>', 'eab'), $macros)); ?></span>
			<?php wp_editor($body, 'eab_rsvps-email_me-body', array(
				'name' => 'eab_rsvps_me-email_me-body',
			)); ?>
	    </div>
	    <div class="eab-settings-settings_item"><small><?php printf(__('Du kannst diese Makros in Betreff und Nachrichtentext verwenden: <code>%s</code>', 'eab'), $macros) ?></small></div>
	<?php if ($events) { ?>
	    <div class="eab-settings-settings_item">
	    	<input type="button" class="button" id="eab_event-eab_rsvps-me-preview" value="<?php esc_attr_e(__('Vorschau', 'eab')); ?>" />
	    	<?php _e('using this event data:', 'eab'); ?>
	    	<select id="eab_event-eab_rsvps-me-events">
	    	<?php foreach ($events as $event) { ?>
	    		<option value="<?php esc_attr_e($event->get_id()); ?>"><?php echo $event->get_title(); ?></option>
	    	<?php } ?>
	    	</select>
	    	<div id="eab_event-eab_rsvp_me-email_preview_container" style="line-height: 1.2em"></div>
	    </div>
	<?php } ?>
	</div>
</div>
<script type="text/javascript">
(function ($) {
$(function () {
	var $container = $("#eab_event-eab_rsvp_me-email_preview_container"),
		$subject = $("#eab_event-eab_rsvps-me-subject"),
		$events = $("#eab_event-eab_rsvps-me-events")
	;
	$("#eab_event-eab_rsvps-me-preview").on("click", function () {
		var body_string = (tinyMCE && tinyMCE.activeEditor 
			? tinyMCE.activeEditor.getContent()
			: $("#eab_rsvps_me-email_me-body").val()
		);
		$container.html('<?php echo esc_js(__("Einen Augenblick... ", 'eab')); ?>');
		$.post(ajaxurl, {
			"action": "eab_rsvp_email_me-preview_email",
			"subject": $subject.val(),
			"body": body_string,
			"event_id": $events.val()
		}, function (data) {
			$container.html(data);
		}, 'html');
	});
})
})(jQuery);
</script>
		<?php
	}
}
Eab_Events_RsvpEmailMe::serve();
