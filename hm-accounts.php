<?php

/*
Plugin Name: HM Accounts
Description: Functionality for user logins / registrations / profiles etc.
Author: Human Made Limited
Version: 0.2
Author URI: http://humanmade.co.uk/
*/

include_once( 'hm-accounts.classes.php' );
include_once( 'hm-accounts.functions.php' );
include_once( 'hm-accounts.template-tags.php' );
include_once( 'hm-accounts.rewrite.php' );
include_once( 'hm-accounts.hooks.php' );
include_once( 'hm-accounts.actions.php' );

/**
 * Setup HM Accounts
 *
 * @access public
 * @return null
 */
function hma_init() {

	foreach( hma_default_profile_fields() as $field )
		hma_register_profile_field( $field );

}
add_action( 'init', 'hma_init', 9 );

function hma_default_profile_fields() {
	return array( 
		'user_avatar_path', 
		'first_name',
		'last_name',
		'description',
		'display_name_preference',
		'url',
		'location',
		'gender'
	);
}