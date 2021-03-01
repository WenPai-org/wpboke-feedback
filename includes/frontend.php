<?php
if ( ! defined( 'WPINC' ) ) { die; }

/**
 * Handles all public page requests
 */
class Flagged_Content_Pro_Frontend
{
    private $plugin;
    private $settings;
    private $form;
    private $post_id;
    private $content_id;
    private $content_name;
    private $content_type;
    private $already_enqueued_scripts = false;

    /**
     * Adds init() to the wp hook, which fires late enough for is_single, is_page, is_singular, etc. to be used. Otherwise,
     * these functions are not available at the time of this plugin's loading.
     * @param Flagged_Content_Pro $plugin
     */
    public function __construct( $plugin )
    {
        $this->plugin   = $plugin;
        $this->settings = $this->plugin->settings;
        add_action( 'init',               array( $this, 'shortcode_init' ) );
        add_action( 'wp',                 array( $this, 'auto_placer_init' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'register_public_files' ) );
    }

    /**
     * Callback function: Determines which flagging forms to auto add for various content types.
     * @action wp ( this->__construct() )
     */
	public function auto_placer_init()
    {
		// Do not load on index/archive pages
		//if ( ( ! is_single() and ! is_page() ) or is_front_page() ) {
		if ( ! is_singular() || is_front_page() ) {
			return;
		}

        global $post;
        $this->post_id = $post->ID;
        $at_least_one_form = false;

        // bbPress - currently on a single topic page
        // On the page, 'topic' appears once and 'reply' can appear multiple times
        if ( class_exists( 'bbPress' ) && is_bbpress() && bbp_is_single_topic() )
        {
            // check for topic and then reply
             $form = $this->plugin->get_form_by_content( 'topic', 'post', 'auto' );
            if ( $form === FALSE || $form['form_status'] == 'inactive' ) {
                $form = $this->plugin->get_form_by_content( 'reply', 'post', 'auto' );
            }

            if ( $form !== FALSE && $form['form_status'] == 'active' )
            {
                add_filter( 'bbp_get_reply_content', array( $this, 'add_to_bbpress' ), $form['reveal_priority'] );
                $at_least_one_form = true;
            }
        }

        // currently on a regular page
        elseif ( is_singular() )
        {
            // handle posts
            $form = $this->plugin->get_form_by_content( get_post_type( $this->post_id ), 'post', 'auto' );
            if ( $form !== FALSE && $form['form_status'] == 'active' )
            {
                add_filter( 'the_content', array( $this, 'add_to_post' ), $form['reveal_priority'] );
                $at_least_one_form = true;
            }

            // handle builtin wordpress comments
            $form = $this->plugin->get_form_by_content( 'comment', 'comment', 'auto' );
            if ( $form !== FALSE && $form['form_status'] == 'active' && get_comments_number( $this->post_id ) )
            {
                add_filter( 'comment_text', array( $this, 'add_to_comment' ), $form['reveal_priority'] );
                $at_least_one_form = true;
            }
        }
        // else, current public page not supported
        else {
            return;
        }

        // at least one form will appear on this page - Check if icons need to be enqueued.
        if ( $at_least_one_form ) {
            add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_public_files' ) );
        }
	}

    /**
     * Registers scripts and styles on public pages.
     * Registration must be separated from the enqueue for shortcode scripts and styles to load correctly
     */
	public function register_public_files()
    {
        if ( $this->settings['modal_type'] == 'magnific-popup' ) 
        {
            wp_register_script( 'flaggedc-magpop-script',        FLAGCPRO_URL . 'includes/magnific-popup/jquery.magnific-popup.min.js', array( 'jquery' ), FLAGCPRO_VERSION );
            wp_register_style(  'flaggedc-magpop-style',         FLAGCPRO_URL . 'includes/magnific-popup/magnific-popup.css',           array(),           FLAGCPRO_VERSION );
        }
        else 
        {
            wp_register_script( 'flaggedc-featherlight-script',  FLAGCPRO_URL . 'includes/featherlight/featherlight.min.js',  array( 'jquery' ), FLAGCPRO_VERSION );
            wp_register_style(  'flaggedc-featherlight-style',   FLAGCPRO_URL . 'includes/featherlight/featherlight.min.css', array(),           FLAGCPRO_VERSION );
        }
    
        wp_register_style(  'flaggedc-frontend-style',       FLAGCPRO_URL . 'css/frontend-styles.css',  array(),           FLAGCPRO_VERSION );
        wp_register_script( 'flaggedc-ajax-frontend-script', FLAGCPRO_URL . 'js/frontend-script.js',    array( 'jquery' ), FLAGCPRO_VERSION );
    }

