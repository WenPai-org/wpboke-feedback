<?php
/*
* Plugin Name:       Flagged Content Pro
* Plugin URI:        http://divspark.com/products/flagged-content-pro/
* Description:       Allows visitors to flag content.
* Version:           1.5.1
* Author:            DivSpark
* Author URI:        http://divspark.com/
* License:           GPL-2.0+ & Envato Regular/Extended License
* License URI:       http://www.gnu.org/licenses/gpl-2.0.txt & https://codecanyon.net/licenses
* Text Domain:       flagged-content-pro
* Domain Path:       /languages
*/

// Kill execution if file is called directly
if ( ! defined( 'WPINC' ) ) {
	die; 
}

global $wpdb;
define( 'FLAGCPRO_VERSION', '1.5.1' );
/** The plugin's debug mode setting. 1 outputs information in several locations throughout the plugin. 0 suppresses all debugging information. */
define( 'FLAGCPRO_DEBUG', 0 );
/** Prefixed with wpdb prefix (e.g. wp_). Used throughout to refer to the plugin's table name */
define( 'FLAGCPRO_TABLE', $wpdb->prefix . 'flagged_content_pro' );
/** @var string The flagged content pro root directory. Used for requires. */
define( 'FLAGCPRO_DIR', plugin_dir_path( __FILE__ ) );
/** @var string The flagged content pro root url. Used for including scripts and styles.  */
define( 'FLAGCPRO_URL', plugin_dir_url( __FILE__ ) );
/** @var string The plugin basename, i.e. flagged-content-pro */
define( 'FLAGCPRO_BASENAME', plugin_basename( __FILE__ ) );


/**
 * Main class which assembles and executes all other php files
 */
class Flagged_Content_Pro
{
    /** @var array Array of global plugin settings found in the settings page. Retrieved from wp options */
	public $settings;
    /** @var array Array of forms created by user through the forms page. Retrieved from wp options */
    private $forms;

	public function __construct()
    {
        $this->settings = get_option( 'flagged_content_pro_settings', array() );
        $this->check_plugin_version();
        $this->init();
	}

    /**
     * Loads required php files and initializes objects. Filters request types to limit which resources are loaded
     */
	public function init()
    {
		// frontend request
		if ( ! is_admin() )
		{
            require_once ( FLAGCPRO_DIR . 'includes/frontend-output.php' );
		    require_once ( FLAGCPRO_DIR . 'includes/frontend.php' );
			$load_public = new Flagged_Content_Pro_Frontend( $this );
		}

		// ajax request
		elseif ( is_admin() && defined( 'DOING_AJAX' ) && DOING_AJAX )
        {
			require_once ( FLAGCPRO_DIR . 'includes/ajax.php' );
			$flaggedc_ajax = new Flagged_Content_Pro_AJAX( $this );
		}

		// admin request (non-ajax)
		else
        {
            // cannot use global $pagenow in multisite installations as it is unavailable at this point
            $current_page          = basename( $_SERVER['PHP_SELF'] );
			$page_query_param 	   = isset( $_GET['page'] ) ? $_GET['page'] : '';
			$admin_settings_page   = null;
			$admin_forms_page 	   = null;
			$admin_forms_edit_page = null;
			$admin_flags_page 	   = null;
			
			// flags page
			if ( $current_page == 'admin.php' && $page_query_param == 'flagged_content_pro_flags_page' )
			{
                require_once ( FLAGCPRO_DIR . 'admin/content.php' );
			    require_once ( FLAGCPRO_DIR . 'admin/class-wp-list-table.php' );
				require_once ( FLAGCPRO_DIR . 'admin/admin-flags-page.php' );
				$admin_flags_page = new Flagged_Content_Pro_Admin_Flags_Page( $this );
			}
			
			// forms page
			if ( $current_page == 'admin.php' && $page_query_param == 'flagged_content_pro_forms_page' )
			{
				require_once ( FLAGCPRO_DIR . 'admin/class-wp-list-table.php' );
				require_once ( FLAGCPRO_DIR . 'admin/admin-forms-page.php' );
				$admin_forms_page = new Flagged_Content_Pro_Admin_Forms_Page( $this );
			}

			// forms add edit page
			if ( ( $current_page == 'admin.php' && $page_query_param == 'flagged_content_pro_forms_edit_page' ) || $current_page == 'options.php' )
			{
				require_once ( FLAGCPRO_DIR . 'admin/admin-forms-edit-page.php' );
				$admin_forms_edit_page = new Flagged_Content_Pro_Admin_Forms_Edit_Page( $this );
			}

			// settings page
			if ( ( $current_page == 'admin.php' && $page_query_param == 'flagged_content_pro_settings_page' ) || $current_page == 'options.php' )
			{
				require_once ( FLAGCPRO_DIR . 'admin/admin-settings-page.php' );
				$admin_settings_page = new Flagged_Content_Pro_Admin_Settings_Page( $this );
			}

			// all admin pages
			require_once ( FLAGCPRO_DIR . 'admin/admin-pages.php' );
			$admin_page = new Flagged_Content_Pro_Admin( $admin_flags_page, $admin_forms_page, $admin_forms_edit_page, $admin_settings_page, $this );

            add_filter( 'plugin_action_links_' . FLAGCPRO_BASENAME, array( $this, 'add_plugin_action_links' ) );
            add_filter( 'plugin_row_meta', array( $this, 'add_plugin_row_meta' ), 10, 2 );
			register_activation_hook( __FILE__, array( $this, 'install_plugin' ) );
		}

        // all requests
        add_action( 'plugins_loaded', array( $this, 'load_i18n_textdomain' ) );

        // Check if the delete cleanup setting is true before adding the hooks
        if ( isset( $this->settings['delete_cleanup'] ) && $this->settings['delete_cleanup'] )
        {
            add_action( 'delete_post',    array( $this, 'post_delete_cleanup' ) );
            add_action( 'delete_comment', array( $this, 'comment_delete_cleanup' ) );
        }
	}

