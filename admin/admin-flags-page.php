<?php
if ( ! defined( 'WPINC' ) ) { die; }

// if ( ! class_exists( 'WP_List_Table' ) ) {
// 	require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
// }

/*
 * Upkeep: Flagged_Content_Pro_WP_List_Table
 * If class-wp-list-table.php has to be changed, then:
 * - Change WP_List_Table class name to Flagged_Content_Pro_WP_List_Table
 * - Change all WP_List_Table references within the class to Flagged_Content_Pro_WP_List_Table
 */
class Flagged_Content_Pro_Admin_Flags_Page extends Flagged_Content_Pro_WP_List_Table
{
    public $plugin; 
    public $status_array;
    public $status_current;

    public $flagged_content = array();
    public $content_status_counts = array();
    public $ip_counts = array();

    private $sql_where = '';
    private $sql_where_conditions = array();

    private $viewing_ip = false;
    private $viewing_content = false;

    /**
     * Had to add the processing of actions into this constructor to keep the badge number in sync. The actions and setting of object properties 
     * have to be hooked into wp_loaded, otherwise undefined function errors will occur.
     */
    function __construct( $plugin )
    {
        $this->plugin = $plugin;

        $this->status_array = array(
            'all'       => 0,  // default
            'pending'   => 1,
            'completed' => 2
        );

        // Verify that no tampering occurred with the query parameter &=status
        $this->status_current = isset( $_REQUEST['status'] ) ? $_REQUEST['status'] : 'all';

        if ( ! array_key_exists( $this->status_current, $this->status_array ) ) {
            $this->status_current = 'all';
        }

        add_action( 'wp_loaded', array( $this, 'process_actions' ) );
        add_action( 'wp_loaded', array( $this, 'load_flagged_content' ) );
        add_action( 'wp_loaded', array( $this, 'load_ip_counts' ) );
    }


    /**
     * Handles bulk and row actions, then redirects back to same page.
     * - $this->current_action(), found in class-wp-list-table.php, checks $_REQUEST['filter_action'], $_REQUEST['action'], and $_REQUEST['action2'].
     *
     * @action wp_loaded - construct()
     */
    function process_actions()
    {
        if ( ! $this->plugin->check_permissions( 'flag', 'edit' ) ) {
            return;
        }

        // Exit function if no action has been submitted
        if ( ! isset( $_REQUEST['action'] ) && ! isset( $_REQUEST['action2'] ) ) {
            return;
        }

        global $wpdb;

        /**
         * Process bulk actions
         */
        if ( $this->current_action() === 'bulk_delete' )
        {
            if ( ! wp_verify_nonce( $_REQUEST['_wpnonce'], 'bulk-flags' ) ) { die; }

            $id_items = join( ',', $_GET['flag'] );
            $sql = "DELETE FROM " . FLAGCPRO_TABLE . " WHERE id IN ({$id_items})";
            $wpdb->query( $sql );
        }

        elseif ( $this->current_action() === 'bulk_pending' )
        {
            if ( ! wp_verify_nonce( $_REQUEST['_wpnonce'], 'bulk-flags' ) ) { die; }

            $id_items = join( ',', $_GET['flag'] );
            $sql = "UPDATE " . FLAGCPRO_TABLE . " SET status=1 WHERE id IN ({$id_items})";
            $wpdb->query( $sql );
        }

        elseif ( $this->current_action() === 'bulk_completed' )
        {
            if ( ! wp_verify_nonce( $_REQUEST['_wpnonce'], 'bulk-flags' ) ) { die; }

            $id_items = join( ',', $_GET['flag'] );
            $sql = "UPDATE " . FLAGCPRO_TABLE . " SET status=2 WHERE id IN ({$id_items})";
            $wpdb->query( $sql );
        }

        /**
         * Process row actions
         */
        elseif (  $this->current_action() === 'delete_all_pending' )
        {
            if ( ! wp_verify_nonce( $_REQUEST['_wpnonce'], 'flaggedc_row_delete_pending' ) ) { die; }

            $content_id = absint( $_GET['content_id'] );
            $content_type = (string) $_GET['content_type'];

            if ( $content_id > 0 )
            {
                global $wpdb;
                $sql = $wpdb->prepare( "DELETE FROM " . FLAGCPRO_TABLE . " WHERE status = 1 AND content_id = %d AND content_type = %s", $content_id, $content_type );
                $wpdb->query( $sql );
            }
        }

        elseif ( $this->current_action() === 'delete_this_flag' )
        {
            if ( ! wp_verify_nonce( $_REQUEST['_wpnonce'], 'flaggedc_row_delete_flag' ) ) { die; }

            $flag_id = absint( $_GET['flag_id'] );

            if ( $flag_id > 0 )
            {
                global $wpdb;
                $sql = $wpdb->prepare( "DELETE FROM " . FLAGCPRO_TABLE . " WHERE id = %d", $flag_id );
                $wpdb->query( $sql );
            }
        }

        /**
         * Removes all extraneous query arguments from the URL and redirects back to the same page
         */
        $redirect_to = remove_query_arg( array( '_wp_http_referer', '_wpnonce', 'action', 'action2', 'flag', 'flag_id', 'content_id', 'content_type' ), wp_get_referer() );
        $redirect_to = add_query_arg( 'paged', $this->get_pagenum(), $redirect_to );
        $redirect_to = esc_url_raw( $redirect_to );
        wp_safe_redirect( $redirect_to );
        exit();
    }


