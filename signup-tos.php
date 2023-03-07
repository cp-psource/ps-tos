<?php
/*
Plugin Name: CP-TOS
Plugin URI: https://n3rds.work/cp_psource/cp-tos-plugin/
Description: Dieses Plugin platziert ein Feld mit Nutzungsbedingungen auf dem Anmeldeformular für CP Seiten, CP Multisite oder BuddyPress und zwingt den Benutzer, das zugehörige Kontrollkästchen zu aktivieren, um fortzufahren
Author: WMS N@W
Version: 1.4.4
Author URI: https://n3rds.work
https://n3rds.work/spendenaktionen/unterstuetze-unsere-psource-free-werke/
Domain Path: /languages
Text Domain: cp-tos
Network: true
*/

/*
Copyright 2018-2023 WMS N@W (https://n3rds.work)
Author - DerN3rd
This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License (Version 2 - GPLv2) as published by
the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

require 'psource/psource-plugin-update/psource-plugin-updater.php';
use Psource\PluginUpdateChecker\v5\PucFactory;
$MyUpdateChecker = PucFactory::buildUpdateChecker(
	'https://n3rds.work//wp-update-server/?action=get_metadata&slug=ps-tos', 
	__FILE__, 
	'ps-tos' 
);

//------------------------------------------------------------------------//
//---Hook-----------------------------------------------------------------//
//------------------------------------------------------------------------//

add_action( 'signup_extra_fields', 'signup_tos_field_wpmu', 20 );
add_action( 'bp_before_registration_submit_buttons', 'signup_tos_field_bp' );
add_action( 'register_form', 'signup_tos_field_wp' );
add_filter( 'wpmu_validate_user_signup', 'signup_tos_filter_wpmu' );
add_action( 'bp_signup_validate', 'signup_tos_filter_bp' );
add_filter( 'registration_errors', 'signup_tos_validate_wp' );
add_action( 'admin_menu', 'signup_tos_plug_pages' );
add_action( 'network_admin_menu', 'signup_tos_plug_pages' );
add_action( 'plugins_loaded', 'signup_tos_localization' );
add_shortcode( 'signup-tos', 'signup_tos_shortcode' );

//------------------------------------------------------------------------//
//---Functions------------------------------------------------------------//
//------------------------------------------------------------------------//

function signup_tos_localization() {
	// Load up the localization file if we're using ClassicPress in a different language
	// Place it in the mu-plugins folder or plugins and name it "tos-LOCALE.mo"
	load_plugin_textdomain( 'cp-tos', false, '/signup-tos/languages/' );
}

function signup_tos_load_textdomain() {
	// get user's selected locale
	$user_locale = get_user_locale();
	
	// load textdomain for the plugin
	load_plugin_textdomain( 'cp-tos', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
	
	// load textdomain for TOS page
	load_plugin_textdomain( 'cp-tos', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/cp-tos-' . $user_locale . '.mo' );
}
add_action( 'plugins_loaded', 'signup_tos_load_textdomain' );

/**
 * Adds an entry in Dashboard
 */
define( 'SIGNUP_TOS_TITLE', __( 'CP-TOS', 'cp-tos' ) );
define( 'SIGNUP_TOS_SLUG', 'signup-tos' );
define( 'SIGNUP_TOS_CAPABILITY', is_multisite() ? 'manage_network_options' : 'manage_options' );
define( 'SIGNUP_TOS_CALLBACK', 'signup_tos_page_main_output' );
function signup_tos_plug_pages() {
	if ( is_multisite() ) {
		add_submenu_page( 'settings.php', SIGNUP_TOS_TITLE, SIGNUP_TOS_TITLE, SIGNUP_TOS_CAPABILITY, SIGNUP_TOS_SLUG, SIGNUP_TOS_CALLBACK );
	} else {
		add_options_page( SIGNUP_TOS_TITLE, SIGNUP_TOS_TITLE, SIGNUP_TOS_CAPABILITY, SIGNUP_TOS_SLUG, SIGNUP_TOS_CALLBACK );
	}
}

//------------------------------------------------------------------------//
//---Page Output Functions------------------------------------------------//
//------------------------------------------------------------------------//
/**
 * Shortcode for Adding TOS
 *
 * @param type $atts
 *
 * @return string
 */