    public function get_forms()
    {
        if ( isset( $this->forms ) )
        {
            return $this->forms;
        }
        else
        {
            $this->forms = $this->load_forms();
            return $this->forms;
        }
    }

    /**
     * Loads the plugin's forms from the database into an associative array.
     *
     * Form = array(
     *      form_id => assoc array,
     *      form_id => assoc array,
     *      ...
     * )
     *
     * Gets the option flagged_c forms which returns an array of form_id's. The form_id
     * is used to correctly select flagged_content_pro_form_x options containing forms.
     *      flagged_content_pro_form_1, flagged_content_pro_form_2, flagged_content_pro_form_3, flagged_content_pro_form_x, ...
     *
     * @param $unpack boolean By default, the function unpacks the forms and prepares them for display. Setting to false will bypass
     * the unpacking process and will return forms ready for saving (but not for display).
     * @return array Returns an associative array, or if no forms are found then an empty array is returned
     */
     public function load_forms( $unpack = true )
     {
        // flaggedc_forms is an array of form_ids of all existing forms
        $forms_ids = get_option( 'flagged_content_pro_forms', array() );

        if ( empty( $forms_ids ) )
        {
            $forms = array();
        }
        else
        {
            foreach ( $forms_ids as $form_id )
            {
                $forms[ $form_id ] = get_option( 'flagged_content_pro_form_' . $form_id );

                if ( $unpack )
                {
                    $forms[ $form_id ]['reason_choose'] = $this->unpack_reason( $forms[ $form_id ]['reason_choose'] );
                    $forms[ $form_id ]['content']       = $this->unpack_content( $forms[ $form_id ]['content'] );
                }
            }
        }

        return $forms;
    }


    /**
     * $packed_reason comes in as:
     * 	"reason 1\nreason 2\nreason 3"
     *
     * Reason choose needs to be unpacked as:
     * Array(
     * 		[0] => reason 1
     * 		[1] => reason 2
     * 		[2] => reason 3
     * )
     *
     * @param $packed_reason
     * @return array
     */
    public function unpack_reason( $packed_reason )
    {
        if ( empty( $packed_reason ) ) {
            return array();
        }
        else {
            return explode( "\n", $packed_reason );
        }
    }