    /**
     * Queries the database to build an array of arrays containing a count of each content's flag statuses.
     *
     * sets content_status_counts = associate array (
     *          [$content_id$content_type] => array(),
     *          [$content_id$content_type] => array(),
     *      );
     *
     * @action wp_loaded - construct()
     */  
    public function load_flagged_content()
    {
        global $wpdb;

        $sql =
            "SELECT  content_id,
                     SUM(case status when 1 then 1 else 0 end) AS status_1,
                     SUM(case status when 2 then 1 else 0 end) AS status_2,
                     SUM(case status when 1 then 1 when 2 then 1 else 0 end) AS status_all,
                     content_name,
                     content_type
            FROM     " . FLAGCPRO_TABLE . "
            GROUP BY content_id
            ORDER BY content_id";

        $db_resultset = $wpdb->get_results( $sql, ARRAY_A );

        $content = array();
        $status_counts = array();

        foreach ( $db_resultset as $db_row )
        {
            $id   = (int) $db_row['content_id'];
            $name = $db_row['content_name'];
            $type = $db_row['content_type'];

            $args = array(
                'content_id'   => $id,
                'content_name' => $name,
                'content_type' => $type
            );
            $content[ $id . $type ] = new Flagged_Content_Pro_Content( $args );

            $status_counts[ $id . $type ]['status_1'] = $db_row['status_1'];
            $status_counts[ $id . $type ]['status_2'] = $db_row['status_2'];
            $status_counts[ $id . $type ]['status_all'] = $db_row['status_all'];
        }

        $this->flagged_content = $content;
        $this->content_status_counts = $status_counts;
    }


    /**
     * Queries the database to build an array with ip address as the key and the number of flags belonging to that ip address as the value.
     *
     * sets ip_counts = array ( 
     *          [ip_address] => [# of flags by ip address],
     *          [ip_address] => [# of flags by ip address]
     *      );
     *
     * @action wp_loaded - construct()
     */ 
    public function load_ip_counts()
    {
        global $wpdb;

        $sql = "SELECT 
                    ip, 
                    COUNT(*) AS ip_count  
                FROM " . FLAGCPRO_TABLE . "
                GROUP BY ip
                ORDER BY ip";

        $ip_address_count_db = $wpdb->get_results( $sql, ARRAY_A );
        
        $ip_address_count = array();

        foreach ( $ip_address_count_db as $ip_address_count_row ) {
            $ip_address_count[ $ip_address_count_row['ip'] ] = $ip_address_count_row['ip_count'];
        }

        /*echo '<pre>';
        print_r ($ip_address_count);
        echo '</pre>';*/

        $this->ip_counts = $ip_address_count;
    }


    /**
     * This method is called when the parent class can't find a method
     * specifically for a given column.
     * 
     * @param array $item A singular item (one full row's worth of data)
     * @param array $column_name The name/slug of the column to be processed
     * @return string Text or HTML to be placed inside the column <td>
     */
    function column_default( $item, $column_name )
    {
        switch( $column_name )
        {
            //case 'reason':
            //case 'description':
                //return $item[ $column_name ];
            default:
                return print_r( $item, true ); //Show the whole array for troubleshooting purposes
        }
    }


