<?php

/**
 * Redirect the user after they login
 *
 * @param mixed $return
 * @return null
 */
function hma_do_login_redirect( $return, $do_redirect_on_error = false ) {

	if ( is_wp_error( $return ) ) {

		do_action( 'hma_login_submitted_error', $return );

		$redirect = ( wp_get_referer() ) ? wp_get_referer() : hma_get_login_url();
		if ( ! empty( $_REQUEST['redirect_to'] ) )
			$redirect = add_query_arg( 'redirect_to', esc_url_raw( $_REQUEST['redirect_to'] ), $redirect );

		if ( $do_redirect_on_error ) {
			wp_safe_redirect( add_query_arg( 'errored', time(), $redirect ), 303 );
			exit;
		}

		return;

	} else {

		if ( ! empty( $_REQUEST['redirect_to'] ) )
			$redirect = esc_url_raw( urldecode( $_REQUEST['redirect_to'] ) );

		elseif ( ! empty( $_POST['referer'] ) ) //success
			$redirect = esc_url_raw( $_POST['referer'] );

		else
			$redirect = get_bloginfo('url');

		do_action( 'hma_login_submitted_success', $redirect );

		$redirect = apply_filters( 'hma_login_redirect', $redirect );

		// we have to use header: location as wp_redirect messes up arrays in GET params
		header( 'Location: ' . hm_parse_redirect( $redirect ), true, 303 );
		exit;
	}

}

/**
 * Parse the redirect string and replace _user_login_ with
 * the users login.
 *
 * @param string $redirect
 * @return string
 */
function hm_parse_redirect( $redirect ) {

	if ( is_user_logged_in() )
		$redirect = str_replace( '_user_login_', wp_get_current_user()->user_login, $redirect );

	$redirect = wp_sanitize_redirect( $redirect );
	$redirect = wp_validate_redirect( $redirect, home_url() );

	return apply_filters( 'hm_parse_login_redirect',  $redirect );

}


/**
 * Process the edit profile form submission
 *
 * @return null
 */
function hma_profile_submitted() {

	check_admin_referer( 'hma-update-profile' );

	$current_user = wp_get_current_user();

	// check the user is logged in
	if ( !is_user_logged_in() )
		return;

	// Loop through all data and only user user_* fields or fields which have been registered using hma_register_profile_field
	foreach( $_POST as $key => $value ) {

		if ( ( ! hma_is_profile_field( $key ) && hma_custom_profile_fields() ) || ( ! hma_custom_profile_fields() && strpos( $key, 'user_' ) !== 0 ) )
			continue;

		$user_data[$key] = is_string( $value ) ? sanitize_text_field( $value ) : array_map( 'sanitize_text_field', $value );

	}

	// Check that the passwords match if they were $_POST'd
	if ( ! empty( $_POST['user_pass'] ) && isset( $_POST['user_pass2'] ) && ( $_POST['user_pass'] !== $_POST['user_pass2'] ) ) {
		hm_error_message( 'The passwords you entered do not match', 'update-user' );
		return;
	}

	if ( ! empty( $_POST['user_pass'] ) )
		$user_data['user_pass'] = $_POST['user_pass'];

	else
		unset( $user_data['user_pass'] );

	if ( ! empty( $_POST['user_email'] ) )
		$user_data['user_email'] = sanitize_email( $_POST['user_email'] );

	$user_data['ID'] = $current_user->ID;

	if ( isset( $_POST['first_name'] ) )
		$user_data['first_name'] = sanitize_text_field( $_POST['first_name'] );

	if ( isset( $_POST['last_name'] ) )
		$user_data['last_name'] = sanitize_text_field( $_POST['last_name'] );

	if ( isset( $_POST['nickname'] ) )
		$user_data['nickname'] = sanitize_text_field( $_POST['nickname'] );

	$user_data['user_login'] = $current_user->user_login;

	if ( isset( $_POST['description'] ) )
		$user_data['description'] = wp_kses_post( $_POST['description'] );

	if ( isset( $_POST['display_name'] ) ) {

		$name = trim( sanitize_text_field( $_POST['display_name'] ) );
		$match = preg_match_all( '/([\S^\,]*)/', $name, $matches );

		foreach( array_filter( (array) $matches[0] ) as $match )
			$name = trim( str_replace( $match, $user_data[$match], $name ) );

		$user_data['display_name'] = $name;
		$user_data['display_name_preference'] = $name;

	}

	if ( ! empty( $_FILES['user_avatar']['name'] ) ) {

		if ( wp_check_filetype_and_ext( $_FILES['user_avatar']['tmp_name'], $_FILES['user_avatar']['name'] ) )

			$user_data['user_avatar'] = $_FILES['user_avatar'];
	}

	$success = hma_update_user_info( $user_data );

	if ( is_wp_error( $success ) ) {

		do_action( 'hma_update_user_profile_error', $success );
		return;

	} else {

		if ( ! empty( $_POST['redirect_to'] ) )
			$redirect = esc_url_raw( $_POST['redirect_to'] );

		elseif ( ! empty( $_POST['referer'] ) )
			$redirect = esc_url_raw( $_POST['referer'] );

		elseif ( wp_get_referer() )
			$redirect = wp_get_referer();

		else
			$redirect = hma_get_edit_profile_url();

		do_action( 'hma_update_user_profile_completed', $redirect );

		wp_redirect( $redirect, 303 /* 303 means redirect for form submission - remove this comment */ );

		exit;

	}

}

/**
 * Log the user out
 *
 * @return null
 */
function hma_logout() {

	if ( isset( $_GET['action'] ) && $_GET['action'] == 'logout' ) :

		// Fire the WordPress logout
		wp_logout();

		if ( ! empty( $_GET['redirect_to'] ) ) {
			$redirect = esc_url_raw( $_GET['redirect_to'] );

		} else {
			$redirect = remove_query_arg( 'action', wp_get_referer() );

			// Redirect to homepage if logged out from wp-admin
			if ( strpos( $redirect, '/wp-admin' ) )
				$redirect = get_bloginfo( 'url' );

		}

		wp_redirect( $redirect );
		exit;

	endif;

}
add_action( 'init', 'hma_logout', 9 );