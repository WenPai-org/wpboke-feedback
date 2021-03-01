<?php
if ( ! defined( 'WPINC' ) ) { die; }

/**
 * Creates the output (form(s) to flag content) displayed on public pages
 */
class Flagged_Content_Pro_Frontend_Output
{
	private $plugin;
    private $settings;
    private $form;

    private $content_id;
    private $content_name;
    private $content_type = '';

    private $data_cname;
    private $data_ctype;
    private $data_output_id;
    private $data_form_id;

    private $output_alert = '';
    private $output_instructions = '';
    private $output_name_email_container_open = '';
    private $output_name_email_container_close = '';
    private $output_name = '';
    private $output_email = '';
    private $output_reason = '';
    private $output_description = '';
    private $output_hidden = '';
    private $output_reveal = '';
    private $output_submit = '';
    private $output = '';

    private $alignment;

    /**
     * Static property to count each instantiation of this class on a wp public page. It is used to link a
     * reveal button with a modal and the submit button with an output form. This ensures the correct
     * form data is passed when there are multiple output forms on the same page (e.g. post form followed
     * by multiple comment forms).
     */
    private static $output_id = 0;

    public function __construct( $content_id, $content_name, $content_type, $form, $plugin )
    {
        self::$output_id++;

        $this->content_id     = $content_id;
        $this->content_name   = $content_name;
        $this->content_type   = $content_type;

        $this->form     = $form;
        $this->plugin   = $plugin;
        $this->settings = $this->plugin->settings;

        $this->data_cname     = 'data-flaggedc-cname="'     . esc_attr( $this->content_name )    . '"';
        $this->data_ctype     = 'data-flaggedc-ctype="'     . esc_attr( $this->content_type)     . '"';
        $this->data_output_id = 'data-flaggedc-output-id="' . esc_attr( self::$output_id )       . '"';
        $this->data_form_id   = 'data-flaggedc-form-id="'   . esc_attr( $this->form['form_id'] ) . '"';
     
        // prepare the html through a series of conditionals
        $this->prepare_output();
        // structure the prepared html so it can be rendered
        $this->structure_output();
	}

