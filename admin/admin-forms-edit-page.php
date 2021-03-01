<?php
if ( ! defined( 'WPINC' ) ) { die; }

/**
 * Prepares and renders the settings page (sections, fields and register) when forms are added or edited
 */
class Flagged_Content_Pro_Admin_Forms_Edit_Page
{
	private $plugin;
    public  $settings;
	private $forms;
	private $form_id;
	private $is_new;
    private $page_slug    = 'flagged_content_pro_forms_edit_page';
    private $option;
    private $option_group = 'flaggedc_forms_group';
	private $option_name;
    private $hr           = '</td></tr><tr><th></th><td><hr>';
    private $space        = '<div class="flaggedc-admin-spacer"></div>';

	public function __construct( $plugin )
    {
	    $this->plugin   = $plugin;
        $this->settings = $this->plugin->settings;
		$this->is_new   = ! isset( $_GET['form_id'] ) || $_GET['form_id'] == '0' ? 1 : 0;

        // Submitted and new form
		if ( isset( $_GET['settings-updated'] ) && $this->is_new )
		{
            // New form - generate new, proper ID number and transfer data from flagged_content_pro_form_0 to flagged_content_pro_form_{new ID}
            // Tried to set this up in the sanitization method (fires before updating flagged_content_pro_form_0 option), but it turns
            // out the sanitization method will run twice. WP tries update_option and if the option doesn't exist then
            // tries add_option, running the sanitization method each time.

            $flaggedc_forms  = get_option( 'flagged_content_pro_forms', array() );
            $flaggedc_form_0 = get_option( 'flagged_content_pro_form_0', array() );

            //unset ( $flaggedc_forms[0] );

            if ( empty( $flaggedc_forms ) )
            {
                $next_id = 1;
                $flaggedc_forms[] = $next_id;
            }
            else
            {
                $next_id = end( $flaggedc_forms ) + 1;
                $flaggedc_forms[] = $next_id;
                sort( $flaggedc_forms );
            }

            $flaggedc_form_0['form_id'] = $next_id;

            update_option( 'flagged_content_pro_forms', $flaggedc_forms );
            update_option( 'flagged_content_pro_form_' . $next_id, $flaggedc_form_0 );

            add_action( 'load-admin_page_flagged_content_pro_forms_edit_page', array( $this, 'redirect_to_forms_page' ) );
		}
		else
        {
            if ( isset( $_POST['form_id'] ) ) {
                $this->form_id = (int) $_POST['form_id'];
            }
            elseif ( isset( $_GET['form_id'] ) ) {
                $this->form_id = (int) $_GET['form_id'];
            }
            else {
                $this->form_id = 0;
            }
		}

		$this->plugin = $plugin;
        $this->forms = $this->plugin->get_forms();
        $this->option_name = 'flagged_content_pro_form_' . $this->form_id;

        if ( $this->is_new )
        {
            $this->option = $this->plugin->get_default_form_settings();
        }
        else
        {
            // $this->option = get_option( $this->option_name );
            $this->option = array_merge( $this->plugin->get_default_form_settings(), get_option( $this->option_name, array() ) );
        }

        add_action( 'admin_init', array( $this, 'admin_settings_init' ), 10 );
    }


	public function redirect_to_forms_page()
    {
        wp_redirect( "admin.php?page=flagged_content_pro_forms_page&form_action=add" );
        exit;
    }


