<?php

class Eab_AdminHelp {
	
	private $_help;
	private $_sidebar;
	
	private $_pages = array (
		'list',
		'edit',
		'settings',
		'welcome',
		'post',
		'page',
	);
	
	private function __construct () {
		if (!class_exists('PSource_ContextualHelp')) require_once 'class_wd_contextual_help.php';
		$this->_help = new PSource_ContextualHelp();
		$this->_set_up_sidebar();
	}
	
	public static function serve () {
		$me = new Eab_AdminHelp;
		$me->_initialize();
	}
	
	private function _initialize () {
		foreach ($this->_pages as $page) {
			$method = "_add_{$page}_page";
			if (method_exists($this, $method)) $this->$method();
		}
		$this->_help->initialize();
	}
	
	private function _set_up_sidebar() {
		add_action('init', function() {
			$this->_sidebar = '<h4>' . __('PS-Events', 'eab') . '</h4>';
			if (defined('PSOURCE_REMOVE_BRANDING') && constant('PSOURCE_REMOVE_BRANDING')) {
				$this->_sidebar .= '<p>' . __('PS-Events fügt Deiner Webseite oder Deiner Multisite ein mächtiges Events & Bookings System hinzu.', 'eab') . '</p>';
			} else {
				$this->_sidebar .= '<ul>' .
					'<li><a href="https://n3rds.work/piestingtal_source/ps-events-eventmanagement-fuer-wordpress/" target="_blank">' . __('Projektseite', 'eab') . '</a></li>' .
					'<li><a href="https://n3rds.work/docs/ps-events-plugin-handbuch/" target="_blank">' . __('Installations- und Anleitungsseite', 'eab') . '</a></li>' .
					'<li><a href="https://n3rds.work/forums/forum/psource-support-foren/ps-forum-supportforum/https://n3rds.work/piestingtal-source-project/eventsps-das-eventmanagment-fuer-wordpress/" target="_blank">' . __('Hilfeforum', 'eab') . '</a></li>' .
				'</ul>';
			}
		});
	}
	
	private function _add_shortcodes_contextual_help ($screen_id) {
		$help = apply_filters('eab-shortcodes-shortcode_help', array());
		$out = '';

		foreach ($help as $shortcode) {
			$out .= '<div><h5>' . $shortcode['title'] . '</h5>';
			$out .= '<div>';
			$out .= '		<strong>' . __('Datum:', 'eab') . '</strong> <code>[' . $shortcode['tag'] . ']</code>';
			if (!empty($shortcode['note'])) $out .= '<div><em>' . $shortcode['note'] . '</em></div>';
		    $out .= '	</div>';
			if (!empty($shortcode['arguments'])) {
				$out .= ' <div class="eab-shortcode_help-argument"><strong>' . __('Argumente:', 'eab') . '</strong>';
				foreach ($shortcode['arguments'] as $argument => $data) {
					if (!empty($shortcode['advanced_arguments'])) {
						if (in_array($argument, $shortcode['advanced_arguments'])) continue;
					}
					$type = !empty($data['type'])
						? eab_call_template('util_shortcode_argument_type_string', $data['type'], $argument, $shortcode['tag'])
						: false
					;
					$out .= "<div class='eab-shortcode-attribute_item'><code>{$argument}</code> - {$type} {$data['help']}</div>";
				}
				$out .= '</div><!-- argument -->';
			}
			$out .= '</div>';
		}

		$this->_help->add_page(
			$screen_id,
			array(
				array(
					'id' => 'eab_shortcodes',
					'title' => __('PS-Events Shortcodes', 'eab'),
					'content' => $out,
				),
			)
		);
	}

	private function _add_post_page () {
		$this->_add_shortcodes_contextual_help('post');
	}

	private function _add_page_page () {
		$this->_add_shortcodes_contextual_help('page');
	}
	
