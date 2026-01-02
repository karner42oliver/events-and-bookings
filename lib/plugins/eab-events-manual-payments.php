<?php
/*
Plugin Name: Manuelle Zahlungen
Description: Ermöglicht Benutzern das manuelle Bezahlen (Scheck, Überweisung usw.)
Plugin URI: https://n3rds.work/piestingtal-source-project/eventsps-das-eventmanagment-fuer-wordpress/
Version: 0.3
Author: DerN3rd
AddonType: Events
*/

/*
Detail: Fügt dem Frontend Codes für die manuellen Zahlungsanweisungen hinzu. Diese Anweisungen werden auf dieser Einstellungsseite unter <b>Manuelle Zahlungseinstellungen</b> eingegeben. Fügt der Ereignisseite außerdem Codes hinzu, sodass Du auswählen kannst, dass ein Mitglied manuell bezahlt hat.
*/


class Eab_Events_ManualPayments {

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
		$me = new Eab_Events_ManualPayments;
		$me->_add_hooks();
	}

	/**
	 * Hooks to the main plugin Events+
	 *
	 */	
	private function _add_hooks () {
		add_action('eab-settings-after_payment_settings', array($this, 'show_settings'));
		add_action('admin_notices', array($this, 'show_nags'));
		add_action('wp_ajax_eab_manual_payment', array($this, 'do_payment'));
		add_action('wp_ajax_eab_approve_manual_payment', array($this, 'approve_payment'));
		add_filter('eab-event-show_pay_note', array($this,'will_show_pay_note'), 10, 2);
		add_filter('eab-event-payment_status', array($this,'status'), 10, 2);
		add_filter('eab-event-booking_metabox_content', array($this,'add_approve_payment'), 10, 2);
		add_filter('eab-settings-before_save', array($this,'save_settings'));
		add_filter('eab-event-payment_forms', array($this,'add_select_button'), 10, 2);
		add_filter('eab-event-after_payment_forms', array($this,'add_instructions'), 10, 2);
	}

	/**
	 * Ajax call when user clicks payment button
	 */	
	function do_payment() {
		check_ajax_referer( 'manual-payment-nonce', 'nonce' );
		$user_id = $_POST["user_id"];
		$event_id = $_POST["event_id"];
		if ( !$user_id OR !$event_id )
			die();
		$payments = maybe_unserialize( stripslashes( Eab_EventModel::get_booking_meta( $event_id, "manual_payment" ) ) );
		if ( !is_array( $payments ) )
			$payments = array();
		else {
			foreach ( $payments as $payment ) { // Make a check
				if ( $payment["id"] == $user_id ) // User has a record before!?
					die();
			}
		}
                
		array_push( $payments, array( "id"=>$user_id, "stat"=>"pending"));
		//$payments = array_filter( array_unique( $payments ) ); // Clear empty records, just in case
                $payments = array_map( "unserialize", array_unique( array_map( "serialize", $payments ) ) );
		Eab_EventModel::update_booking_meta( $event_id, "manual_payment", serialize( $payments ) );
		die();
	}

	/**
	 * Adds the button to the front end that reveals instructions box
	 */	
	function add_select_button( $content, $event_id ) {
		if ($this->_data->get_option('paypal_email')) $content .= '<br /><br />';
		$content .= '<a class="psourceevents-yes-submit" style="float:none !important" href="javascript:void(0)" id="manual_payment_select_'.$event_id.'">'. $this->_data->get_option('manual_payment_select') . '</a>';
		$content .= '<script type="text/javascript">';
		$content .= 'jQuery(document).ready(function($){
						$("#manual_payment_select_'.$event_id.'").on("click", function() {
							$("#manual_payment_instructions_'.$event_id.'").slideToggle("slow");
						});
					});';
		$content .= '</script>';
		
		return $content;
	}

	/**
	 * Adds instructions box to the front end
	 */	
	function add_instructions( $content, $event_id ) {
		global $current_user;
		$content .= '<div class="message" id="manual_payment_instructions_'.$event_id.'" style="display:none">';
		$button = '<a class="psourceevents-yes-submit" style="float:none !important" href="javascript:void(0)" id="manual_payment_pay_'.$event_id.'">'. $this->_data->get_option('manual_payment_pay') . '</a>';
		$content .= wpautop(str_replace("MANUALPAYMENTBUTTON", $button,$this->_data->get_option('manual_payment_instructions')));
		$content .= '</div>';
		$content .= '<script type="text/javascript">';
		$content .= 'jQuery(document).ready(function($){
						$("#manual_payment_pay_'.$event_id.'").on("click",function() {
							$.post("'.admin_url('admin-ajax.php').'", {
								"action": "eab_manual_payment",
								"user_id":'.$current_user->ID.',
								"event_id":'.$event_id.',
								"nonce":"'.wp_create_nonce("manual-payment-nonce").'"
							}, function (data) {
								if (data && data.error) {alert(data.error);}
								else {
									$("#manual_payment_pay_'.$event_id.'").css("opacity","0.2");
									alert("'.__('Vielen Dank für die Zahlung!','eab').'");
								}
							});
							return false;
						});
					});';
		$content .= '</script>';
		return $content;
	}

	/**
	 * Warn admin if Manual Button is not inserted
	 */
	function show_nags() {
		if ( strpos($this->_data->get_option('manual_payment_instructions'), "MANUALPAYMENTBUTTON") === false) {
			echo '<div class="error"><p>' .
				__("Du hast kein Schlüsselwort MANUALPAYMENTBUTTON im Feld Anweisungen. Dies bedeutet, dass es keine Schaltfläche gibt und der Benutzer Dich nicht darüber informieren kann, dass er eine Zahlung geleistet hat, was wiederum bedeutet, dass die manuelle Zahlung praktisch unbrauchbar ist.", 'eab') .
			'</p></div>';
		}
	}
	 
	/**
	 * Add Addon settings to the other admin options to be saved
	 */	
	function save_settings( $options ) {
		$options['manual_payment_select']		= stripslashes($_POST['event_default']['manual_payment_select']);
		$options['manual_payment_pay']			= stripslashes($_POST['event_default']['manual_payment_pay']);
		$options['manual_payment_instructions']	= stripslashes($_POST['event_default']['manual_payment_instructions']);
		
		return $options;
	}
	
	/**
	 * Admin settings
	 *
	 */	
	function show_settings() {
		if ( !class_exists( 'PSource_HelpTooltips' ) ) 
			require_once dirname(__FILE__) . '/lib/class_wd_help_tooltips.php';
		$tips = new PSource_HelpTooltips();
		$tips->set_icon_url( EAB_PLUGIN_URL . 'img/information.png' );
		?>
		<div id="eab-settings-manual_payments" class="eab-metabox postbox">
				<h3 class="eab-hndle"><?php _e('Manuelle Zahlungseinstellungen', 'eab'); ?></h3>
				<div class="eab-inside">
					<div class="eab-settings-settings_item">
					    <label for="psource_event-manual_payment_select" ><?php _e('Wähle den Schaltflächentext aus', 'eab'); ?></label>
						<input type="text" size="40" name="event_default[manual_payment_select]" value="<?php print $this->_data->get_option('manual_payment_select'); ?>" />
						<span><?php echo $tips->add_tip(__('Dies ist der Text, der auf der Schaltfläche Manuelle Zahlung auswählen angezeigt wird.', 'eab')); ?></span>
					</div>
					    
					<div class="eab-settings-settings_item">
					    <label for="psource_event-manual_payment_pay" ><?php _e('Bezahlbutton Text', 'eab'); ?></label>
						<input type="text" size="40" name="event_default[manual_payment_pay]" value="<?php print $this->_data->get_option('manual_payment_pay'); ?>" />
						<span><?php echo $tips->add_tip( __('Dies ist der Text, der auf der Schaltfläche Bezahlen angezeigt wird. Der Benutzer muss auf diese Schaltfläche klicken, nachdem er die Zahlung getätigt hat.', 'eab')); ?></span>
					</div>
					
					<div class="eab-settings-settings_item">
					    <label for="psource_event-manual_payment_instructions" ><?php _e('Anleitung', 'eab'); ?>&nbsp;:</label>
						<span><?php echo $tips->add_tip( __('Schreibe hier das Verfahren, das der Benutzer für eine manuelle Zahlung ausführen muss. Verwende den MANUALPAYMENTBUTTON, um den Pay Button an der gewünschten Stelle einzufügen.', 'eab')); ?></span>
						<?php wp_editor( $this->_data->get_option('manual_payment_instructions'), 'manualpaymentsinstructions', array('textarea_name'=>'event_default[manual_payment_instructions]', 'textarea_rows' => 5) ); ?>
					</div>
					    
				</div>
		    </div>
		<?php
	}

	/**
	 * Check if 'You havent paid for this event' note will be displayed
	 * If user is approved to be paid, return false, i.e don't show pay note
	 * Otherwise return whatever sent here
	 */	
	function will_show_pay_note( $show_pay_note, $event_id ) {
		global $current_user;
		$payments = maybe_unserialize( stripslashes( Eab_EventModel::get_booking_meta( $event_id, "manual_payment") ) );
		if ( is_array( $payments ) ) {
			foreach ( $payments as $payment ) {
				if ( $payment["id"] == $current_user->ID AND $payment["stat"] == "paid" )
					return false; // User approved to be paid
			}
		}
		return $show_pay_note;
	}

	/**
	 * Modify payment status text if user is manually selected that he paid
	 */		
	function status( $payment_status, $user_id ) {
		global $post; // This object should be available as we are on Admin side
		$payments = maybe_unserialize( stripslashes( Eab_EventModel::get_booking_meta( $post->ID, "manual_payment") ) );
		if ( is_array( $payments ) ) {
			foreach ( $payments as $payment ) {
				if ( $payment["id"] == $user_id )
					return $payment["stat"]; // User status is either paid or pending
			}
		}
		return $payment_status;
	}

	/**
	 * Approve a payment on the admin side
	 */	
	function approve_payment() {
		$user_id = $_POST["user_id"];
		$event_id = $_POST["event_id"];
		if ( !$user_id OR !$event_id )
			die( json_encode( array( "error" => __( "Benutzer-ID oder Ereignis-ID fehlt", 'eab' ) ) ) );
		$payments = maybe_unserialize( stripslashes( Eab_EventModel::get_booking_meta( $event_id, "manual_payment" ) ) );
		if ( is_array( $payments ) ) {
			$post  		= get_post( $event_id );
			$event 		= ( $post instanceof Eab_EventModel ) ? $post : new Eab_EventModel( $post );
			$booking_id = $event->get_user_booking_id( $user_id );
			foreach ( $payments as $key=>$payment ) { 
				if ( $payment["id"] == $user_id ) {
					$payments[$key]["stat"] = "paid";
					//$payments = array_filter( array_unique( $payments ) );
                    $payments = array_map( "unserialize", array_unique( array_map( "serialize", $payments ) ) );
					Eab_EventModel::update_booking_meta( $event_id, "manual_payment", serialize( $payments ) );
					Eab_EventModel::update_booking_meta( $booking_id, 'booking_transaction_key', true );
					Eab_EventModel::update_booking_meta( $booking_id, 'booking_ticket_count', true );
					die();
				}
			}
		}
		die( json_encode( array( "error" => __( "Datensatz konnte nicht gefunden werden", 'eab' ) ) ) );
	}
	
	/**
	 * Add manual payment link inside the RSVP box
	 */		
	function add_approve_payment( $content, $user_id ) {
		global $post;
		$payments = maybe_unserialize( stripslashes( Eab_EventModel::get_booking_meta( $post->ID, "manual_payment") ) );
		if ( is_array($payments ) ) {
			foreach ( $payments as $payment ) {
				if ( $payment["id"] == $user_id AND $payment["stat"] == 'pending' ) {
					$content .= '<div class="eab-guest-actions" id="div_approve_payment_'.$user_id.'">
					<a id="approve_payment_'.$user_id.'" href="javascript:void(0)" class="eab-guest-manual_payment" >' .
					__('Zahlung genehmigen', 'eab') .
					'</a></div>';
					$content .= '<script type="text/javascript">';
					$content .= 'jQuery(document).ready(function($){
									$("#approve_payment_'.$user_id.'").on("click",function() {
										if (confirm("'. __( "Bist Du sicher, diese Zahlung zu genehmigen?", 'eab' ) .'")){
											$.post(ajaxurl, {
												"action": "eab_approve_manual_payment",
												"user_id":'.$user_id.',
												"event_id":'.$post->ID.'
											}, function (data) {
												if (data && data.error) {alert(data.error);}
												else {
													$("#div_approve_payment_'.$user_id.'").parent(".eab-guest").find(".eab-guest-payment_info").html("'.__( 'Bezahlt', 'eab' ).'");
													$("#div_approve_payment_'.$user_id.'").remove();
												}
											},
											"json");
											return false;
										}
										else {return false;}
									});
								});';
					$content .= '</script>';
				}
			}
		}
		return $content;
	}
}

Eab_Events_ManualPayments::serve();