    /**
     * Callback function - Prepares the form and fields for the page through the wordpress settings api
     * @action admin_init
     */
	public function admin_settings_init()
    {
        // add_settings_field( $id, $title, $callback, $page, $section, $args);
        add_settings_field( 'form_name',          __( 'Form name', 'flagged-content-pro' ),         array( $this, 'field_form_name' ),           $this->page_slug, 'form_section' );
        add_settings_field( 'form_status',        __( 'Status', 'flagged-content-pro' ),            array( $this, 'field_form_status' ),         $this->page_slug, 'form_section' );
        add_settings_field( 'form_user',          __( 'Visibility', 'flagged-content-pro' ),        array( $this, 'field_form_user' ),           $this->page_slug, 'form_section' );
        add_settings_field( 'content',            __( 'Content', 'flagged-content-pro' ),           array( $this, 'field_content' ),             $this->page_slug, 'form_section' );
        add_settings_field( 'reveal_location',    __( 'Placement', 'flagged-content-pro' ),         array( $this, 'field_reveal_location' ),     $this->page_slug, 'form_section' );
        add_settings_field( 'reveal_priority',    __( 'Auto tweaking', 'flagged-content-pro' ),     array( $this, 'field_reveal_priority' ),     $this->page_slug, 'form_section' );
        add_settings_field( 'form_action',        __( 'Perform action', 'flagged-content-pro' ),    array( $this, 'field_form_action' ),         $this->page_slug, 'form_section' );
        add_settings_field( 'form_action_number', '',                                               array( $this, 'field_form_action_number' ),  $this->page_slug, 'form_section' );

        add_settings_field( 'reveal_icon',          __( 'Icon on button', 'flagged-content-pro' ),          array( $this, 'field_reveal_icon' ),          $this->page_slug, 'reveal_section' );
        add_settings_field( 'reveal_label',         __( 'Text on button', 'flagged-content-pro' ),          array( $this, 'field_reveal_label' ),         $this->page_slug, 'reveal_section' );
        add_settings_field( 'reveal_success_icon',  __( 'Success icon', 'flagged-content-pro' ),            array( $this, 'field_reveal_success_icon' ),  $this->page_slug, 'reveal_section' );
        add_settings_field( 'reveal_success_label', __( 'Success text', 'flagged-content-pro' ),            array( $this, 'field_reveal_success_label' ), $this->page_slug, 'reveal_section' );
        add_settings_field( 'reveal_display',       __( 'Display button or link?', 'flagged-content-pro' ), array( $this, 'field_reveal_display' ),       $this->page_slug, 'reveal_section' );
        add_settings_field( 'reveal_style',         __( 'Flag button style', 'flagged-content-pro' ) ,      array( $this, 'field_reveal_style' ),         $this->page_slug, 'reveal_section' );
        add_settings_field( 'reveal_color_base',    __( 'Choose button colors', 'flagged-content-pro' ),    array( $this, 'field_reveal_color_base' ),    $this->page_slug, 'reveal_section' );
        add_settings_field( 'reveal_color_hover',   '',                                                     array( $this, 'field_reveal_color_hover' ),   $this->page_slug, 'reveal_section' );
        add_settings_field( 'reveal_color_text',    '',                                                     array( $this, 'field_reveal_color_text' ),    $this->page_slug, 'reveal_section' );
        add_settings_field( 'reveal_align',         __( 'Alignment', 'flagged-content-pro' ),               array( $this, 'field_reveal_align' ),         $this->page_slug, 'reveal_section' );

        add_settings_field( 'name',                __( '<em>Name</em> field', 'flagged-content-pro' ),        array( $this, 'field_name' ),                $this->page_slug, 'fields_section' );
        add_settings_field( 'email',               __( '<em>E-mail</em> field', 'flagged-content-pro' ),      array( $this, 'field_email' ),               $this->page_slug, 'fields_section' );
        add_settings_field( 'reason',              __( '<em>Reason</em> field', 'flagged-content-pro' ),      array( $this, 'field_reason' ),              $this->page_slug, 'fields_section' );
        add_settings_field( 'reason_choose',       '',	                                                      array( $this, 'field_reason_choose' ),       $this->page_slug, 'fields_section' );
        add_settings_field( 'reason_display',      '',                                                        array( $this, 'field_reason_display' ),      $this->page_slug, 'fields_section' );
        add_settings_field( 'description',         __( '<em>Description</em> field', 'flagged-content-pro' ), array( $this, 'field_description' ),         $this->page_slug, 'fields_section' );

        add_settings_field( 'submit_label',         __( 'Text on button', 'flagged-content-pro' ),       array( $this, 'field_submit_label' ),         $this->page_slug, 'submit_section' );
        add_settings_field( 'submit_sending_label', __( 'Sending text', 'flagged-content-pro' ),         array( $this, 'field_submit_sending_label' ), $this->page_slug, 'submit_section' );
        add_settings_field( 'submit_style',         __( 'Submit button style', 'flagged-content-pro' ),  array( $this, 'field_submit_style' ),         $this->page_slug, 'submit_section' );
        add_settings_field( 'submit_color_base',    __( 'Choose button colors', 'flagged-content-pro' ), array( $this, 'field_submit_color_base' ),    $this->page_slug, 'submit_section' );
        add_settings_field( 'submit_color_hover',   '',                                                  array( $this, 'field_submit_color_hover' ),   $this->page_slug, 'submit_section' );
        add_settings_field( 'submit_color_text',    '',                                                  array( $this, 'field_submit_color_text' ),    $this->page_slug, 'submit_section' );

        add_settings_field( 'message_instructions',    __( 'Instructions', 'flagged-content-pro' ),       array( $this, 'field_message_instructions' ),    $this->page_slug, 'messages_section' );
        add_settings_field( 'message_success',         __( 'Success', 'flagged-content-pro' ),            array( $this, 'field_message_success' ),         $this->page_slug, 'messages_section' );
        add_settings_field( 'message_fail_required',   __( 'Required field', 'flagged-content-pro' ),     array( $this, 'field_message_fail_required' ),   $this->page_slug, 'messages_section' );
        add_settings_field( 'message_fail_email',      __( 'Invalid email', 'flagged-content-pro' ),      array( $this, 'field_message_fail_email' ),      $this->page_slug, 'messages_section' );
        add_settings_field( 'message_fail_validation', __( 'Validation failure', 'flagged-content-pro' ), array( $this, 'field_message_fail_validation' ), $this->page_slug, 'messages_section' );

        add_settings_field( 'email_enabled',           __( 'Enable email notifications?', 'flagged-content-pro' ), array( $this, 'field_email_enabled' ),           $this->page_slug, 'email_section' );
        add_settings_field( 'email_to_blog_admin',     __( 'Recipients', 'flagged-content-pro' ),                  array( $this, 'field_email_to_blog_admin' ),     $this->page_slug, 'email_section' );
        add_settings_field( 'email_to_admins',         '',                                                         array( $this, 'field_email_to_admins' ),         $this->page_slug, 'email_section' );
        add_settings_field( 'email_to_editors',        '',                                                         array( $this, 'field_email_to_editors' ),        $this->page_slug, 'email_section' );
        add_settings_field( 'email_to_author',         '',                                                         array( $this, 'field_email_to_author' ),         $this->page_slug, 'email_section' );
        add_settings_field( 'email_to_custom_address', __( 'Recipient email address', 'flagged-content-pro' ),     array( $this, 'field_email_to_custom_address' ), $this->page_slug, 'email_section' );
        add_settings_field( 'email_subject',           __( 'Email subject', 'flagged-content-pro' ),               array( $this, 'field_email_subject' ),           $this->page_slug, 'email_section' );
        add_settings_field( 'email_message',           __( 'Email message', 'flagged-content-pro' ),               array( $this, 'field_email_message' ),           $this->page_slug, 'email_section' );
        add_settings_field( 'email_limit',             __( 'Limit email notifications', 'flagged-content-pro' ),   array( $this, 'field_email_limit' ),             $this->page_slug, 'email_section' );
        add_settings_field( 'email_limit_number',      '',                                                         array( $this, 'field_email_limit_number' ),      $this->page_slug, 'email_section' );

		// add_settings_section( $id, $title, $callback, $page );
		add_settings_section( 'form_section',     '', array( $this, 'form_section_display' ),    $this->page_slug );
        add_settings_section( 'reveal_section',   '', array( $this, 'reveal_section_display' ),  $this->page_slug );
		add_settings_section( 'fields_section',   '', array( $this, 'fields_section_display' ),  $this->page_slug );
		add_settings_section( 'submit_section',   '', array( $this, 'submit_section_display'),   $this->page_slug );
        add_settings_section( 'messages_section', '', array( $this, 'messages_section_display'), $this->page_slug );
		add_settings_section( 'email_section',    '', array( $this, 'email_section_display' ),   $this->page_slug );

		//register_setting( $option_group, $option_name, $callback );
		register_setting( $this->option_group, $this->option_name, array( $this, 'sanitize_values' ) );

		add_filter( 'option_page_capability_' . $this->option_group, array( $this, 'change_edit_forms_permissions' ) );
	}

    /**
     * Options.php requires manage_options. This function changes the permission to the plugin's global setting for form edits.
     */
    public function change_edit_forms_permissions( $current = 'manage_options' )
    {
        return $this->settings['permission_form_edit'];
    }

	public function field_form_name()
    {
        echo "<input type='text' name='{$this->option_name}[form_name]' value='{$this->option['form_name']}' />";
        echo "<p class='description'>" . esc_html__( 'Name of this form - For internal use only, users will not see the form name.', 'flagged-content-pro' ) . "</p>";
    }

    public function field_form_status()
    {
        echo "<input type='radio' id='flaggedc_radio_form_status_active' name='{$this->option_name}[form_status]' value='active' " . checked( $this->option['form_status'], 'active', false ) . " />";
        echo "<label for='flaggedc_radio_form_status_active'>" . esc_html__( 'Active', 'flagged-content-pro' ) . "</label><br>";

        echo "<input type='radio' id='flaggedc_radio_form_status_inactive' name='{$this->option_name}[form_status]' value='inactive' " . checked( $this->option['form_status'], 'inactive', false ) . " />";
        echo "<label for='flaggedc_radio_form_status_inactive'>" . esc_html__( 'Inactive', 'flagged-content-pro' ) . "</label>";

        echo "<p class='description'>" . esc_html__( 'Active forms are displayed to users whereas inactive forms cannot be seen or interacted with.', 'flagged-content-pro' ) . "</p>";
	}

    public function field_form_user()
    {
        echo "<select name='{$this->option_name}[form_user]'>";
        echo "<option value='everyone' "  . selected( $this->option['form_user'], 'everyone', false )  . ">" . esc_html__( 'Everyone', 'flagged-content-pro' ) . "</option>";
        echo "<option value='logged_in' " . selected( $this->option['form_user'], 'logged_in', false )  . ">" . esc_html__( 'Only logged in users', 'flagged-content-pro' ) . "</option>";
        echo '</select>';
        echo "<p class='description'>" . esc_html__( 'Select who will be shown and able to submit the form', 'flagged-content-pro' ) . "</p>";
    }

