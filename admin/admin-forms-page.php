<?php
if ( ! defined( 'WPINC' ) ) { die; }

// if ( ! class_exists( 'WP_List_Table' ) ) {
// 	require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
// }

/**
 * Upkeep: Flagged_Content_Pro_WP_List_Table
 * If class-wp-list-table.php has to be changed, then:
 * - Change WP_List_Table class name to Flagged_Content_Pro_WP_List_Table
 * - Change all WP_List_Table references within the class to Flagged_Content_Pro_WP_List_Table
 */
class Flagged_Content_Pro_Admin_Forms_Page extends Flagged_Content_Pro_WP_List_Table
{
    private $plugin;
    public  $forms;

    /**
     * Had to add the processing of actions into this constructor to keep the badge number in sync. The actions and setting of object properties 
     * have to be hooked into wp_loaded, otherwise undefined function errors will occur.
     */
    function __construct( $plugin )
    {
        $this->plugin = $plugin;
        $this->forms = $this->plugin->get_forms();
    }

     /**
      * The parent constructor fires too early in the WP cycle and errors out with an unknown function error for convert_to_screen(). Instead, we use init()
      * to run the parent constructor. Init() is called in the display_page() method which is called in the admin_menu hook (admin.php)
      *
      * Need to fire the parent constructor and pass it default config arguments in this method.
      */
    public function init()
    {
        global $status, $page;

        $this->process_bulk_action();

        //Set parent defaults
        parent::__construct(
            array(
                'singular'  => 'form',    // singular name of the listed records
                'plural'    => 'forms',   // plural name of the listed records
                'ajax'      => false      // does this table support ajax?
            )
        );

        if ( isset( $_GET['form_action'] ) && $_GET['form_action'] == 'add' )
        {
            add_settings_error(
                'forms_added_notice',
                'forms_added_notice',
                __( 'New form added.', 'flagged-content-pro' ),
                'updated'
            );
        }
    }


    function prepare_items()
    {
        global $wpdb; 

        /**
         * First, lets decide how many records per page to show
         */
        $per_page = 20;

        $columns  = $this->get_columns();
        $hidden   = array();
        $sortable = $this->get_sortable_columns();

        $this->_column_headers = array( $columns, $hidden, $sortable );

        $current_page = $this->get_pagenum();

        if ( empty( $this->forms ) ) {
            $data = array();
        }
        else {
            $data = $this->forms;
        }

        $total_items = count( $data );

        $data = array_slice( $data, ( ( $current_page - 1 ) * $per_page ), $per_page );

        $this->items = $data;

        $this->set_pagination_args(
            array(
                'total_items' => $total_items,
                'per_page'    => $per_page,
                'total_pages' => ceil( $total_items / $per_page )
            )
        );
    }

    /**
     * Dictates the table's columns and titles.
     */
    function get_columns()
    {
        $columns = array(
            'cb'            => '<input type="checkbox" />',
            'form_status'   => '', // Status
            'form_name'     => __( 'Form', 'flagged-content-pro' ),
            'form_fields'   => __( 'Fields', 'flagged-content-pro' ),
            'content'       => __( 'Appears On', 'flagged-content-pro' ),
            'form_user'     => __( 'Appears For', 'flagged-content-pro' ),
            'date_updated'  => __( 'Last Updated', 'flagged-content-pro' )
        );
        
        return $columns;
    }


    function column_default( $item, $column_name )
    {
        switch( $column_name )
        {
             default:
                return print_r( $item, true ); //Show the whole array for troubleshooting purposes
        }
    }


    /**
     * Required if displaying checkboxes or using bulk actions! The 'cb' column
     * is given special treatment when columns are processed. It always needs to
     * have it's own method.
     * 
     * @see WP_List_Table::::single_row_columns()
     * @param array $item A singular item
     * @return string Text to be placed inside the column <td>
     */
    function column_cb( $item )
    {
        return sprintf(
            '<input type="checkbox" name="%1$s[]" value="%2$s" />',
            /*$1%s*/ $this->_args['singular'],      // Repurpose the table's singular label
            /*$2%s*/ $item['form_id']               // The value of the checkbox should be the record's id
        );
    }


    function column_form_name ( $item )
    {
        // Check if user has 'edit' permission to view edit form links
        if ( $this->plugin->check_permissions( 'form', 'edit' ) )
        {
            return sprintf(
                '<a href="admin.php?page=flagged_content_pro_forms_edit_page&form_id=%1$s&form_action=edit" title="%2$s">%3$s</a>',
                /* &form_id= */ $item['form_id'],                                      /* %1$s */
                /* title=    */ esc_attr__( 'Edit this form', 'flagged-content-pro' ), /* %2$s */
                /* <a></a>   */ esc_html( $item['form_name'] )                         /* %3$s */
            );
        }
        else {
            return esc_html( $item['form_name'] );
        }
    }