    /**
     * Takes in an array of sequential content (string values) and unpacks it into an array of arrays.
     *
     * $packed_content comes in as:
     * 	Array(
     * 		[0] => post;builtin
     * 		[1] => page;builtin
     * 		[2] => movie;custom
     * )
     *
     * Content needs to be unpacked as:
     * 	Array(
     * 		[0] => Array( 	[name]
     * 				        [type]
     *             )
     * 		[1] => Array(	[name]
     * 			        	[type]
     *             )
     * )
     * @param $packed_content
     * @return array
     */
    public function unpack_content( $packed_content )
    {
        $content_ready = array();
        $unpacked = array();

        foreach ( $packed_content as $index => $content ) {
            $unpacked = explode( ';', $content );
            $content_ready[ $index ]['name'] = $unpacked[0];
            $content_ready[ $index ]['type'] = $unpacked[1];
        }

        return $content_ready;
    }


    /**
     * Returns a form matching $form_id
     * @param $form_id
     * @return array|bool Returns the form as an associative array, or false if no form is found
     */
    public function get_form_by_id( $form_id )
    {
        $forms = $this->get_forms();

        foreach ( $forms as $form )
        {
            if ( $form['form_id'] == $form_id ) {
                return $form;
            }
        }

        return false;
    }

    /**
     * Returns an array containing the form for the current content (post type). This array contains all user defined settings for the form
     * including fields, location, whether to send emails after a submit.
     *
     * @param $content_name
     * @param $content_type
     * @return array|bool Returns an array containing the form for the current content (post) type. Or, false if no form was found for this content type.
     */
    public function get_form_by_content( $content_name, $content_type = 'post', $location = false )
    {
        $forms =  $this->get_forms();

        foreach ( $forms as $form )
        {
            if ( $location == 'auto' && ( $form['reveal_location'] != 'content_before' && $form['reveal_location'] != 'content_after' ) ) {
                continue;
            }

            if ( $location == 'shortcode' && ( $form['reveal_location'] != 'shortcode' ) ) {
                continue;
            }

            foreach ( $form['content'] as $content )
            {
                if ( $content['name'] == $content_name && $content['type'] == $content_type ) {
                    return $form;
                }
            }
        }

        return false;
    }


    /**
     * Generates and returns additional information for a content item
     * [0] => Array (
     * 		[name]
     * 		[type]
     *  	[value]
     *  	[label]
     *      [wp_builtin]
     * 	)
     * @param $content_name
     * @param string $content_type post, comment
     * @return array associative array
     */
    public function get_content_info( $content_name, $content_type = '' )
    {
        $name = $content_name;
        $type = $content_type;
        $value = $name . ';' . $type;
        $exists = true;

        // WP Comment
        if ( $type == 'comment' )
        {
            $label = __( 'Comments', 'flagged-content-pro' );
            $wp_builtin = true;
        }
        // Post Type
        else
        {
            $args = array( 'name' => $content_name );
            $post_types = get_post_types( $args, 'objects' );

            // Content does not exist
            if ( empty( $post_types ) )
            {
                $label      = $name;
                $wp_builtin = false;
                $exists     = false;
            }

            // Content exists
            else
            {
                // Return is an array, but this will only loop once
                foreach ( $post_types as $post_type )
                {
                    // bbPress
                    if ( ( $name == 'forum' || $name == 'topic' || $name == 'reply' ) && class_exists( 'bbPress' ) )
                    {
                        $label = "bbPress: {$post_type->label}";
                        $wp_builtin = false;
                    }
                    // Post
                    else
                    {
                        $label = $post_type->_builtin ? $post_type->label : "{$post_type->label}";
                        $wp_builtin = $post_type->_builtin;
                    }
                }
            }
        }

        return array(
            'name' 		 => $name,
            'type'       => $type,
            'value' 	 => $value,
            'label' 	 => $label,
            'wp_builtin' => $wp_builtin,
            'exists'     => $exists
        );
    }

    /**
     * Returns all existing builtin and custom content items
     *
     * @return array
     */
    public function get_all_content()
    {
        $args = array( 'public' => true, '_builtin' => true );
        $post_types = get_post_types( $args, 'objects' );

        $content = array();

        // Handle builtin post types
        // post, page, attachment
        foreach ( $post_types as $post_type ) {
            $content[] = $this->get_content_info( $post_type->name, 'post' );
        }

        // Handle builtin wp comments
        $content[] = $this->get_content_info( 'comment', 'comment' );

        // Handle bbPress and custom post types
        // e.g. topic, reply, movie, product,
        $args = array( 'public' => true, '_builtin' => false );
        $post_types = get_post_types( $args, 'objects' );

        foreach ( $post_types as $post_type ) {
            $content[] = $this->get_content_info( $post_type->name, 'post' );
        }

        return $content;
    }