    /**
     * Assembles the html for the form to be added into the modal
     */
    private function prepare_output()
    {
	    $at_least_one_field = false;

        $this->output_alert = '<div class="flaggedc-alert-box flaggedc-validation-errors-description">' . esc_html( $this->form['message_fail_required'] ) . '</div>';

        if ( ! empty( $this->form['message_instructions'] ) && strlen( trim( $this->form['message_instructions'] ) ) != 0 ) {
            $this->output_instructions = '<div>' . esc_html( $this->form['message_instructions'] ). '</div>';
        }

        if ( $this->form['name'] != 'no_display' || $this->form['email'] != 'no_display' )
        {
            $this->output_name_email_container_open .= '<div class="flaggedc-name-email-container">';

            if ( is_user_logged_in() )
            {
                $readonly = 'readonly';
                $current_user = wp_get_current_user();
            }
            else
            {
                $readonly = '';
            }
        }

        $required_text = esc_html__( '(required)', 'flagged-content-pro' );

        if ( $this->form['name'] != 'no_display' )
        {
            $user_name          = is_user_logged_in() ? $current_user->user_login : '';
            $name_wording       = esc_html__( 'Your Name', 'flagged-content-pro' );
            $required_wording 	= $this->form['name'] == 'required' ? " <small>{$required_text}</small>" : '';
            $required_attribute = $this->form['name'] == 'required' ? 'required ' : '';

            $this->output_name .= '<div class="flaggedc-name-field">';
            $this->output_name .= "<label for='flaggedc_name'>{$name_wording}{$required_wording}</label><br>";
            $this->output_name .= "<input type='text' name='flaggedc_name' id='flaggedc_name' value='{$user_name}' {$required_attribute} {$readonly}>";
            $this->output_name .= '</div>';

            $at_least_one_field = true;
        }

        if ( $this->form['email'] != 'no_display' )
        {
            $user_email         = is_user_logged_in() ? $current_user->user_email : '';
            $email_wording      = esc_html__( 'Email', 'flagged-content-pro' );
            $required_wording 	= $this->form['email'] == 'required' ? " <small>{$required_text}</small>" : '';
            $required_attribute = $this->form['email'] == 'required' ? 'required ' : '';

            $this->output_email .= '<div class="flaggedc-email-field">';
            $this->output_email .= "<label for='flaggedc_email'>{$email_wording}{$required_wording}</label><br>";
            $this->output_email .= "<input type='text' name='flaggedc_email' id='flaggedc_email' value='{$user_email}' {$required_attribute} {$readonly}>";
            $this->output_email .= '</div>';

            $at_least_one_field = true;
        }

        if ( $this->form['name'] != 'no_display' || $this->form['email'] != 'no_display' )
        {
            $this->output_name_email_container_close .= '</div>';
            $this->output_name_email_container_close .= '<div class="flaggedc-clear-floated-fields"></div>';
        }

        if ( $this->form['reason'] != 'no_display' && ! empty( $this->form['reason_choose'] ) )
        {
            $reason_wording     = esc_html__( 'Reason', 'flagged-content-pro' );
            $required_wording   = $this->form['reason'] == 'required' ? " <small>{$required_text}</small>" : '';
            $required_attribute = $this->form['reason'] == 'required' ? 'required ' : '';

            if ( $this->form['reason_display'] == 'dropdown' )
            {
                $this->output_reason .= '<div class="flaggedc-reason-container">';
                $this->output_reason .= "<label for='flaggedc_reason'>{$reason_wording}{$required_wording}</label><br />";
                $this->output_reason .= "<select name='flaggedc_reason' id='flaggedc_reason' {$required_attribute}>";

                foreach ( $this->form['reason_choose'] as $reason ) {
                    $this->output_reason .= sprintf( '<option value="%s">%s</option>', esc_attr( $reason ), esc_html( $reason ) );
                }

                $this->output_reason .= '</select></div>';
            }
            else
            {
                $this->output_reason .= '<div class="flaggedc-reason-container">';
                $this->output_reason .= "<div class='flaggedc-radio-group-label'><label>{$reason_wording}{$required_wording}</label></div>";
                $this->output_reason .= '<div class="flaggedc-reason-radio-group-container">';

                foreach ( $this->form['reason_choose'] as $index => $reason )
                {
                    $reason_value = esc_attr( trim( $reason ) );
                    $reason_label = esc_html( trim( $reason ) );
                    $this->output_reason .= "<input type='radio' name='flaggedc_reason' id='flaggedc_reason{$index}' value='{$reason_value}' {$required_attribute}> ";
                    $this->output_reason .= "<label for='flaggedc_reason{$index}'>{$reason_label}</label><br>";
                }

                $this->output_reason .= '</div></div>';
            }

            $at_least_one_field = true;
        }

        if ( $this->form['description'] != 'no_display' )
        {
            $description_wording = esc_html__( 'Description', 'flagged-content-pro' );
            $required_wording 	 = $this->form['description'] == 'required' ? " <small>{$required_text}</small>" : '';
            $required_attribute  = $this->form['description'] == 'required' ? 'required ' : '';

            $this->output_description .= '<div class="flaggedc-description-container">';
            $this->output_description .= "<label for='flaggedc_description'>{$description_wording}{$required_wording}</label><br />";
            $this->output_description .= "<textarea name='flaggedc_description' id='flaggedc_description' {$required_attribute}></textarea>";
            $this->output_description .= '</div>';

            $at_least_one_field = true;
        }

        $this->output_hidden .= "<input type='hidden' name='flaggedc_content_id' value='{$this->content_id}'>";

        // Spam Settings - Honeypot and Timestamp Defense
        if ( $this->settings['honeypot'] )
        {
            $this->output_hidden .= '<input type="hidden" name="flaggedc_sticky_paper" value="' . rand(5000, 6000) . '">';
            $this->output_hidden .= '<input type="hidden" name="flaggedc_sticky_paper_2" value="1">';
        }

        if ( $this->settings['time_review'] ) {
            $this->output_hidden .= '<input type="hidden" name="flaggedc_pocketwatch" value="' . time() . '">'; // Try encrypting this number?
        }
        // --- --- //


        /*
         * reveal_style has string data that needs to be exploded (e.g. theme;red )
         * $reveal['style'] = theme
         * $reveal['color'] = red
         */
        $keys = array( 'style', 'color' );
        $reveal = array_combine( $keys, explode( ';', $this->form['reveal_style'] ) );
        $reveal_classes = "flaggedc-button flaggedc-reveal-button flaggedc-button-style-{$reveal['style']} flaggedc-button-color-{$reveal['color']}";

        if ( $reveal['color'] == 'custom' )
        {
            $reveal_color_base  = "background-color: {$this->form['reveal_color_base']};";
            $reveal_color_base .= "border-color: {$this->form['reveal_color_base']};";
            $reveal_color_base .= "color: {$this->form['reveal_color_text']};";
        }
        else
        {
            $reveal_color_base = '';
        }

        $reveal_icon          = $this->form['reveal_icon']          == 'no_icon' ? '' : "<span class='dashicons " . esc_html( $this->form['reveal_icon'] ) . "'></span>";
        $reveal_success_icon  = $this->form['reveal_success_icon']  == 'no_icon' ? '' : $this->form['reveal_success_icon'];
        $reveal_success_label = $this->form['reveal_success_label'] == ''        ? '' : $this->form['reveal_success_label'];


        if ( $this->form['reveal_label'] == '' && $reveal_icon == '' ) {
            $reveal_label = __( 'Flag', 'flagged-content-pro' );
        }
        else {
            $reveal_label = $this->form['reveal_label'];
        }

        if ( $reveal_success_label == '' && $reveal_success_icon == '' ) {
            $reveal_success_label = __( 'Flagged', 'flagged-content-pro' );
        }


        if ( $this->form['reveal_display'] == 'button' )
        {
            $this->output_reveal .= sprintf(
                '<button type="button" class="%1$s" style="%2$s" data-flaggedc-success-icon="%3$s" data-flaggedc-success-label="%4$s" data-flaggedc-color-saved="%5$s" data-flaggedc-color-hover="%6$s" %7$s %8$s %9$s>%10$s%11$s</button>',
                /* class=                       */ esc_attr( $reveal_classes ),                   /* %1$s */
                /* style=                       */ esc_attr( $reveal_color_base ),                /* %2$s */
                /* data-flaggedc-success-icon=  */ esc_attr( $reveal_success_icon ),              /* %3$s */
                /* data-flaggedc-success-label= */ esc_attr( $reveal_success_label ),             /* %4$s */
                /* data-flaggedc-color-saved=   */ esc_attr( $this->form['reveal_color_base'] ),  /* %5$s */
                /* data-flaggedc-color-hover=   */ esc_attr( $this->form['reveal_color_hover'] ), /* %6$s */
                /* data-flaggedc-data_cname     */ $this->data_cname,                             /* %7$s */
                /* data-flaggedc-data_ctype     */ $this->data_ctype,                             /* %8$s */
                /* data-flaggedc-output-id      */ $this->data_output_id,                         /* %9$s */
                /* <> </>                       */ $reveal_icon,                                  /* %10$s */
                /* <> </>                       */ esc_html( $reveal_label )                      /* %11$s */
                );
        }
        else
        {
            $this->output_reveal .= sprintf(
                '<a href="#/" class="%1$s" data-flaggedc-success-icon="%2$s" data-flaggedc-success-label="%3$s" %4$s %5$s %6$s>%7$s%8$s</a>',
                /* class=                       */ esc_attr( 'flaggedc-button flaggedc-reveal-button' ), /* %1$s */
                /* data-flaggedc-success-icon=  */ esc_attr( $reveal_success_icon ),                     /* %2$s */
                /* data-flaggedc-success-label= */ esc_attr( $reveal_success_label ),                    /* %3$s */
                /* data-flaggedc-cname          */ $this->data_cname,                                    /* %4$s */
                /* data-flaggedc-ctype          */ $this->data_ctype,                                    /* %5$s */
                /* data-flaggedc-output-id      */ $this->data_output_id,                                /* %6$s */
                /* <> </>                       */ $reveal_icon,                                         /* %7$s */
                /* <> </>                       */ esc_html( $reveal_label )                             /* %8$s */
            );
        }

        /*
         * submit_style has string data that needs to be exploded (e.g. theme;red )
         * $submit['style'] = theme
         * $submit['color'] = red
         */
        $keys           = array( 'style', 'color' );
        $submit         = array_combine( $keys, explode( ';', $this->form['submit_style'] ) );
        $submit_classes = "flaggedc-button flaggedc-submit-button flaggedc-button-style-{$submit['style']} flaggedc-button-color-{$submit['color']}";

        if ( $submit['color'] == 'custom' )
        {
            $submit_color_base  = "background-color: {$this->form['submit_color_base']};";
            $submit_color_base .= "border-color: {$this->form['submit_color_base']};";
            $submit_color_base .= "color: {$this->form['submit_color_text']};";
        }
        else
        {
            $submit_color_base = '';
        }

        $submit_label = $this->form['submit_label'] == '' ? __( 'Submit', 'flagged-content-pro' ) : $this->form['submit_label'];
        $submit_sending_label = ( isset( $this->form['submit_sending_label'] ) && $this->form['submit_sending_label'] != '' ) ? $this->form['submit_sending_label'] : __( 'Sending', 'flagged-content-pro' );

        $this->output_submit  = '<div class="flaggedc-submit-container">';
        $this->output_submit .= sprintf(
            '<button type="button" class="%1$s" style="%2$s" data-flaggedc-color-saved="%3$s" data-flaggedc-color-hover="%4$s" data-flaggedc-sending-label="%5$s" %6$s %7$s %8$s>%9$s</button>',

            /* class=                       */ esc_attr( $submit_classes ),                    /* %1$s */
            /* style=                       */ esc_attr( $submit_color_base ),                 /* %2$s */
            /* data-flaggedc-color-saved=   */ esc_attr( $this->form['submit_color_base'] ),   /* %3$s */
            /* data-flaggedc-color-hover=   */ esc_attr( $this->form['submit_color_hover'] ),  /* %4$s */
            /* data-flaggedc-sending-label= */ esc_attr( $submit_sending_label ),              /* %5$s */
            /* data-flaggedc-cname          */ $this->data_cname,                              /* %6$s */
            /* data-flaggedc-ctype          */ $this->data_ctype,                              /* %7$s */
            /* data-flaggedc-output-id      */ $this->data_output_id,                          /* %8$s */
            /* <> </>                       */ esc_html( $submit_label )                       /* %9$s */
        );

        $this->output_submit .= '<img src="' . FLAGCPRO_URL . 'images/loading.gif' . '" class="flaggedc-submit-spinner">';
        $this->output_submit .= '</div>';

        if ( isset( $this->form['reveal_align'] ) && ! empty( $this->form['reveal_align'] ) ) {
            $this->alignment = "style='text-align:{$this->form['reveal_align']}'";
        }
        else {
            $this->alignment = '';
        }
    }