function signup_tos_shortcode( $atts ) {
	$atts = shortcode_atts( array(
		'checkbox'   => 0,
		'show_label' => 1,
		'error'      => '',
		'multisite'  => true
	), $atts );

	$signup_tos = get_site_option( 'signup_tos_data' );
	if ( empty( $signup_tos ) ) {
		return '';
	}

	ob_start();

	if ( $atts['show_label'] ) { ?>
		<label for="tos_content"><?php _e( 'Nutzungsbedingungen', 'cp-tos' ) ?>:</label>
	<?php }

	if ( ! $atts['multisite'] ) {
		$style = "max-height:150px; overflow:auto; padding:10px; font-size:80%;";
	} else {
		$style = "background-color:white; border:1px gray inset; font-size:80%; margin-bottom: 10px; max-height:800px; min-height:80px; overflow:auto; padding:5px;";
	} ?>
	<div id="tos_content" style="<?php echo $style; ?>"><?php echo wpautop( $signup_tos ) ?></div>

	<?php if ( ! empty( $atts['error'] ) ) : ?>
		<p class="error"><?php echo $atts['error'] ?></p>
	<?php endif; ?>

	<?php
	if ( $atts['checkbox'] ) {
		?>
		<label>
		<input type="checkbox" id="tos_agree" name="tos_agree" value="1" <?php checked( isset( $_POST['tos_agree'] ) ? true : false ); ?> style="width:auto;display:inline">
		<?php _e( 'Ich stimme zu', 'cp-tos' ) ?>
		</label><?php
	}

	return ob_get_clean();
}

/**
 * Add TOS checkbox for Multisite signup
 *
 * @param type $errors
 */
function signup_tos_field_wpmu( $errors ) {
	// render error message if Membership plugin not exists otherwise Membership
	// plugin will use it's own errors rendering approach
	$message = ! empty( $errors ) &&
	           ! class_exists( 'Membership_Plugin', false ) ? $errors->get_error_message( 'tos' ) : '';

	$atts = array(
		'checkbox' => true,
		'error'    => $message,
	);
	echo signup_tos_shortcode( $atts );
}

/**
 * Render Checkbox on signup for Buddypress
 */
function signup_tos_field_bp() {
	$signup_tos = get_site_option( 'signup_tos_data' );
	if ( ! empty( $signup_tos ) ) {
		?>
		<div class="register-section" id="blog-details-section">
			<label for="tos_content"><?php _e( 'Nutzungsbedingungen', 'cp-tos' ); ?></label>
			<?php do_action( 'bp_tos_agree_errors' ) ?>
			<div id="tos_content" style="height:150px;width:100%;overflow:auto;background-color:white;padding:5px;border:1px gray inset;font-size:80%;"><?php echo $signup_tos ?></div>
			<label for="tos_agree"><input type="checkbox" id="tos_agree" name="tos_agree" value="1" <?php checked( isset( $_POST['tos_agree'] ) ? true : false ); ?>/> <?php _e( 'Ich  stimme zu', 'cp-tos' ); ?>
			</label>
		</div>
	<?php
	}
}

/**
 * Add TOS to WP regisstration form
 *
 * @param type $errors
 */
function signup_tos_field_wp( $errors ) {
	// render error message if Membership plugin not exists otherwise Membership
	// plugin will use it's own errors rendering approach
	$message = ! empty( $errors ) && ! class_exists( 'Membership_Plugin', false ) ? $errors->get_error_message( 'tos' ) : '';
	$atts    = array(
		'checkbox'  => true,
		'error'     => $message,
		'multisite' => false,
	);
	echo signup_tos_shortcode( $atts );
}

/**
 * Check if User agress to TOS or Display error
 *
 * @param type $errors
 *
 * @return type
 */
function signup_tos_filter_wpmu( $errors ) {

	$current_screen = function_exists( 'get_current_screen' ) ? get_current_screen() : false;
	$skip_admin_pages = array( 'user-network', 'user' );
	$skip_tos_check = ( ! empty( $current_screen ) && in_array( $current_screen->id, $skip_admin_pages ) );

	if ( apply_filters( 'signup_tos/skip_tos_check', $skip_tos_check ) ) {
		return $errors;
	}

	$signup_tos = get_site_option( 'signup_tos_data' );

	if ( ! empty( $signup_tos ) && ( ! isset( $_POST['tos_agree'] ) || (int) $_POST['tos_agree'] == 0 ) ) {
		$message = __( 'Du musst den Nutzungsbedingungen zustimmen, um Dich anmelden zu können.', 'cp-tos' );
		if ( is_array( $errors ) && isset( $errors['errors'] ) && is_wp_error( $errors['errors'] ) ) {
			$errors['errors']->add( 'tos', $message );
		} elseif ( is_wp_error( $errors ) ) {
			$errors->add( 'tos', $message );
		}
	}

	return apply_filters( 'signup_tos/wpmu_errors', $errors );
}