    public function field_content()
    {
        $selectables = $this->get_selectable_post_types( TRUE );

        if ( empty( $selectables ) ) {
            echo "<p>" . esc_html__( 'All content has already been selected with other forms', 'flagged-content-pro' ) . "</p>";
        }
        else {
            echo "<p>" . esc_html__( 'Select the content users can flag using this form', 'flagged-content-pro' ) . "</p>";
        }

        echo '<div class="flaggedc-admin-content-left">';
        $right_side = false;

        foreach ( $selectables as $selectable )
        {
            if ( ! $selectable['wp_builtin'] && ! $right_side )
            {
                echo '</div>';
                echo '<div class="flaggedc-admin-content-right">';
                $right_side = true;
            }

            echo "<p><input type='checkbox' id='flaggedc_multi_content_{$selectable['name']}' name='{$this->option_name}[content][]' value='{$selectable['value']}' ";

            if ( isset( $this->option['content'] ) && ! empty( $this->option['content'] ) )
            {
                $all_content = $this->plugin->unpack_content( $this->option['content'] );

                foreach ( $all_content as $content ) {
                    echo checked( $content['name'] . ';' . $content['type'], $selectable['name'] . ';' . $selectable['type'], false );
                }
            }
            echo " />";
            echo "<label for='flaggedc_multi_content_{$selectable['name']}'>{$selectable['label']}</p>";
        }

        echo '</div>';
        echo '<div style="clear:both;"></div>';

        $not_selectables = $this->get_selectable_post_types( FALSE );

        if ( ! empty( $not_selectables ) )
        {
            echo '<p class="flaggedc-admin-content-info">' . esc_html__( 'Content in use by other forms', 'flagged-content-pro' ) . '</p>';

            foreach ( $not_selectables as $not_selectable ) {
                echo "<p>{$not_selectable['label']}</p>";
            }
        }
    }


    public function field_reveal_location()
    {
        echo "<select name='{$this->option_name}[reveal_location]'>";
            echo "<option value='content_before' " . selected( $this->option['reveal_location'], 'content_before', false ) . ">" . esc_html__( 'Automatic - Before the content', 'flagged-content-pro' ) . "</option>";
            echo "<option value='content_after' "  . selected( $this->option['reveal_location'], 'content_after', false )  . ">" . esc_html__( 'Automatic - After the content', 'flagged-content-pro' )  . "</option>";
            echo "<option value='shortcode' "      . selected( $this->option['reveal_location'], 'shortcode', false )      . ">" . esc_html__( 'Manual shortcode', 'flagged-content-pro' )          . "</option>";
        echo "</select>";
        echo "<p class='description'>" . esc_html__( 'Choose if the form should be automatically or manually placed.', 'flagged-content-pro' ) . "</p>";

        echo '<div class="reveal-location-auto">';
            echo "<p class='description'>" . esc_html__( 'Automatic placement requires the theme to use certain functions.', 'flagged-content-pro' ) . "</p>";
        echo '</div>';

        echo '<div class="reveal-location-shortcode">';

            echo "<p class='description'>" . esc_html__( 'Only one item content may be selected when using a manual shortcode. The content selection above has been changed to reflect this. Also, shortcodes will not display on index / archive pages.', 'flagged-content-pro' ) . "</p><br>";

            if ( $this->is_new )
            {
                echo '<p>' . esc_html__( 'Shortcode will be available after the new form has been saved.', 'flagged-content-pro' ) . '</p>';
            }
            else
            {
                echo '<p>' . esc_html__( 'Shortcode:', 'flagged-content-pro' ) . '</p>';
                echo '<code>[flagged-content-pro id="' . $this->form_id . '"]</code>';
            }
        echo '</div>';
    }

    public function field_reveal_priority()
    {
        echo "<input type='text' name='{$this->option_name}[reveal_priority]' value='{$this->option['reveal_priority']}' class='flaggedc-admin-text-short' />";
        echo "<p class='description'>" . esc_html__( 'If needed, please enter a number (1-1000). This number sets the priority and should be used to fine-tune the auto placement of the button when there are plugins competing for space.', 'flagged-content-pro' ) . "</p>";
    }


    public function field_form_action()
    {
        echo "<input type='checkbox' id='{$this->option_name}[form_action]' name='{$this->option_name}[form_action]' value='1' " . checked( $this->option['form_action'], 1, false ) . " />";
        echo "<label for='{$this->option_name}[form_action]'>" . esc_html__( 'Perform an action on the content after it has been flagged', 'flagged-content-pro' ) . "</label>";
    }

