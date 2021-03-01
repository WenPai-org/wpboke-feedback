<?php
if ( ! defined( 'WPINC' ) ) { die; }

/**
 * Prepares and renders the settings page (sections, fields and register)
 */
class Flagged_Content_Pro_Admin_Settings_Page
{
	private $plugin;
	private $settings;

    private $option;
	private $option_group = 'flaggedc_settings_group';
    private $option_name  = 'flagged_content_pro_settings';
    private $page_slug    = 'flagged_content_pro_settings_page';
    private $hr           = '</td></tr><tr><th></th><td><hr>';

	public function __construct( $plugin )
    {
		$this->plugin   = $plugin;
		$this->settings = $this->plugin->settings;
        $this->option   = get_option( 'flagged_content_pro_settings' );
        add_action( 'admin_init', array( $this, 'admin_settings_init' ) );
	}

    /**
     * Callback function - Prepares the form and fields for the page through the wordpress settings api
     *
     * @action admin_init
     */
    public function admin_settings_init()
    {
        // add_settings_field( $id, $title, $callback, $page, $section, $args);
        add_settings_field( 'permission_flag_view', __( 'View flags', 'flagged-content-pro' ),                       array( $this, 'field_permission_flag_view' ), $this->page_slug, 'permissions_section' );
        add_settings_field( 'permission_flag_edit', __( 'Edit flags', 'flagged-content-pro' ),                       array( $this, 'field_permission_flag_edit' ), $this->page_slug, 'permissions_section' );
        add_settings_field( 'permission_form_view', __( 'View forms', 'flagged-content-pro' ),                       array( $this, 'field_permission_form_view' ), $this->page_slug, 'permissions_section' );
        add_settings_field( 'permission_form_edit', __( 'Edit forms', 'flagged-content-pro' ),                       array( $this, 'field_permission_form_edit' ), $this->page_slug, 'permissions_section' );
        add_settings_field( 'honeypot',             __( 'Honeypot', 'flagged-content-pro' ),                         array( $this, 'field_honeypot' ),             $this->page_slug, 'spam_section' );
        add_settings_field( 'time_review',          __( 'Timestamp Defense', 'flagged-content-pro' ),                array( $this, 'field_time_review' ),          $this->page_slug, 'spam_section' );
        add_settings_field( 'modal_type',           __( 'Modal popup type', 'flagged-content-pro' ),                 array( $this, 'field_modal_type' ),           $this->page_slug, 'other_section' );
        add_settings_field( 'delete_cleanup',       __( 'Remove flags on delete', 'flagged-content-pro' ),           array( $this, 'field_delete_cleanup' ),       $this->page_slug, 'other_section' );
        add_settings_field( 'save_ip_address',      __( 'Save flag submitters IP address', 'flagged-content-pro' ),  array( $this, 'field_save_ip_address' ),      $this->page_slug, 'other_section' );

		// add_settings_section( $id, $title, $callback, $page );
		add_settings_section( 'permissions_section', 	'', array( $this, 'permissions_section_display' ),  $this->page_slug );
		add_settings_section( 'spam_section', 			'', array( $this, 'spam_section_display' ), 		$this->page_slug );
		add_settings_section( 'other_section', 		    '', array( $this, 'other_section_display' ), 		$this->page_slug );

		//register_setting( $option_group, $option_name, $callback );
		register_setting( $this->option_group, 'flagged_content_pro_settings', array( $this, 'sanitize_values' ) );
	}