    /**
     * Generates and returns an associative array of default values for a form
     *
     * @param array $override Override the default values
     * @return array associative array
     */
    public function get_default_form_settings( $override = array() )
    {
        $default = array(
            'form_id'                   => '',
            'form_name'                 => __( 'Unnamed', 'flagged-content-pro' ),
            'form_status'               => 'active',
            'form_user'                 => 'everyone',
            'content'                   => array(),
            'reveal_location' 			=> 'content_before',
            'reveal_priority' 	        => '10',
            'form_action'               => 0,
            'form_action_number'        => '3',
            'reveal_icon'               => 'no_icon',
            'reveal_label' 				=> __( 'Report Issue', 'flagged-content-pro' ),
            'reveal_success_icon'       => 'no_icon',
            'reveal_success_label'      => __( 'Reported', 'flagged-content-pro' ),
            'reveal_display' 			=> 'button',
            'reveal_style' 				=> 'theme;theme',
            'reveal_color_base' 		=> '#1e73be',
            'reveal_color_hover'        => '#185c98',
            'reveal_color_text'         => '#fdfdfd',
            'reveal_align'              => 'left',
            'name' 						=> 'optional',
            'email' 					=> 'optional',
            'reason' 					=> 'required',
            'reason_choose' 			=> __( "This post contains broken links\nPost has incorrect information\nPost has spam\nCopyright Issue\nOther", 'flagged-content-pro' ),
            'reason_display' 		    => 'dropdown',
            'description' 				=> 'optional',
            'submit_label' 				=> __( 'Submit', 'flagged-content-pro' ),
            'submit_sending_label'      => __( 'Sending', 'flagged-content-pro' ),
            'submit_style' 				=> 'theme;theme',
            'submit_color_base' 		=> '#1e73be',
            'submit_color_hover'        => '#185c98',
            'submit_color_text'         => '#fdfdfd',
            'message_instructions' 		=> '',
            'message_success' 			=> __( 'You have flagged this item.', 'flagged-content-pro' ),
            'message_fail_required' 	=> __( 'Please complete the required fields.', 'flagged-content-pro' ),
            'message_fail_email' 	    => __( 'Please enter a valid e-mail address.', 'flagged-content-pro' ),
            'message_fail_validation' 	=> __( 'There was an error. Please try again.', 'flagged-content-pro' ),
            'email_enabled' 	        => 1,
            'email_to_blog_admin' 	    => 1,
            'email_to_admins' 	        => 0,
            'email_to_editors' 	        => 0,
            'email_to_author' 	        => 0,
            'email_to_custom_address' 	=> '',
            'email_subject'		        => __( '[[site_name]] [content_name] flagged', 'flagged-content-pro' ),
            'email_message' 	        => __( "[content_name] flagged: [content_desc]\nContent link: [content_link]\nFlag reason: [flag_reason]\nFlag description: [flag_description]", 'flagged-content-pro' ),
            'email_limit'               => 0,
            'email_limit_number'        => '3'
        );

        if ( is_array( $override ) and ! empty( $override ) ) {
            return array_merge( $default, $override );
        }
        else {
            return $default;
        }
    }

    /**
     * Generates and returns an associative array of default values for the global settings
     *
     * @param array $override Override the default values. Leave blank to just get all default values.
     * @return array associative array
     * @since 1.3.0
     */
    public function get_default_global_settings( $override = array() )
    {
        $default = array(
            'permission_flag_view' => 'manage_options',
            'permission_flag_edit' => 'manage_options',
            'permission_form_view' => 'manage_options',
            'permission_form_edit' => 'manage_options',
            'honeypot' 		       => 1,
            'time_review' 	       => 1,
            'modal_type'           => 'magnific-popup',
            'delete_cleanup'       => 1,
            'save_ip_address'      => 1
        );

        if ( is_array( $override ) and ! empty( $override ) ) {
            return array_merge( $default, $override );
        }
        else {
            return $default;
        }
    }