    /**
     * Required if displaying checkboxes or using bulk actions. The 'cb' column
     * is given special treatment when columns are processed. It always needs to
     * have it's own method.
     * 
     * @see WP_List_Table::::single_row_columns()
     * @param array $item A singular item
     * @return string Text to be placed inside the column <td>
     */
    function column_cb($item)
    {
        return sprintf(
            '<input type="checkbox" name="%1$s[]" value="%2$s" />',
            /*$1%s*/ $this->_args['singular'],  //Let's simply repurpose the table's singular label
            /*$2%s*/ $item['id']                //The value of the checkbox should be the record's id
        );
    }


    function column_status( $item )
    {
        $class = 'dashicons dashicons-flag ';
        $title = '';

        if ( $item['status'] == 1 )
        {
            $class .= 'flaggedc-pending-flag-icon';
            $title = esc_attr__( 'This flag is pending review', 'flagged-content-pro' );
        }
        elseif ( $item['status'] == 2 )
        {
            $class .= 'flaggedc-completed-flag-icon';
            $title = esc_attr__( 'This flag has been marked completed', 'flagged-content-pro' );
        }

        return "<span class='{$class}' title='{$title}'></span>";
    }


    function column_reason ( $item )
    {
        return "<p>{$item['reason']}</p><p class='flaggedc-sub-text-color'>{$item['description']}</p>";
    }


    function column_content_id ( $item )
    {
        $content = $item['content'];
        $actions = array();

        // There are actions inside the content array
        if ( isset( $content->actions ) || array_key_exists( 'actions', $content ) )
        {
            foreach ( $content->actions as $action_key => $action_value )
            {
                $actions[ $action_key ] = $action_value;
            }
        }

        // content title + row actions
        return sprintf( '%1$s %2$s', $content->title_render, $this->row_actions( $actions ) );
    }


