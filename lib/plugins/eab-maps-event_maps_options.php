<?php
/*
Plugin Name: Optionen für Ereignismaps
Description: Maps verwendet standardmäßig die globalen Einstellungen für das PS-Gmaps-Plugin. Verwende diese Erweiterung, um ereignisspezifische Einstellungen anzuwenden.
Plugin URI: https://n3rds.work/piestingtal-source-project/eventsps-das-eventmanagment-fuer-wordpress/
Version: 1.1
Author: DerN3rd
AddonType: Integration
*/

class Eab_Maps_EventMapsOptions {

	private $_data;

	private function __construct () {
		$this->_data = Eab_Options::get_instance();
	}

	public static function serve () {
		$me = new Eab_Maps_EventMapsOptions;
		$me->_add_hooks();
	}

	private function _add_hooks () {
		add_action('admin_notices', array($this, 'show_nags'));
		add_action('eab-settings-after_plugin_settings', array($this, 'show_settings'));
		add_filter('eab-settings-before_save', array($this, 'save_settings'));

		add_filter('eab-maps-map_defaults', array($this, 'apply_defaults'));
	}

	function apply_defaults ($options) {
		return $this->_data->get_option('google_maps-overrides');
	}

	function show_nags () {
		if (!class_exists('AgmMapModel')) {
			echo '<div class="error"><p>' .
				sprintf(__("Du benötigst das <a href='%s'>PS-Gmaps</a> Plugin installiert and aktiviert um diese Erweiterung aktivieren zu können", 'eab'), 'https://n3rds.work/piestingtal-source-project/ps-gmaps/') .
			'</p></div>';
		}
	}

	function save_settings ($options) {
		if ( !isset( $_POST['google_maps'] ) ) {
			return $options;
		}

		$data = stripslashes_deep( $_POST['google_maps'] );
		$options['google_maps-overrides'] = !empty( $data['overrides'] ) ? array_filter($data['overrides']) : array();
		return $options;
	}

	function show_settings () {
		$map_types = array(
			'ROADMAP' 	=> __('ROADMAP', 'agm_google_maps'),
			'SATELLITE' => __('SATELLITE', 'agm_google_maps'),
			'HYBRID' 	=> __('HYBRID', 'agm_google_maps'),
			'TERRAIN' 	=> __('TERRAIN', 'agm_google_maps'),
		);
		$map_units = array(
			'METRIC' 	=> __('Metrisch', 'agm_google_maps'),
			'IMPERIAL' 	=> __('Imperial', 'agm_google_maps'),
		);
		$options = $this->_data->get_option('google_maps-overrides');
?>
<div id="eab-settings-event_maps_options" class="eab-metabox postbox">
	<h3 class="eab-hndle"><?php _e('Optionen für Ereigniskarten', 'eab'); ?></h3>
	<div class="eab-inside">
		<p><em><?php _e('Alle Einstellungen, die Du hier leer lässt, werden von den Standardeinstellungen des Google Maps-Plugins übernommen.', 'eab'); ?></em></p>
		<table class="form-table">
			<tr valign="top">
				<th scope="row"><?php _e('Map Größe', 'eab')?></th>
				<td>
					<label for="eab-google_maps-width">
						<?php _e('Breite:', 'eab'); ?>
						<input type="text" size="4" id="eab-google_maps-width" name="google_maps[overrides][width]" value="<?php esc_attr_e(@$options['width']); ?>" /><em class="eab-inline_help">px</em>
					</label>
					<span class="eab-hspacer">&times;</span>
					<label for="eab-google_maps-height">
						<?php _e('Höhe:', 'eab'); ?>
						<input type="text" size="4" id="eab-google_maps-height" name="google_maps[overrides][height]" value="<?php esc_attr_e(@$options['height']); ?>" /><em class="eab-inline_help">px</em>
					</label>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row"><?php _e('Kartenaussehen', 'eab')?></th>
				<td>
					<style>
						.eab_inner_table td{padding: 0 !important}
					</style>
					<table celpadding="5" cellspacing="5" class="eab_inner_table">
						<tr>
							<td valign="top">
								<label for="eab-google_maps-zoom">
									<?php _e('Zoom:', 'eab'); ?>
								</label>
							</td>
							<td valign="top">
								<input type="text" size="4" id="eab-google_maps-zoom" name="google_maps[overrides][zoom]" value="<?php esc_attr_e(@$options['zoom']); ?>" />
								<em class="eab-inline_help"><?php _e('Zahlenwert', 'eab'); ?></em>
							</td>
						</tr>
						<tr>
							<td valign="top">
								<label for="eab-google_maps-type">
									<?php _e('Typ:', 'eab'); ?>
								</label>
							</td>
							<td valign="top">
								<select name="google_maps[overrides][map_type]">
									<option value=""></option>
									<?php foreach ($map_types as $type => $label) { ?>
										<option value="<?php esc_attr_e($type); ?>"
											<?php selected(@$options['map_type'], $type); ?>
										><?php echo $label; ?></option>
									<?php } ?>
								</select>
							</td>
						</tr>
						<tr>
							<td valign="top"><label for="eab-google_maps-units"><?php _e('Einheiten:', 'eab'); ?></label></td>
							<td valign="top">
								<select name="google_maps[overrides][units]">
									<option value=""></option>
									<?php foreach ($map_units as $units => $label) { ?>
										<option value="<?php esc_attr_e($units); ?>"
											<?php selected(@$options['units'], $units); ?>
										><?php echo $label; ?></option>
									<?php } ?>
								</select>
							</td>
						</tr>
						<tr>
							<td valign="top">
								<input type="hidden" name="google_maps[overrides][show_images]" value="" />
						<input type="checkbox" id="eab-google_maps-show_images" name="google_maps[overrides][show_images]" value="1" <?php checked(1, @$options['show_images']); ?> />
							</td>
							<td valign="top"><label for="eab-google_maps-show_images"><?php _e('Bilder anzeigen', 'eab'); ?></label></td>
						</tr>
					</table>
				</td>
			</tr>
		</table>
	</div>
</div>
<?php
	}
}
Eab_Maps_EventMapsOptions::serve();