    /**
     * Enqueues scripts and styles on public pages.
     */
    public function enqueue_public_files()
    {
        if ( $this->already_enqueued_scripts ) {
            return;
        }
 
        if ( $this->settings['modal_type'] == 'magnific-popup' ) 
        {
            if ( ! wp_script_is ( 'magnific-popup', 'registered' ) && ! wp_script_is ( 'jquery.magnific-popup.min.js', 'enqueued' ) && ! wp_script_is ( 'jquery.magnific-popup.js', 'enqueued' ) ) {
                wp_enqueue_script( 'flaggedc-magpop-script' );
            }

            if ( ! wp_style_is ( 'magnific-popup', 'registered' ) && ! wp_style_is ( 'magnific-popup.min.css', 'enqueued' ) && ! wp_style_is ( 'magnific-popup.css', 'enqueued' ) ) {
                wp_enqueue_style( 'flaggedc-magpop-style' );
            }
        }
        else 
        {
            if ( ! wp_script_is ( 'featherlight', 'registered' ) && ! wp_script_is ( 'featherlight.min.js', 'enqueued' ) && ! wp_script_is ( 'featherlight.js', 'enqueued' ) ) {
                wp_enqueue_script( 'flaggedc-featherlight-script' );
            }
            if ( ! wp_style_is ( 'featherlight', 'registered' ) && ! wp_style_is ( 'featherlight.min.css', 'enqueued' ) && ! wp_style_is ( 'featherlight.css', 'enqueued' ) ) {
                wp_enqueue_style( 'flaggedc-featherlight-style' );
            }
        }

        wp_enqueue_style(  'flaggedc-frontend-style' );
        wp_enqueue_script(  'flaggedc-ajax-frontend-script' );        

        wp_localize_script( 'flaggedc-ajax-frontend-script', 'flaggedc_ajax_object', array(
            'ajax_url' 	 => admin_url( 'admin-ajax.php' ),
            'nonce' 	 => wp_create_nonce( 'flaggedc_form_nonce' ),
            'modal_type' => $this->settings['modal_type'],
            'user_id' 	 => get_current_user_id(),
            'debug_mode' => FLAGCPRO_DEBUG
        ) );

        $forms = $this->plugin->get_forms();

        if ( isset( $forms ) && ! empty( $forms ) ) {
            foreach ( $forms as $form ) {
                if ( $form['reveal_icon'] != 'no_icon' && $form['form_status'] == 'active' ) {
                    wp_enqueue_style( 'dashicons' );
                    break;
                }
            }
        }

        $this->already_enqueued_scripts = true;
    }

    /**
     * Callback function outputs form to the post
     *
     * @filter the_content ( this->init() )
     * @param $content
     * @return string
     */
    public function add_to_post( $content )
    {
        // if ( ! in_the_loop() && ! is_main_query() ) {
        if ( ! in_the_loop() ) {
            return $content;
        }

        // $this->set_content_form_properties( 'post', 'auto' );
        $this->set_content_form_properties( array( 'type' => 'post', 'location' => 'auto' ) );

        if ( ! $this->check_form() ) {
            return $content;
        }

        $output = new Flagged_Content_Pro_Frontend_Output(
            $this->content_id,
            $this->content_name,
            $this->content_type,
            $this->form,
            $this->plugin
        );

        // Add form before the content "content_before" or after "content_after"
        if ( $this->form['reveal_location'] == "content_before" ) {
            return $output->get_output() . $content;
        }
        else {
            return $content . $output->get_output();
        }
	}

