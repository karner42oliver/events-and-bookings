<?php if ( $updated ): ?>
	<div class="updated fade"><p><?php _e('Einstellungen gespeichert.', eab_domain() ); ?></p></div>
<?php endif; ?>

<div class="wrap <?php echo esc_attr($tabbable); ?> <?php echo esc_attr($hide); ?>">
	<h1><?php _e('PS-Event Einstellungen', eab_domain() ); ?></h1>
	<?php if (defined('EAB_PREVENT_SETTINGS_SECTIONS') && EAB_PREVENT_SETTINGS_SECTIONS) { ?>
		<div class="eab-note">
			<p><?php _e('Hier verwaltest Du Deine allgemeinen Einstellungen für das Plugin und wie Ereignisse auf Deiner Seite angezeigt werden.', eab_domain() ); ?>.</p>
		</div>
	<?php } ?>
	<form method="post" action="edit.php?post_type=psource_event&page=eab_settings">
		<?php wp_nonce_field('psource_event-update-options'); ?>
                <input type="hidden" name="event_default[event_settings_url]" value="" class="event_settings_url">
		<div class="eab-metaboxcol metabox-holder eab-metaboxcol-one eab-metaboxcol-center">
			<?php do_action('eab-settings-before_plugin_settings'); ?>
			<div id="eab-settings-general" class="eab-metabox postbox">
				<h3 class="eab-hndle"><?php _e('Plugin Einstellungen', eab_domain() ); ?></h3>
				<div class="eab-inside">
					<div class="eab-settings-settings_item">
						<label for="psource_event-slug" id="psource_event_label-slug"><?php _e('Stelle hier Root-Slug ein:', eab_domain() ); ?></label>
						/<input type="text" size="20" id="psource_event-slug" name="event_default[slug]" value="<?php print $this->_data->get_option('slug'); ?>" />
						<span><?php echo $tips->add_tip(__('Dies ist die URL, unter der sich Dein Ereignisarchiv befindet. Standardmäßig lautet das Format yoursite.com/events. Du kannst dies jedoch nach Belieben ändern.', eab_domain() )); ?></span>
					</div>
					<div class="eab-settings-settings_item">
						<label for="psource_event-ordering_direction" id="psource_event_label-ordering_direction"><?php _e('Stelle absteigende Reihenfolge nach Startdatum ein:', eab_domain() ); ?></label>
						<?php
							$ordering_direction = $this->_data->get_option('ordering_direction');
						?>
						<input type="checkbox" size="20" id="psource_event-ordering_direction" name="event_default[ordering_direction]" value="1" <?php $ordering_direction = $this->_data->get_option('ordering_direction'); checked( !empty( $ordering_direction ) ); ?>>
						<span><?php echo $tips->add_tip(__('Dies ist die Bestellrichtung, in der Ihr Veranstaltungsarchiv bestellt werden kann. Standardmäßig ist die Richtung aufsteigend.', eab_domain() )); ?></span>
					</div>
					<div class="eab-settings-settings_item">
						<label for="psource_event-pagination" id="psource_event_label-pagination"><?php _e('Paginierung einstellen (Ereignisse):', eab_domain() ); ?></label>
                        <input type="number" size="20" id="psource_event-pagination" name="event_default[pagination]" value="<?php echo (int)$this->_data->get_option('pagination'); ?>" />
						<span><?php echo $tips->add_tip(__('Dies ist die Paginierung für das Ereignisarchiv. Standardmäßig - 0, ohne Paginierung.', eab_domain() )); ?></span>
					</div>

					<div class="eab-settings-settings_item">
						<label for="psource_event-accept_payments" id="psource_event_label-accept_payments"><?php _e('Akzeptierst Du Zahlung für eine Deiner Veranstaltungen?', eab_domain() ); ?></label>
						<input type="checkbox" size="20" id="psource_event-accept_payments" name="event_default[accept_payments]" value="1" <?php print ($this->_data->get_option('accept_payments') == 1)?'checked="checked"':''; ?> />
						<span><?php echo $tips->add_tip(__('Lasse dieses Kontrollkästchen deaktiviert, wenn Du zu keinem Zeitpunkt eine Zahlung einziehen möchtest.', eab_domain() )); ?></span>
					</div>

					<div class="eab-settings-settings_item">
						<label for="psource_event-accept_api_logins" id="psource_event_label-accept_api_logins"><?php _e('Erlaube Facebook und Twitter Login?', eab_domain() ); ?></label>
						<input type="checkbox" size="20" id="psource_event-accept_api_logins" name="event_default[accept_api_logins]" value="1" <?php print ($this->_data->get_option('accept_api_logins') == 1)?'checked="checked"':''; ?> />
						<span><?php echo $tips->add_tip(__('Aktiviere dieses Kontrollkästchen, damit Gäste mit ihrem Facebook- oder Twitter-Konto zu einer Veranstaltung antworten können. (Wenn diese Funktion nicht aktiviert ist, benötigen Gäste ein WordPress-Konto für RSVPs).', eab_domain() )); ?></span>
					</div>

					<div class="eab-settings-settings_item">
						<label for="psource_event-display_attendees" id="psource_event_label-display_attendees"><?php _e('Öffentliche RSVPs anzeigen?', eab_domain() ); ?></label>
						<input type="checkbox" size="20" id="psource_event-display_attendees" name="event_default[display_attendees]" value="1" <?php print ($this->_data->get_option('display_attendees') == 1)?'checked="checked"':''; ?> />
						<span><?php echo $tips->add_tip(__('Aktiviere dieses Kontrollkästchen, um in den Ereignisdetails eine Liste mit den Teilnehmern anzuzeigen.', eab_domain() )); ?></span>
					</div>
				</div>
			</div>
			<?php if (!$theme_tpls_present) { ?>
				<div id="eab-settings-appearance" class="eab-metabox postbox">
					<h3 class="eab-hndle"><?php _e('Darstellung Einstellungen', eab_domain() ); ?></h3>
					<div class="eab-inside">
						<div class="eab-settings-settings_item">
							<label for="psource_event-override_appearance_defaults" id="psource_event_label-override_appearance_defaults"><?php _e('Standarddarstellung überschreiben?', eab_domain() ); ?></label>
							<input type="checkbox" size="20" id="psource_event-override_appearance_defaults" name="event_default[override_appearance_defaults]" value="1" <?php print ($this->_data->get_option('override_appearance_defaults') == 1)?'checked="checked"':''; ?> />
							<span><?php echo $tips->add_tip(__('Aktiviere dieses Kontrollkästchen, wenn Du das Erscheinungsbild Deiner Ereignisse mit überschreibenden Vorlagen anpassen möchtest.', eab_domain() )); ?></span>
						</div>

						<div class="eab-settings-settings_item">
							<?php if (!$archive_tpl_present) { ?>
								<label for="psource_event-archive_template" id="psource_event_label-archive_template"><?php _e('Archivvorlage', eab_domain() ); ?></label>
								<select id="psource_event-archive_template" name="event_default[archive_template]">
									<?php foreach ($templates as $tkey => $tlabel) { ?>
										<?php $selected = ($this->_data->get_option('archive_template') == $tkey) ? 'selected="selected"' : ''; ?>
										<option value="<?php esc_attr_e($tkey);?>" <?php echo $selected;?>><?php echo $tlabel;?></option>
									<?php } ?>
								</select>
								<span>
							<small><em><?php _e('* Vorlagen funktionieren möglicherweise nicht in allen Themen', eab_domain() ); ?></em></small>
									<?php echo $tips->add_tip(__('Wähle aus, wie das Ereignisarchiv auf Deiner Seite angezeigt werden soll.', eab_domain() ) ); ?>
						</span>
							<?php } ?>
						</div>

						<div class="eab-settings-settings_item">
							<?php if (!$single_tpl_present) { ?>
								<label for="psource_event-single_template" id="psource_event_label-single_template"><?php _e('Einzelereignisvorlage', eab_domain() ); ?></label>
								<select id="psource_event-single_template" name="event_default[single_template]">
									<?php foreach ($templates as $tkey => $tlabel) { ?>
										<?php $selected = ($this->_data->get_option('single_template') == $tkey) ? 'selected="selected"' : ''; ?>
										<option value="<?php esc_attr_e($tkey);?>" <?php echo $selected;?>><?php echo $tlabel;?></option>
									<?php } ?>
								</select>
								<span>
							<small><em><?php _e('* Vorlagen funktionieren möglicherweise nicht in allen Themen', eab_domain() ); ?></em></small>
									<?php echo $tips->add_tip(__('Wähle aus, wie einzelne Ereignislisten auf Deiner Seite angezeigt werden sollen.', eab_domain() )); ?>
						</span>
							<?php } ?>
						</div>

					</div>
				</div>
			<?php } ?>

			<?php do_action('eab-settings-after_appearance_settings'); /* the hook happens whether we have appearance settings or not */ ?>

			<!-- Payment settings -->
			<div id="eab-settings-paypal" class="eab-metabox postbox">
				<h3 class="eab-hndle"><?php _e('Zahlungseinstellungen', eab_domain() ); ?></h3>
				<div class="eab-inside">
					<div class="eab-settings-settings_item">
						<label for="psource_event-currency" id="psource_event_label-currency"><?php _e('Währung', eab_domain() ); ?></label>
						<input type="text" size="4" id="psource_event-currency" name="event_default[currency]" value="<?php print $this->_data->get_option('currency'); ?>" />
						<span><?php echo $tips->add_tip(sprintf(__('Nominiere die Währung, in der Du Zahlung für Deine Veranstaltungen akzeptierst. Weitere Informationen findest Du unter <a href="%s" target="_blank">Akzeptierte PayPal-Währungscodes</a>.', eab_domain() ), 'https://cms.paypal.com/us/cgi-bin/?cmd=_render-content&content_ID=developer/e_howto_api_nvp_currency_codes')); ?></span>
					</div>

					<div class="eab-settings-settings_item">
						<label for="psource_event-paypal_email" id="psource_event_label-paypal_email"><?php _e('PayPal E-Mail Addresse', eab_domain() ); ?></label>
						<input type="text" size="20" id="psource_event-paypal_email" name="event_default[paypal_email]" value="<?php print $this->_data->get_option('paypal_email'); ?>" />
						<span><?php echo $tips->add_tip(__('Füge die primäre E-Mail-Adresse des PayPal-Kontos hinzu, mit dem Du die Zahlung für Deine Veranstaltungen einziehen möchtest.', eab_domain() )); ?></span>
					</div>

					<div class="eab-settings-settings_item">
						<label for="psource_event-paypal_sandbox" id="psource_event_label-paypal_sandbox"><?php _e('PayPal Sandbox Modus?', eab_domain() ); ?></label>
						<input type="checkbox" size="20" id="psource_event-paypal_sandbox" name="event_default[paypal_sandbox]" value="1" <?php print ($this->_data->get_option('paypal_sandbox') == 1)?'checked="checked"':''; ?> />
						<span><?php echo $tips->add_tip(__('Verwende den PayPal Sandbox-Modus zum Testen Deiner Zahlungen', eab_domain() )); ?></span>
					</div>
				</div>
			</div>
			<?php do_action('eab-settings-after_payment_settings'); ?>
			<?php $this->_api->render_settings($tips); // API settings ?>
			<!-- Addon settings -->
			<div id="eab-settings-addons" class="eab-metabox postbox">
				<h3 class="eab-hndle"><?php _e('Erweiterungen', eab_domain() ); ?></h3>
				<!--<div class="eab-inside">-->
				<?php Eab_AddonHandler::create_addon_settings(); ?>
				<br />
				<!--</div>-->
			</div>
			<?php do_action('eab-settings-after_plugin_settings'); ?>
		</div>

		<p class="submit clear">
			<input type="submit" class="button-primary" name="submit_settings" value="<?php _e('Speichern', eab_domain() ) ?>" />
			<?php if (isset($_REQUEST['eab_step']) && $_REQUEST['eab_step'] == 1) { ?>
				<a href="edit.php?post_type=psource_event&page=eab_welcome&eab_step=-1" class="button"><?php _e('Gehe zurück zu Erste Schritte', eab_domain() ) ?></a>
			<?php } ?>
		</p>
	</form>
</div>
<?php if (!empty($tabbable)) { ?>
	<div class="eab-loading-cover <?php echo esc_attr($tabbable); ?>"><h1><?php _e('Lade...einen Augenblick bitte...', eab_domain() ); ?></h1></div>
<?php } ?>