    function column_flag_actions( $item )
    {
        $content                = $item['content'];
        $actions_flag           = '';
        $multiple_flags         = false;
        $multiple_pending_flags = false;
        $flags_pending_num      = $item['content_counts']['status_1'];
        $flags_completed_num    = $item['content_counts']['status_2'];
        $view_all_class         = '';
        $view_all_alt           = '';
        $actions                = array();

        /** If multiple flags exist for this post then construct the "View all flags" flag icon */

        // multiple pending and completed flags
        if ( $flags_pending_num >= 1 && $flags_completed_num >= 1 )
        {
            $multiple_flags = true;
            $multiple_pending_flags = $flags_pending_num > 1 ? true : false;
            $view_all_class = 'flaggedc-view-all-flags flaggedc-view-all-flags-both';
            $view_all_alt = sprintf( _n( 'There is %d pending flag', 'There are %d pending flags', $flags_pending_num, 'flagged-content-pro' ), $flags_pending_num );
            $view_all_alt .= sprintf(
                _n( ' and %d completed flag for this %s. Click to view them.', ' and %d completed flags for this %s. Click to view them.', $flags_completed_num, 'flagged-content-pro' ),
                $flags_completed_num,
                $content->name
            );
            $view_all_alt .= " ";
        }
        // multiple pending flags
        elseif ( $flags_pending_num > 1 )
        {
            $multiple_flags = true;
            $multiple_pending_flags = true;
            $view_all_class = 'flaggedc-view-all-flags flaggedc-view-all-flags-pending';
            $view_all_alt = sprintf( __( 'There are %d pending flags for this %s. Click to view them.', 'flagged-content-pro' ), $flags_pending_num, $content->name );
        }
        // multiple completed flags
        elseif ( $flags_completed_num > 1 )
        {
            $multiple_flags = true;
            $view_all_class = 'flaggedc-view-all-flags flaggedc-view-all-flags-complete';
            $view_all_alt = sprintf( __( 'There are %d completed flags for this %s. Click to view them.', 'flagged-content-pro' ), $flags_completed_num, $content->name );
        }

        if ( $multiple_flags )
        {
            $actions_flag = sprintf(
                '<a href="?page=%1$s&content_id=%2$s&content_type=%3$s&status=%4$s" class="%5$s" title="%6$s"><span class="dashicons dashicons-flag"></span></a>',
                $_REQUEST['page'],          // href ?page= %1$s
                $content->id,               // href &content_id = %2$s
                $content->type,             // href &content_type = %3$s
                'all',                      // href &status= %4$s
                $view_all_class,            // class = %5$s
                esc_attr( $view_all_alt )   // title= %6$s
               // $content['title']  // <a> %7$s </a>
            );
        }

        // row action - delete this flag
        // check if user has 'edit' permission to view delete this flag link
        if ( $this->plugin->check_permissions( 'flag', 'edit' ) )
        {
            $delete_nonce = isset( $delete_nonce ) ? $delete_nonce : wp_create_nonce( 'flaggedc_row_delete_flag' );

            //$actions['delete_this_flag'] = sprintf
            $actions['delete_flag'] = sprintf(
                '<a href="?page=%1$s&action=%2$s&flag_id=%3$d&_wpnonce=%4$s" class="%5$s">%6$s</a><br>',
                $_REQUEST['page'],                                      // %1$s
                'delete_this_flag',                                     // %2$s
                $item['id'],                                            // %3$d
                $delete_nonce,                                          // %4$s
                'flaggedc-delete-this-flag-link',                       // %5$s
                esc_html__( 'Delete this flag', 'flagged-content-pro' ) // %6$s
            );
        }

        // row action - delete all pending
        if ( $multiple_pending_flags )
        {
            $delete_nonce = wp_create_nonce( 'flaggedc_row_delete_pending' );

            // Check if user has 'edit' permission to view delete all pending link
            if ( $this->plugin->check_permissions( 'flag', 'edit' ) )
            {
                $delete_all_title_attr = sprintf(
                    __( 'Delete all %d pending flags for this %s. Flags with completed status will not be affected.', 'flagged-content-pro' ),
                    $flags_pending_num,
                    $content->name
                );
                $actions['delete_flag'] .= sprintf(
                    '<a href="?page=%1$s&action=%2$s&content_id=%3$d&content_type=%4$s&_wpnonce=%5$s" class="%6$s" data-flaggedc-content-name="%7$s" title="%8$s" >%9$s</a>',
                    $_REQUEST['page'],                                              // %1$s
                    'delete_all_pending',                                           // %2$s
                    $content->id,                                                   // %3$d
                    $content->type,                                                 // %4$d
                    $delete_nonce,                                                  // %5$s
                    'flaggedc-delete-all-pending-link',                             // %6$s
                    $content->name,                                                 // %7$s
                    esc_attr( $delete_all_title_attr ),                             // %8$s
                    esc_html__( 'Delete all pending flags', 'flagged-content-pro' ) // %9$s
                );
            }
        }

        return sprintf('%1$s %2$s', $actions_flag, $this->row_actions( $actions ) );
    }