    /**
     * Render fields: Callback functions to render the fields.
     */
    public function field_permission_flag_view()
    {
        echo "<select name='{$this->option_name}[permission_flag_view]'>";
        echo "<option value='manage_options' "    . selected( $this->option['permission_flag_view'], 'manage_options', false )    . ">" . esc_html__( 'Administrator', 'flagged-content-pro' ) . "</option>";
        echo "<option value='edit_others_posts' " . selected( $this->option['permission_flag_view'], 'edit_others_posts', false ) . ">" . esc_html__( 'Editor', 'flagged-content-pro' )        . "</option>";
        echo "<option value='publish_posts' "     . selected( $this->option['permission_flag_view'], 'publish_posts', false )     . ">" . esc_html__( 'Author', 'flagged-content-pro' )        . "</option>";
        echo "<option value='edit_posts' "        . selected( $this->option['permission_flag_view'], 'edit_posts', false )        . ">" . esc_html__( 'Contributor', 'flagged-content-pro' )   . "</option>";
        echo "<option value='read' "              . selected( $this->option['permission_flag_view'], 'read', false )              . ">" . esc_html__( 'Subscriber', 'flagged-content-pro' )    . "</option>";
        echo "</select>";
        echo "<p class='description'>" . esc_html__( 'Minimum role needed to view the list of submitted flags within the admin section.', 'flagged-content-pro' ) . "</p>";
    }

    public function field_permission_flag_edit()
    {
        echo "<select name='{$this->option_name}[permission_flag_edit]'>";
        echo "<option value='manage_options' "    . selected( $this->option['permission_flag_edit'], 'manage_options', false )    . ">" . esc_html__( 'Administrator', 'flagged-content-pro' ) . "</option>";
        echo "<option value='edit_others_posts' " . selected( $this->option['permission_flag_edit'], 'edit_others_posts', false ) . ">" . esc_html__( 'Editor', 'flagged-content-pro' )        . "</option>";
        echo "<option value='publish_posts' "     . selected( $this->option['permission_flag_edit'], 'publish_posts', false )     . ">" . esc_html__( 'Author', 'flagged-content-pro' )        . "</option>";
        echo "<option value='edit_posts' "        . selected( $this->option['permission_flag_edit'], 'edit_posts', false )        . ">" . esc_html__( 'Contributor', 'flagged-content-pro' )   . "</option>";
        echo "<option value='read' "              . selected( $this->option['permission_flag_edit'], 'read', false )              . ">" . esc_html__( 'Subscriber', 'flagged-content-pro' )    . "</option>";
        echo "</select>";
        echo "<p class='description'>" . esc_html__( 'Minimum role needed to edit submitted flags such as changing a flag&#39;s status or deleting it.', 'flagged-content-pro' ) . "</p>";
    }

    public function field_permission_form_view()
    {
        echo "<select name='{$this->option_name}[permission_form_view]'>";
        echo "<option value='manage_options' "    . selected( $this->option['permission_form_view'], 'manage_options', false )    . ">" . esc_html__( 'Administrator', 'flagged-content-pro' ) . "</option>";
        echo "<option value='edit_others_posts' " . selected( $this->option['permission_form_view'], 'edit_others_posts', false ) . ">" . esc_html__( 'Editor', 'flagged-content-pro' )        . "</option>";
        echo "<option value='publish_posts' "     . selected( $this->option['permission_form_view'], 'publish_posts', false )     . ">" . esc_html__( 'Author', 'flagged-content-pro' )        . "</option>";
        echo "<option value='edit_posts' "        . selected( $this->option['permission_form_view'], 'edit_posts', false )        . ">" . esc_html__( 'Contributor', 'flagged-content-pro' )   . "</option>";
        echo "<option value='read' "              . selected( $this->option['permission_form_view'], 'read', false )              . ">" . esc_html__( 'Subscriber', 'flagged-content-pro' )    . "</option>";
        echo "</select>";
        echo "<p class='description'>" . esc_html__( 'Minimum role needed to view the list of forms.', 'flagged-content-pro' ) . "</p>";
    }

