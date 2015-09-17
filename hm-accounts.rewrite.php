<?php

/**
 * Create the rewrite rules for the user accounts section
 *
 * Will only create rules for the template files that exist
 *
 * @return null
 */
function hma_rewrite_rules() {

	if ( file_exists( $login = hma_get_login_template() ) )
		hm_add_rewrite_rule( '^' . hma_get_login_rewrite_slug() .'/?$', 'is_login=1', $login, array( 'post_query_properties' => array( 'is_home' => false, 'is_404' => false, 'is_login' => true ), 'permission' => 'logged_out_only' ) );

	if ( file_exists( $lost_pass = hma_get_lost_password_template() ) )
		hm_add_rewrite_rule( '^' . hma_get_lost_password_rewrite_slug() . '/?$', 'is_lost_password=1',  $lost_pass, array( 'post_query_properties' => array( 'is_home' => false, 'is_404' => false, 'is_lost_password' => true ), 'permission' => 'logged_out_only' ) );

	if ( file_exists( $register = hma_get_register_template() ) )
		hm_add_rewrite_rule( '^' . hma_get_register_rewrite_slug() . '/?$', 'is_register=1', $register, array( 'post_query_properties' => array( 'is_home' => false, 'is_404' => false, 'is_register' => true ), 'permission' => 'logged_out_only' ) );

	if ( file_exists( $edit_profile = hma_get_edit_profile_template() ) )
		hm_add_rewrite_rule( 
			'^' . hma_get_edit_profile_rewrite_slug() . '/?$', 
			'author_name=$matches[1]&is_profile=1', 
			$edit_profile, 
			array( 
				'post_query_properties' => array( 'is_home' => false, 'is_edit_profile' => true ), 
				'permission' => 'displayed_user_only',
				'request_callback' => function( $request ) {
					
					if ( is_user_logged_in() )
						$request->query_vars['author'] = get_current_user_id();
				}
			 )
		);
		
	if ( file_exists( $profile = hma_get_user_profile_template() ) )
		hm_add_rewrite_rule( '^' . hma_get_user_profile_rewrite_slug() . '/([^\/]*)(/page/([\d]*))?/?$', 'author_name=$matches[1]&paged=$matches[3]', $profile, array( 'post_query_properties' => array( 'is_home' => false, 'is_user_profile' => true ) ) );

	/**
 	 * Controller to catch the registration submitting
 	 */
	hm_add_rewrite_rule( array(
		'rewrite' => '^register/submit/?$',
		'request_callback' => function() {

			$type = ! empty( $_GET['type'] ) ? sanitize_key( $_GET['type'] )  : 'manual';

			$hm_accounts = HM_Accounts::get_instance( $type );
			 
			$details = array(

				'user_login' 	=> ! empty( $_POST['user_login'] ) ? sanitize_text_field( $_POST['user_login'] ) : '',
				'user_email'	=> ! empty( $_POST['user_email'] ) ? sanitize_email( $_POST['user_email'] ) : '',
				'use_password' 	=> true,
				'user_pass'		=> ! empty( $_POST['user_pass'] ) ? (string) $_POST['user_pass'] : '',
				'user_pass2'	=> ! empty( $_POST['user_pass_1'] ) ? (string) $_POST['user_pass_1'] : '',
				'unique_email'	=> true,
				'do_login' 		=> true
			);

			// also pass any registered profile fields
			foreach ( hma_get_profile_fields() as $field ) {
				if ( isset( $_POST[$field] ) )
					$details[$field] = $_POST[$field];
			}

			$details = apply_filters( 'hma_register_args', $details );

			$hm_accounts->set_registration_data( $details );

			$hm_return = $hm_accounts->register();

			if ( is_wp_error( $hm_return ) ) {

				do_action( 'hma_register_submitted_error', $hm_return );
				hm_error_message( $hm_return->get_error_message() ? $hm_return->get_error_message() : 'Something went wrong, error code: ' . $hm_return->get_error_code(), 'register' );
				wp_safe_redirect( wp_get_referer() );
				exit;

			} else {

				do_action( 'hma_register_completed', $hm_return );

				if ( ! empty( $_POST['redirect_to'] ) )
					$redirect = esc_url_raw( $_POST['redirect_to'] );

				elseif ( ! empty( $_POST['referer'] ) )
					$redirect = esc_url_raw( $_POST['referer'] );

				else
					$redirect = hma_get_edit_profile_url();

				wp_safe_redirect( $redirect );
				exit;
			}
		}
	) );

	/**
	 * Controller to catch the registration submitting
	 */
	hm_add_rewrite_rule( array(
		'rewrite' => '^login/submit/?$',
		'request_callback' => function() {

			$type = ! empty( $_GET['type'] ) ? sanitize_key( $_GET['type'] )  : 'manual';

			$hm_accounts = HM_Accounts::get_instance( $type );

			// normal login form authentication
			if ( isset( $_POST['user_pass'] ) ) {

				$details = array( 
					'password' => $_POST['user_pass'], 
					'username' => sanitize_text_field( $_POST['user_login'] ),
					'remember' => ! empty( $_POST['remember'] ) ? true : false
				);

			} else {
				$details = array();	
			}
			
			$details = apply_filters( 'hma_login_args', $details );

			$status = $hm_accounts->login( $details );

			if ( is_wp_error( $status ) )
				hm_error_message( 
					apply_filters( 
						'hma_login_error_message', 
						$status->get_error_message() ? $status->get_error_message() : 'Something went wrong, error code: ' . $status->get_error_code(), 
						$status
					), 
					'login' 
				);


			hma_do_login_redirect( $status, true );
		}
	) );

	/**
	 * Controller to catch the registration submitting
  	 */
	hm_add_rewrite_rule( array(
		'rewrite' => '^login/lost-password/submit/?$',
		'request_callback' => function() {

			$success = hma_lost_password( sanitize_email( $_POST['user_email'] ) );

			wp_safe_redirect( add_query_arg( array( 'message' => is_wp_error( $success ) ? 'reset-error' : 'reset-success' ), wp_get_referer() ) ) ;
		}
	) );

	/**
	 * Controller to catch the edit profile submit
	 */
	hm_add_rewrite_rule( array(
		'regex' => '^' . hma_get_edit_profile_rewrite_slug() . '/submit/?$', 
		'request_callback' => function() {
			hma_profile_submitted();
			exit;
		}
	));

}
add_action( 'init', 'hma_rewrite_rules', 2 );