	private function _add_list_page () {
		$this->_help->add_page(
			'edit-psource_event', 
			array(
				array(
					'id' => 'eab_intro',
					'title' => __('Intro', 'eab'),
					'content' => '' .
						'<p>' .
							__('Hier kannst Du alle Deine Ereignisse sehen.', 'eab') .
						'</p>' .
					''
				),
				array(
					'id' => 'eab_tutorial',
					'title' => __('Tutorial', 'eab'),
					'content' => '' .
						'<p>' . 
							__('Tutorial-Dialoge führen Dich durch die wichtigen Punkte.', 'eab') . 
						'</p>' .
						'<p><a href="#" class="eab-restart_tutorial" data-eab_tutorial="0">' . __('Starte das Tutorial neu', 'eab') . '</a></p>',
					''
				),
			),
			$this->_sidebar,
			true
		);
	}

	private function _add_edit_page () {
		// Determine if we have the Maps plugin
		$agm = class_exists('AgmMapModel') 
			? __('Wenn das <href="https://n3rds.work/piestingtal-source-project/ps-gmaps/">PS-Gmaps</a> Plugin installiert ist, kannst Du die vollständige Integration von Google Maps in Deinen Veranstaltungen verwenden', 'eab') 
			: __('Dein Standort wird automatisch auf einer Google Map zugeordnet. Du kannst auch selbst eine Karte erstellen und diese mithilfe des Globussymbols über dem Feld mit Ihrem Ereignis verknüpfen', 'eab')
		; 
		$this->_help->add_page(
			'psource_event', 
			array(
				array(
					'id' => 'eab_intro',
					'title' => __('Intro', 'eab'),
					'content' => '' .
						'<p>' .
							__('Hier erstellst und bearbeitest Du Deine Ereignisse', 'eab') .
						'</p>' .
					''
				),
				array(
					'id' => 'eab_details',
					'title' => __('Veranstaltungsdetails', 'eab'),
					'content' => '' .
						'<h4>' . __('Veranstaltungsort', 'eab') . '</h4>' .
						'<p>' . 
							__('In dieses Feld kannst Du Deine Ereignisadresse eingeben.', 'eab') .
							" {$agm}" . 
						'</p>' .
						'<h4>' . __('Veranstaltungszeiten und -daten', 'eab') . '</h4>' .
						'<p>' .
							__('Du kannst Deiner Veranstaltung mehrere Start- und Endzeiten hinzufügen. Du kannst so viele hinzufügen, wie Du möchtest.', 'eab') .
						'</p>' .
					''
				),
				array(
					'id' => 'eab_tutorial',
					'title' => __('Tutorial', 'eab'),
					'content' => '' .
						'<p>' . 
							__('Tutorial-Dialoge führen Dich durch die wichtigen Punkte.', 'eab') . 
						'</p>' .
						'<p><a href="#" class="eab-restart_tutorial" data-eab_tutorial="5">' . __('Starte das Tutorial neu', 'eab') . '</a></p>',
					''
				),
			),
			$this->_sidebar,
			true
		);
	}

