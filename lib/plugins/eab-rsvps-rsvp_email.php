<?php
/*
Plugin Name: E-Mail auf RSVP senden
Description: Sende Deinen Benutzer automatisch eine E-Mail zum Ereignis-RSVP
Plugin URI: https://n3rds.work/piestingtal-source-project/eventsps-das-eventmanagment-fuer-wordpress/
Version: 1.1
Author: DerN3rd
AddonType: Email, RSVP
*/


class Eab_Events_RsvpEmail_Codec extends Eab_Macro_Codec {

	public function __construct ($event_id=false, $user_id=false) {
		parent::__construct($event_id, $user_id);
		$this->_macros = apply_filters('eab-events-rsvp_email-codec-macros', $this->_macros);
	}

	public function expand ($str, $filter=false) {
		return apply_filters('eab-events-rsvp_email-codec-expand', parent::expand($str, $filter), $this->_event);
	}

}

class Eab_Events_RsvpEmail {

	private $_data;

	function __construct () {
		$this->_data = Eab_Options::get_instance();
	}

	public static function serve () {
		$me = new Eab_Events_RsvpEmail;
		$me->_add_hooks();
	}

	private function _add_hooks () {
		add_action('eab-settings-after_plugin_settings', array($this, 'show_settings'));
		add_filter('eab-settings-before_save', array($this, 'save_settings'));

		add_action('wp_ajax_eab_rsvp_email-preview_email', array($this, 'ajax_preview_email'));

		add_action('psource_event_booking_yes', array($this, 'dispatch_positive_rsvp_update'), 10, 2);
	}

	function dispatch_positive_rsvp_update ($event_id, $user_id) {
		$user = get_user_by('id', $user_id);
		$from = $this->_data->get_option('eab_rsvps-email-from');
		$from = $from ? $from : get_option('admin_email');
		$subject = $this->_data->get_option('eab_rsvps-email-subject');
		$body = $this->_data->get_option('eab_rsvps-email-body');
		
		$from_name = $this->_data->get_option('eab_rsvps-email-from-name');
		$from_name = ! empty( $from_name ) ? $from_name : get_bloginfo( 'name' );

		if (!is_email($user->user_email) || empty($from) || empty($subject) || empty($body)) return false;
		$headers = array(
			'From: ' . $from_name . ' <' . $from . '>',
			'Content-Type: ' . $this->email_charset() . '; charset="' . get_option('blog_charset') . '"'
		);

		$codec = new Eab_Events_RsvpEmail_Codec($event_id, $user_id);
		add_filter('wp_mail_content_type', array($this, 'email_charset'));
		wp_mail(
			$user->user_email, 
			$codec->expand($subject, Eab_Macro_Codec::FILTER_TITLE),
			$codec->expand($body, Eab_Macro_Codec::FILTER_BODY),
			$headers
		);
		remove_filter('wp_mail_content_type', array($this, 'email_charset'));
	}

	function email_charset () { return 'text/html'; }

	function ajax_preview_email () {
		$data = stripslashes_deep($_POST);
		$event_id = !empty($data['event_id']) ? $data['event_id'] : false;
		if (!$event_id) die;
		$user = wp_get_current_user();
		$codec = new Eab_Events_RsvpEmail_Codec($event_id, $user->ID);
		die(
			'<strong>' . $codec->expand($data['subject'], Eab_Macro_Codec::FILTER_TITLE) . '</strong>' .
			'<div>' . $codec->expand($data['body'], Eab_Macro_Codec::FILTER_BODY) . '</div>'
		);
	}

	function save_settings ($options) {
		$data = stripslashes_deep($_POST);
		$options['eab_rsvps-email-from'] = @$data['eab_rsvps']['email-from'];
		$options['eab_rsvps-email-from-name'] = @$data['eab_rsvps']['email-from-name'];
		$options['eab_rsvps-email-subject'] = @$data['eab_rsvps']['email-subject'];
		$options['eab_rsvps-email-body'] = @$data['eab_rsvps-email-body'];
		return $options;
	}