    /**
     * Structures the html for the form within the modal
     */
    private function structure_output()
    {
        $modal_classes = '';
        if ( $this->settings['modal_type'] == 'magnific-popup' ) {
            $modal_classes = 'flaggedc-zoom-anim-dialog mfp-hide'; 
        }

        $this->output = <<<HTML
        
            <div class='flaggedc-form-container flaggedc-form-status-unflagged' {$this->alignment} {$this->data_form_id} {$this->data_output_id}>
                <div> 
                    {$this->output_reveal} 
                </div> 
                <div class='flaggedc-form flaggedc-form-modal {$modal_classes} flaggedc-hide' {$this->data_output_id}> 
                    <form> 
                        <div class="flaggedc-form-inside"> 
                            {$this->output_alert} 
                            <div class="flaggedc-form-fields"> 
                                {$this->output_instructions} 
                                {$this->output_name_email_container_open} 
                                    {$this->output_name} 
                                    {$this->output_email} 
                                {$this->output_name_email_container_close} 
                                {$this->output_reason} 
                                {$this->output_description} 
                                {$this->output_hidden} 
                                {$this->output_submit} 
                            </div> 
                        </div> 
                    </form> 
                </div> 
            </div>
HTML;
    }

    public function get_output()
    {
        return $this->output;
    }
}