/**
 * Validate TOS if Buddypress is active and display error if TOS not checked
 * @global type $bp
 * @return type
 */
function signup_tos_filter_bp() {
	global $bp;
	if ( ! is_object( $bp ) || ! is_a( $bp, 'BuddyPress' ) ) {
		return;
	}
	$signup_tos = esc_attr( get_site_option( 'signup_tos_data' ) );
	if ( ! empty( $signup_tos ) && ( !isset( $_POST['tos_agree'] ) || (int) $_POST['tos_agree'] == 0 ) ) {
		$bp->signup->errors['tos_agree'] = __( 'Du musst den Nutzungsbedingungen zustimmen, um Dich anmelden zu können.', 'cp-tos' );
	}
}

/**
 * Validate TOS for wp
 */
function signup_tos_validate_wp( $errors ) {
	$signup_tos = esc_attr( get_site_option( 'signup_tos_data' ) );
	if ( ! empty( $signup_tos ) && ( !isset( $_POST['tos_agree'] ) || (int) $_POST['tos_agree'] == 0 ) ) {
		$errors->add( 'tos_agree', __( '<strong>ERROR</strong>: Du musst den Nutzungsbedingungen zustimmen, um Dich anmelden zu können.', 'cp-tos' ) );
	}

	return $errors;
}

/**
 *Adds a setting page
 * @return type
 */
function signup_tos_page_main_output() {
	if ( ! current_user_can( 'edit_users' ) ) {
		echo "<p>Nice Try...</p>"; //If accessed properly, this message doesn't appear.
		return;
	}

	// update message if posted
	$message = '';
	if ( $_SERVER['REQUEST_METHOD'] == 'POST' && isset( $_POST['signup_tos_data'] ) ) {
		update_site_option( "signup_tos_data", stripslashes( trim( $_POST['signup_tos_data'] ) ) );
		$message = esc_html__( 'Einstellungen gespeichert.', 'cp-tos' );
	}

	// render page
	?>
	<div class="wrap">
	<h2><?php _e( 'Nutzungsbedingungen', 'cp-tos' ) ?></h2>

	<?php if ( ! empty( $message ) ) : ?>
		<div id="message" class="updated fade"><p><?php echo $message ?></p></div>
	<?php endif; ?>

	<p class="description"><?php
		_e( 'Bitte gib hier den Text für Deine Nutzungsbedingungen ein. Es wird auf der Multisite-Seite wp-signup.php oder im BuddyPress-Registrierungsformular angezeigt. Du kannst auch den Shortcode [signup-tos] in Deinen Posts oder Seiten verwenden. Beachte dass Du das Kontrollkästchen aktivieren kannst (obwohl es nicht funktionsfähig ist), indem Du dem Shortcode das entsprechende Argument hinzufügst [signup-tos checkbox="1"].', 'cp-tos' )
		?></p>

	<br>

	<form method="post">
		<?php wp_editor( get_site_option( 'signup_tos_data' ), 'signuptosdata', array( 'textarea_name' => 'signup_tos_data' ) ) ?>

		<p class="submit">
			<input type="submit" class="button-primary" name="save_settings" value="<?php _e( 'Änderungen speichern', 'cp-tos' ) ?>">
		</p>
	</form>
	</div><?php
}


/**
 * Fix conflct with BP and M2
 */

function signup_tos_ms_validate_new_user ( $validation_errors, $member ) {
	if( function_exists( 'buddypress' ) )
	{
			$bp = buddypress();
	}
	else
	{
			$bp = '';
	}

	if ( ! is_object( $bp ) || ! is_a( $bp, 'BuddyPress' ) ) {
			return $validation_errors;
	}

	$signup_tos = esc_attr( get_site_option( 'signup_tos_data' ) );
if ( ! empty( $signup_tos ) && ( !isset( $_POST['tos_agree'] ) || (int) $_POST['tos_agree'] == 0 ) ) {
			$validation_errors->add(
					'tos_agree',
					__( 'Du musst den Nutzungsbedingungen zustimmen, um Dich anmelden zu können.', 'membership2' )
			);
	}

	return $validation_errors;
}

// PHP 5.2 does not support anonymous functions
add_filter( 'ms_model_membership_create_new_user_validation_errors', 'signup_tos_ms_validate_new_user', 90, 2 );


