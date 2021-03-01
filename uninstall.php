<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) { die; }

// Single site uninstall
// Also, don't run uninstall script on large networks (more than 10,000 users or more than 10,000 sites). Just uninstall from main site
if  ( ! is_multisite() || ( is_multisite() && wp_is_large_network() ) )
{
    flagged_content_pro_uninstall();
}
// Multisite uninstall
else
{
    global $wpdb;
    $blog_ids = $wpdb->get_col( "SELECT blog_id FROM $wpdb->blogs" );
    $original_blog_id = get_current_blog_id();

    foreach ( $blog_ids as $blog_id ) {
        switch_to_blog( $blog_id );
        flagged_content_pro_uninstall();
    }

    switch_to_blog( $original_blog_id );
}

function flagged_content_pro_uninstall()
{
    $forms_ids = get_option( 'flagged_content_pro_forms', array() );

    foreach ( $forms_ids as $form_id ) {
        delete_option( 'flagged_content_pro_form_' . $form_id );
    }

    delete_option( 'flagged_content_pro_forms' );
    delete_option( 'flagged_content_pro_form_0' );
    delete_option( 'flagged_content_pro_version' );
    delete_option( 'flagged_content_pro_settings' );

    global $wpdb;
    $table_name = $wpdb->prefix . 'flagged_content_pro';
    $wpdb->query( "DROP TABLE IF EXISTS {$table_name}" );
}