    public function field_permission_form_edit()
    {
        echo "<select name='{$this->option_name}[permission_form_edit]'>";
        echo "<option value='manage_options' "    . selected( $this->option['permission_form_edit'], 'manage_options', false )    . ">" . esc_html__( 'Administrator', 'flagged-content-pro' ) . "</option>";
        echo "<option value='edit_others_posts' " . selected( $this->option['permission_form_edit'], 'edit_others_posts', false ) . ">" . esc_html__( 'Editor', 'flagged-content-pro' )        . "</option>";
        echo "<option value='publish_posts' "     . selected( $this->option['permission_form_edit'], 'publish_posts', false )     . ">" . esc_html__( 'Author', 'flagged-content-pro' )        . "</option>";
        echo "<option value='edit_posts' "        . selected( $this->option['permission_form_edit'], 'edit_posts', false )        . ">" . esc_html__( 'Contributor', 'flagged-content-pro' )   . "</option>";
        echo "<option value='read' "              . selected( $this->option['permission_form_edit'], 'read', false )              . ">" . esc_html__( 'Subscriber', 'flagged-content-pro' )    . "</option>";
        echo "</select>";
        echo "<p class='description'>" . esc_html__( 'Minimum role needed to edit forms.', 'flagged-content-pro' ) . "</p>";
    }

    public function field_honeypot()
    {
        echo "<input type='checkbox' id='{$this->option_name}[honeypot]' name='{$this->option_name}[honeypot]' value='1' " . checked( $this->option['honeypot'], 1, false ) . " />";
        echo "<label for='{$this->option_name}[honeypot]'>" . esc_html__( 'Enable', 'flagged-content-pro' ) . "</label>";
    }

    public function field_time_review()
    {
        echo "<input type='checkbox' id='{$this->option_name}[time_review]' name='{$this->option_name}[time_review]' value='1' " . checked( $this->option['time_review'], 1, false ) . " />";
        echo "<label for='{$this->option_name}[time_review]'>" . esc_html__( 'Enable', 'flagged-content-pro' ) . "</label>";
    }
    
    public function field_modal_type()
    {
        echo "<select name='{$this->option_name}[modal_type]'>";
        echo "<option value='magnific-popup' " . selected( $this->option['modal_type'], 'magnific-popup', false ) . ">" . esc_html__( 'Magnific Popup', 'flagged-content-pro' ) . "</option>";
        echo "<option value='featherlight' "   . selected( $this->option['modal_type'], 'featherlight', false )   . ">" . esc_html__( 'Featherlight', 'flagged-content-pro' )   . "</option>";
        echo "</select>";
        echo "<p class='description'>" . esc_html__( 'Select which modal should display the form. This can be changed to avoid conflicts and change the appearance or behavior.', 'flagged-content-pro' ) . "</p>";
    }