    function column_user_id( $item )
    {
        $ip_address_array = $this->ip_counts;
        $user_column_output = '';

        // Show username if this was a logged in user
        if ( $item['user_id'] > 0 )
        {
            $user = get_user_by( 'id', $item['user_id'] );
            $user_login = $user ? $user->user_login : '';

            //'<span class="dashicons dashicons-admin-users" title="Flagged by a logged in user"></span> <a href="%1$s">%2$s</a>'
            if ( current_user_can( 'edit_users' ) )
            {
                $user_column_output .= sprintf(
                    '<span class="dashicons dashicons-admin-users flaggedc-list-table-icon" title="%1$s"></span><a href="%2$s" target="_blank">%3$s</a><br />',
                    esc_attr__( 'Flagged by a logged in user', 'flagged-content-pro' ), // %1$s
                    get_edit_user_link( $item['user_id'] ),                             // %2$s
                    $user_login                                                         // %3$s
                );
            }
            else
            {
                $user_column_output .= $user_login . '<br />';
            }
        }

        // Only show the flag submitters name and email if it was submitted by a non-logged in user, or the name or email differs from a logged in user's profile username or email
        if ( $item['user_id'] == 0 ||  $user->user_login != $item['name_entered'] || $user->user_email != $item['email'] )
        {
            // Show the entered user name
            if ( $item['name_entered'] !== '' && $item['name_entered'] !== NULL ) {
                $user_column_output .= $item['name_entered'] . '<br />';
            }

            // Show the email address
            if ( $item['email'] !== '' && $item['email'] !== NULL ) {
                $user_column_output .= $item['email'] . '<br />';
            }
        }

        // Convert and show the ip address
        $ip_readable = $this->ip_address_convert_to_readable( $item['ip'] );

        if ( ! empty( $ip_readable ) )
        {
            // Clicking on the ip address will open a new tab and whois it
            // Another option: https://whois.domaintools.com/
            $user_column_output .= sprintf(
                '<a href="http://www.traceip.net/?query=%1$s" title="%2$s" target="_blank">%1$s</a>',
                $ip_readable,                                                                     // %1$s href, <a> </a>
                esc_attr__( 'View whois information for this IP address', 'flagged-content-pro' ) // %2$s title,
            );

            // Clicking on the ip address icon will search on it, s_type=ip
            // The search icon only appears if there is more than 1 ip address
            if ( $ip_address_array[ $item['ip'] ] > 1 )
            {
                $user_column_output .= sprintf(
                    '<a href="?page=%1$s&ip=%2$s&status=%3$s" title="%4$s"><span class="dashicons dashicons-search flaggedc-list-table-icon flaggedc-ip-icon"></span></a>',
                    $_REQUEST['page'],                                                                 // %1$s href ?page=
                    $ip_readable,                                                                      // %2$s href &ip=
                    'all',                                                                             // %3$s href &status=
                    esc_attr__( 'View all flags submitted by this IP address', 'flagged-content-pro' ) // %4$s title
                );
            }
        }

        return "<div class='flaggedc-sub-text-color'>{$user_column_output}</div>";
    }

    /**
     * Save mySQL datetime into PHP Datetime object and
     * convert to WordPress format specified by user in Settings > General
     */
    function column_date_notified( $item )
    {
        
        $db_datetime = new DateTime( $item['date_notified'] );    

        $wp_formatted_datetime = $db_datetime->format( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ); 

        return "<div class='flaggedc-sub-text-color'>{$wp_formatted_datetime}</div>";
    }


    /**
     * Required. This method dictates the table's columns and titles. This should
     * return an array where the key is the column slug (and class) and the value 
     * is the column's title text. If you need a checkbox for bulk actions, refer
     * to the $columns array below.
     * 
     * The 'cb' column is treated differently than the rest. If including a checkbox
     * column in your table you must create a column_cb() method. If you don't need
     * bulk actions or checkboxes, simply leave the 'cb' entry out of your array.
     * 
     * @see WP_List_Table::::single_row_columns()
     * @return array An associative array containing column information: 'slugs'=>'Visible Titles'
     */
    function get_columns()
    {
        $columns = array(
            'cb'            => '<input type="checkbox" />',
            'status'        => esc_html__( 'Flag', 'flagged-content-pro' ),
            'reason'        => '',
            'content_id'    => esc_html__( 'For', 'flagged-content-pro' ),
            'flag_actions'  => esc_html__( 'Actions', 'flagged-content-pro' ),
            'user_id'       => esc_html__( 'Submitted By', 'flagged-content-pro' ),
            'date_notified' => esc_html__( 'Submitted On', 'flagged-content-pro' ),
        );
        
        return $columns;
    }


    /**
     * Optional. Return an array where the key is the column that needs to be sortable,
     * and the value is db column to sort by.
     *
     * @return array An associative array containing all the columns that should be sortable: 'slugs'=>array('data_values',bool)
     */
    function get_sortable_columns()
    {
        $sortable_columns = array(
            'date_notified' => array( 'date_notified', false ) // date_notified is the default sorting, in descending order. true means it's already sorted
        );

        return $sortable_columns;
    }