    function column_form_status ( $item )
    {
        $class = 'dashicons ';
        $title = '';

        if ( $item['form_status'] == 'active' )
        {
            $class .= 'dashicons-yes flaggedc-icon-form-status-active';
            $title = esc_attr__( 'This form is active', 'flagged-content-pro' );
        }
        elseif ( $item['form_status'] == 'inactive' )
        {
            $class .= 'dashicons-minus flaggedc-sub-text-color';
            $title = esc_attr__( 'This form is inactive. It will not be shown to users.', 'flagged-content-pro' );
        }

        return "<span class='{$class}' title='{$title}'></span>";
    }


    function column_form_fields ( $item )
    {
        return $this->form_list_fields( $item['form_id'], false );
    }


    function column_content( $item )
    {
        if ( ! isset( $item['content'] ) || empty( $item['content'] ) ) {
            return '';
        }

        $output = '';
        $shortcode_icon = '';

        foreach( $item['content'] as $content )
        {
            $content_info = $this->plugin->get_content_info( $content['name'], $content['type'] );

            if ( $content_info['exists'] ) {
                $output .= "{$content_info['label']}<br>";
            }
            else {
                $output .= "<s>{$content_info['label']}</s><br>";
            }
        }

        if ( $item['reveal_location'] == 'shortcode' ) {
            $shortcode_icon = '<code><span class="dashicons dashicons-editor-code" title="This form has been set to appear through shortcodes"></span> Shortcode </code>';
        }

        return $output . $shortcode_icon;
    }

    function column_form_user ( $item )
    {
        if ( $item['form_user'] == 'everyone' ) {
            return __( 'Everyone', 'flagged-content-pro' );
        }
        elseif ( $item['form_user'] == 'logged_in' ) {
            return __( 'Only logged in users', 'flagged-content-pro' );
        }
        else {
            return $item['form_user'];
        }
    }


    function column_date_updated( $item )
    {
        $user_login = '';
        $wp_formatted_datetime = '';

        if ( isset( $item['form_updated_by'] ) )
        {
            $user = get_user_by( 'id', $item['form_updated_by'] );
            $user_login = $user ? $user->user_login : '';
        }

        if ( isset( $item['form_updated_on'] ) )
        {
            $datetime = new DateTime( $item['form_updated_on'] );
            $wp_formatted_datetime = $datetime->format( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) );
        }