    public function field_delete_cleanup()
    {
        echo "<input type='checkbox' id='{$this->option_name}[delete_cleanup]' name='{$this->option_name}[delete_cleanup]' value='1' " . checked( $this->option['delete_cleanup'], 1, false ) . " />";
        echo "<label for='{$this->option_name}[delete_cleanup]'>" . esc_html__( 'Enable - when a post, comment or other content type is permanently deleted then all flags for that content will also be deleted.', 'flagged-content-pro' ) . "</label><br>";
        echo "<p class='description'>" . esc_html__( 'Recommended to be checked to keep the admin flags screen clean and avoid missing content titles. However, this should be unchecked if 
		    there are completed flags you would like to preserve even if the content for those flags has been deleted.', 'flagged-content-pro' ) . "</p>";
    }

    public function field_save_ip_address()
    {
        echo "<input type='checkbox' id='{$this->option_name}[save_ip_address]' name='{$this->option_name}[save_ip_address]' value='1' " . checked( $this->option['save_ip_address'], 1, false ) . " />";
        echo "<label for='{$this->option_name}[save_ip_address]'>" . esc_html__( 'Enable - Save the IP address of the flag submitter.', 'flagged-content-pro' ) . "</label><br>";
        echo "<p class='description'>" . esc_html__( 'If enabled, the IP address of the flag submitter will be stored and made visible in the flags section. If disabled, then IP 
            address information will be unavailable for the user who submitted the flag.', 'flagged-content-pro' ) . "</p>";
    }

    /**
     * Section display functions: Callback functions to display the sections. Each section is under its own tab.
     */
	function permissions_section_display()
    {
		echo '</section>';
		echo '<section id="Permissions">';
		echo '<h2>' . esc_html__( 'Permissions Settings', 'flagged-content-pro' ) . '</h2>';
        echo "<p>" . esc_html__( 'Grant or restrict access to the plugin&#39;s functionality in the admin backend.', 'flagged-content-pro' ) . "</p>";
	}

	function spam_section_display()
    {
		echo '</section>';
		echo '<section id="Spam">';
		echo '<h2>' . esc_html__( 'Spam Settings', 'flagged-content-pro' ) . '</h2>';
		echo '<p>' . esc_html__( 'The settings below enable different types of spam protection to fight bots. Please note that some bots may still get past the defenses and submit the form.', 'flagged-content-pro' ) . '</p>';
	}

	function other_section_display()
    {
		echo '</section>';
		echo '<section id="Other">';
		echo '<h2>' . esc_html__( 'Other Settings', 'flagged-content-pro' ) . '</h2>';
	}

    /**
     * Callback function: sanitizes the values before saving to the options table in the database.
     *
     * Sanitization examples:
     * - If checkbox has not been selected, then store 0 in the db. Otherwise the value
     * is deleted in the db and causes a PHP warning upon showing the page again.
     * - Trim is used to remove extraneous \n and whitespaces from text input and textarea.
     *
     * @param $value
     * @return mixed
     */
    public function sanitize_values( $value )
    {
		$value['permission_flag_view'] = isset( $value['permission_flag_view'] ) ? $value['permission_flag_view'] : 'manage_options';
		$value['permission_flag_edit'] = isset( $value['permission_flag_edit'] ) ? $value['permission_flag_edit'] : 'manage_options';
        $value['permission_form_view'] = isset( $value['permission_form_view'] ) ? $value['permission_form_view'] : 'manage_options';
        $value['permission_form_edit'] = isset( $value['permission_form_edit'] ) ? $value['permission_form_edit'] : 'manage_options';
		$value['honeypot']             = isset( $value['honeypot'] )             ? $value['honeypot']             : 0;
		$value['time_review']          = isset( $value['time_review'] )          ? $value['time_review']          : 0;
        $value['modal_type']           = isset( $value['modal_type'] )           ? $value['modal_type']           : 'magnific-popup';
        $value['delete_cleanup']       = isset( $value['delete_cleanup'] )       ? $value['delete_cleanup']       : 0;
        $value['save_ip_address']      = isset( $value['save_ip_address'] )      ? $value['save_ip_address']      : 0;

		return $value;
	}

	public function display_page()
    {
		echo '<div class="wrap">';

			echo '<h1>' . esc_html( get_admin_page_title() ) . '</h1>';

			settings_errors();

			echo '<div class="flaggedc-admin-settings-main">';

				echo '<div class="flaggedc-admin-tabs">';

					echo '<h2 class="nav-tab-wrapper">';
						echo '<a href="#" class="nav-tab nav-tab-active"><span class="dashicons dashicons-lock"></span> ' . esc_html__( 'Permissions', 'flagged-content-pro' ) . '</a>';
						echo '<a href="#" class="nav-tab"><span class="dashicons dashicons-welcome-comments"></span> ' . esc_html__( 'Spam', 'flagged-content-pro' ) . '</a>';
						echo '<a href="#" class="nav-tab"><span class="dashicons dashicons-admin-tools"></span> ' . esc_html__( 'Other', 'flagged-content-pro' ) . '</a>';
					echo '</h2>';
				echo '</div>';

				echo '<div class="flaggedc-admin-settings-wrapper">';

					echo '<form method="post" action="options.php">';
							
						do_settings_sections( $this->page_slug );
						settings_fields( $this->option_group );

						echo '</section>';

                        $submit_wording = esc_html__( 'Save All Changes', 'flagged-content-pro' );
                        submit_button( $submit_wording );

						if ( FLAGCPRO_DEBUG )
						{
							echo 'Settings array:<pre>';
							print_r ( $this->settings );
							echo '</pre>';
						}

					echo '</form>';
				echo '</div>';
			echo '</div>';

		echo '</div>';
	}
}