    protected function get_views()
    {
        global $wpdb;
        $views = $this->status_array;

        $sql_select = "SELECT 
                    COUNT('id') AS 'all',
                    SUM(case status when 1 then 1 else 0 end) AS 'pending',
                    SUM(case status when 2 then 1 else 0 end) AS 'completed' 
                FROM " . FLAGCPRO_TABLE;

        $conditions = $this->sql_where_conditions;

        // Remove status=* from WHERE clause to get total flag count for all statuses
        unset( $conditions['status'] );

        $sql = $this->create_prepared_sql_statement( $sql_select, $conditions );

        // If no conditions, then do not use the prepare statement. Otherwise, there will be no
        // placeholders in the prepared SQL and wpdb::prepare will trigger a notice.
        if ( empty( $conditions ) ) {
            $flag_count = $wpdb->get_row( $sql, ARRAY_A );
        }
        else {
            $flag_count = $wpdb->get_row( $wpdb->prepare( $sql, $conditions ), 'ARRAY_A' );
        }

        $status_links = array();

        foreach ( $views as $view => $value )
        {
            if ( empty( $flag_count[ $view ] ) ) {
                $status_count = 0;
            }
            else {
                $status_count = $flag_count[ $view ];
            }

            switch ( $view )
            {
                case 'all':       $status_text = esc_html__( ' All ',       'flagged-content-pro' ); break;
                case 'pending':   $status_text = esc_html__( ' Pending ',   'flagged-content-pro' ); break;
                case 'completed': $status_text = esc_html__( ' Completed ', 'flagged-content-pro' ); break;
            }

            //$url = esc_url('?page=' . $_REQUEST['page'] . '&status=' . $view);
            $url = add_query_arg( 'status', $view );
            $url = esc_url( remove_query_arg( 'paged', $url ) );
            $class = ( $view == $this->status_current ) ? ' class="current"' : '';
            $status_text .= "<span class='count'>({$status_count})</span>";

            // Build the link and span count
            // <a href="&status=pending" class="current"> Pending <span class="count">(10)</span></a>
            // WP_List_Table handles the <ul>, <li>'s and |'s
            $status_links[ $view ] = "<a href='{$url}' {$class}>{$status_text}</a>";
           
        }

        return $status_links;
    }


    /**
     * Optional. If you need to include bulk actions in your list table, this is
     * the place to define them. Bulk actions are an associative array in the format
     * 'slug'=>'Visible Title'
     *
     * @return array An associative array containing all the bulk actions: 'slugs'=>'Visible Titles'
     */
    function get_bulk_actions()
    {
        if ( ! $this->plugin->check_permissions( 'flag', 'edit' ) ) {
            return array();
        }

        $actions = array(
            'bulk_delete'    => esc_html__( 'Delete', 'flagged-content-pro' ),
            'bulk_completed' => esc_html__( 'Change status to completed', 'flagged-content-pro' ),
            'bulk_pending'   => esc_html__( 'Change status to pending', 'flagged-content-pro' )
        );
        
        return $actions;
    }

    /**
     * Called by add_admin_menus() -> add_menu_page() in admin-pages.php
     */
    public function display_page()
    {
        $this->init();
        $this->prepare_items();

        echo '<div class="wrap">';
            echo '<h1>' . esc_html__( 'Flagged Content Pro - Flags', 'flagged-content-pro' ) . '</h1>';

            if ( $this->viewing_ip ) {
                echo "<h2>" . sprintf( esc_html__( 'Showing %s flags submitted by IP address: %s', 'flagged-content-pro' ), $this->status_current, $_REQUEST['ip'] ) . "</h2>";
            }
            elseif ( $this->viewing_content )
            {
                $title_link = $this->flagged_content[ absint( $_REQUEST['content_id'] ) . (string) $_REQUEST['content_type'] ]->title_link;
                //$title_link = $this->find_content_in_array( $this->items, array( 'content_id' => absint( $_REQUEST['content_id'] ), 'content_type' => (string) $_REQUEST['content_type'] ) );
                //$title_link = $title_link['content']->title_link;
                echo "<h2>" . sprintf( esc_html__( 'Showing %s flags for: %s', 'flagged-content-pro' ), $this->status_current, $title_link ) . "</h2>";
            }

            echo '<div class="flaggedc-list-table">';

                echo '<form method="get" id="flaggedc-form">';

                    // For plugins, ensure that the form posts back to the current page
                    echo "<input type='hidden' name='page' value='{$_REQUEST['page']}' />";
                    
                    // $this->search_box('Search Items', 'search_id');
                    
                    $this->views();
                    
                    // Render the completed list table
                    $this->display();

                echo '</form>';
            echo '</div>';

            // Debug
            if ( FLAGCPRO_DEBUG )
            {
                echo '<pre>';
                $redirect_to = remove_query_arg( array( '_wp_http_referer', '_wpnonce', 'action', 'action2', 'flag' ), wp_get_referer() );
                $redirect_to = add_query_arg( 'paged', $this->get_pagenum(), $redirect_to );

                echo '<br>$this->sql_where';
                var_dump( $this->sql_where );
                //print_r( get_defined_constants(true) );

                echo '<br>wp_get_referer: ' . wp_get_referer() . '<br>';
                echo 'this->get_pagenum: ' . $this->get_pagenum() . '<br>';
                echo 'redirect_to: ' . $redirect_to . '<br>';
                echo 'esc_url_raw (redirect_to): ' . esc_url_raw($redirect_to) . '<br>';

                echo 'absint(blank): ' . absint('') . '<br>';

                echo '<br>Items: ';
                print_r ( $this->items );

                echo '<br>content_status_counts: ';
                print_r ( $this->flagged_content );

                echo '<br>content_status_counts: ';
                print_r ( $this->content_status_counts );

                echo '<br>ipcounts: ';
                print_r ( $this->ip_counts );

                echo '<br>$_GET: ';
                print_r ( $_GET );

                echo '<br>$_POST: ';
                print_r ( $_POST );

                echo '</pre>';
            }

        echo '</div>';
    }