        return '<div class="flaggedc-sub-text-color">' . $user_login . '<br>' . $wp_formatted_datetime . '</div>';
    }


    function get_bulk_actions()
    {
        
        if ( ! $this->plugin->check_permissions( 'form', 'edit' ) ) {
            return array();
        }

        $actions = array(
            'delete'     => __( 'Delete', 'flagged-content-pro' ),
            'activate'   => __( 'Activate', 'flagged-content-pro' ),
            'deactivate' => __( 'Deactivate', 'flagged-content-pro' )
        );
        
        return $actions;
    }


    function process_bulk_action()
    {
        if ( ! $this->plugin->check_permissions( 'form', 'edit' ) ) {
            return;
        }

        // exit function if no action submitted
        if ( ! isset( $_REQUEST['action'] ) && ! isset( $_REQUEST['action2'] ) ) {
            return;
        }

        // exit function if form_id has not been submitted or is not valid
        if ( ! isset( $_GET['form'] ) || ! is_array( $_GET['form'] ) ) {
            return;
        }

        $form_ids = $_GET['form'];

        // delete bulk action is being triggered
        if ( $this->current_action() === 'delete' )
        {
            if ( ! wp_verify_nonce( $_REQUEST['_wpnonce'], 'bulk-forms' ) ) { die; }

            $flaggedc_forms = get_option( 'flagged_content_pro_forms' );

            foreach ( $form_ids as $form_id )
            {
                delete_option( 'flagged_content_pro_form_' . $form_id );
                // Delete element from array by value
                $flaggedc_forms = array_diff( $flaggedc_forms, array( $form_id ) );
            }

            $num_deleted = count( $form_ids );

            sort( $flaggedc_forms );
            update_option( 'flagged_content_pro_forms', $flaggedc_forms );

            add_settings_error(
                'forms_deleted_notice',
                'forms_deleted_notice',
                _n( 'Form deleted.', 'Forms deleted.', $num_deleted, 'flagged-content-pro' ),
                'updated'
            );
        }

        // Activate bulk action is being triggered
        elseif ( $this->current_action() === 'activate' )
        {
            if ( ! wp_verify_nonce( $_REQUEST['_wpnonce'], 'bulk-forms' ) ) { die; }

            foreach ( $form_ids as $form_id )
            {
                $form = get_option( 'flagged_content_pro_form_' . $form_id );
                $form['form_status'] = 'active';
                update_option( 'flagged_content_pro_form_' . $form_id, $form );
            }

            $num_activated = count( $form_ids );

            add_settings_error(
                'forms_deleted_notice',
                'forms_deleted_notice',
                _n( 'Form activated.', 'Forms activated.', $num_activated, 'flagged-content-pro' ),
                'updated'
            );
        }

        // Deactivate bulk action is being triggered
        elseif ( $this->current_action() === 'deactivate' )
        {
            if ( ! wp_verify_nonce( $_REQUEST['_wpnonce'], 'bulk-forms' ) ) { die; }

            foreach ( $form_ids as $form_id )
            {
                $form = get_option( 'flagged_content_pro_form_' . $form_id );
                $form['form_status'] = 'inactive';
                update_option( 'flagged_content_pro_form_' . $form_id, $form );
            }

            $num_deactivated = count( $form_ids );

            add_settings_error(
                'forms_deleted_notice',
                'forms_deleted_notice',
                _n( 'Form deactivated.', 'Forms deactivated.', $num_deactivated, 'flagged-content-pro' ),
                'updated'
            );
        }

        $this->forms = $this->plugin->load_forms();
    }


    /**
     * Text displayed when no data is available
     */
    public function no_items()
    {
        $no_item_text = '<span class="dashicons dashicons-info"></span> ' . __( 'There are no forms. No content can be flagged. Please click "Add New Form" to begin.', 'flagged-content-pro' );
        echo $no_item_text;       
    }


    public function form_list_fields( $form_id, $show_as_list = true )
    {
        $list_start         = $show_as_list ? '<ul>' : '<div>';
        $list_end           = $show_as_list ? '</ul>' : '</div>';

        $output = $list_start;

        $fields = array( 'name', 'email', 'reason', 'description' );
        $form = $this->plugin->get_form_by_id( $form_id );

        $no_fields_selected = true;

        foreach ( $fields as $field )
        {
            $value = $form[ $field ];

            if ( $value == 'optional' || $value == 'required' )
            {
                $no_fields_selected = false;
                $required = $value == 'required' ? '*' : '';
                $field_name = ucfirst( $field );
                $output .= $show_as_list ? "<li>{$field_name} {$required}</li>" : "{$field_name} {$required}<br>";

                if ( $field == 'reason' && isset( $form['reason_choose'] ) && ! empty( $form['reason_choose'] ) )
                {
                    $output .= $show_as_list ? '<li>' : '';
                    $output .= '<ul class="flaggedc-admin-list flaggedc-sub-text-color">';

                    foreach ( $form['reason_choose'] as $reason_item ) {
                        $output .= '<li><span>' . esc_html( $reason_item ) . '</span></li>';
                    }

                    $output .= $show_as_list ? '</li></ul>' : '</ul>';
                }
            }
        }

        if ( $no_fields_selected ) {
            $output = '<em>No fields selected</em>';
        }

        $output .= $list_end;

        return $output;
    }


    /**
     * Called by add_admin_menus() -> add_menu_page() in admin-pages.php
     */
    public function display_page()
    {
        $this->init();
        $this->prepare_items();

        echo '<div class="wrap">';
            echo '<h1>' . __( 'Flagged Content Pro - Forms', 'flagged-content-pro' ) . '</h1>';

            settings_errors();

            echo '<div class="flaggedc-list-table-forms">';

            // Check if user has 'edit' permission to view edit form links
            if ( $this->plugin->check_permissions( 'form', 'edit' ) ) {
                echo "<a class='add-new-h2' href='admin.php?page=flagged_content_pro_forms_edit_page&form_id=0'>" . __( 'Add New Form', 'flagged-content-pro' ) . "</a>";
            }

                echo '<form method="get" id="flaggedc-form">';

                    // Ensure the form posts back to the current page
                    echo "<input type='hidden' name='page' value='{$_REQUEST['page']}' />";
                    
                    // $this->search_box('Search Items', 'search_id');
                    echo $this->views();
                    
                    // Render the completed list table
                    echo $this->display();

                echo '</form>';
            echo '</div>';

             // Debug
                if ( FLAGCPRO_DEBUG )
                {
                    $post_types = get_post_types();
                    echo '<pre>';

                    echo '<br><br>basename( $_SERVER[\'PHP_SELF\'] ): ';
                    print_r ( basename( $_SERVER['PHP_SELF'] ) );

                    echo '<br><br>$post_types: ';
                    print_r ( $post_types );
                    echo '<br><br>this->items: ';
                    print_r ( $this->items );
                    echo '<br><br>this->forms: ';
                    print_r ( $this->forms );
                    echo '<br><br>_GET: ';
                    print_r ( $_GET );
                    echo '<br><br>_POST: ';
                    print_r ( $_POST );
                    echo '</pre>';
                }
        echo '</div>';
    }
}