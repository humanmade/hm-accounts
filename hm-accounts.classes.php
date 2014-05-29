<?php

class HM_Accounts {

	public $registration_data = array();
	public $id = 'manual';

	/**
	 * Get the account class, twitter / FB alternativly
	 *
	 * @static
	 * @param string $id
	 * @return HM_Accounts|HMA_SSO_Twitter|HMA_SSO_Facebook|null
	 */
	public static function get_instance( $id = 'manual' ) {

		$classes = array( 'manual' => 'HM_Accounts', 'twitter' => 'HMA_SSO_Twitter', 'facebook' => 'HMA_SSO_Facebook' );

		if ( ! isset( $classes[$id] ) )
			return null;

		$class = $classes[$id];

		return new $class();
	}

	/**
	 * Set the data to be used for registration. Registering a using is 2 stop. 1: set registration data, 2: register()
	 *
	 * @param array $data - user_login, user_email... etc
	 */
	public function set_registration_data( $data ) {

		$this->registration_data = $data;
	}

	/**
	 * Register an account based of registration data. Will perform validatino on the registration data
	 *
	 * @return int|WP_Error
	 */
	public function register() {

		$user_data = $this->registration_data;

		$defaults = array(
			'use_password' => false,
			'tos' => '',
			'use_tos' => true,
			'unique_email' => false,
			'do_login' => false,
			'send_email' => false,
			'user_login' => '',
			'user_email' => '',
			'user_pass' => false,
			'role' => 'subscriber',
			'set_cookie' => true
		);

		$original_args = $user_data;

		$args = wp_parse_args( $user_data, $defaults );

		unset( $args['user_pass2'] );
		unset( $original_args['user_pass2'] );
		unset( $user_pass2 );

		if ( is_wp_error( $err = $this->validate_registration_data() ) )
			return $err;

		// Merge arrays overwritting defaults, remove any non-standard keys keys with empty values.
		$user_vars = array_filter( array( 'user_login' => $args['user_login'], 'user_pass' => $args['user_pass'], 'user_email' => $args['user_email'], 'display_name' => $args['display_name'] ) );

		$user_vars = apply_filters( 'hma_register_user_data', $user_vars, $this );
		$user_id = wp_insert_user( $user_vars );

		if ( ! $user_id || is_wp_error( $user_id ) )
			return $user_id;

		// Setup the users role
		if ( $args['role'] ) {
			$user = new WP_User( $user_id );
			$user->set_role( $args['role'] );
		}

		// Get any remaining variable that were passed
		$meta_vars = array_diff_key( $original_args, $defaults, $user_vars );

		$meta_vars = apply_filters( 'hma_register_user_meta', $meta_vars, $this );

		foreach ( (array) $meta_vars as $key => $value ) {

			if ( hma_is_profile_field( $key ) || ! hma_custom_profile_fields() ) {
				update_user_meta( $user_id, $key, $value );
			}
		}

		$user = get_user_by( 'id', $user_id );

		// Send Notifcation email if specified
		if ( $args['send_email'] )
			$email = hma_email_registration_success( $user, $args['user_pass'] );

		// If they chose a password, login them in
		if ( ( $args['do_login'] == true ) && ! empty( $user->ID ) ) :

			wp_signon( array( 'user_login' => $user->user_login, 'user_password' => $args['user_pass'] ) );
			do_action( 'wp_login', $user->user_login, $user );

			wp_set_current_user( $user->ID );

		endif;

		do_action( 'hma_registered_user', $user );

		return $user_id;
	}

	/**
	 * Validation the registration data
	 * 
	 * @return bool|WP_error on fail
	 */
	public function validate_registration_data() {

		$args = $this->registration_data;

		// Unique username?
		// TODO could this not use username_exists?
		if ( !empty( $args['user_login'] ) && !empty( get_user_by( 'login', $args['user_login'] )->ID ) ) {
			return new WP_Error( 'username-exists', 'Sorry, the username: ' . $args['user_login'] . ' already exists.');
		}

		// Valid email?
		if ( ! is_email( $args['user_email'] ) ) {
			return new WP_Error( 'invalid-email', 'Invalid email address.');
		}

		// Unique email?
		// TODO whats wrong with email_exists?
		if ( !empty( $args['unique_email'] ) && !empty( $args['user_email'] ) && get_user_by( 'email', $args['user_email'] ) ) {
			return new WP_Error( 'email-in-use', 'That email is already in use.');
		}

		// Passwords match
		if ( ! empty( $args['user_pass'] ) && ! empty( $args['user_pass2'] ) && $args['user_pass'] != $args['user_pass2'] ) {
			return new WP_Error( 'password-mismatch', 'The passwords you entered do not match.');
		}

		return true;
	}

	/**
	 * Log a user in
	 *
	 * @param string|array|null $args
	 * @return bool|WP_Error
	 */
	public function login( $args = null ) {

		$args = apply_filters( 'hma_log_user_in_args', $args );

		if ( empty( $args['username'] ) ) {
			return new WP_Error( 'no-username', 'Please enter your username' );
		}

		// Strip any tags then may have been put into the array
		foreach( $args as $i => $a ) {
			if ( is_string( $a ) )
				$args[$i] = strip_tags( $a );
		}

		$defaults = array(
			'remember' => false,
			'allow_email_login' => true,
			'password_hashed' => false,
			'set_cookie' => true
		);

		$args = wp_parse_args( $args, $defaults );

		$user = get_user_by( 'login', $args['username'] );

		if ( ! $user && $args['allow_email_login'] )
			$user = get_user_by( 'email', $args['username'] );

		if ( empty( $user->ID ) ) {

			if ( $args['allow_email_login'] )
				return new WP_Error( 'unrecognized-username', 'The username / email you entered was not recognised.');

			return new WP_Error( 'unrecognized-username', 'The username you entered was not recognised.');
		}

		if ( $args['password_hashed'] != true ) {

			if ( ! wp_check_password( $args['password'], $user->user_pass ) ) {

				return new WP_Error('incorrect-password', 'The password you entered is incorrect.');
			}

		} else {

			if ( $args['password'] != $user->user_pass ) {

				return new WP_Error('incorrect-password', 'The password you entered is incorrect.');
			}

		}

		//Apply WordPress authenticate hooks - allows other plugins to fail the authentication
		$default_authenticate = apply_filters( 'authenticate', null, $user->user_login, $args['password'] );

		if ( is_wp_error( $default_authenticate ) )
			return $default_authenticate;

		if ( $args['set_cookie'] ) {
			wp_set_auth_cookie( $user->ID, $args['remember'] );
			wp_set_current_user( $user->ID );
		}

		do_action( 'wp_login', $user->user_login, $user );
		do_action( 'hma_log_user_in', $user);

		return true;
	}

	/**
	 * Get the URL to submit the registration form to
	 *
	 * @return string
	 */
	public function get_register_submit_url() {
		return add_query_arg( 'type', $this->id, get_bloginfo( 'url' ) . '/register/submit/' );
	}

	/**
	 * Get the URL to submit the login form to
	 *
	 * @return string
	 */
	public function get_login_submit_url() {
		return add_query_arg( 'type', $this->id, get_bloginfo( 'url' ) . '/login/submit/' );
	}
}

