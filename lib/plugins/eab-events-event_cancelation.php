<?php
/*
Plugin Name: Absage der Veranstaltung
Description: Ermöglicht es Dir, Deine Veranstaltungen schnell abzubrechen und eine Benachrichtigungs-E-Mail an Deine Teilnehmer zu senden.
Plugin URI: https://n3rds.work/piestingtal-source-project/eventsps-das-eventmanagment-fuer-wordpress/
Version: 1.1
Author: DerN3rd
AddonType: Events
*/

class Eab_Events_EventCancel {

	const STATUS_CANCEL = 'cancel';
	const EVENT_SCHEDULE_QUEUE = 'eab-cancellation-queue';

	private $_data;

	private $_default_subject;
	private $_default_message;

	function __construct () {
		$this->_data = Eab_Options::get_instance();

		$this->_default_subject = __('Schlechte Nachrichten! EVENT_NAME wurde abgebrochen', 'eab');
		$this->_default_message = __("Hallo, USER_NAME\n\nUnfortunately, Leider wurde unser EVENT_LINK-Event abgesagt.", 'eab');
	}

	public static function serve () {
		$me = new Eab_Events_EventCancel;
		$me->_add_hooks();
	}

	private function _add_hooks () {
		add_action('eab-settings-after_plugin_settings', array($this, 'show_settings'));
		add_filter('eab-settings-before_save', array($this, 'save_settings'));

		if ($this->_data->get_option('eab_cancelations-hide_events')) {
			add_filter('eab-collection-forbidden_statuses', array($this, 'filter_forbidden_status'));
		}
		add_filter('eab-rsvps-rsvp_form', array($this, 'filter_cancel_rsvp_form'), 10, 2);
		add_filter('eab-event_meta-extra_event_status', array($this, 'inject_cancelled_status'), 10, 2);
		add_filter('eab-event_meta-after_event_status', array($this, 'inject_cancel_button'), 10, 2);

		// FPE
		add_filter('eab-events-fpe-event_meta-extra_event_status', array($this, 'inject_cancelled_status'), 10, 2);
		add_filter('eab-events-fpe-event_meta-after_event_status', array($this, 'inject_cancel_button'), 10, 2);

		add_action('wp_head', array($this, 'inject_public_styles'));

		add_action('eab_scheduled_jobs', array($this, 'send_queued_notifications'));

		add_action('wp_ajax_eab_cancellation_email-preview_email', array($this, 'ajax_preview_email'));
		add_action('wp_ajax_eab_cancellation_email-send_email', array($this, 'ajax_send_email'));
	}

	public function is_cancelled ($event) {
		return self::STATUS_CANCEL == $event->get_status();
	}

	function inject_public_styles () {
		echo <<<EOPublicCancellationCss
<style type="text/css">
.eab-event_cancelled p {
	color: #A00;
	font-weight: bold;
}
</style>
EOPublicCancellationCss;
	}

	function inject_cancelled_status ($options, $event) {
		if (!$this->is_cancelled($event)) return $options;
		$value = esc_attr(self::STATUS_CANCEL);
		$title = __('Abgebrochen', 'eab');
		return "{$options}<option value='{$value}' selected='selected'>{$title}</option>";
	}

	function inject_cancel_button ($after, $event) {
		if ($this->is_cancelled($event)) return $after;
		if (!$event->get_status()) return $after;
		$button = '' .
			'<div class="misc-eab-section">' .
				'<input type="button" class="button" id="eab-event-cancel_event" value="' . esc_attr(__('Ereignis abbrechen', 'eab')) . '" />' .
			'</div>' .
		'';
		$js = '<script type="text/javascript">(function ($) { $(function() {' .
			'$("#eab-event-cancel_event").on("click", function () {' .
				'$.post("' . admin_url('admin-ajax.php') . '", {' .
					'action: "eab_cancellation_email-send_email",' .
					'event_id: ' . $event->get_id() .
				'}, function () {' .
					'window.location.reload();' .
				'});' .
				'return false;' .
			'});' .
		'});})(jQuery);</script>';
		return $after . $button . $js;
	}

