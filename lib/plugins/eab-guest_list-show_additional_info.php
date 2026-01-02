<?php
/**
 * Plugin Name: Gästelisten Optionen
 * Description: Bietet mehr Kontrolle über Benutzerinformationen, die in den RSVP-Listen angezeigt werden
 * Plugin URI: https://n3rds.work/piestingtal-source-project/eventsps-das-eventmanagment-fuer-wordpress/
 * Version: 1.1
 * Author: DerN3rd
 * AddonType: Integration
 */

class Eab_GuestList_ShowAdditionalInfo {

    public function __construct() {
        $this->_data = Eab_Options::get_instance();
    }

    public static function serve() {
        $me = new Eab_GuestList_ShowAdditionalInfo;
        $me->_add_hooks();
    }

    private function _add_hooks() {
        add_action('eab-settings-after_plugin_settings', array($this, 'show_settings'));
        add_filter('eab-settings-before_save', array($this, 'save_settings'));

        add_action('wp_print_styles', array($this, 'add_styles'));
        add_filter('eab-guest_list-guest_avatar', array($this, 'process_avatar'), 10, 4);

        if ($this->_data->get_option('guest_lists-sai-show_in_admin')) {
            add_filter('eab-guest_list-admin-guest_name', array($this, 'process_username'), 10, 3);
        }
        if ($this->_data->get_option('guest_lists-sai-show_in_export')) {
            add_filter('eab-guest_list-export-guest_name', array($this, 'process_username'), 10, 3);
        }
    }

    function add_styles() {
        global $post;
    
        // Überprüfen, ob $post nicht null ist und ob es eine post_type-Eigenschaft hat
        if ($post instanceof WP_Post && property_exists($post, 'post_type') && Eab_EventModel::POST_TYPE != $post->post_type) {
            return false;
        }
    }

    function process_username($username, $user_id, $user_data) {
        $name = $this->_userdata_to_user_name($user_id, $user_data);
        return trim($name) ? $name : $username;
    }

    function process_avatar($avatar, $user_id, $user_data, $event) {
        $avatar_sizes = array(
            '' => false,
            'small' => 32,
            'medium' => 48,
            'large' => 96,
        );
        $size = $this->_data->get_option('guest_lists-sai-avatar_size');
        $size = in_array($size, array_keys($avatar_sizes)) ? (int)$avatar_sizes[$size] : false;
        $avatar = $size ? get_avatar($user_id, $size) : false;

        $name = $this->_userdata_to_user_name($user_id, $user_data);
        $name = sprintf("<span class='eab-guest_lists-user_name'>%s</span>", $name);

        $url = defined('BP_VERSION') ?
            bp_core_get_user_domain($user_id) :
            get_author_posts_url($user_id);

        $style = '';
        if ($size) {
            $width = $size + 4;
            $style = "style='display:block; width:{$width}px; float:left; overflow:hidden; margin: 0 5px;'";
        }

        $avatar = '<a ' . $style . ' href="' . $url . '" title="' . esc_attr(strip_tags($name)) . '">' .
            $avatar . $name .
            '</a>';
        return $avatar;
    }

    private function _userdata_to_user_name($user_id, $user_data) {
        $name = $user_data->user_login;
        switch ($this->_data->get_option('guest_lists-sai-show_name')) {
            case "username":
                $tmp_name = $user_data->user_login;
                break;
            case "display_name":
                $tmp_name = $user_data->display_name;
                break;
            case "firstname":
                $tmp_name = get_user_meta($user_id, 'first_name', true);
                break;
            case "lastname":
                $tmp_name = get_user_meta($user_id, 'last_name', true);
                break;
            case "fullname_first":
                $first = get_user_meta($user_id, 'first_name', true);
                $last = get_user_meta($user_id, 'last_name', true);
                $tmp_name = "{$first} {$last}";
                break;
            case "fullname_last":
                $first = get_user_meta($user_id, 'first_name', true);
                $last = get_user_meta($user_id, 'last_name', true);
                $tmp_name = "{$last} {$first}";
                break;
            default:
                $tmp_name = false;
                break;
        }
        return trim($tmp_name) ? $tmp_name : $name;
    }