/**
 * Return the rewrite slug for the login page
 *
 * @return string
 */
function hma_get_login_rewrite_slug() {
	return apply_filters( 'hma_login_rewrite_slug', 'login' );
}

/**
 * Return the rewrite slug for the lost password page
 *
 * @return string
 */
function hma_get_lost_password_rewrite_slug() {
	return apply_filters( 'hma_lost_password_rewrite_slug', 'login/lost-password' );
}

/**
 * Return the rewrite slug for the register page
 *
 * @return string
 */
function hma_get_register_rewrite_slug() {
	return apply_filters( 'hma_register_rewrite_slug', 'register' );
}

/**
 * Return the rewrite slug for the edit profile page
 *
 * @return string
 */
function hma_get_edit_profile_rewrite_slug() {
	return apply_filters( 'hma_edit_profile_rewrite_slug', 'profile' );
}

/**
 * Return the rewrite slug for the user profile page
 *
 * @return string
 */
function hma_get_user_profile_rewrite_slug() {
	return apply_filters( 'hma_user_profile_rewrite_slug', 'users' );
}

/**
 * Return the path to the login template
 *
 * @return string
 */
function hma_get_login_template() {
	return apply_filters( 'hma_login_template', locate_template( 'login.php' ) );
}

/**
 * Return the path to the lost password template
 *
 * @return string
 */
function hma_get_lost_password_template() {
	return  apply_filters( 'hma_lost_password_template', get_stylesheet_directory() . '/login.lost-password.php' );
}

/**
 * Return the path to the register template
 *
 * @return string
 */
function hma_get_register_template() {
	return  apply_filters( 'hma_register_template', get_stylesheet_directory() . '/register.php' );
}

/**
 * Return the path to the user profile template
 *
 * @return string
 */
function hma_get_user_profile_template() {
	return apply_filters( 'hma_user_profile_template', get_stylesheet_directory() . '/profile.php' );
}

/**
 * Return the path to the edit profile template
 *
 * @return string
 */
function hma_get_edit_profile_template() {
	return apply_filters( 'hma_edit_profile_template', get_stylesheet_directory() . '/profile.edit.php' );
}