    /**
     * Check if the user has the appropriate permissions.
     *
     * @param string $area Pass in which area to check permissions for: flags or forms.
     * @param string $action Pass in the action to check permissions for: 'view' or 'edit'.
     * @return bool|string Returns TRUE if the user has permission, FALSE otherwise. If invalid parameters are passed then FALSE is returned as well.
     */
    public function check_permissions( $area = 'flag', $action = 'edit' )
    {
        $settings = $this->settings;

        if ( $area == 'flag' && $action == 'view' ) {
            $current_permissions = $settings['permission_flag_view'];
        }
        elseif ( $area == 'flag' && $action == 'edit' ) {
            $current_permissions = $settings['permission_flag_edit'];
        }

        elseif ( $area == 'form' && $action == 'view' ) {
            $current_permissions = $settings['permission_form_view'];
        }
        elseif ( $area == 'form' && $action == 'edit' ) {
            $current_permissions = $settings['permission_form_edit'];
        }

        else {
            return false;
        }

        return current_user_can( $current_permissions );
    }

    /**
     * Adds a settings link to the plugin's actions under plugins.php
     * - filter plugin_action_links_ . FLAGCPRO_BASENAME - __construct()
     * @param $links
     * @return array
     */
    public function add_plugin_action_links( $links )
    {
        $add_links = array();
        $add_links[] = '<a href="' . admin_url( 'admin.php?page=flagged_content_pro_forms_page' ) . '">' . __( 'Forms', 'flagged-content-pro' ) . '</a>';
        $add_links[] = '<a href="' . admin_url( 'admin.php?page=flagged_content_pro_settings_page' ) . '">' . __( 'Settings', 'flagged-content-pro' ) . '</a>';
        return array_merge( $add_links, $links );
    }

    /**
     * Adds a view more link to the plugin's meta under plugins.php
     * - filter plugin_row_meta - __construct()
     * @param $links
     * @param $file
     * @return array
     */
    public function add_plugin_row_meta( $links, $file )
    {
        $add_links = array();

        if ( $file == FLAGCPRO_BASENAME ) {
            $add_links[] = '<a href="http://divspark.com/tutorials/flagged-content-pro-quick-guide/?utm_source=wordpress_flagged_content_pro&utm_medium=plugins_page_tutorial_link&utm_campaign=wordpress">' . __( 'Tutorials', 'flagged-content-pro' ) . '</a>';
        }

        return array_merge( $links, $add_links );
    }


    /**
     * Compare plugin version number in user's database with this code's version number.
     * If they don't match then an update has happened.
     *
     * @return void
     */
    public function check_plugin_version()
    {
       // check if using old option names
        if ( get_option( 'flaggedc_version' ) !== false && version_compare( get_option( 'flaggedc_version' ), '1.2.0', '<' )  ) {
            $this->update_to_120();
        }

        // check if version stored in database matches the current plugin version
        // version stored in database does not match current plugin version - run update function
        if ( get_option( 'flagged_content_pro_version' ) !== FLAGCPRO_VERSION ) {
            $this->update_plugin();
        }
        // versions match: do nothing and return
        else {
            return;
        }
    }


    /**
     * Sync the form and settings stored in the database with any newly added options by a new version
     * @since 1.1.0
     */
    public function update_plugin()
    {
        // sync forms in db with any newly added options
        $forms = $this->load_forms( false );

        foreach ( $forms as $form )
        {
            $form_id = $form['form_id'];
            $updated_form = array_merge( $this->get_default_form_settings(), $form );
            update_option( 'flagged_content_pro_form_' . $form_id, $updated_form );
        }

        // sync global settings in db with any newly added options
        $settings = $this->settings;
        $updated_settings = array_merge( $this->get_default_global_settings(), $settings );
        update_option( 'flagged_content_pro_settings', $updated_settings );
        $this->settings = $updated_settings;

        if ( FLAGCPRO_DEBUG )
        {
            echo '<strong>Update running</strong><br>';
            echo '<pre>';
                echo '$forms<br>';
                print_r ( $forms );
                echo '$settings:<br>';
                print_r ( $settings );
            echo '</pre>';
        }

        // update flagged content pro version number in database
        update_option( 'flagged_content_pro_version', FLAGCPRO_VERSION );
    }