    function show_settings() {
        $tips = new PSource_HelpTooltips();
        $tips->set_icon_url(EAB_PLUGIN_URL . 'img/information.png');

        $no_avatar = !$this->_data->get_option('guest_lists-sai-avatar_size') ? 'checked="checked"' : '';
        $avatar_small = ('small' == $this->_data->get_option('guest_lists-sai-avatar_size')) ? 'checked="checked"' : '';
        $avatar_med = ('medium' == $this->_data->get_option('guest_lists-sai-avatar_size')) ? 'checked="checked"' : '';
        $avatar_large = ('large' == $this->_data->get_option('guest_lists-sai-avatar_size')) ? 'checked="checked"' : '';

        $no_name = !$this->_data->get_option('guest_lists-sai-show_name') ? 'checked="checked"' : '';
        $username = ('username' == $this->_data->get_option('guest_lists-sai-show_name')) ? 'checked="checked"' : '';
        $display_name = ('display_name' == $this->_data->get_option('guest_lists-sai-show_name')) ? 'checked="checked"' : '';
        $firstname = ('firstname' == $this->_data->get_option('guest_lists-sai-show_name')) ? 'checked="checked"' : '';
        $lastname = ('lastname' == $this->_data->get_option('guest_lists-sai-show_name')) ? 'checked="checked"' : '';
        $fullname_first = ('fullname_first' == $this->_data->get_option('guest_lists-sai-show_name')) ? 'checked="checked"' : '';
        $fullname_last = ('fullname_last' == $this->_data->get_option('guest_lists-sai-show_name')) ? 'checked="checked"' : '';

        $show_in_admin = $this->_data->get_option('guest_lists-sai-show_in_admin') ? 'checked="checked"' : '';
        $show_in_export = $this->_data->get_option('guest_lists-sai-show_in_export') ? 'checked="checked"' : '';
        ?>
        <div id="eab-settings-guest_lists" class="eab-metabox postbox">
            <h3 class="eab-hndle"><?php _e('Optionen für Gästelisten', 'eab'); ?></h3>
            <div class="eab-inside">
                <div class="eab-settings-settings_item" style="line-height:1.8em">
                    <label><?php _e('Gastavatare', 'eab'); ?></label>
                    <br />
                    <input type="radio" id="eab_event-guest_lists-sai-no_avatar" name="event_default[guest_lists-sai-avatar_size]" value="" <?php echo $no_avatar; ?> />
                    <label for="eab_event-guest_lists-sai-no_avatar"><?php _e('Zeige keine Avatare', 'eab'); ?></label>
                    <span><?php echo $tips->add_tip(__('Benutzeravatare in RSVP-Listen ausblenden', 'eab')); ?></span>
                    <br />
                    <input type="radio" id="eab_event-guest_lists-sai-small_avatar" name="event_default[guest_lists-sai-avatar_size]" value="small" <?php echo $avatar_small; ?> />
                    <label for="eab_event-guest_lists-sai-small_avatar"><?php _e('Zeige kleine Avatare', 'eab'); ?></label>
                    <span><?php echo $tips->add_tip(__('Zeige kleine Avatare in RSVP-Listen', 'eab')); ?></span>
                    <br />
                    <input type="radio" id="eab_event-guest_lists-sai-med_avatar" name="event_default[guest_lists-sai-avatar_size]" value="medium" <?php echo $avatar_med; ?> />
                    <label for="eab_event-guest_lists-sai-med_avatar"><?php _e('Mittlere Avatare anzeigen', 'eab'); ?></label>
                    <span><?php echo $tips->add_tip(__('Mittlere Avatare in RSVP-Listen anzeigen', 'eab')); ?></span>
                    <br />
                    <input type="radio" id="eab_event-guest_lists-sai-large_avatar" name="event_default[guest_lists-sai-avatar_size]" value="large" <?php echo $avatar_large; ?> />
                    <label for="eab_event-guest_lists-sai-large_avatar"><?php _e('Zeige große Avatare', 'eab'); ?></label>
                    <span><?php echo $tips->add_tip(__('Zeige große Avatare in RSVP-Listen', 'eab')); ?></span>
                    <p></p>
                </div>
                <div class="eab-settings-settings_item" style="line-height:1.8em">
                    <label><?php _e('Gastnamen', 'eab'); ?></label>
                    <br />
                    <input type="radio" id="eab_event-guest_lists-sai-show_name-no_name" name="event_default[guest_lists-sai-show_name]" value="" <?php echo $no_name; ?> />
                    <label for="eab_event-guest_lists-sai-show_name-no_name"><?php _e('Namen nicht anzeigen', 'eab'); ?></label>
                    <span><?php echo $tips->add_tip(__('Verberge Namen in RSVP-Listen', 'eab')); ?></span>
                    <br />
                    <input type="radio" id="eab_event-guest_lists-sai-show_name-display_name" name="event_default[guest_lists-sai-show_name]" value="display_name" <?php echo $display_name; ?> />
                    <label for="eab_event-guest_lists-sai-show_name-display_name"><?php _e('Anzeigenamen anzeigen', 'eab'); ?></label>
                    <span><?php echo $tips->add_tip(__('Anzeigen von Benutzernamen in RSVP-Listen', 'eab')); ?></span>
                    <br />
                    <input type="radio" id="eab_event-guest_lists-sai-show_name-username" name="event_default[guest_lists-sai-show_name]" value="username" <?php echo $username; ?> />
                    <label for="eab_event-guest_lists-sai-show_name-username"><?php _e('Benutzername anzeigen', 'eab'); ?></label>
                    <span><?php echo $tips->add_tip(__('Benutzernamen in RSVP-Listen anzeigen', 'eab')); ?></span>
                    <br />
                    <input type="radio" id="eab_event-guest_lists-sai-show_name-firstname" name="event_default[guest_lists-sai-show_name]" value="firstname" <?php echo $firstname; ?> />
                    <label for="eab_event-guest_lists-sai-show_name-firstname"><?php _e('Vorname anzeigen', 'eab'); ?></label>
                    <span><?php echo $tips->add_tip(__('Benutzer-Vornamen in RSVP-Listen anzeigen', 'eab')); ?></span>
                    <br />
                    <input type="radio" id="eab_event-guest_lists-sai-show_name-lastname" name="event_default[guest_lists-sai-show_name]" value="lastname" <?php echo $lastname; ?> />
                    <label for="eab_event-guest_lists-sai-show_name-lastname"><?php _e('Familienname anzeigen', 'eab'); ?></label>
                    <span><?php echo $tips->add_tip(__('Zeigt Familiennamen der Benutzer in den RSVP-Listen an', 'eab')); ?></span>
                    <br />
                    <input type="radio" id="eab_event-guest_lists-sai-show_name-fullname_first" name="event_default[guest_lists-sai-show_name]" value="fullname_first" <?php echo $fullname_first; ?> />
                    <label for="eab_event-guest_lists-sai-show_name-fullname_first"><?php _e('Vollständigen Namen anzeigen, Vorname zuerst', 'eab'); ?></label>
                    <span><?php echo $tips->add_tip(__('Zeigt vollständigen Benutzernamen in RSVP-Listen als Vorname Nachname an', 'eab')); ?></span>
                    <br />
                    <input type="radio" id="eab_event-guest_lists-sai-show_name-fullname_last" name="event_default[guest_lists-sai-show_name]" value="fullname_last" <?php echo $fullname_last; ?> />
                    <label for="eab_event-guest_lists-sai-show_name-fullname_last"><?php _e('Vollständigen Namen anzeigen, Familienname zuerst', 'eab'); ?></label>
                    <span><?php echo $tips->add_tip(__('Zeigt den vollständigen Benutzernamen in RSVP-Listen als Nachname Vorname an', 'eab')); ?></span>
                    <p></p>
                </div>
                <div class="eab-settings-settings_item" style="line-height:1.8em">
                    <label><?php _e('Zeige hübsche Gästelistennamen in ...', 'eab'); ?></label>
                    <br />
                    <input type="hidden" name="event_default[guest_lists-sai-show_in_admin]" value="" />
                    <input type="checkbox" id="eab_event-guest_lists-sai-show_in_admin" name="event_default[guest_lists-sai-show_in_admin]" value="1" <?php echo $show_in_admin; ?> />
                    <label for="eab_event-guest_lists-sai-show_in_admin"><?php _e('Admin-Bereich für Veranstaltungen', 'eab'); ?></label>
                    <span><?php echo $tips->add_tip(__('Standardmäßig werden hier die Benutzernamen angezeigt', 'eab')); ?></span>
                    <br />
                    <input type="hidden" name="event_default[guest_lists-sai-show_in_export]" value="" />
                    <input type="checkbox" id="eab_event-guest_lists-sai-show_in_export" name="event_default[guest_lists-sai-show_in_export]" value="1" <?php echo $show_in_export; ?> />
                    <label for="eab_event-guest_lists-sai-show_in_export"><?php _e('Ereignisse exportieren Dateien', 'eab'); ?></label>
                    <span><?php echo $tips->add_tip(__('Standardmäßig werden hier die Benutzernamen angezeigt', 'eab')); ?></span>
                    <br />
                </div>
            </div>
        </div>
        <?php
    }

    function save_settings($options) {
        $options['guest_lists-sai-avatar_size'] = isset($_POST['event_default']['guest_lists-sai-avatar_size']) ? sanitize_text_field($_POST['event_default']['guest_lists-sai-avatar_size']) : '';
        $options['guest_lists-sai-show_name'] = isset($_POST['event_default']['guest_lists-sai-show_name']) ? sanitize_text_field($_POST['event_default']['guest_lists-sai-show_name']) : '';
        $options['guest_lists-sai-show_in_admin'] = isset($_POST['event_default']['guest_lists-sai-show_in_admin']) ? true : false;
        $options['guest_lists-sai-show_in_export'] = isset($_POST['event_default']['guest_lists-sai-show_in_export']) ? true : false;
        return $options;
    }
}

Eab_GuestList_ShowAdditionalInfo::serve();