    public function field_form_action_number()
    {
        echo "<p>". __( 'Posts, pages, custom post types, and bbPress: Change status to pending review', 'flagged-content-pro') . "</p>";
        echo "<p>". __( 'Comments: Unapprove comment', 'flagged-content-pro') . "</p><br>";
        echo "<p>". __( 'Perform the action after the content has been flagged X or more times.', 'flagged-content-pro') . "</p>";
        echo "<input type='text' name='{$this->option_name}[form_action_number]' value='{$this->option['form_action_number']}' class='flaggedc-admin-text-short' />";
        echo "<p class='description'>" . __(
                'For example, setting this number to 3 means the content you have chosen for this form (post, comment, etc). will have its status 
            changed after 3 or more pending flags have been submitted for it.<br><br>            
            Please note: This relies on standard statuses. Custom statuses may or may not work. Deleting or changing pending flags&#39; status 
            affects the count.', 'flagged-content-pro' ) . "</p>";
    }

    public function field_reveal_icon()
    {
        echo "<select name='{$this->option_name}[reveal_icon]' class='flaggedc-select-icon'>";
            echo "<option value='no_icon' "               . selected( $this->option['reveal_icon'], 'no_icon', false )               . "> </option>";
            echo "<option value='dashicons-flag' "        . selected( $this->option['reveal_icon'], 'dashicons-flag', false )        . ">&#xf227;</option>";
            echo "<option value='dashicons-megaphone' "   . selected( $this->option['reveal_icon'], 'dashicons-megaphone', false )   . ">&#xf488;</option>";
            echo "<option value='dashicons-thumbs-up' "   . selected( $this->option['reveal_icon'], 'dashicons-thumbs-up', false )   . ">&#xf529;</option>";
            echo "<option value='dashicons-thumbs-down' " . selected( $this->option['reveal_icon'], 'dashicons-thumbs-down', false ) . ">&#xf542;</option>";
            echo "<option value='dashicons-plus-alt' "    . selected( $this->option['reveal_icon'], 'dashicons-plus-alt', false )    . ">&#xf502;</option>";
            echo "<option value='dashicons-warning' "     . selected( $this->option['reveal_icon'], 'dashicons-warning', false )     . ">&#xf534;</option>";
            echo "<option value='dashicons-dismiss' "     . selected( $this->option['reveal_icon'], 'dashicons-dismiss', false )     . ">&#xf153;</option>";
            echo "<option value='dashicons-no' "          . selected( $this->option['reveal_icon'], 'dashicons-no', false )          . ">&#xf158;</option>";
            echo "<option value='dashicons-no-alt' "      . selected( $this->option['reveal_icon'], 'dashicons-no-alt', false )      . ">&#xf335;</option>";
            echo "<option value='dashicons-plus' "        . selected( $this->option['reveal_icon'], 'dashicons-plus', false )        . ">&#xf132;</option>";
            echo "<option value='dashicons-yes' "         . selected( $this->option['reveal_icon'], 'dashicons-yes', false )         . ">&#xf147;</option>";
        echo "</select>";

        echo "<p class='description'>" . esc_html__( 'Adds an icon to the button, before the text', 'flagged-content-pro' ) . "</p>";
    }

    public function field_reveal_label()
    {
        echo "<input type='text' name='{$this->option_name}[reveal_label]' value='{$this->option['reveal_label']}' />";
    }

    public function field_reveal_success_icon()
    {
        echo "<select name='{$this->option_name}[reveal_success_icon]' class='flaggedc-select-icon'>";
            echo "<option value='no_icon' "               . selected( $this->option['reveal_success_icon'], 'no_icon', false )               . "> </option>";
            echo "<option value='dashicons-flag' "        . selected( $this->option['reveal_success_icon'], 'dashicons-flag', false )        . ">&#xf227;</option>";
            echo "<option value='dashicons-megaphone' "   . selected( $this->option['reveal_success_icon'], 'dashicons-megaphone', false )   . ">&#xf488;</option>";
            echo "<option value='dashicons-thumbs-up' "   . selected( $this->option['reveal_success_icon'], 'dashicons-thumbs-up', false )   . ">&#xf529;</option>";
            echo "<option value='dashicons-thumbs-down' " . selected( $this->option['reveal_success_icon'], 'dashicons-thumbs-down', false ) . ">&#xf542;</option>";
            echo "<option value='dashicons-plus-alt' "    . selected( $this->option['reveal_success_icon'], 'dashicons-plus-alt', false )   . ">&#xf502;</option>";
            echo "<option value='dashicons-warning' "     . selected( $this->option['reveal_success_icon'], 'dashicons-warning', false )     . ">&#xf534;</option>";
            echo "<option value='dashicons-dismiss' "     . selected( $this->option['reveal_success_icon'], 'dashicons-dismiss', false )     . ">&#xf153;</option>";
            echo "<option value='dashicons-no' "          . selected( $this->option['reveal_success_icon'], 'dashicons-no', false )          . ">&#xf158;</option>";
            echo "<option value='dashicons-no-alt' "      . selected( $this->option['reveal_success_icon'], 'dashicons-no-alt', false )      . ">&#xf335;</option>";
            echo "<option value='dashicons-plus' "        . selected( $this->option['reveal_success_icon'], 'dashicons-plus', false )        . ">&#xf132;</option>";
            echo "<option value='dashicons-yes' "         . selected( $this->option['reveal_success_icon'], 'dashicons-yes', false )         . ">&#xf147;</option>";
        echo "</select>";

        echo "<p class='description'>" . esc_html__( 'Icon shown, before the text, after a successful submission', 'flagged-content-pro' ) . "</p>";
    }

    public function field_reveal_success_label()
    {
        echo "<input type='text' name='{$this->option_name}[reveal_success_label]' value='{$this->option['reveal_success_label']}' />";
        echo "<p class='description'>" . esc_html__( 'Text shown on the button after the form has been successfully submitted', 'flagged-content-pro' ) . "</p>";
        echo $this->add_sub_heading( 'Style' );
    }


    public function field_reveal_display()
    {
        echo "<input type='radio' id='flaggedc_radio_reveal_display_button' name='{$this->option_name}[reveal_display]' value='button' " . checked( $this->option['reveal_display'], 'button', false ) . " />";
        echo "<label for='flaggedc_radio_reveal_display_button'>" . esc_html__( 'Button', 'flagged-content-pro' ) . "</label><br>";

        echo "<input type='radio' id='flaggedc_radio_reveal_display_link' name='{$this->option_name}[reveal_display]' value='link' " . checked( $this->option['reveal_display'], 'link', false ) . " />";
        echo "<label for='flaggedc_radio_reveal_display_link'>" . esc_html__( 'Link', 'flagged-content-pro' ) . "</label>";
    }

    public function field_reveal_style()
    {
        echo "<select name='{$this->option_name}[reveal_style]'>";
            echo "<option value='theme;theme' "  . selected( $this->option['reveal_style'], 'theme;theme', false )  . ">" . esc_html__( 'Default theme style and color', 'flagged-content-pro' ) . "</option>";
            echo "<option value='theme;black' "  . selected( $this->option['reveal_style'], 'theme;black', false )  . ">" . esc_html__( 'Default theme style - Black', 'flagged-content-pro' ) . "</option>";
            echo "<option value='theme;gray' "   . selected( $this->option['reveal_style'], 'theme;gray', false )   . ">" . esc_html__( 'Default theme style - Gray', 'flagged-content-pro' ) . "</option>";
            echo "<option value='theme;green' "  . selected( $this->option['reveal_style'], 'theme;green', false )  . ">" . esc_html__( 'Default theme style - Green', 'flagged-content-pro' ) . "</option>";
            echo "<option value='theme;red' "    . selected( $this->option['reveal_style'], 'theme;red', false )    . ">" . esc_html__( 'Default theme style - Red', 'flagged-content-pro' ) . "</option>";
            echo "<option value='theme;custom' " . selected( $this->option['reveal_style'], 'theme;custom', false ) . ">" . esc_html__( 'Default theme style - Choose colors', 'flagged-content-pro' ) . "</option>";

            echo "<option value='flat;theme' "  . selected( $this->option['reveal_style'], 'flat;theme', false )  . ">" . esc_html__( 'Flat style - Theme color', 'flagged-content-pro' ) . "</option>";
            echo "<option value='flat;black' "  . selected( $this->option['reveal_style'], 'flat;black', false )  . ">" . esc_html__( 'Flat style - Black', 'flagged-content-pro' ) . "</option>";
            echo "<option value='flat;gray' "   . selected( $this->option['reveal_style'], 'flat;gray', false )   . ">" . esc_html__( 'Flat style - Gray', 'flagged-content-pro' ) . "</option>";
            echo "<option value='flat;green' "  . selected( $this->option['reveal_style'], 'flat;green', false )  . ">" . esc_html__( 'Flat style - Green', 'flagged-content-pro' ) . "</option>";
            echo "<option value='flat;red' "    . selected( $this->option['reveal_style'], 'flat;red', false )    . ">" . esc_html__( 'Flat style - Red', 'flagged-content-pro' ) . "</option>";
            echo "<option value='flat;custom' " . selected( $this->option['reveal_style'], 'flat;custom', false ) . ">" . esc_html__( 'Flat style - Choose colors', 'flagged-content-pro' ) . "</option>";

        echo "</select>";
    }

    public function field_reveal_color_base()
    {
        echo "<input type='text' name='{$this->option_name}[reveal_color_base]' value='{$this->option['reveal_color_base']}' data-flaggedc-color-picker='true' />";
        echo "<span class='flaggedc-admin-color-picker-text'>" . esc_html__( 'Button color', 'flagged-content-pro' ) . "</span>";
    }

    public function field_reveal_color_hover()
    {
        echo "<input type='text' name='{$this->option_name}[reveal_color_hover]' value='{$this->option['reveal_color_hover']}' data-flaggedc-color-picker='true' />";
        echo "<span class='flaggedc-admin-color-picker-text'>" . esc_html__( 'Color on hover', 'flagged-content-pro' ) . "</span>";
    }

    public function field_reveal_color_text()
    {
        echo "<input type='text' name='{$this->option_name}[reveal_color_text]' value='{$this->option['reveal_color_text']}' data-flaggedc-color-picker='true' />";
        echo "<span class='flaggedc-admin-color-picker-text'>" . esc_html__( 'Text color', 'flagged-content-pro' ) . "</span>";
        //echo $this->add_sub_heading( 'Location' );
    }

    public function field_reveal_align()
    {
        echo "<select name='{$this->option_name}[reveal_align]'>";
            echo "<option value='left' "   . selected( $this->option['reveal_align'], 'left', false )   . ">" . esc_html__( 'Left', 'flagged-content-pro' ) . "</option>";
            echo "<option value='center' " . selected( $this->option['reveal_align'], 'center', false ) . ">" . esc_html__( 'Center', 'flagged-content-pro' ) . "</option>";
            echo "<option value='right' "  . selected( $this->option['reveal_align'], 'right', false )  . ">" . esc_html__( 'Right', 'flagged-content-pro' ) . "</option>";
        echo "</select>";

        echo "<p class='description'>" . esc_html__( 'How the flag button should be aligned within the container', 'flagged-content-pro' ) . "</p>";
    }


    public function field_name()
    {
        echo "<input type='radio' id='flaggedc_radio_name_optional' name='{$this->option_name}[name]' value='optional' " . checked( $this->option['name'], 'optional', false ) . " />";
        echo "<label for='flaggedc_radio_name_optional'>" . esc_html__( 'Optional - User does not need to enter a name', 'flagged-content-pro' ) . "</label><br>";

        echo "<input type='radio' id='flaggedc_radio_name_required' name='{$this->option_name}[name]' value='required' " . checked( $this->option['name'], 'required', false ) . " />";
        echo "<label for='flaggedc_radio_name_required'>" . esc_html__( 'Required - User must enter a name to submit the form', 'flagged-content-pro' ) . "</label><br>";

        echo "<input type='radio' id='flaggedc_radio_name_no_display' name='{$this->option_name}[name]' value='no_display' " . checked( $this->option['name'], 'no_display', false ) . " />";
        echo "<label for='flaggedc_radio_name_no_display'>" . esc_html__( 'Do not display the name field', 'flagged-content-pro' ) . "</label>";

        echo $this->space;
    }

    public function field_email()
    {
        echo "<input type='radio' id='flaggedc_radio_email_optional' name='{$this->option_name}[email]' value='optional' " . checked( $this->option['email'], 'optional', false ) . " />";
        echo "<label for='flaggedc_radio_email_optional'>" . esc_html__( 'Optional - User does not need to enter an e-mail', 'flagged-content-pro' ) . "</label><br>";

        echo "<input type='radio' id='flaggedc_radio_email_required' name='{$this->option_name}[email]' value='required' " . checked( $this->option['email'], 'required', false ) . " />";
        echo "<label for='flaggedc_radio_email_required'>" . esc_html__( 'Required - User must enter a valid e-mail', 'flagged-content-pro' ) . "</label><br>";

        echo "<input type='radio' id='flaggedc_radio_email_no_display' name='{$this->option_name}[email]' value='no_display' " . checked( $this->option['email'], 'no_display', false ) . " />";
        echo "<label for='flaggedc_radio_email_no_display'>" . esc_html__( 'Do not display the e-mail field', 'flagged-content-pro' ) . "</label>";

        echo $this->space;
    }

    public function field_reason()
    {
        echo "<input type='radio' id='flaggedc_radio_reason_optional' name='{$this->option_name}[reason]' value='optional' " . checked( $this->option['reason'], 'optional', false ) . " />";
        echo "<label for='flaggedc_radio_reason_optional'>" . esc_html__( 'Optional - User does not need to select a reason', 'flagged-content-pro' ) . "</label><br>";

        echo "<input type='radio' id='flaggedc_radio_reason_required' name='{$this->option_name}[reason]' value='required' " . checked( $this->option['reason'], 'required', false ) . " />";
        echo "<label for='flaggedc_radio_reason_required'>" . esc_html__( 'Required - User must select a reason to submit the form', 'flagged-content-pro' ) . "</label><br>";

        echo "<input type='radio' id='flaggedc_radio_reason_no_display' name='{$this->option_name}[reason]' value='no_display' " . checked( $this->option['reason'], 'no_display', false ) . " />";
        echo "<label for='flaggedc_radio_reason_no_display'>" . esc_html__( 'Do not display the reason field', 'flagged-content-pro' ) . "</label>";
    }

    public function field_reason_choose ()
    {
        echo "<p>Add reasons for users to choose from, 1 per line</p>";
        echo "<textarea name='{$this->option_name}[reason_choose]' class='flaggedc-textarea' />{$this->option['reason_choose']}</textarea>";
        echo "<p class='description'>" . esc_html__( 'The reason field will not be shown if this is left empty. There will be no reasons for the user to select from', 'flagged-content-pro' ) . "</p>";
    }

    public function field_reason_display()
    {
        echo "<p>Show reasons through a dropdown or radio buttons?</p>";

        echo "<input type='radio' id='flaggedc_radio_reason_display_dropdown' name='{$this->option_name}[reason_display]' value='dropdown' " . checked( $this->option['reason_display'], 'dropdown', false ) . " />";
        echo "<label for='flaggedc_radio_reason_display_dropdown'>" . esc_html__( 'Dropdown', 'flagged-content-pro' ) . "</label><br>";

        echo "<input type='radio' id='flaggedc_radio_reason_display_radio' name='{$this->option_name}[reason_display]' value='radio' " . checked( $this->option['reason_display'], 'radio', false ) . " />";
        echo "<label for='flaggedc_radio_reason_display_radio'>" . esc_html__( 'Radio buttons', 'flagged-content-pro' ) . "</label>";

        echo $this->space;
    }

    public function field_description()
    {
        echo "<input type='radio' id='flaggedc_radio_description_optional' name='{$this->option_name}[description]' value='optional' " . checked( $this->option['description'], 'optional', false ) . " />";
        echo "<label for='flaggedc_radio_description_optional'>" . esc_html__( 'Optional - User does not need to add a description', 'flagged-content-pro' ) . "</label><br>";

        echo "<input type='radio' id='flaggedc_radio_description_required' name='{$this->option_name}[description]' value='required' " . checked( $this->option['description'], 'required', false ) . " />";
        echo "<label for='flaggedc_radio_description_required'>" . esc_html__( 'Required - User must add a description to submit the form', 'flagged-content-pro' ) . "</label><br>";

        echo "<input type='radio' id='flaggedc_radio_description_no_display' name='{$this->option_name}[description]' value='no_display' " . checked( $this->option['description'], 'no_display', false ) . " />";
        echo "<label for='flaggedc_radio_description_no_display'>" . esc_html__( 'Do not display the description field', 'flagged-content-pro' ) . "</label>";
    }


    public function field_submit_label()
    {
        echo "<input type='text' name='{$this->option_name}[submit_label]' value='{$this->option['submit_label']}' />";
    }


    public function field_submit_sending_label()
    {
        echo "<input type='text' name='{$this->option_name}[submit_sending_label]' value='{$this->option['submit_sending_label']}' />";
        echo "<p class='description'>" . esc_html__( 'Text shown on the button while the form is being submitted', 'flagged-content-pro' ) . "</p>";
    }


    public function field_submit_style()
    {
        echo "<select name='{$this->option_name}[submit_style]'>";
            echo "<option value='theme;theme' "  . selected( $this->option['submit_style'], 'theme;theme', false ) . ">" . esc_html__( 'Default theme style and color', 'flagged-content-pro' ) . "</option>";
            echo "<option value='theme;black' "  . selected( $this->option['submit_style'], 'theme;black', false )  . ">" . esc_html__( 'Default theme style - Black', 'flagged-content-pro' ) . "</option>";
            echo "<option value='theme;gray' "   . selected( $this->option['submit_style'], 'theme;gray', false )   . ">" . esc_html__( 'Default theme style - Gray', 'flagged-content-pro' ) . "</option>";
            echo "<option value='theme;green' "  . selected( $this->option['submit_style'], 'theme;green', false )  . ">" . esc_html__( 'Default theme style - Green', 'flagged-content-pro' ) . "</option>";
            echo "<option value='theme;red' "    . selected( $this->option['submit_style'], 'theme;red', false )    . ">" . esc_html__( 'Default theme style - Red', 'flagged-content-pro' ) . "</option>";
            echo "<option value='theme;custom' " . selected( $this->option['submit_style'], 'theme;custom', false ) . ">" . esc_html__( 'Default theme style - Choose colors', 'flagged-content-pro' ) . "</option>";

            echo "<option value='flat;theme' "  . selected( $this->option['submit_style'], 'flat;theme', false )  . ">" . esc_html__( 'Flat style - Theme color', 'flagged-content-pro' ) . "</option>";
            echo "<option value='flat;black' "  . selected( $this->option['submit_style'], 'flat;black', false )  . ">" . esc_html__( 'Flat style - Black', 'flagged-content-pro' ) . "</option>";
            echo "<option value='flat;gray' "   . selected( $this->option['submit_style'], 'flat;gray', false )   . ">" . esc_html__( 'Flat style - Gray', 'flagged-content-pro' ) . "</option>";
            echo "<option value='flat;green' "  . selected( $this->option['submit_style'], 'flat;green', false )  . ">" . esc_html__( 'Flat style - Green', 'flagged-content-pro' ) . "</option>";
            echo "<option value='flat;red' "    . selected( $this->option['submit_style'], 'flat;red', false )    . ">" . esc_html__( 'Flat style - Red', 'flagged-content-pro' ) . "</option>";
            echo "<option value='flat;custom' " . selected( $this->option['submit_style'], 'flat;custom', false ) . ">" . esc_html__( 'Flat style - Choose colors', 'flagged-content-pro' ) . "</option>";

        echo "</select>";
    }

    public function field_submit_color_base()
    {
        echo "<input type='text' name='{$this->option_name}[submit_color_base]' value='{$this->option['submit_color_base']}' data-flaggedc-color-picker='true' />";
        echo "<span class='flaggedc-admin-color-picker-text'>" . esc_html__( 'Button color', 'flagged-content-pro' ) . "</span>";
    }

    public function field_submit_color_hover()
    {
        echo "<input type='text' name='{$this->option_name}[submit_color_hover]' value='{$this->option['submit_color_hover']}' data-flaggedc-color-picker='true' />";
        echo "<span class='flaggedc-admin-color-picker-text'>" . esc_html__( 'Color on hover', 'flagged-content-pro' ) . "</span>";
    }

    public function field_submit_color_text()
    {
        echo "<input type='text' name='{$this->option_name}[submit_color_text]' value='{$this->option['submit_color_text']}' data-flaggedc-color-picker='true' />";
        echo "<span class='flaggedc-admin-color-picker-text'>" . esc_html__( 'Text color', 'flagged-content-pro' ) . "</span>";
    }


    public function field_message_instructions()
    {
        echo "<input type='text' name='{$this->option_name}[message_instructions]' value='{$this->option['message_instructions']}' />";
        echo "<p class='description'>" . esc_html__( 'Add wording to the top of the form', 'flagged-content-pro' ) . "</p>";
    }

    public function field_message_success()
    {
        echo "<input type='text' name='{$this->option_name}[message_success]' value='{$this->option['message_success']}' />";
        echo "<p class='description'>" . esc_html__( 'Shown after user successfully flags an item', 'flagged-content-pro' ) . "</p>";
    }

    public function field_message_fail_required()
    {
        echo "<input type='text' name='{$this->option_name}[message_fail_required]' value='{$this->option['message_fail_required']}' />";
        echo "<p class='description'>" . esc_html__( 'Displayed when a user fails to complete a required field', 'flagged-content-pro' ) . "</p>";
    }

    public function field_message_fail_email()
    {
        echo "<input type='text' name='{$this->option_name}[message_fail_email]' value='{$this->option['message_fail_email']}' />";
        echo "<p class='description'>" . esc_html__( 'Message when an invalid email is entered', 'flagged-content-pro' ) . "</p>";
    }

    public function field_message_fail_validation()
    {
        echo "<input type='text' name='{$this->option_name}[message_fail_validation]' value='{$this->option['message_fail_validation']}' />";
        echo "<p class='description'>" . esc_html__( 'Displayed if validation fails or the spam check is not passed', 'flagged-content-pro' ) . "</p>";
    }

    public function field_email_enabled()
    {
        echo "<input type='checkbox' id='{$this->option_name}[email_enabled]' name='{$this->option_name}[email_enabled]' value='1' " . checked( $this->option['email_enabled'], 1, false ) . " />";
        echo "<label for='{$this->option_name}[email_enabled]'>" . esc_html__( 'Send email notifications', 'flagged-content-pro' ) . "</label>";
    }

    public function field_email_to_blog_admin()
    {
        echo "<input type='checkbox' id='{$this->option_name}[email_to_blog_admin]' name='{$this->option_name}[email_to_blog_admin]' value='1' " . checked( $this->option['email_to_blog_admin'], 1, false ) . " />";
        echo "<label for='{$this->option_name}[email_to_blog_admin]'>" . esc_html__( 'Blog Administrator', 'flagged-content-pro' ) . " (" . get_option( 'admin_email' ) . ")</label>";
    }

    public function field_email_to_admins()
    {
        echo '<div class="flaggedc-checkbox-short">';
        echo "<input type='checkbox' id='{$this->option_name}[email_to_admins]' name='{$this->option_name}[email_to_admins]' value='1' " . checked( $this->option['email_to_admins'], 1, false ) . " class='flaggedc-checkbox-short' />";
        echo "<label for='{$this->option_name}[email_to_admins]'>" . esc_html__( 'All Administrators', 'flagged-content-pro' ) . "</label>";
        echo '</div>';
    }

    public function field_email_to_editors()
    {
        echo '<div class="flaggedc-checkbox-short">';
        echo "<input type='checkbox' id='{$this->option_name}[email_to_editors]' name='{$this->option_name}[email_to_editors]' value='1' " . checked( $this->option['email_to_editors'], 1, false ) . " class='flaggedc-checkbox-short' />";
        echo "<label for='{$this->option_name}[email_to_editors]'>" . esc_html__( 'All Editors', 'flagged-content-pro' ) . "</label>";
        echo '</div>';
    }

    public function field_email_to_author()
    {
        echo '<div class="flaggedc-checkbox-short">';
        echo "<input type='checkbox' id='{$this->option_name}[email_to_author]' name='{$this->option_name}[email_to_author]' value='1' " . checked( $this->option['email_to_author'], 1, false ) . " class='flaggedc-checkbox-short' />";
        echo "<label for='{$this->option_name}[email_to_author]'>" . esc_html__( 'Author of the flagged content', 'flagged-content-pro' ) . "</label>";
        echo '</div>';
        echo "<br><p class='description'>" . esc_html__( 'Attempt to get and send an email to the author of posts, pages, and some custom post types', 'flagged-content-pro' ) . "</p>";
    }

    public function field_email_to_custom_address()
    {
        echo '<input type="text" name="' . $this->option_name . '[email_to_custom_address]" value="' . $this->option['email_to_custom_address'] . '" class="flaggedc-admin-text-medium" /> ';
        echo "<p class='description'>" . esc_html__( 'Type in a valid email address for a recipient. Emails will be sent to all checked and typed in recipients.', 'flagged-content-pro' ) . "</p>";
    }

    public function field_email_subject()
    {
        echo "<input type='text' name='{$this->option_name}[email_subject]' value='{$this->option['email_subject']}' />";
        echo "<p class='description'>" . __(
            'The following words can be used to include special information in the email subject. Make sure to include the square brackets.<br>
            <strong>[site_name]</strong> will include the name of the website.<br>
            <strong>[content_name]</strong> will include the name of the content that was flagged.', 'flagged-content-pro' ) . "</p>";
    }

    public function field_email_message()
    {
        echo "<textarea name='{$this->option_name}[email_message]' class='flaggedc-textarea' />{$this->option['email_message']}</textarea>";
        echo "<p class='description'>" . __(
            'The following words can be used to include special information in the email message. Make sure to include the square brackets.<br> 
			<strong>[site_name]</strong> will include the name of the website.<br>
			<strong>[content_name]</strong> will include the name of the content that was flagged.<br>
            <strong>[content_desc]</strong> will include a description of the content that was flagged such as a title or excerpt. E.g. post title, comment excerpt, etc.<br>
            <strong>[content_link]</strong> will include a link to the content that was flagged.<br>
            <strong>[post_title]</strong> will include the title of the post the content belongs to (if it can be determined).<br>
            <strong>[flag_reason]</strong> will include the reason the content was flagged (if the user selected one).<br>
            <strong>[flag_description]</strong> will include additional information the user may have left.', 'flagged-content-pro' ) . "</p>";
    }

    public function field_email_limit()
    {
        echo "<input type='checkbox' id='{$this->option_name}[email_limit]' name='{$this->option_name}[email_limit]' value='1' " . checked( $this->option['email_limit'], 1, false ) . " />";
        echo "<label for='{$this->option_name}[email_limit]'>" . esc_html__( 'Limit when email notifications should be sent', 'flagged-content-pro' ) . "</label>";
    }

    public function field_email_limit_number()
    {
        echo "<p>" . __( 'Send email notifications only after a content item has reached X flags.', 'flagged-content-pro' ) . "</p>";
        echo "<input type='text' name='{$this->option_name}[email_limit_number]' value='{$this->option['email_limit_number']}' class='flaggedc-admin-text-short' />";
        echo "<p class='description'>" . __(
            'For example, setting this number to 3 means emails will only be sent after a particular post, page, 
            custom post, (or whatever content this form is set to appear on) has 3 or more flags. Each time a flag is 
            submitted for that item beyond the 3rd flag, an email will be sent. <br><br>            
            Please note: this is based off the total pending flags an item has. Deleting or changing pending flags&#39; status 
            affects the count.', 'flagged-content-pro' ) . "</p>";
    }


	function form_section_display()
    {
		echo '<section id="form_section">';
		echo '<h2>' . esc_html__( 'Form Settings', 'flagged-content-pro' ) . '</h2>';
        echo '<p>' . esc_html__( 'The form is used by visitors to submit flags. The form appears within a modal (aka lightbox, pop-up) after the user clicks the "Flag Button" for a particular piece of content.', 'flagged-content-pro' ) . '</p>';
	}

    function reveal_section_display()
    {
        echo '</section>';
        echo '<section id="reveal_section">';
        echo '<h2>' . esc_html__( 'Flag Button Settings', 'flagged-content-pro' ) . '</h2>';
        echo '<p>' . esc_html__( 'The flag button is clicked to show the form.', 'flagged-content-pro' ) . '</p><br>';
        echo '<h3>' . esc_html__( 'Labels', 'flagged-content-pro' ) . '</h3>';
    }

    function fields_section_display()
    {
		echo '</section>';
		echo '<section id="fields_section">';
		echo '<h2>' . esc_html__( 'Fields Settings', 'flagged-content-pro' ) . '</h2>';
        echo '<p>' . esc_html__( 'The fields appearing within the form.', 'flagged-content-pro' ) . '</p>';
	}

	function submit_section_display()
    {
		echo '</section>';
		echo '<section id="submit_section">';
		echo '<h2>' . esc_html__( 'Submit Button Settings', 'flagged-content-pro' ) . '</h2>';
        echo '<p>' . esc_html__( 'The submit button is in the form and is clicked to submit the flag.', 'flagged-content-pro' ) . '</p>';
    }

	function messages_section_display()
    {
        echo '</section>';
        echo '<section id="messages_section">';
        echo '<h2>' . esc_html__( 'Messages Settings', 'flagged-content-pro' ) . '</h2>';
        echo '<p>' . esc_html__( 'Various messages shown within the form to users.', 'flagged-content-pro' ) . '</p>';
    }

	function email_section_display()
    {
		echo '</section>';
		echo '<section id="email_section">';
		echo '<h2>' . esc_html__( 'Email Settings', 'flagged-content-pro' ) . '</h2>';
        echo '<p>' . esc_html__( 'Email notifications can be sent to site users when a flag is submitted.', 'flagged-content-pro' ) . '</p>';
	}

	/**
	 * Returns an array of content which can be selected for this form ( $selectable = true ), or which
	 * can not be selected ( $selectable = false). If no post types are found then an empty array is
	 * returned. If no forms exist then an empty array will be returned as well.
	 *
	 * @param bool $selectable
	 * @return array
	 */
	private function get_selectable_post_types( $selectable = true )
    {
		$forms = $this->forms;

		if ( ! isset( $forms ) || empty( $forms ) ) {
			return array();
		}

		$selected_types = array();
		$selectable_types = array();
		$not_selectable_types = array();

		// $selected_types contains content already selected to be used in flagging forms
		foreach ( $forms as $form ) {
			if ( $form['form_id'] != $this->form_id ) {
				$selected_types = array_merge( $selected_types, $form['content'] );
			}
		}

		$all_content = $this->plugin->get_all_content();

		foreach ( $all_content as $content )
		{
			$found = false;

			// If the content has already been selected by another form then add
			// the content information to the return array
			foreach ( $selected_types as $selected_type )
			{
				if ( $selected_type['name'] == $content['name'] && $selected_type['type'] == $content['type'] )
				{
					$not_selectable_types[] = $content;
					$found = true;
					break;
				}
			}

			// Content was not already selected, make it selectable
			if ( ! $found ) {
				$selectable_types[] = $content;
			}
		}

		if ( $selectable ) {
			return $selectable_types;
		}
		else {
			return $not_selectable_types;
		}
	}


	private function add_sub_heading( $heading )
    {
        return sprintf( '</td></tr><tr><th><h3>%s</h3></th><td>', $heading );
    }

    /**
     * Callback function: sanitizes the values before saving to the options table in the database.
     *
     * Sanitization examples:
     * - If checkbox has not been selected, then store 0 in the db. Otherwise the value
     * is deleted in the db and causes a PHP warning upon showing the page again.
     * - Trim is used to remove extraneous \n and whitespaces from text input and textarea
     *
     * @param $value
     * @return mixed
     */
	function sanitize_values( $value )
    {
		$value['form_id']             = $this->form_id;
		$value['form_name']           = isset( $value['form_name'] ) && strlen( trim( $value['form_name'] ) ) != 0 ? trim( $value['form_name'] ) : 'Unnamed';
        $value['form_status']         = isset( $value['form_status'] ) ? $value['form_status'] : 'active';
        $value['form_user']           = isset( $value['form_user'] ) ? $value['form_user'] : 'everyone';
        $value['content']             = isset( $value['content'] ) && ! empty( $value['content'] ) ? $value['content'] : array();
        $value['reveal_location']     = isset( $value['reveal_location'] ) ? $value['reveal_location'] : 'content_before';
        $value['form_action']         = isset( $value['form_action'] )         ? $value['form_action'] : 0;

        if ( ! isset( $value['reveal_priority'] ) || absint( $value['reveal_priority'] ) < 1 || absint( $value['reveal_priority'] ) > 1000 ) {
            $value['reveal_priority'] = 10;
        }

        if ( ! isset( $value['form_action_number'] ) || absint( $value['form_action_number'] ) < 1 || absint( $value['form_action_number'] ) > 10000 ) {
            $value['form_action_number'] = 1;
        }

        $value['reveal_icon']          = isset( $value['reveal_icon'] ) ? $value['reveal_icon'] : 'no_icon';
		$value['reveal_label']         = trim( $value['reveal_label'] );
        $value['reveal_success_icon']  = isset( $value['reveal_success_icon'] ) ? $value['reveal_success_icon'] : 'no_icon';
        $value['reveal_success_label'] = trim( $value['reveal_success_label'] );
        $value['reveal_display']       = isset( $value['reveal_display'] ) ? $value['reveal_display'] : 'button';
        $value['reveal_style']         = isset( $value['reveal_style'] ) ? $value['reveal_style'] : 'theme;theme';
        $value['reveal_color_base']    = isset( $value['reveal_color_base'] ) ? $value['reveal_color_base'] : '#1e73be';
        $value['reveal_color_hover']   = isset( $value['reveal_color_hover'] ) ? $value['reveal_color_hover'] : '#185c98';
        $value['reveal_color_text']    = isset( $value['reveal_color_text'] ) ? $value['reveal_color_text'] : '#fdfdfd';
        $value['reveal_align']         = isset( $value['reveal_align'] ) ? $value['reveal_align'] : 'left';

        $value['name']                  = isset( $value['name'] )        ? $value['name']        : 'optional';
        $value['email']                 = isset( $value['email'] )       ? $value['email']       : 'optional';
        $value['reason']                = isset( $value['reason'] )      ? $value['reason']      : 'required';
        $value['reason_choose']         = trim( $value['reason_choose'] );
        $value['reason_choose']         = preg_replace( '/^\s+/m', '', $value['reason_choose'] ); // Removes multiple newlines in a row
        $value['reason_display']        = isset( $value['reason_display'] ) ? $value['reason_display'] : 'dropdown';
        $value['description']           = isset( $value['description'] ) ? $value['description'] : 'optional';

        $value['submit_label']         = trim( $value['submit_label'] );
        $value['submit_sending_label'] = trim( $value['submit_sending_label'] );
        $value['submit_style']         = isset( $value['submit_style'] )       ? $value['submit_style'] : 'theme;theme';
        $value['submit_color_base']    = isset( $value['submit_color_base'] )  ? $value['submit_color_base'] : '#1e73be';
        $value['submit_color_hover']   = isset( $value['submit_color_hover'] ) ? $value['submit_color_hover'] : '#185c98';
        $value['submit_color_text']    = isset( $value['submit_color_text'] )  ? $value['submit_color_text'] : '#fdfdfd';

        $value['message_instructions'] 	  = trim( $value['message_instructions'] );
        $value['message_success'] 		  = trim( $value['message_success'] );
        $value['message_fail_required']   = trim( $value['message_fail_required'] );
        $value['message_fail_email'] 	  = trim( $value['message_fail_email'] );
        $value['message_fail_validation'] = trim( $value['message_fail_validation'] );

		$value['email_enabled']           = isset( $value['email_enabled'] )       ? $value['email_enabled']       : 0;
        $value['email_to_blog_admin']     = isset( $value['email_to_blog_admin'] ) ? $value['email_to_blog_admin'] : 0;
        $value['email_to_admins']         = isset( $value['email_to_admins'] )     ? $value['email_to_admins']     : 0;
        $value['email_to_editors']        = isset( $value['email_to_editors'] )    ? $value['email_to_editors']    : 0;
        $value['email_to_author']         = isset( $value['email_to_author'] )     ? $value['email_to_author']     : 0;
        $value['email_to_custom_address'] = isset( $value['email_to_custom_address'] ) && is_email( trim( $value['email_to_custom_address'] ) ) ? sanitize_email( $value['email_to_custom_address'] ) : '';
		$value['email_subject']           = trim( $value['email_subject'] );
		$value['email_message']           = trim( $value['email_message'] );
		$value['email_limit']             = isset( $value['email_limit'] )         ? $value['email_limit'] : 0;

		if ( ! isset( $value['email_limit_number'] ) || absint( $value['email_limit_number'] ) < 1 || absint( $value['email_limit_number'] ) > 10000 ) {
			$value['email_limit_number'] = 1;
		}

        $value['form_updated_on'] = current_time( 'mysql' );
        $value['form_updated_by'] = get_current_user_id();

        add_settings_error(
            'forms_updated_notice',
            'forms_updated_notice',
            sprintf( __( 'Form updated. Return to the <a href="%s">forms page</a>', 'flagged-content-pro' ), 'admin.php?page=flagged_content_pro_forms_page' ),
            'updated'
        );

		return $value;
	}


	public function display_page()
    {
		echo '<div class="wrap">';

			$add_or_edit = $this->is_new ? __( 'Add', 'flagged-content-pro' ) : __( 'Edit', 'flagged-content-pro' );
			echo "<h1>" . sprintf( esc_html__( 'Flagged Content Pro - %s Form', 'flagged-content-pro' ), $add_or_edit ) . "</h1>";

			settings_errors();

			echo '<div class="flaggedc-admin-settings-main flaggedc-admin-settings-edit-form">';

				echo '<div class="flaggedc-admin-tabs">';
					echo '<h2 class="nav-tab-wrapper">';
						echo '<a href="#" class="nav-tab nav-tab-active"><span class="dashicons dashicons-feedback"></span> ' . __( 'Form', 'flagged-content-pro' )          . '</a>';
                        echo '<a href="#" class="nav-tab"><span class="dashicons dashicons-flag"></span> '                    . __( 'Flag Button', 'flagged-content-pro' )   . '</a>';
                        echo '<a href="#" class="nav-tab"><span class="dashicons dashicons-forms"></span> '                   . __( 'Fields', 'flagged-content-pro' )        . '</a>';
						echo '<a href="#" class="nav-tab"><span class="dashicons dashicons-yes"></span> '                     . __( 'Submit Button', 'flagged-content-pro' ) . '</a>';
                        echo '<a href="#" class="nav-tab"><span class="dashicons dashicons-admin-comments"></span> '          . __( 'Messages', 'flagged-content-pro' )      . '</a>';
						echo '<a href="#" class="nav-tab"><span class="dashicons dashicons-email-alt"></span> '               . __( 'Email', 'flagged-content-pro' )         . '</a>';
					echo '</h2>';
				echo '</div>';

				echo '<div class="flaggedc-admin-settings-wrapper">';

					echo '<form method="post" action="options.php">';

						echo "<input type ='hidden' name='form_id' value='{$this->form_id}'>";

                        /* 'option_group' must match 'option_group' from register_setting call */
                        settings_fields( $this->option_group );
                        do_settings_sections( $this->page_slug );

						echo '</section>';

                        $submit_wording = $this->is_new ? __( 'Add New Form', 'flagged-content-pro' ) : __( 'Save All Changes', 'flagged-content-pro' );
						submit_button( $submit_wording );

						if ( FLAGCPRO_DEBUG )
						{
                            global $new_whitelist_options;
                            global $whitelist_options;

                            echo '$this->option: (current form merged with default settings array)<pre>';
                            print_r ( $this->option );

                            echo '<br>_GET: ';
                            print_r ( $_GET );

                            echo '<br>_POST: ';
                            print_r ( $_POST );

                            echo 'Forms array:<pre>';
                            print_r ( $this->forms );

                            /*echo '<br>_SESSION: ';
                            print_r ( $_SESSION );*/

                            echo '</pre>';
                        }

					echo '</form>';
				echo '</div>';
			echo '</div>';
		echo '</div>';
	}
}