    /**
     * The parent constructor fires too early in the WP cycle and errors out with a function error for convert_to_screen(). Instead, we use init()
     * to run the parent constructor. Init() is called in the display_page() method which is called in the admin_menu hook (admin.php)
     *
     * Need to fire the parent constructor and pass it default config arguments in this method.
     */
    public function init()
    {
        global $status, $page;

        //Set parent defaults
        parent::__construct(
            array(
                'singular'  => 'flag',    // singular name of the listed records
                'plural'    => 'flags',   // plural name of the listed records
                'ajax'      => false      // does this table support ajax?
            )
        );
    }

    /**
     * Prepare data for display.
     */
    function prepare_items()
    {
        global $wpdb;

        /** records per page to show */
        $per_page = 20;

        /**
         * Required. This includes a complete
         * array of columns to be displayed (slugs & titles), a list of columns
         * to keep hidden, and a list of columns that are sortable. Each of these
         * can be defined in another method (as we've done here) before being
         * used to build the value for our _column_headers property.
         */
        $columns = $this->get_columns();
        $hidden = array();
        $sortable = $this->get_sortable_columns();

        /**
         * Required. Build an array to be used by the class for column
         * headers. The $this->_column_headers property takes an array which contains
         * 3 other arrays. One for all columns, one for hidden columns, and one
         * for sortable columns.
         */
        $this->_column_headers = array( $columns, $hidden, $sortable );

        /** Required for pagination. Gets the current page. */
        $current_page = $this->get_pagenum();

        global $wpdb;

        $sql_select = "SELECT * FROM " . FLAGCPRO_TABLE;
        $conditions = array();
        $sql_orderby = '';

        /** Status &status= */
        $statuses = $this->status_array;

        // status 'all' is 0
        if ( $statuses[$this->status_current] !== 0 ) {
            $conditions['status'] = $statuses[ $this->status_current ];
        }

        /**
         * &ip IP address - Show all flags submitted by a particular ip address
         */
        if ( isset( $_REQUEST['ip'] ) && ! empty( $_REQUEST['ip'] ) )
        {
            $ip = $_REQUEST['ip'];

            if ( filter_var( $ip, FILTER_VALIDATE_IP ) )
            {
                $conditions['ip'] = inet_pton( $ip );
                $this->viewing_ip = true;
            }
        }

        /**
         * &content_id - Show flags for a particular piece of content.
         */
        elseif ( isset( $_REQUEST['content_id'] ) && isset( $_REQUEST['status'] ) && ! empty( $_REQUEST['content_id'] ) )
        {
            $content_id = absint( $_REQUEST['content_id'] );
            $content_type = (string) $_REQUEST['content_type'];

            if ( $content_id > 0 )
            {
                $conditions['content_id'] = $content_id;
                $conditions['content_type'] = $content_type;
                $this->viewing_content = true;
            }
        }

        /**
         * Order By &orderby=
         */
        if ( ! empty( $_REQUEST['orderby'] ) )
        {
            $sql_orderby .= ' ORDER BY ' . $_REQUEST['orderby'];
            $sql_orderby .= ! empty( $_REQUEST['order'] ) ? ' ' . $_REQUEST['order'] : ' ASC';
        }
        else
        {
            $sql_orderby .= ' ORDER BY date_notified DESC' ;
        }

        $this->sql_where_conditions = $conditions;
        $sql = $this->create_prepared_sql_statement( $sql_select, $conditions, $sql_orderby );

        // If no conditions, then do not use the prepare statement. Otherwise, there will be no
        // placeholders in the prepared SQL and wpdb::prepare will trigger a notice.
        if ( empty( $conditions ) ) {
            $data = $wpdb->get_results( $sql, 'ARRAY_A' );
        }
        else {
            $data = $wpdb->get_results( $wpdb->prepare( $sql, $conditions ), 'ARRAY_A' );
        }

        /** Required for pagination. Total number of items in your database */
        $total_items = count( $data );

        /**
         * The WP_List_Table class does not handle pagination. Need to ensure the data
         * is trimmed to only the current page.
         */
        $data = array_slice( $data, ( ( $current_page - 1 ) * $per_page ), $per_page );

        // add content and content counts into the main data/items array
        foreach( $data as $index => $item )
        {
            $data[ $index ]['content'] = $this->flagged_content[ $item['content_id'] . $item['content_type'] ];
            $data[ $index ]['content_counts'] = $this->content_status_counts[ $item['content_id'] . $item['content_type'] ];
        }

        /**
         * Required. Add *sorted* data to the items property, where
         * it can be used by the rest of the class.
         */
        $this->items = $data;

        /** Required. Register pagination options & calculations. */
        $this->set_pagination_args(
            array(
                'total_items' => $total_items,                      // we have to calculate the total number of items
                'per_page'    => $per_page,                         // we have to determine how many items to show on a page
                'total_pages' => ceil( $total_items / $per_page )   // we have to calculate the total number of pages
            )
        );
    }