	function show_settings () {
		$tips = new PSource_HelpTooltips();
		$tips->set_icon_url(EAB_PLUGIN_URL . 'img/information.png' );
		
		$from = $this->_data->get_option('eab_rsvps-email-from');
		$from = $from ? $from : get_option('admin_email');
		$from_name = $this->_data->get_option('eab_rsvps-email-from-name');
		$from_name = ! empty( $from_name ) ? $from_name : get_bloginfo( 'name' );
		$subject = $this->_data->get_option('eab_rsvps-email-subject');
		$body = $this->_data->get_option('eab_rsvps-email-body');
		
		$codec = new Eab_Events_RsvpEmail_Codec;
		$macros = join('</code>, <code>', $codec->get_macros());

		$events = Eab_CollectionFactory::get_upcoming_events(eab_current_time(), array('posts_per_page' => 10));
		?>
<div id="eab-settings-eab_rsvps_email" class="eab-metabox postbox">
	<h3 class="eab-hndle"><?php _e('RSVP Email Einstellungen', 'eab'); ?></h3>
	<div class="eab-inside">
		<div class="eab-settings-settings_item">
	    	<label for="eab_event-eab_rsvps-from-name"><?php _e('Vom E-Mail-Namen', 'eab'); ?></label>
			<span><?php echo $tips->add_tip(__('Dies ist der Name, von dem die RSVP-E-Mail gesendet wird', 'eab')); ?></span>
			<input type="text" id="eab_event-eab_rsvps-from-name" name="eab_rsvps[email-from-name]" value="<?php esc_attr_e($from_name); ?>" />
	    </div>
		<div class="eab-settings-settings_item">
	    	<label for="eab_event-eab_rsvps-from"><?php _e('Von der Email Adresse', 'eab'); ?></label>
			<span><?php echo $tips->add_tip(__('Dies ist die Adresse, von der aus die RSVP-E-Mail gesendet wird', 'eab')); ?></span>
			<input type="text" id="eab_event-eab_rsvps-from" name="eab_rsvps[email-from]" value="<?php esc_attr_e($from); ?>" />
	    </div>
	    <div class="eab-settings-settings_item">
	    	<label for="eab_event-eab_rsvps-subject"><?php _e('E-Mail Betreff', 'eab'); ?></label>
			<span><?php echo $tips->add_tip(sprintf(__('Dies ist der E-Mail-Betreff. Du kannst diese Makros verwenden: <code>%s</code>', 'eab'), $macros)); ?></span>
			<input type="text" class="widefat" id="eab_event-eab_rsvps-subject" name="eab_rsvps[email-subject]" value="<?php esc_attr_e($subject); ?>" />
	    </div>
	    <div class="eab-settings-settings_item">
	    	<label for="eab_event-eab_rsvps-body"><?php _e('Nachrichtentext', 'eab'); ?></label>
			<span><?php echo $tips->add_tip(sprintf(__('Dies ist der E-Mail-Text. Du kannst diese Makros verwenden: <code>%s</code>', 'eab'), $macros)); ?></span>
			<?php wp_editor($body, 'eab_rsvps-email-body', array(
				'name' => 'eab_rsvps-email-body',
			)); ?>
	    </div>
	    <div class="eab-settings-settings_item"><small><?php printf(__('Du kannst diese Makros in Betreff und Nachrichtentext verwenden: <code>%s</code>', 'eab'), $macros) ?></small></div>
	<?php if ($events) { ?>
	    <div class="eab-settings-settings_item">
	    	<input type="button" class="button" id="eab_event-eab_rsvps-preview" value="<?php esc_attr_e(__('Vorschau', 'eab')); ?>" />
	    	<?php _e('Verwenden dieser Ereignisdaten:', 'eab'); ?>
	    	<select id="eab_event-eab_rsvps-events">
	    	<?php foreach ($events as $event) { ?>
	    		<option value="<?php esc_attr_e($event->get_id()); ?>"><?php echo $event->get_title(); ?></option>
	    	<?php } ?>
	    	</select>
	    	<div id="eab_event-eab_rsvp-email_preview_container" style="line-height: 1.2em"></div>
	    </div>
	<?php } ?>
	</div>
</div>
<script type="text/javascript">
(function ($) {
$(function () {
	var $container = $("#eab_event-eab_rsvp-email_preview_container"),
		$subject = $("#eab_event-eab_rsvps-subject"),
		$events = $("#eab_event-eab_rsvps-events")
	;
	$("#eab_event-eab_rsvps-preview").on("click", function () {
		var body_string = (tinyMCE && tinyMCE.activeEditor 
			? tinyMCE.activeEditor.getContent()
			: $("#eab_rsvps-email-body").val()
		);
		$container.html('<?php echo esc_js(__("Einen Augenblick... ", 'eab')); ?>');
		$.post(ajaxurl, {
			"action": "eab_rsvp_email-preview_email",
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
Eab_Events_RsvpEmail::serve();
