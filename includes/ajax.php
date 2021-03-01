<?php
if ( ! defined( 'WPINC' ) ) { die; }

/**
 * Class requires user's settings for the plugin and the ajax request form data.
 */
class Flagged_Content_Pro_AJAX
{
	private $plugin;
	private $settings;
    private $form;
	private $data;
	private $pending_count;

	private $fail_message_blank;
	private $fail_message_email;
	private $fail_message_generic;

	public function __construct( $plugin )
    {
	    $this->plugin = $plugin;
        add_action( 'wp_ajax_nopriv_flaggedc_ajax_handler', array( $this, 'flaggedc_ajax_handler' ) ); // visitors
        add_action( 'wp_ajax_flaggedc_ajax_handler', 		array( $this, 'flaggedc_ajax_handler' ) ); // logged-in users
	}

    /**
     * Callback function processes ajax data for the plugin.
     * Runs security and validation checks. Sanitizes the data, adds to plugin table in DB and sends out emails (if enabled).
     */
	public function flaggedc_ajax_handler()
    {
		// Quick check to verify nonce. Dies if cannot be verified.
		check_ajax_referer( 'flaggedc_form_nonce', 'nonce' );

        // Get and set the user settings for the plugin
        $this->settings = $this->plugin->settings;

        // Get and set the form data passed through the ajax request
        $this->data = $this->get_ajax_data();

        // Get the form for the content type passed in through the ajax data
        $this->form = $this->plugin->get_form_by_content( $this->data['content_name'], $this->data['content_type'] );

        // If there is no form for the content type, then fail
        if ( $this->form === false ) {
            $this->send_failure_message( __( 'There was an issue submitting your form. Please reload the page and try again.', 'flagged-content-pro' ), 'no form: there is no form set for this content type');
        }

        $this->fail_message_blank   = $this->form['message_fail_required'];
        $this->fail_message_email   = $this->form['message_fail_email'];
        $this->fail_message_generic = $this->form['message_fail_validation'];

		// Verify the spam checks are met
		$this->check_spam_security();

		// Validate incoming content and user data
		$this->validate_content_user_data();
		
		// Validate incoming form field data
		$this->validate_form_fields( 'reason' );
		$this->validate_form_fields( 'name' );
		$this->validate_form_fields( 'email' );
		$this->validate_form_fields( 'description' );

		// Validation is finished, now sanitize the strings (just in case)
		$this->sanitize_form_fields();
	
		global $wpdb;		

		// $wpdb->prepare() not needed, insert uses prepare internally
		$wpdb->insert(
            FLAGCPRO_TABLE,
			array(
				'status' 		=> 1,
				'reason' 		=> $this->data['reason'],
				'name_entered' 	=> $this->data['name'],
				'email' 		=> $this->data['email'],
				'description' 	=> $this->data['description'],
				'ip' 			=> $this->data['ip'],
				'date_notified' => current_time('mysql'),
				'content_id' 	=> $this->data['content_id'],
				'content_name' 	=> $this->data['content_name'],
				'content_type' 	=> $this->data['content_type'],
				'user_id' 		=> $this->data['user_id']
            )
		);

		// email notification (if enabled)
		if ( $this->form['email_enabled'] == TRUE ) {
			$this->send_email();			
		}

		// perform an action (if enabled)
        if ( $this->form['form_action'] == TRUE ) {
            $this->perform_action();
        }

		// Return success message to user
		$return_array['message'] = $this->form['message_success'];
		wp_send_json_success( $return_array );

		// Precaution - Ajax handlers must die when finished.
	    wp_die();
	}

    /**
     * Assembles the data sent through ajax into an array. Cleans the data.
     *
     * @return array
     */
	private function get_ajax_data()
    {
		$data = array(
			'content_id' 	 => $_POST['content_id'],
			'user_id' 		 => $_POST['user_id'],
			'reason'		 => $_POST['flaggedc_reason'],
			'name' 			 => $_POST['flaggedc_name'],
			'email' 		 => $_POST['flaggedc_email'],
			'description'	 => $_POST['flaggedc_description'],
			'sticky_paper'   => $_POST['flaggedc_sticky_paper'],
			'sticky_paper_2' => $_POST['flaggedc_sticky_paper_2'],
			'pocketwatch' 	 => $_POST['flaggedc_pocketwatch'],
			'content_name' 	 => $_POST['content_name'],
			'content_type' 	 => $_POST['content_type']
		);

		// Use post_id generated by php, if that doesn't work, then use the post id from the hidden form field
		/*if ( (int) $data['content_id'] <= 0 ) {
			$data['content_id'] = $data['content_id_2'];
		}*/

		$data['reason'] 	 = trim( stripslashes_deep( $data['reason'] 	 ) );
		$data['name']  		 = trim( stripslashes_deep( $data['name'] 		 ) );
		$data['email'] 		 = trim( stripslashes_deep( $data['email'] 	  	 ) );
		$data['description'] = trim( stripslashes_deep( $data['description'] ) );

		// Add submitter's IP address to the data
        if ( $this->settings['save_ip_address'] )
        {
            $ip = $_SERVER['REMOTE_ADDR'];

            // Debug - Alter IP address to something more easily tested
            if ( FLAGCPRO_DEBUG ) {
                if (  mt_rand( 0, 1 ) ) {
                    $ip = long2ip( mt_rand() );
                }
            }

            if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
                $data['ip'] = inet_pton( $ip ); // Convert to binary
            }
            else {
                $data['ip'] = '';
            }
        }
        else
        {
            $data['ip'] = '';
        }