	function filter_forbidden_status ($statuses) {
		if (is_array($statuses)) $statuses[] = self::STATUS_CANCEL;
		return is_array($statuses) ? $statuses : array(self::STATUS_CANCEL);
	}

	function filter_cancel_rsvp_form ($form, $event) {
		return $this->is_cancelled($event)
			? $this->_get_cancelled_notice($event)
			: $form
		;
	}

	function _get_cancelled_notice ($event) {
		return '<div class="psourceevents-buttons eab-event_cancelled">' .
			'<p>' . __('Veranstaltung wurde abgesagt', 'eab') . '</p>' .
		'</div>';
	}

	function send_queued_notifications () {
		$all = $this->_get_events_schedule_queue();
		$limit = $this->_get_email_batch_limit();
		$counter = 0;
		foreach ($all as $event_id => $timestamp) {
			$counter += $this->_send_cancel_event_email($event_id);
			if ($counter >= $limit) break;
		}
	}

	function ajax_send_email () {
		$event_id = !empty($_POST['event_id']) ? (int)$_POST['event_id'] : false;
		if (!$event_id) die;

		$this->_cancel_event($event_id);
		die;
	}

	private function _cancel_event ($event_id) {
		$event = new Eab_EventModel(get_post($event_id));
		if ($event->is_recurring() && !$event->is_recurring_child()) {
			// Recurring root - cancel children too
			update_post_meta($event_id, 'psource_event_status', self::STATUS_CANCEL);
			$events = Eab_CollectionFactory::get_all_recurring_children_events($event);
			foreach ($events as $event) {
				$this->_cancel_event($event->get_id());
			}
		} else {
			// Regular event or single instance. All good
			update_post_meta($event_id, 'psource_event_status', self::STATUS_CANCEL);
			$this->_add_event_to_schedule_queue($event_id);
		}
	}

	private function _get_users_notification_queue ($event_id) {
		$already_sent = get_post_meta($event_id, 'eab-event_cancellation-sent_to', true);
		return is_array($already_sent) ? $already_sent : array();
	}

	private function _set_users_notification_queue ($event_id, $users) {
		return update_post_meta($event_id, 'eab-event_cancellation-sent_to', $users);
	}

	private function _get_events_schedule_queue () {
		$all = get_option(self::EVENT_SCHEDULE_QUEUE, array());
		asort($all);
		return $all;
	}

	private function _add_event_to_schedule_queue ($event_id) {
		$all = $this->_get_events_schedule_queue();
		if (empty($all[$event_id])) $all[$event_id] = time();
		return update_option(self::EVENT_SCHEDULE_QUEUE, $all);
	}

	private function _remove_event_from_schedule_queue ($event_id) {
		$all = $this->_get_events_schedule_queue();
		if (!empty($all[$event_id])) unset($all[$event_id]);
		return update_option(self::EVENT_SCHEDULE_QUEUE, $all);
	}

	private function _get_email_batch_limit () {
		$limit = (int)trim($this->_data->get_option('eab_cancelations-email-hourly_limit'));
		$limit = $limit ? $limit : 20;
		return $limit;
	}