    /**
     * Callback function outputs form to comments
     *
     * 1.0.1 - Removed in_the_loop() check.
     *
     * @filter comment_text  ( this->init() )
     * @param $comment_text
     * @return string
     */
	function add_to_comment( $comment_text )
    {
        /*if ( ! in_the_loop() ) {
            return $comment_text;
        }*/

        // $this->set_content_form_properties( 'comment', 'auto' );
        $this->set_content_form_properties( array( 'type' => 'comment', 'location' => 'auto' ) );

        if ( ! $this->check_form() ) {
            return $comment_text;
        }

        $output = new Flagged_Content_Pro_Frontend_Output(
            $this->content_id,
            $this->content_name,
            $this->content_type,
            $this->form,
            $this->plugin
        );

        if ( $this->form['reveal_location'] == "content_before" ) {
             return $output->get_output() . $comment_text;
        }
        // else: "content_after"
        else {
            return $comment_text . $output->get_output();
        }
	}


    /**
     * Callback function: runs inside of the bbpress loop within a single topic and outputs a form for each reply.
     *
     * Other potential bbpress hooks:
     * bbp_template_after_replies_loop - After the replies, after "Viewing x posts" wording
     * bbp_theme_before_reply_form - Before the "Reply To: {topic name}" form at the bottom of each topic
     *
     * @filter bbp_get_reply_content init()
     * @param $content
     * @return string
     */
    public function add_to_bbpress( $content )
    {
        // $this->set_content_form_properties( 'post', 'auto' );
        $this->set_content_form_properties( array( 'type' => 'post', 'location' => 'auto' ) );

        if ( ! $this->check_form() ) {
            return $content;
        }

        $output = new Flagged_Content_Pro_Frontend_Output(
            $this->content_id,
            $this->content_name,
            $this->content_type,
            $this->form,
            $this->plugin
        );

        if ( $this->form['reveal_location'] == "content_before" ) {
            return $output->get_output() . $content;
        }
        // "content_after"
        else {
            return $content . $output->get_output();
        }
	}

    /**
     * @since 1.5.0
     */
    public function shortcode_init()
    {
        add_shortcode( 'flagged-content-pro', array( $this, 'shortcode_process' ) );
    }

    /**
     * @since 1.5.0
     */
    public function shortcode_process( $atts = array() )
    {
        //return 'shortcode_processed(): ' . $this->display_debug() . '<br>';

        // allow loading on single and front pages, but not indexes/archives
        //if ( ( ! is_single() and ! is_page() ) ) {
        if ( ! is_singular() ) {
            return '';
        }

        $atts = shortcode_atts( array( 'id' => false ), $atts );

        if ( $atts['id'] ) {
            $form = $this->plugin->get_form_by_id( $atts['id'] );
        }
        else {
            return '';
        }

        if ( empty( $form['content'][0] ) ) {
            return '';
        }

        // $this->set_content_form_properties( $form['content'][0]['type'], 'shortcode' );
        $this->set_content_form_properties( array(
            'name' => $form['content'][0]['name'],
            'type' => $form['content'][0]['type'],
            'location' => 'shortcode'
        ) );

        if ( ! $this->check_form() ) {
            return '';
        }

        $this->enqueue_public_files();

        $output = new Flagged_Content_Pro_Frontend_Output(
            $this->content_id,
            $this->content_name,
            $this->content_type,
            $this->form,
            $this->plugin
        );

        // return 'shortcode_processed(): ' . $this->display_debug() . '<br>';
        return $output->get_output();
    }