    /**
     * Updating from pre 1.2.0 versions: change option names and table name
     * @since 1.2.0
     */
    private function update_to_120()
    {
        $forms_ids = get_option( 'flaggedc_forms', array() );

        foreach ( $forms_ids as $form_id )
        {
            add_option( 'flagged_content_pro_form_' . $form_id,  get_option( 'flaggedc_form_' . $form_id ) );
            delete_option( 'flaggedc_form_' . $form_id );
        }

        add_option( 'flagged_content_pro_forms',    $forms_ids );
        add_option( 'flagged_content_pro_form_0',   get_option( 'flaggedc_form_0' ) );
        add_option( 'flagged_content_pro_settings', get_option( 'flaggedc_settings' ) );
        add_option( 'flagged_content_pro_version',  get_option( 'flaggedc_version' ) );
        delete_option( 'flaggedc_forms' );
        delete_option( 'flaggedc_form_0' );
        delete_option( 'flaggedc_settings' );
        delete_option( 'flaggedc_version' );

        global $wpdb;
        $wpdb->query( "RENAME TABLE {$wpdb->prefix}flagged_content TO {$wpdb->prefix}flagged_content_pro" );
    }


    /**
     * Builds a table, adds a default form and default settings
     */
    function install_plugin()
    {
        if ( get_option( 'flaggedc_version' ) !== false && version_compare( get_option( 'flaggedc_version' ), '1.2.0', '<' ) ) {
            $this->update_to_120();
        }

        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        $sql =
            "CREATE TABLE " . FLAGCPRO_TABLE . " (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			status tinyint NOT NULL,
			name_entered varchar(75),
			email varchar(255),
			reason varchar(255),
			description text,
			ip varbinary(16),
			user_id bigint(20) NOT NULL,
			date_notified datetime NOT NULL,
			content_id bigint(20) NOT NULL,
			content_name varchar(20),
			content_type varchar(20) NOT NULL,
			PRIMARY KEY  (id),
			KEY status (status),
			KEY content_type (content_type)
		    ) $charset_collate;";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

        dbDelta( $sql );

        $args = array (
            'form_id'   => '1',
            'form_name' => __( 'Post form', 'flagged-content-pro' ),
            'content' => array( 'post;post' )
        );

        add_option( 'flagged_content_pro_version',  FLAGCPRO_VERSION );
        add_option( 'flagged_content_pro_forms',    array( 1 ) );
        add_option( 'flagged_content_pro_form_1',   $this->get_default_form_settings( $args ) );
        add_option( 'flagged_content_pro_settings', $this->get_default_global_settings() );
    }


    /**
     * When a post is deleted, remove any flags under that post
     * This is a plugin setting and setting must be TRUE before hooking into delete_post
     *
     * @param $content_id - id of post about to be deleted
     */
    public function post_delete_cleanup( $content_id )
    {
        global $wpdb;
        $sql = $wpdb->prepare( "DELETE FROM " . FLAGCPRO_TABLE . " WHERE content_id = %d AND content_type = 'post'", $content_id );
        $wpdb->query( $sql );
    }

    /**
     * When a comment is deleted, remove any flags under it
     * This is a plugin setting and setting must be TRUE before hooking into delete_post
     * Has to be here Flagged_Content_Pro (all requests), otherwise misses ajax deletes.
     *
     * @param $content_id - id of comment about to be deleted
     * @since 1.2.1
     */
    public function comment_delete_cleanup( $content_id )
    {
        global $wpdb;
        $sql = $wpdb->prepare( "DELETE FROM " . FLAGCPRO_TABLE . " WHERE content_id = %d AND content_type = 'comment'", $content_id );
        $wpdb->query( $sql );
    }

    /**
     * @since 1.3.0
     */
    public function load_i18n_textdomain()
    {
        load_plugin_textdomain( 'flagged-content-pro', FALSE, basename( dirname( __FILE__ ) ) . '/languages/' );
    }
}
$flagged_content_pro_plugin = new Flagged_Content_Pro();