	private function _send_cancel_event_email ($event_id) {
		$event = new Eab_EventModel(get_post($event_id));
		$rsvps = $event->get_event_bookings(Eab_EventModel::BOOKING_YES);
		if (empty($rsvps)) return false;

		$from = $this->_data->get_option('eab_cancelations-email-from');
		$from = $from ? $from : get_option('admin_email');
		
		$subject = trim($this->_data->get_option('eab_cancelations-email-subject'));
		$subject = !empty($subject) ? $subject : $this->_default_subject;
		
		$body = trim($this->_data->get_option('eab_cancelations-email-body'));
		$body = !empty($body) ? $body : $this->_default_message;

		if (empty($from) || empty($subject) || empty($body)) return false;

		$already_sent = $this->_get_users_notification_queue($event->get_id());

		$limit = $this->_get_email_batch_limit();
		$counter = 0;

		$codec = new Eab_Macro_Codec($event_id);
		$headers = array(
			'From: ' . $from,
			'Content-Type: ' . $this->email_charset() . '; charset="' . get_option('blog_charset') . '"'
		);

		add_filter('wp_mail_content_type', array($this, 'email_charset'));
		foreach ($rsvps as $rsvp) {
			if (in_array($rsvp->user_id, $already_sent)) continue; // Don't send email twice
			$user = get_user_by('id', $rsvp->user_id);
			if (!is_email($user->user_email)) continue;
			$codec->set_user($user);
			wp_mail(
				$user->user_email, 
				$codec->expand($subject, Eab_Macro_Codec::FILTER_TITLE),
				$codec->expand($body, Eab_Macro_Codec::FILTER_BODY),
				$headers
			);
			$already_sent[] = $rsvp->user_id;
			$counter++; if ($counter == $limit) break;
		}
		remove_filter('wp_mail_content_type', array($this, 'email_charset'));

		$this->_set_users_notification_queue($event->get_id(), $already_sent);
		if (count($rsvps) == count($already_sent)) $this->_remove_event_from_schedule_queue($event->get_id());

		return $counter;
	}

	function email_charset () { return 'text/html'; }

	function ajax_preview_email () {
		$data = stripslashes_deep($_POST);
		$event_id = !empty($data['event_id']) ? $data['event_id'] : false;
		if (!$event_id) die;
		$user = wp_get_current_user();
		$codec = new Eab_Macro_Codec($event_id, $user->ID);
		die(
			'<strong>' . $codec->expand($data['subject'], Eab_Macro_Codec::FILTER_TITLE) . '</strong>' .
			'<div>' . $codec->expand($data['body'], Eab_Macro_Codec::FILTER_BODY) . '</div>'
		);
	}

	function save_settings ($options) {
		$data = stripslashes_deep($_POST);
		$options['eab_cancelations-hide_events'] = @$data['eab_cancelations']['hide_events'];
		$options['eab_cancelations-email-hourly_limit'] = @$data['eab_cancelations']['email_batch_limit'];
		$options['eab_cancelations-email-from'] = @$data['eab_cancelations']['email-from'];
		$options['eab_cancelations-email-subject'] = @$data['eab_cancelations']['email-subject'];
		$options['eab_cancelations-email-body'] = @$data['eab_cancelations-email-body'];
		return $options;
	}

