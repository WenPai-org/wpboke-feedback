jQuery(document).ready(function( $ ) {

	/*
	 * Settings init
	 */
    $( 'input[data-flaggedc-color-picker="true"]' ).wpColorPicker();


	/*
	 * Settings tabs
	 */
	$( '.flaggedc-admin-tabs' ).on( 'click', '.nav-tab-wrapper a', function() {
		
		$( this ).addClass( 'nav-tab-active' );

		// Remove active class from all tab links except the one clicked on
		$( '.nav-tab-wrapper a' ).not( this ).removeClass( 'nav-tab-active' );
		
		$( 'section' ).hide();
		$( '.flaggedc-admin-settings-wrapper section' ).eq( $( this ).index() ).show();
		
		$( this ).blur(); // Removes blue outline/box-shadow from the tab (a:focus)

		return false;
	});


	/*
	 * Settings - Hide / show fields based on selections
	 */
	function check_fields( element, action ) {

        var field_name = $( element ).attr( 'name' ).match(/\[(.*?)\]/);
        field_name = field_name[1];

		var field_value;

        if ( $( element ).is( 'input[type="radio"]:checked' ) ) {
            field_value = $( element ).val();
        }

        else if ( $( element ).is( 'input[type="checkbox"]' ) && $( element ).is( ':checked' ) ) {
            field_value = 1;
        }

        else if ( $( element ).is( 'input[type="checkbox"]' ) && ! ( $( element ).is( ':checked' ) ) ) {
        	field_value = 0;
		}

		else if ( $( element ).is( 'select' ) ) {
            field_value = $( element ).val();
		}

        else {
            return;
        }


        if ( action == 'change' && field_name == 'content' && $( '[name*="[reveal_location]"]' ).val() == 'shortcode'  )
        {
            $( '[name*="[content]"]' ).prop( 'checked', false );
            $( element ).prop( 'checked', true );
        }

        if ( field_name == 'reveal_location' && ( field_value == 'content_before' || field_value == 'content_after' ) )
        {
            $( '[name*="[reveal_priority]"]' ).closest( 'tr' ).show();
            $( '.reveal-location-auto' ).show();
            $( '.reveal-location-shortcode' ).hide();
        }
        else if ( field_name == 'reveal_location' && field_value == 'shortcode' )
        {
            $( '[name*="[reveal_priority]"]' ).closest( 'tr' ).hide();
            $( '.reveal-location-auto' ).hide();
            $( '.reveal-location-shortcode' ).show();
            $( '[name*="[content]"]:checked:not(:eq(0))' ).prop( 'checked', false ); // uncheck all checkboxes, but leave the first checked
        }

        if ( field_name == 'form_action' && field_value == 1 ) {
            $( '[name*="[form_action_number]"]' ).closest( 'tr' ).show();
        }
        else if ( field_name == 'form_action' && field_value == 0 ) {
            $( '[name*="[form_action_number]"]' ).closest( 'tr' ).hide();
        }

		if ( field_name == 'reveal_display' && field_value == 'button' )
		{
            $( '[name*="[reveal_style]"]' ).closest( 'tr' ).show();

            if ( $( '[name*="[reveal_style]"]' ).val() == 'theme;custom' || $( '[name*="[reveal_style]"]' ).val() == 'flat;custom' )
            {
                $( '[name*="[reveal_color_base]"]' ).closest( 'tr' ).show();
                $( '[name*="[reveal_color_hover]"]' ).closest( 'tr' ).show();
                $( '[name*="[reveal_color_text]"]' ).closest( 'tr' ).show();
            }
            else
            {
                $( '[name*="[reveal_color_base]"]' ).closest( 'tr' ).hide();
                $( '[name*="[reveal_color_hover]"]' ).closest( 'tr' ).hide();
                $( '[name*="[reveal_color_text]"]' ).closest( 'tr' ).hide();
            }
		}
        else if ( field_name == 'reveal_display' && field_value == 'link' )
        {
            $( '[name*="[reveal_style]"]' ).closest( 'tr' ).hide();
            $( '[name*="[reveal_color_base]"]' ).closest( 'tr' ).hide();
            $( '[name*="[reveal_color_hover]"]' ).closest( 'tr' ).hide();
            $( '[name*="[reveal_color_text]"]' ).closest( 'tr' ).hide();
        }


        if ( ( field_name == 'reveal_style' && ( field_value == 'theme;custom' || field_value == 'flat;custom' ) ) && $( '[name*="[reveal_display]"]:checked' ).val() == 'button' )
        {
			$( '[name*="[reveal_color_base]"]' ).closest( 'tr' ).show();
			$( '[name*="[reveal_color_hover]"]' ).closest( 'tr' ).show();
			$( '[name*="[reveal_color_text]"]' ).closest( 'tr' ).show();
        }
        else if ( ( field_name == 'reveal_style' && field_value != 'theme;custom' && field_value != 'flat;custom' ) || $( '[name*="[reveal_display]"]:checked' ).val() == 'link' )
        {
			$( '[name*="[reveal_color_base]"]' ).closest( 'tr' ).hide();
			$( '[name*="[reveal_color_hover]"]' ).closest( 'tr' ).hide();
			$( '[name*="[reveal_color_text]"]' ).closest( 'tr' ).hide();
		}


        if ( field_name == 'reason' && field_value == 'no_display' ) {
            $( '[name*="[reason_choose]"]' ).closest( 'tr' ).hide();
            $( '[name*="[reason_display]"]' ).closest( 'tr' ).hide();
        }
        else if ( field_name == 'reason' && field_value != 'no_display' ) {
            $( '[name*="[reason_choose]"]' ).closest( 'tr' ).show();
            $( '[name*="[reason_display]"]' ).closest( 'tr' ).show();
		}


        if ( field_name == 'submit_style' && ( field_value == 'theme;custom' || field_value == 'flat;custom' ) ) {

            $( '[name*="[submit_color_base]"]' ).closest( 'tr' ).show();
            $( '[name*="[submit_color_hover]"]' ).closest( 'tr' ).show();
            $( '[name*="[submit_color_text]"]' ).closest( 'tr' ).show();
        }
        else if ( field_name == 'submit_style' && field_value != 'theme;custom' && field_value != 'flat;custom' ) {
            $( '[name*="[submit_color_base]"]' ).closest( 'tr' ).hide();
            $( '[name*="[submit_color_hover]"]' ).closest( 'tr' ).hide();
            $( '[name*="[submit_color_text]"]' ).closest( 'tr' ).hide();
        }


        if ( field_name == 'email_enabled' && field_value == 1 )
        {
            $( '[name*="[email_to_blog_admin]"]' ).closest( 'tr' ).show();
            $( '[name*="[email_to_admins]"]' ).closest( 'tr' ).show();
            $( '[name*="[email_to_editors]"]' ).closest( 'tr' ).show();
            $( '[name*="[email_to_author]"]' ).closest( 'tr' ).show();
            $( '[name*="[email_to_custom_address]"]' ).closest( 'tr' ).show();
            $( '[name*="[email_subject]"]' ).closest( 'tr' ).show();
            $( '[name*="[email_message]"]' ).closest( 'tr' ).show();
            $( '[name*="[email_limit]"]' ).closest( 'tr' ).show();

            if ( $( '[name*="[email_limit]"]' ).is( ':checked' ) ) {
                $( '[name*="[email_limit_number]"]' ).closest( 'tr' ).show();
            }
            else {
                $( '[name*="[email_limit_number]"]' ).closest( 'tr' ).hide();
            }
        }
        else if ( field_name == 'email_enabled' && field_value == 0 )
        {
            $( '[name*="[email_to_blog_admin]"]' ).closest( 'tr' ).hide();
            $( '[name*="[email_to_admins]"]' ).closest( 'tr' ).hide();
            $( '[name*="[email_to_editors]"]' ).closest( 'tr' ).hide();
            $( '[name*="[email_to_author]"]' ).closest( 'tr' ).hide();
            $( '[name*="[email_to_custom_address]"]' ).closest( 'tr' ).hide();
            $( '[name*="[email_subject]"]' ).closest( 'tr' ).hide();
            $( '[name*="[email_message]"]' ).closest( 'tr' ).hide();
            $( '[name*="[email_limit]"]' ).closest( 'tr' ).hide();
            $( '[name*="[email_limit_number]"]' ).closest( 'tr' ).hide();
        }

        if ( field_name == 'email_limit' && field_value == 1 && $( '[name*="[email_enabled]"]' ).is( ':checked' ) ) {
            $( '[name*="[email_limit_number]"]' ).closest( 'tr' ).show();
        }
        else if ( field_name == 'email_limit' && field_value == 0 ) {
            $( '[name*="[email_limit_number]"]' ).closest( 'tr' ).hide();
        }

	}

	// Settings hide / show - On change
	$( '.flaggedc-admin-settings-main input[type="checkbox"], ' +
		'.flaggedc-admin-settings-main input[type="radio"], ' +
		'.flaggedc-admin-settings-main select' ).change( function () {
		check_fields( $( this ), 'change' );
 	});

	// Settings hide / show - Init
    $( '.flaggedc-admin-settings-main input[type="checkbox"], ' +
        '.flaggedc-admin-settings-main input[type="radio"], ' +
        '.flaggedc-admin-settings-main select' ).each( function () {
        check_fields( $( this ), 'init' );
    });


	/**
	 * Admin flags page
	 * */
    $( '.flaggedc-delete-all-pending-link' ).click( function() {
        var content_name = $( this ).attr( 'data-flaggedc-content-name' );
        return window.confirm( flaggedc_admin_object.delete_all_wording + content_name + "." );
    });
});