	private function _add_settings_page () {
		$this->_help->add_page(
			'psource_event_page_eab_settings', 
			array(
				array(
					'id' => 'eab_intro',
					'title' => __('Intro', 'eab'),
					'content' => '' .
						'<p>' .
							__('Hier richtest Du dein Plugin ein.', 'eab') .
						'</p>' .
					''
				),
				array(
					'id' => 'eab_appearance_settings',
					'title' => __('Darstellungseinstellungen', 'eab'),
					'content' => '' .
						'<p>' . __('Lege fest, wie Deine Veranstaltungen präsentiert werden.', 'eab') . '</p>' .
						'<p>' . __('Wenn Du die Option "Standarddarstellung überschreiben" aktivierst, kannst Du zwischen verschiedenen vordefinierten Vorlagen auswählen, um die Darstellung Deiner Ereignisse zu ändern.', 'eab') . '</p>' .
						'<p>' . __('Um zur Standardausgabe des Plugins zurückzukehren, deaktiviere jederzeit die Option "Standarddarstellung überschreiben"', 'eab') . '</p>' .
						'<p>' . __('Wenn Du die Vorlagen weiter anpassen möchtest, kannst Du einen gewünschten Satz aus dem Plugin-Verzeichnis in Dein aktuelles Themenverzeichnis kopieren und bearbeiten.', 'eab') . '</p>' .
						'<p><em>' . __('<b>Hinweis:</b> Diese Einstellungen sind nicht verfügbar, wenn Du die Vorlagen zur Anpassung in Dein Themenverzeichnis kopierst', 'eab') . '</em></p>' .
					'',
				),
				array(
					'id' => 'eab_api_settings',
					'title' => __('API Einstellungen', 'eab'),
					'content' => '' .
						'<p>' .
							__('Dieser Abschnitt wird verfügbar, wenn Du Anmeldungen mit Twitter und Facebook zulässt, indem Du das entsprechende Kontrollkästchen in den Plugin-Einstellungen aktivierst.', 'eab') .
						'</p>' .
						
						'<h4>' . __('Facebook API Einstellungen', 'eab') . '</h4>' .
						sprintf(__('<p>Bevor wir beginnen, musst Du <a target="_blank" href="http://www.facebook.com/developers/createapp.php">eine Facebook-Anwendung</a> erstellen.</p>' .
						'<p>Befolgen dazu diese Schritte:</p>' .
						'<ol>' .
							'<li><a target="_blank" href="http://www.facebook.com/developers/createapp.php">Erstelle Deine Anwendung</a></li>' .
							'<li>Danach gehe zur <a target="_blank" href="http://www.facebook.com/developers/apps.php">Facebook-Anwendungslistenseite</a> und wähle Deine neu erstellte Anwendung aus.</li>' .
							'<li>Kopiere den Wert im <strong>App ID</strong>/<strong>API Key</strong> Feld, und gib ihn in das Feld "Facebook App ID" ein.</li>' .
						'</ol>', 'eab'), get_bloginfo('url')) .
						
						'<h4>' . __('Twitter API Einstellungen', 'eab') . '</h4>' .
						__('<p>Zuerst wird eine Twitter-Anwendung benötigt <a target="_blank" href="https://dev.twitter.com/apps/new">Erstelle eine Twitter-Anwendung</a>.</p>' .
						'<p>Befolgen dazu diese Schritte:</p>' .
						'<ol>' .
							'<li><a target="_blank" href="https://dev.twitter.com/apps/new">Erstelle Deine Anwendung</a></li>' .
							'<li>Suche nach dem <strong>Callback URL</strong> Feld und gib Deine Callback-URL in dieses Feld ein: <code>%s</code></li>' .
							'<li>Danach gehe zur <a target="_blank" href="https://dev.twitter.com/apps">Seite mit der Twitter-Anwendungsliste</a> und wähle Deine neu erstellte Anwendung aus.</li>' .
							'<li>Kopiere die Werte aus den folgenden Feldern: <strong>Consumer Key</strong> und <strong>Consumer Secret </strong> und gib diese in die Plugin-Einstellungen ein.</li>' .
						'</ol>', 'eab') .
					'',
				),
				array(
					'id' => 'eab_tutorial',
					'title' => __('Tutorial', 'eab'),
					'content' => '' .
						'<p>' . 
							__('Tutorial-Dialoge führen Dich durch die wichtigen Punkte.', 'eab') . 
						'</p>' .
						'<p><a href="#" class="eab-restart_tutorial" data-eab_tutorial="0">' . __('Starte das Tutorial neu', 'eab') . '</a></p>',
					''
				),
			),
			$this->_sidebar,
			true
		);
	}
	
	private function _add_welcome_page () {
		$this->_help->add_page(
			'psource_event_page_eab_welcome', 
			array(
				array(
					'id' => 'eab_intro',
					'title' => __('Willkommen', 'eab'),
					'content' => '' .
						'<p>' .
							__('Willkommen bei Events! Diese Seite führt Dich durch die Einrichtung Deines Plugins und die Veröffentlichung Deiner ersten Ereignisse.', 'eab') .
						'</p>' .
					''
				),
				array(
					'id' => 'eab_tutorial',
					'title' => __('Tutorial', 'eab'),
					'content' => '' .
						'<p>' . 
							__('Tutorial-Dialoge führen Dich durch die wichtigen Punkte.', 'eab') . 
						'</p>' .
						'<p><a href="#" class="eab-restart_tutorial" data-eab_tutorial="0">' . __('Starte das Tutorial neu', 'eab') . '</a></p>',
					''
				),
			),
			$this->_sidebar,
			true
		);
	}
	
	function show_screen () {
		//echo '<pre>'; var_export(get_current_screen());
	}
}