	function show_settings () {
		$tips = new PSource_HelpTooltips();
		$tips->set_icon_url(EAB_PLUGIN_URL . 'img/information.png');
		
		$from = $this->_data->get_option('eab_cancelations-email-from');
		$from = $from ? $from : get_option('admin_email');
		
		$subject = trim($this->_data->get_option('eab_cancelations-email-subject'));
		$subject = !empty($subject) ? $subject : $this->_default_subject;
		
		$body = trim($this->_data->get_option('eab_cancelations-email-body'));
		$body = !empty($body) ? $body : $this->_default_message;
		
		$codec = new Eab_Macro_Codec;
		$macros = join('</code>, <code>', $codec->get_macros());

		$events = Eab_CollectionFactory::get_upcoming_events(eab_current_time(), array('posts_per_page' => 10));
		?>
<div id="eab-settings-eab_cancelations" class="eab-metabox postbox">
	<h3 class="eab-hndle"><?php _e('Einstellungen Ereignisstornierung', 'eab'); ?></h3>
	<div class="eab-inside">
		<div class="eab-settings-settings_item">
			<label for="eab_cancellations-hide_events"><?php _e('Abgebrochene Ereignisse ausblenden', 'eab'); ?></label>
			<input type="hidden" name="eab_cancelations[hide_events]" value="" />
			<input type="checkbox" name="eab_cancelations[hide_events]" id="eab_cancellations-hide_events" value="1" <?php checked(true, $this->_data->get_option('eab_cancelations-hide_events')); ?> />
		</div>
		<div class="eab-settings-settings_item">
			<label for="eab_cancellations-email_batch_limit"><?php _e('E-Mail-Batch-Limit', 'eab'); ?>:</label>
			<span><?php echo $tips->add_tip(__('Dies ist die maximale Anzahl von E-Mails, die auf einmal gesendet werden. Der Rest wird für den Versand geplant.', 'eab')); ?></span>
			<input type="text" name="eab_cancelations[email_batch_limit]" id="eab_cancellations-email_batch_limit" value="<?php echo (int)$this->_get_email_batch_limit(); ?>" />
		</div>
		<div class="eab-note">
			<?php _e('Dies ist die E-Mail, die bei Absage der Veranstaltung an Ihre Teilnehmer gesendet wird.', 'eab'); ?>
		</div>
		<div class="eab-settings-settings_item">
	    	<label for="eab_event-eab_cancelations-from"><?php _e('Von der Email Adresse', 'eab'); ?></label>
			<span><?php echo $tips->add_tip(__('Dies ist die Adresse, von der aus die Stornierungs-E-Mail gesendet wird', 'eab')); ?></span>
			<input type="text" id="eab_event-eab_cancelations-from" name="eab_cancelations[email-from]" value="<?php esc_attr_e($from); ?>" />
	    </div>
	    <div class="eab-settings-settings_item">
	    	<label for="eab_event-eab_cancelations-subject"><?php _e('Email Betreff', 'eab'); ?></label>
			<span><?php echo $tips->add_tip(sprintf(__('Dies ist der E-Mail-Betreff. Du kannst diese Makros verwenden: <code>%s</code>', 'eab'), $macros)); ?></span>
			<input type="text" class="widefat" id="eab_event-eab_cancelations-subject" name="eab_cancelations[email-subject]" value="<?php esc_attr_e($subject); ?>" />
	    </div>
	    <div class="eab-settings-settings_item">
	    	<label for="eab_event-eab_cancelations-body"><?php _e('Nachrichtentext', 'eab'); ?></label>
			<span><?php echo $tips->add_tip(sprintf(__('Dies ist der E-Mail-Text. Du kannst diese Makros verwenden: <code>%s</code>', 'eab'), $macros)); ?></span>
			<?php wp_editor($body, 'eab_cancelations-email-body', array(
				'name' => 'eab_cancelations-email-body',
			)); ?>
	    </div>
	    <div class="eab-settings-settings_item"><small><?php printf(__('Du kannst diese Makros in Deinem Betreff und Nachrichtentext verwenden: <code>%s</code>', 'eab'), $macros) ?></small></div>
	<?php if ($events) { ?>
	    <div class="eab-settings-settings_item">
	    	<input type="button" class="button" id="eab_event-eab_cancelations-preview" value="<?php esc_attr_e(__('VORSCHAU', 'eab')); ?>" />
	    	<?php _e('Verwende diese Ereignisdaten:', 'eab'); ?>
	    	<select id="eab_event-eab_cancelations-events">
	    	<?php foreach ($events as $event) { ?>
	    		<option value="<?php esc_attr_e($event->get_id()); ?>"><?php echo $event->get_title(); ?></option>
	    	<?php } ?>
	    	</select>
	    	<div id="eab_event-eab_cancelations-email_preview_container" style="line-height: 1.2em"></div>
	    </div>
	<?php } ?>
	</div>
</div>
<script type="text/javascript">
(function ($) {
$(function () {
	var $container = $("#eab_event-eab_cancelations-email_preview_container"),
		$subject = $("#eab_event-eab_cancelations-subject"),
		$events = $("#eab_event-eab_cancelations-events")
	;
	$("#eab_event-eab_cancelations-preview").on("click", function () {
		var editor = tinyMCE.get("eab_cancelations-email-body")
			body_string = (tinyMCE && tinyMCE.activeEditor && tinyMCE.activeEditor.editorId == editor.editorId
			? tinyMCE.activeEditor.getContent()
			: $("#eab_cancelations-email-body").val()
		);
		$container.html('<?php echo esc_js(__("Bitte hör auf... ", 'eab')); ?>');
		$.post(ajaxurl, {
			"action": "eab_cancellation_email-preview_email",
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
Eab_Events_EventCancel::serve();