<?php
/*
Plugin Name: Ereignisgesteuerte Umleitung
Description: Leitet den Besucher von einer ausgewählten Seite, einem Beitrag oder einem Ereignis der Website (mit seiner ID angegeben) zu einer externen oder internen URL um, wenn das Ereignis gerade fortschreitet.
Plugin URI: https://n3rds.work/piestingtal-source-project/eventsps-das-eventmanagment-fuer-wordpress/
Version: 0.3
Author: DerN3rd
AddonType: Events
*/

/*
Detail: Diese Erweiterung fügt jedem Ereignis zwei Felder hinzu: <b>ID der Quellseite</b> und <b>URL des Ziels</b>. Wenn das Ereignis fortschreitet, wird jede Besucherseite mit festgelegter Seite/Post/Ereignis-ID zur festgelegten URL umgeleitet. Die Quellseite muss nicht mit der Ereignisseite identisch sein. Die Erweiterung bietet auf dieser Seite auch zwei globale Felder, die verwendet werden, wenn die zugehörigen Felder auf der Ereignisseite leer bleiben.

*/

class Eab_Events_EventControlledRedirect {

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
		$me = new Eab_Events_EventControlledRedirect;
		$me->_add_hooks();
	}

	/**
	 * Hooks to the main plugin Events+
	 *
	 */	
	private function _add_hooks () {
		add_action('eab-settings-after_payment_settings', array($this, 'show_settings'));
		add_action('template_redirect', array($this, 'redirect'));
		add_action('eab-event_meta-save_meta', array($this, 'save_redirect_meta'));
		add_filter('eab-event_meta-event_meta_box-after', array( $this, 'event_meta_box'));
		add_filter('eab-settings-before_save', array($this,'save_settings'));
		add_action('admin_notices', array($this, 'warn_admin'));
	}
	
	function redirect() {
		global $post, $wpdb;
		
		if ( $post ) {
			/* Find if this page is selected to be redirected.
			For optimization reasons, at this moment we assume that target url is defined
			We will check it later
			*/
			$results 		= $wpdb->get_results("SELECT post_id FROM ". $wpdb->postmeta." WHERE meta_key='eab_events_redirect_source' AND meta_value='".$post->ID ."' "); 
			$global_post_id = $this->_data->get_option('global_redirect_source');
			if ( $results ) {
				$query = '';
				foreach ( $results as $result )
					$query .= $this->generate_query( $result->post_id ). " UNION "; // UNION is faster than OR

				$this->finalize_redirect( rtrim( $query, "UNION ") ); // get rid of the last UNION
			}
			// Let's check if a global source ID is set, but source is not set for this event
			else if ( $global_post_id && $global_post_id == $post->ID ) {
				$this->finalize_redirect( $this->generate_query( ) ); // Check all active events now
			}
		}
			

		return; // No match, no redirect.
	}

	/**
	 * Helper function to generate the query
	 */	
	function generate_query( $post_id=0 ) {
		global $wpdb;
		if ( $post_id )
			$add_query = "wposts.ID=".$post_id." ";
		else
			$add_query = "esource.meta_key='eab_events_redirect_source' AND esource.meta_value <> ''";
			
		$local_now = "DATE_ADD(UTC_TIMESTAMP(),INTERVAL ". ( current_time('timestamp') - time() ). " SECOND)";
		
		//$this->log( $local_now );
			
		return "SELECT wposts.* 
				FROM $wpdb->posts wposts, $wpdb->postmeta estart, $wpdb->postmeta eend, $wpdb->postmeta estatus, $wpdb->postmeta esource 
				WHERE ". $add_query . "
				AND wposts.ID=estart.post_id AND wposts.ID=eend.post_id AND wposts.ID=estatus.post_id 
				AND estart.meta_key='psource_event_start' AND estart.meta_value < $local_now
				AND eend.meta_key='psource_event_end' AND eend.meta_value > $local_now
				AND estatus.meta_key='psource_event_status' AND estatus.meta_value <> 'closed'
				AND wposts.post_type='psource_event' AND wposts.post_status='publish'";
	}

	/**
	 * Save a message in the log file
	 */	
	function log( $message='' ) {
		// Don't give warning if folder is not writable
		@file_put_contents( EAB_PLUGIN_DIR. "log.txt", $message . chr(10). chr(13), FILE_APPEND ); 
	}

	/**
	 * Helper function to redirect, if conditions are met
	 */	
	function finalize_redirect( $query ) {
		global $wpdb;
		$rows = $wpdb->get_results( $query );
		if ( $rows ) {
			$global_target = $this->_data->get_option('global_redirect_target');
			foreach ( $rows as $event ) {
				if ( $url = get_post_meta( $event->ID, 'eab_events_redirect_target', true ) )
					wp_redirect( $url ); // Normally target url should have been defined and we would redirect the user on first match.
				else if ( $global_target )
					wp_redirect( $global_target );
				exit;
			}
		}
		return;
	}


	/**
	 * Warn admin if source ID is not valid or source=target
	 */
	function warn_admin() {
		global $post;
		if ( !is_object( $post ) )
			return;
		if( !$permalink = get_permalink( get_post_meta($post->ID, 'eab_events_redirect_source', true ) ) ) {
			echo '<div class="error"><p>' .
				__("Dies ist keine gültige Quell-ID.", 'eab') .
			'</p></div>';
		}
		else if ( $permalink == get_post_meta($post->ID, 'eab_events_redirect_target', true ) ) {
			echo '<div class="error"><p>' .
				__("Ziel und Quelle zeigen auf dieselbe Seite. Dies führt zu einer endlosen Umleitungsschleife.", 'eab') .
			'</p></div>';
		}
	}

	/**
	 * Save post meta
	 *
	 */	
	function _save_meta ($post_id, $REQUEST) {
		if (isset($REQUEST['psource_event_redirect_source']) ) {
			if ( trim( $REQUEST['psource_event_redirect_source'] ) != '' )
				update_post_meta($post_id, 'eab_events_redirect_source', trim($REQUEST['psource_event_redirect_source']));
			else
				delete_post_meta($post_id, 'eab_events_redirect_source');
		}
		if (isset($REQUEST['psource_event_redirect_target']) ) {
			if ( trim( $REQUEST['psource_event_redirect_target'] ) != '' )
				update_post_meta($post_id, 'eab_events_redirect_target', trim($REQUEST['psource_event_redirect_target']));
			else
				delete_post_meta($post_id, 'eab_events_redirect_target');
		}
	}
	function save_redirect_meta ($post_id) {
		$this->_save_meta($post_id, $_POST);	
	}
	
	/**
	 * Add HTML codes to the event meta box
	 *
	 */	
	function event_meta_box( $content ) {
		global $post;
		
		$source = get_post_meta( $post->ID, 'eab_events_redirect_source', true );
		$target = get_post_meta( $post->ID, 'eab_events_redirect_target', true );
	
		$content .= '<div class="eab_meta_box">';
		$content .= '<input type="hidden" name="psource_event_redirect_meta" value="1" />';
		$content .= '<div class="misc-eab-section">';
		$content .= '<div class="eab_meta_column_box">'.__('Ereignisgesteuerte Umleitung', 'eab').'</div>';
		$content .= '<label for="psource_event_redirect_source" id="psource_event_redirect_source_label">'.__('ID der Quellseite ', 'eab').':</label>&nbsp;';
		$content .= '<input type="text" name="psource_event_redirect_source" id="psource_event_redirect_source" class="psource_event" value="'.$source.'" size="5" /> ';
		$content .= '<div class="clear"></div>';
		$content .= '<label for="psource_event_redirect_target" id="psource_event_redirect_targer_label">'.__('URL des Ziels ', 'eab').':</label>&nbsp;';
		$content .= '<input type="text" name="psource_event_redirect_target" id="psource_event_redirect_target" class="psource_event" value="'.$target.'" /> ';
		$content .= '<div class="clear"></div>';
		$content .= '</div>';
		$content .= '</div>';
	
		return $content;
	
	}

	 
	/**
	 * Add Addon settings to the other admin options to be saved
	 */	
	function save_settings( $options ) {
		$options['global_redirect_source']		= stripslashes($_POST['event_default']['global_redirect_source']);
		$options['global_redirect_target']		= stripslashes($_POST['event_default']['global_redirect_target']);
		
		return $options;
	}
	
	/**
	 * Admin settings
	 *
	 */	
	function show_settings() {
		if (!class_exists('PSource_HelpTooltips')) 
			require_once dirname(__FILE__) . '/lib/class_wd_help_tooltips.php';
		$tips = new PSource_HelpTooltips();
		$tips->set_icon_url(EAB_PLUGIN_URL . 'img/information.png' );
		?>
		<div id="eab-settings-redirect" class="eab-metabox postbox">
				<h3 class="eab-hndle"><?php _e('Ereignisgesteuerte Umleitungseinstellungen', 'eab'); ?></h3>
				<div class="eab-inside">
					<div class="eab-settings-settings_item">
					    <label for="psource_event-global_redirect_source" ><?php _e('Globale Quellenseiten-ID', 'eab'); ?></label>
						<input type="text" size="10" name="event_default[global_redirect_source]" value="<?php print $this->_data->get_option('global_redirect_source'); ?>" />
						<span><?php echo $tips->add_tip(__('Wenn Du hier eine ID eingibst, verwenden alle Ereignisse, für die KEINE ID-Einstellung für die Quellseite vorhanden ist, diese Einstellung.', 'eab')); ?></span>
					</div>
					    
					<div class="eab-settings-settings_item">
					    <label for="psource_event-global_redirect_target" ><?php _e('Globale Ziel-URL', 'eab'); ?></label>
						<input type="text" size="40" name="event_default[global_redirect_target]" value="<?php print $this->_data->get_option('global_redirect_target'); ?>" />
						<span><?php echo $tips->add_tip(__('Wenn Du hier eine URL eingibst, verwenden alle Ereignisse, die KEINE Ziel-URL-Einstellung haben, diese Einstellung.', 'eab')); ?></span>
					</div>
					
					    
				</div>
		    </div>
		<?php
	}
}

Eab_Events_EventControlledRedirect::serve();