    /**
     * Sets the form. Also, sets several content properties.
     * @param array $content
     * @return void
     */
    private function set_content_form_properties( $content )
    {
        // content type is required
        if ( ! isset( $content['type'] ) || empty( $content['type'] ) )
        {
            $this->form = false;
            return;
        }

        // setup content properties
        if ( $content['type'] == 'post' )
        {
            global $post;
            $content_id   = isset( $content['id'] )   ? $content['id']   : $post->ID;
            $content_name = isset( $content['name'] ) ? $content['name'] : get_post_type( $content_id );
        }
        elseif ( $content['type'] == 'comment' )
        {
            $content_id   = isset( $content['id'] )   ? $content['id']   : get_comment_ID();
            $content_name = isset( $content['name'] ) ? $content['name'] : 'comment';
        }
        $this->content_id   = $content_id;
        $this->content_name = $content_name;
        $this->content_type = $content['type'];

        // protect against improper shortcode usage
        if ( isset( $content['location'] ) && $content['location'] == 'shortcode' )
        {
            // wrong post content name, in the wrong area (e.g. shortcode)
            if ( $content['type'] == 'post' && isset( $content['name'] ) && $content['name'] != get_post_type( $content_id ) )
            {
                $this->form = false;
                return;
            }
        }

        // get the right form for the content
        $this->form = $this->plugin->get_form_by_content( $content_name, $content['type'], $content['location'] );
    }


    /**
     * Checks if a form is available. Then checks if the form should be displayed.
     * @return bool
     */
    private function check_form()
    {
        if ( $this->form === FALSE ) {
            return false;
        }

        if ( $this->form['form_status'] != 'active' ) {
            return false;
        }

        if ( $this->form['form_user'] == 'logged_in' && ! is_user_logged_in() ) {
            return false;
        }

        // Debug Settings Array
        if ( FLAGCPRO_DEBUG ) {
            echo 'check_form(): ' . $this->display_debug() . '<br>';
        }

        return true;
    }


    /**
     * Function for debugging purposes.
     */
    private function display_debug()
    {
        global $post, $in_comment_loop;
        $html  = 'Debug:<pre>';
        $html .= '<br>post->ID: '                  . $post->ID;
        $html .= '<br>get_post_type( post->ID ): ' . get_post_type( $post->ID );
        $html .= '<br>get_the_ID(): '              . get_the_ID();
        $html .= '<br>in_the_loop: '               . in_the_loop();
        $html .= '<br>is_single: '                 . is_single();
        $html .= '<br>is_page: '                   . is_page();
        $html .= '<br>is_singular: '               . is_singular();

        $html .= '<br>this->content_id: '          . $this->content_id;
        $html .= '<br>this->content_name: '        . $this->content_name;
        $html .= '<br>this->content_type: '        . $this->content_type;
        $html .= '<br>$in_comment_loop '           . $in_comment_loop;
        $html .= '<br>have_comments(): '           . have_comments();
        $html .= '<br>comments_open( $this->content_id ): '  . comments_open( $this->content_id );
        $html .= '<br>get_comment_ID: '            . get_comment_ID();
        $html .= '<br>get_comments_number(): '     . get_comments_number( $post->ID );

        if ( comments_open( $this->content_id ) && get_comments_number( $post->ID ) )
        {
            $html .= '<br>get_comment_ID: '           . get_comment_ID();
            $html .= '<br>comments_open(): '          . comments_open( $this->content_id );
            $html .= '<br>get_comments_number(): '    . get_comments_number( $this->content_id );
            $html .= '<br>get_comment(): '            . print_r(get_comment(), true);
        }

        if ( class_exists( 'bbPress' ) )
        {
            $html .= '<br>is_bbpress: ' . is_bbpress();
            $html .= '<br>bbp_is_single_forum: ' . bbp_is_single_forum(); // All topics page
            $html .= '<br>bbp_is_forum_archive: ' . bbp_is_forum_archive(); // All forums page
            $html .= '<br>bbp_is_single_topic: ' . bbp_is_single_topic(); // Single topic page showing replies
            $html .= '<br>bbp_is_single_reply' . bbp_is_single_reply();
            $html .= '<br>bbp_is_reply: ' . bbp_is_reply();
        }

        $post_author_id = get_post_field( 'post_author', $post->ID );
        $user = get_user_by( 'id', $post_author_id );
        $html .= '<br><br>$post_author_id: ' . $post_author_id . ' author email: ' . $user->user_email . '<br>';

        $html .= '<br><br>Form used on this content:<br>';
        $html .= print_r( $this->form, true );
        $html .= '</pre>';
        /*echo "button: <button>Testing</button><br>";
        echo "input: <input type='button' value='testing'><br>";
        echo "link: <a href='#/'>Testing</a><br>";*/
        return $html;
    }
}