		return $data;
	}

    /**
     * Spam Security
     * - Honeypot
     * - Timestamp Defense
     *
     * If the function finds a validation error, then it will exit and send a failure message to the user. If the function passes validation then
     * nothing is returned. Control reverts back to the handler.
     */
	private function check_spam_security ()
    {
		if ( $this->settings['honeypot'] )
		{
			// Fail if the honeypot value is not set
			if ( ! isset( $this->data['sticky_paper'] ) || ! isset( $this->data['sticky_paper_2'] ) ) {
				$this->send_failure_message( $this->fail_message_generic, 'honeypot: enabled but not set in ajax data');
			} 

			// Fail if the honeypot value is not numeric
			elseif ( ! is_numeric( $this->data['sticky_paper'] ) || ! is_numeric( $this->data['sticky_paper_2'] ) ) {
				$this->send_failure_message( $this->fail_message_generic, 'honeypot: should be numeric but was not in ajax data');
			}

			// Only a certain range of numbers will be generated. Anything outside this range is bad.
			elseif ( $this->data['sticky_paper_2'] < 5000 || $this->data['sticky_paper_2'] > 6000 ) {
				$this->send_failure_message( $this->fail_message_generic, 'honeypot: the guide number was outside the range');
			} 

			// Check if the honeypot hidden field numbers matches the other. Fail if they don't match.
			elseif ( $this->data['sticky_paper_2'] !== $this->data['sticky_paper'] ) {
				$this->send_failure_message( $this->fail_message_generic, 'honeypot: the guide number and stick fields do not match');
			}
		}

		if ( $this->settings['time_review'] )
		{
			$ajax_check_time = (int) time();

			// Fail if the timestamp defense value is not set
			if ( ! isset( $this->data['pocketwatch'] ) ) {
				$this->send_failure_message( $this->fail_message_generic, 'timestamp: enabled but not set in ajax data');
			}

			// Fail if the timestamp defense value is not numeric
			elseif ( ! is_numeric( $this->data['pocketwatch'] ) ) {
				$this->send_failure_message( $this->fail_message_generic, 'timestamp: should be numeric but was not in ajax data');
			}
			
			// Fail if form took less than 4 seconds to submit.
			elseif ( $ajax_check_time - $this->data['pocketwatch'] <= 4 ) {
				$this->send_failure_message( $this->fail_message_generic, 'timestamp: too fast');
			}
		}

		return;
	}

    /**
     * Validation:
     * - ID numbers
     * - Content type
     * - Form fields - Required
     * - Form fields - Valid E-mail
     *
     * If the function finds a validation error, then it will exit and send a failure message to the user. If the function passes validation then
     * nothing is returned. Control reverts back to the handler.
     */
	private function validate_content_user_data()
    {
	    // content_id
		if ( ! isset( $this->data['content_id'] ) || filter_var( $this->data['content_id'], FILTER_VALIDATE_INT) === FALSE ) {
			$this->send_failure_message( $this->fail_message_generic, 'content_id: should be numeric but was not in ajax data');
		}

		// content_id
        elseif ( (int) $this->data['content_id'] <= 0 ) {
            $this->send_failure_message( $this->fail_message_generic, 'content_id: The content_id received in the ajax data is was int casted to 0 or below');
        }

        // user_id
		elseif ( ! isset( $this->data['user_id'] ) || filter_var( $this->data['user_id'], FILTER_VALIDATE_INT) === FALSE ) {
			$this->send_failure_message( $this->fail_message_generic, 'user_id: should be numeric but was not in ajax data');
		}

        // content_type - verify valid type
		elseif ( $this->data['content_type'] != 'post' && $this->data['content_type'] != 'comment' ) {
			$this->send_failure_message( $this->fail_message_generic, 'content_type: invalid content type was received in the ajax data');
		}

		// content_id - verify valid post
        elseif ( $this->data['content_type'] == 'post' && get_post_status( (int) $this->data['content_id'] ) === FALSE ) {
            $this->send_failure_message( $this->fail_message_generic, 'content_id: The content_id received in the ajax data does not correspond with an actual post type');
        }

        // content_id - verify valid comment
        // get_comment is particular: $id parameter must be passed in as variable and must be int (not literal int). Returns NULL if comment cannot be found
        elseif ( $this->data['content_type'] == 'comment' )
        {
            $content_id = (int) $this->data['content_id'];

            if ( get_comment( $content_id ) === NULL ) {
                $this->send_failure_message( $this->fail_message_generic, 'content_id: The content_id received in the ajax data does not correspond with an actual comment');
            }
	    }

		return;
	}

    /**
     * Sanitization:
     * - ID numbers
     * - Form fields
     * - IP Address
     */
	private function sanitize_form_fields()
    {
		$this->data['content_id']  	= (int) $this->data['content_id'];
		$this->data['user_id'] 	 	= (int) $this->data['user_id'];
		$this->data['reason'] 	 	= sanitize_text_field( 	$this->data['reason'] );
		$this->data['name']  		= sanitize_text_field( 	$this->data['name'] );
		$this->data['email'] 		= sanitize_email( 		$this->data['email'] );
		$this->data['description'] 	= sanitize_text_field( 	$this->data['description'] );

		return;
	}


	/**
     * Possible validation errors:
	 * - 1 or more fields are not set when they should be
	 * - 1 or more required fields are blank
	 * - E-mail field is invalid
	 *
	 * If the function finds a validation error, then it will exit and send a failure message to the user. If the function passes validation then
     * nothing is returned. Control reverts back to the handler.
    */
	private function validate_form_fields( $field )
    {
	    // Validate forms fields if they set to be displayed. In the case of the reason field, only validate if there are reasons to choose from
		if ( ( $this->form[ $field ] != 'no_display' && $field != 'reason' ) || ( $field == 'reason' && ! empty( $this->form['reason_choose'] ) ) )
		{
			if ( ! isset( $this->data[ $field ] ) ) {
				$this->send_failure_message( $this->fail_message_blank, $field . ': is set to be shown but was not set in ajax data');
			}

			elseif ( $this->form[ $field ] == 'required' && $this->data[ $field ] == '' ) {
				$this->send_failure_message( $this->fail_message_blank, $field . ': is required but blank');
			} 

			elseif ( $field == 'email' && $this->data[ $field ] != '' && filter_var( $this->data[ $field ], FILTER_VALIDATE_EMAIL) === false ) {
				$this->send_failure_message( $this->fail_message_email, $field . ': is not a valid email');
			}
		}

		else
        {
			$this->data[ $field ] = '';
		}

		return;
	}

    /**
     * Returns a failure message (ajax) back to the user's browser.
     *
     * @param string $fail_message - message displayed to the user in the flagging form.
     * @param string $debug_reason - The actual reason for the failure, only displayed if debug mode is turned on.
     */
	private function send_failure_message( $fail_message, $debug_reason )
    {
		$return_array['message'] = $fail_message;

		if ( FLAGCPRO_DEBUG ) {
			$return_array['issue'] = $debug_reason;
		}

		wp_send_json_error( $return_array );
	}


    /**
     * Email notification for flag submission. Uses wp_mail to send the e-mail.
     */
	private function send_email()
    {
        // Check if this form has an email limit set. If it does, then check how many times
        // this content item has been already flagged. Only send an email if the content
        // has been flagged enough to pass the limit set.
	    if ( $this->form['email_limit'] && $this->form['email_limit_number'] > 1 )
	    {
            $pending_count = $this->get_pending_count();

            if ( $pending_count < $this->form['email_limit_number'] ) {
                return;
            }
        }

		// Determine who the email is sent to

        $email_to = array();

		if ( $this->form['email_to_blog_admin'] )
		{
            // get_option('admin_email') pulls email from: Settings > General > Email address
			$email_to[] = get_option('admin_email');
		}

		if ( $this->form['email_to_admins'] )
		{
            $admins = get_users( 'role=Administrator' );
            foreach ( $admins as $admin ) {
                if ( ! empty( $admin->user_email ) ) {
                    $email_to[] = $admin->user_email;
                }
            }
        }

		if ( $this->form['email_to_editors'] )
		{
            $editors = get_users( 'role=Editor' );
            foreach ( $editors as $editor ) {
                if ( ! empty( $editor->user_email ) ) {
                    $email_to[] = $editor->user_email;
                }
            }
		}

		if ( $this->form['email_to_author'] && $this->data['content_type'] == 'post' )
		{
            $author_id = get_post_field( 'post_author', $this->data['content_id'] );

            if ( ! empty( $author_id ) )
            {
                $author = get_user_by( 'id', $author_id );

                if ( ! empty( $author->user_email ) ) {
                    $email_to[] = $author->user_email;
                }
            }
		}

        if ( ! empty( $this->form['email_to_custom_address'] ) && is_email( $this->form['email_to_custom_address'] ) )
        {
            $email_to[] = $this->form['email_to_custom_address'];
        }

		// If there are no recipients for the email then exit
		if ( empty( $email_to ) ) {
		    return;
        }
        // Else, remove duplicate emails and reindex the array
        else {
            $email_to = array_values ( array_unique( $email_to ) );
        }

        /** Kept post_title and post_link for backwards compatibility. */

        // Build email subject and message body
        $email_subject = $this->form['email_subject'];
        $email_message = $this->form['email_message'];
        $site_name = get_bloginfo( 'name' );

        // post
        if ( $this->data['content_type'] == 'post' )
        {
            $content_name = get_post_type( $this->data['content_id'] );
            $content_name = get_post_type_object( $content_name )->labels->singular_name;
            $content_desc  = get_the_title( $this->data['content_id'] );
            $content_link  = get_permalink( $this->data['content_id'] );
            $post_title = $content_desc;
            $post_link = $content_link;
        }
        // comment
        elseif ( $this->data['content_type'] == 'comment' )
        {
            $content_name  = __( 'Comment', 'flagged-content-pro' );
            $comment_info  = get_comment( $this->data['content_id'] );
            $post_title    = get_the_title( $comment_info->comment_post_ID );  // get title of post containing the comment
            $content_desc  = get_comment_excerpt( $this->data['content_id'] );
            $content_desc  = str_replace( '&hellip;', '...', $content_desc );  // replace horizontal ellipsis html entity 
            $post_link     = get_permalink( $comment_info->comment_post_ID );  // get the link to the post containing the comment
            $content_link  = get_comment_link( $this->data['content_id'] );
        }
        // unknown
        else {
            return;
        }

		$email_subject 	= str_replace( '[site_name]', 		 $site_name,                 $email_subject );
        $email_subject 	= str_replace( '[content_name]',     $content_name,              $email_subject );
		$email_message	= str_replace( '[site_name]', 		 $site_name,                 $email_message );
        $email_message 	= str_replace( '[post_title]', 		 $post_title,                $email_message );
		$email_message 	= str_replace( '[post_link]', 		 $post_link,                 $email_message );
        $email_message 	= str_replace( '[content_name]',     $content_name,              $email_message );
        $email_message 	= str_replace( '[content_desc]', 	 $content_desc,              $email_message );
        $email_message 	= str_replace( '[content_link]', 	 $content_link,              $email_message );
		$email_message 	= str_replace( '[flag_reason]', 	 $this->data['reason'], 	 $email_message );
		$email_message 	= str_replace( '[flag_description]', $this->data['description'], $email_message );

		wp_mail( $email_to, $email_subject, $email_message );

        //wp_send_json_success( $email_to );
		return;
	}


    /**
     * Performs the user selected action after X number of flags for a content item
     *  - $comment['comment_approved'] = 0 => unapprove
     *  - $comment['comment_approved'] = 'spam' => spam
     * @since 1.4.0
     */
    private function perform_action()
    {
        $pending_count = $this->get_pending_count();

        if ( $pending_count < $this->form['form_action_number'] ) {
            return;
        }

        if ( $this->data['content_type'] == 'comment' )
        {
            $current_status = wp_get_comment_status( $this->data['content_id'] );

            // set status only if current status differs from what it should be set to
            if ( $current_status != 'unapproved' )
            {
                $comment = array();
                $comment['comment_ID'] = $this->data['content_id'];
                // 0 => unapprove
                $comment['comment_approved'] = 0;
                // wp_update_comment() calls wp_transition_comment_status() which has transition_comment_status hook
                wp_update_comment( $comment );
            }
        }

        elseif ( $this->data['content_type'] == 'post' )
        {
            $current_status = get_post_status( $this->data['content_id'] );

            // set status only if current status differs from what it should be set to
            if ( $current_status != 'pending' )
            {
                $args = array(
                    'ID'          =>  $this->data['content_id'],
                    'post_status' =>  'pending'
                );
                wp_update_post( $args );
            }
        }
    }


    /**
     * @return int
     * @since 1.4.0
     */
    private function get_pending_count()
    {
        if ( isset( $this->pending_count ) && $this->pending_count !== null )
        {
            return $this->pending_count;
        }
        else
        {
            global $wpdb;
            $sql = $wpdb->prepare( "SELECT COUNT(*) FROM " . FLAGCPRO_TABLE . " WHERE status = 1 AND content_id = %d AND content_type = %s", $this->data['content_id'], $this->data['content_type'] );
            $this->pending_count = $wpdb->get_var( $sql );
            return $this->pending_count;
        }
    }

}