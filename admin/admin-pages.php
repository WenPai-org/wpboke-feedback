<?php
if ( ! defined( 'WPINC' ) ) { die; }

/**
 * Runs on all admin requests.
 * Controls admin menu output and contains several admin utility functions.
 */
class Flagged_Content_Pro_Admin
{
	public $admin_flags_page;
	public $admin_forms_page;
	public $admin_forms_edit_page;
	public $admin_settings_page;
	public $plugin;
	public $settings;
	public $page_sections;

	public function __construct( $admin_flags_page = null, $admin_forms_page = null, $admin_forms_edit_page = null, $admin_settings_page = null, $plugin )
    {
		$this->admin_flags_page 	 = $admin_flags_page;
		$this->admin_forms_page 	 = $admin_forms_page;
		$this->admin_forms_edit_page = $admin_forms_edit_page;
		$this->admin_settings_page 	 = $admin_settings_page;
		$this->plugin 				 = $plugin;
		$this->settings 			 = $this->plugin->settings;
        $this->init();
	}

	public function init()
    {
        // Cannot use global $pagenow in multisite installations as it is unavailable at this point
        $current_page     = basename( $_SERVER['PHP_SELF'] );
		$page_query_param = ( isset( $_GET['page'] ) ) ? $_GET['page'] : '';

        add_action( 'admin_menu', array( $this, 'add_admin_menus' ) );

		// Enqueue this plugin's admin scripts only on its admin pages
		if ( ( $current_page == 'admin.php' && ( $page_query_param == 'flagged_content_pro_flags_page' || $page_query_param == 'flagged_content_pro_forms_page' ||  $page_query_param == 'flagged_content_pro_forms_edit_page' || $page_query_param == 'flagged_content_pro_settings_page' ) ) || ( $current_page == 'options.php' ) ) {
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_files' ) );
		}
	}

	function add_admin_menus()
    {
	    global $wpdb;
	    $menu_label           = __( 'Flagged', 'flagged-content-pro' );
        $notify_count         = $wpdb->get_var( "SELECT COUNT(*) FROM " . FLAGCPRO_TABLE . " WHERE status = 1;" );
        $permission_flag_view = isset( $this->settings['permission_flag_view'] ) && ! empty( $this->settings['permission_flag_view'] ) ? $this->settings['permission_flag_view'] : 'manage_options';
        $permission_form_view = isset( $this->settings['permission_form_view'] ) && ! empty( $this->settings['permission_form_view'] ) ? $this->settings['permission_form_view'] : 'manage_options';
        $permission_form_edit = isset( $this->settings['permission_form_edit'] ) && ! empty( $this->settings['permission_form_edit'] ) ? $this->settings['permission_form_edit'] : 'manage_options';

	    if ( $notify_count > 0 && $this->plugin->check_permissions( 'flag', 'view' ) )
	    {
	        $menu_label_title = $notify_count . ' ' . _n( 'pending flag', 'pending flags', $notify_count, 'flagged-content-pro' );
	        $menu_label .= " <span class='update-plugins count-{$notify_count}' title='{$menu_label_title}'><span class='update-count'>{$notify_count}</span></span>";
	    }

	    // Main page
		add_menu_page( 
			__( 'Flagged Content Pro', 'flagged-content-pro' ), // $page_title
			$menu_label,  										// $menu_title
            $permission_flag_view, 			                    // $capability
			'flagged_content_pro_flags_page', 					// $menu_slug
			array( $this->admin_flags_page, 'display_page' ), 	// $function
			'dashicons-flag' 									// $icon_url
			  													// (int) $position
		);

		// Forms
		add_submenu_page( 
			'flagged_content_pro_flags_page', 					        // $parent_slug
			__( 'Flagged Content Pro - Forms', 'flagged-content-pro' ), // $page_title
            __( 'Forms', 'flagged-content-pro' ), 						// $menu_title
            $permission_form_view, 			                            // $capability
			'flagged_content_pro_forms_page', 					        // $menu-slug
			array( $this->admin_forms_page, 'display_page') 	        // $function
		);

		// Add edit forms
		$hook = add_submenu_page(
			'flagged_content_pro_forms_page', 						             // $parent_slug
			__( 'Flagged Content Pro - Add/Edit Forms', 'flagged-content-pro' ), // $page_title
			'', 													             // $menu_title
            $permission_form_edit, 				                                 // $capability
			'flagged_content_pro_forms_edit_page', 				                 // $menu-slug
			array( $this->admin_forms_edit_page, 'display_page') 	             // $function
		);

		// Settings
		add_submenu_page( 
			'flagged_content_pro_flags_page', 					           // $parent_slug
			__( 'Flagged Content Pro - Settings', 'flagged-content-pro' ), // $page_title
            __( 'Settings', 'flagged-content-pro' ), 				       // $menu_title
			'manage_options', 									           // $capability
			'flagged_content_pro_settings_page', 				           // $menu-slug
			array( $this->admin_settings_page, 'display_page' ) 	       // $function
		);

        add_action( "admin_footer-$hook", array( $this, 'forms_edit_menu_highlight' ) );
	}


	public function forms_edit_menu_highlight()
    {
        echo <<<HTML
        <script type="text/javascript">
        jQuery(document).ready( function($) {
            var flaggedc_menu_item = $( '#toplevel_page_flagged_content_pro_flags_page' );
            flaggedc_menu_item.removeClass( 'wp-not-current-submenu' ).addClass( 'current wp-has-current-submenu wp-menu-open' );   
            flaggedc_menu_item.find( 'a.wp-has-submenu' ).removeClass( 'wp-not-current-submenu' ).addClass( 'wp-has-current-submenu wp-menu-open' );    
            flaggedc_menu_item.find( 'li:nth-child(3)' ).addClass( 'current' );
        });     
        </script>
HTML;
    }


	function enqueue_admin_files( $hook )
    {
 	    wp_enqueue_style(   'wp-color-picker' );
	    wp_enqueue_style(   'flaggedc-admin-style',  FLAGCPRO_URL . 'css/admin-styles.css', array(), FLAGCPRO_VERSION );
	    wp_enqueue_script(  'flaggedc-admin-script', FLAGCPRO_URL . 'js/admin-script.js', array( 'jquery', 'wp-color-picker' ), FLAGCPRO_VERSION );
        wp_localize_script( 'flaggedc-admin-script', 'flaggedc_admin_object', array(
            'delete_all_wording' => esc_html__( 'Please confirm you want to delete all pending flags for this ', 'flagged-content-pro' )
        ) );
	}
}