    /**
     * Text displayed when no data is available
     */
    public function no_items()
    {
        $status_current = $this->status_current;
        $statuses = $this->status_array;

        switch ( $status_current )
        {
            case 'pending':   $no_item_text = '<span class="dashicons dashicons-yes"></span> '  . esc_html__( 'There are no pending flags', 'flagged-content-pro' );             break;
            case 'completed': $no_item_text = '<span class="dashicons dashicons-info"></span> ' . esc_html__( 'There are no flags marked as completed', 'flagged-content-pro' ); break;
            case 'all':       $no_item_text = '<span class="dashicons dashicons-yes"></span> '  . esc_html__( 'There are no flags', 'flagged-content-pro' );                     break;
        }

        echo $no_item_text;
    }


    public function ip_address_convert_to_readable( $ip_binary )
    {
        // Avoid PHP Warnings
        if ( $ip_binary === NULL || $ip_binary == '' ) {
            $ip_readable = '';
        }
        else {
            $ip_readable = inet_ntop( $ip_binary ); // Convert from binary to readable IP
        }

        return $ip_readable;
    }


    /**
     * Utility function to create a SQL string from an array of $conditions
     * e.g. $conditions['content_id'] = 123 becomes ... content_id = 123 AND ...
     * @param string $sql_select
     * @param array $conditions
     * @param string $sql_orderby
     * @return string
     */
    public function create_prepared_sql_statement( $sql_select, $conditions, $sql_orderby = '' )
    {
        if ( empty( $conditions ) ) {
            return $sql_select . $sql_orderby;
        }

        $where_placeholder = array();

        foreach ( array_keys( $conditions ) as $condition )
        {
            if ( $condition == 'ip' ) {
                $where_placeholder[] = "$condition = %s";
            }
            else {
                $where_placeholder[] = "$condition = %d";
            }
        }

        $sql_where = ' WHERE ' . implode( ' AND ', $where_placeholder );

        $sql = $sql_select . $sql_where . $sql_orderby;

        return $sql;
    }

}