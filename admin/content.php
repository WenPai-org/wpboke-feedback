<?php
if ( ! defined( 'WPINC' ) ) { die; }

/**
 * Structures content
 */
class Flagged_Content_Pro_Content
{
    public $id;
    public $name;
    public $type;
    public $status;
    public $label_singular;
    public $label_plural;
    public $title;
    public $title_link;
    public $title_icon;
    public $view_url;
    public $edit_url;
    public $actions = array();
    public $post_type_exists;
    public $post_type_modified;
    public $title_render;

    private $trash_url_post    = 'edit.php?post_status=trash&post_type=';
    private $trash_url_comment = 'edit-comments.php?comment_status=trash';
    private $edit_url_comment  = 'comment.php?action=editcomment&c=';

    public function __construct( $args = array() )
    {
        $default = array(
            'content_id'   => null,
            'content_name' => null,
            'content_type' => null,
        );

        if ( is_array( $args ) and ! empty( $args ) ) {
            $content = array_merge( $default, $args );
        }
        else {
            $content = $default;
        }

        $this->id   = (int) $content['content_id'];
        $this->name = $content['content_name'];
        $this->type = $content['content_type'];

        // populate content props
        // invalid content
        if ( $this->id === 0 || $this->id === null || $this->name === null || $this->type === null ) {
            $this->load_invalid();
        }
        // comment
        elseif ( $this->type == 'comment' ) {
            $this->load_comment();
        }
        // post
        elseif ( $this->type == 'post' ) {
            $this->load_post();
        }
        // else invalid
        else {
            $this->load_invalid();
        }

        // populate title_render
        if ( $this->status == 'invalid' || $this->status == 'deleted' ) {
            $this->title_render = "{$this->title_icon} {$this->label_singular}";
        }
        elseif ( ( $this->type == 'post' && $this->status == 'publish' ) || ( $this->type == 'comment' && $this->status == 'approved' ) ) {
            //$this->title_render = "{$this->title_icon}{$this->label_singular}<br>{$this->title_link}";
            $this->title_render = "{$this->title_link}<br><span class='flaggedc-sub-text-color'>{$this->title_icon}{$this->label_singular}</span>";
        }
        // include status in title_render if status is not published/approved
        else {
            //$this->title_render = "{$this->title_icon}{$this->label_singular}<span class='flaggedc-sub-text-color'> - $this->status</span><br>{$this->title_link}";
            $this->title_render = "{$this->title_link}<br><span class='flaggedc-sub-text-color'>{$this->title_icon}{$this->label_singular} — " . ucfirst( $this->status ) . "</span>";
        }
	}

	private function load_invalid()
    {
        $invalid_wording         = __( 'Invalid', 'flagged-content-pro' );
        $invalid_icon_title      = __( 'Invalid content', 'flagged-content-pro' );
        $this->status            = 'invalid';
        $this->name              = 'invalid';
        $this->label_singular    = $invalid_wording;
        $this->title             = $invalid_wording;
        $this->title_link        = $invalid_wording;
        $this->title_icon        = "<span class='dashicons dashicons-dismiss' title='{$invalid_icon_title}'></span>";
    }

    private function load_deleted()
    {
        $deleted_wording      = __( 'Deleted', 'flagged-content-pro' );
        $deleted_icon_title   = __( 'This has been permanently deleted', 'flagged-content-pro' );
        $this->status         = 'deleted';
        $this->name           = 'deleted';
        $this->label_singular = $deleted_wording;
        $this->title          = $deleted_wording;
        $this->title_link     = $deleted_wording;
        $this->title_icon     = "<span class='dashicons dashicons-dismiss' title='{$deleted_icon_title}'></span>";
    }

    private function load_comment()
    {
        $this->status = wp_get_comment_status( $this->id );

        // deleted content
        if ( $this->status === FALSE )
        {
            $this->load_deleted();
        }

        // comment - in trash
        elseif ( $this->status === 'trash' )
        {
            $comment_icon_title       = __( 'This is in the trash', 'flagged-content-pro' );
            $this->name               = 'comment';
            $this->label_singular     = __( 'Comment', 'flagged-content-pro' );
            $this->label_plural       = __( 'Comments', 'flagged-content-pro' );
            $this->title              = get_comment_excerpt( $this->id );
            $this->view_url           = $this->trash_url_comment;
            $this->title_link         = sprintf( '<a href="%s" target="_blank">%s</a>', $this->view_url, $this->title );
            $this->title_icon         = "<span class='dashicons dashicons-trash' title='{$comment_icon_title}'></span>";
            $this->actions['view']    = sprintf( '<a href="%s" target="_blank">View trashed %ss</a>', $this->view_url, $this->name );
            $this->post_type_exists   = true;
            $this->post_type_modified = false;
        }

        // comment - active
        else
        {
            $this->name               = 'comment';
            $this->label_singular     = __( 'Comment', 'flagged-content-pro' );
            $this->label_plural       = __( 'Comments', 'flagged-content-pro' );
            $this->title              = get_comment_excerpt( $this->id );
            $this->view_url           = get_comment_link( $this->id );
            $this->edit_url           = $this->edit_url_comment . $this->id;
            $this->title_link         = sprintf( '<a href="%s" target="_blank">%s</a>', $this->edit_url, $this->title );
            $this->title_icon         = '';
            $this->actions['view']    = "<a href='{$this->view_url}' target='_blank'>" . __( 'View', 'flagged-content-pro' ) . '</a>';
            $this->actions['edit']    = "<a href='{$this->edit_url}' target='_blank'>" . __( 'Edit', 'flagged-content-pro' ) . '</a>';
            $this->post_type_exists   = true;
            $this->post_type_modified = false;
        }
    }

    private function load_post()
    {
        $this->status = get_post_status( $this->id );

        // post - in trash
        if ( $this->status === 'trash' )
        {
            $post_trash_wording       = __( 'This is in the trash', 'flagged-content-pro' );
            $this->name               = get_post_type( $this->id );
            $this->label_singular     = get_post_type_object( $this->name )->labels->singular_name;
            $this->label_plural       = get_post_type_object( $this->name )->labels->name;
            $this->title              = get_the_title( $this->id );
            $this->view_url           = $this->trash_url_post . $this->name;
            $this->title_link         = sprintf( '<a href="%s" target="_blank">%s</a>', $this->view_url, $this->title );
            $this->title_icon         = "<span class='dashicons dashicons-trash' title='{$post_trash_wording}'></span>";
            $this->actions['view']    = sprintf( '<a href="%1$s" target="_blank">View trashed %2$s</a>', $this->view_url, $this->label_plural );
            $this->post_type_exists   = true;
            $this->post_type_modified = false;
        }

        // post - active, but post type no longer exists (uses name to check)
        // post type exists for the content (e.g. name = movie and movie post type exists)
        elseif ( ! post_type_exists( $this->name ) )
        {
            $post_type_icon_title     = __( 'This post type no longer exists', 'flagged-content-pro' );
            $this->name               = get_post_type( $this->id );
            $this->label_singular     = ucfirst( $this->name );
            $this->label_plural       = '';
            $this->title              = get_the_title( $this->id );
            $this->view_url           = '';
            $this->edit_url           = '';
            $this->title_link         = $this->title;
            $this->title_icon         = "<span class='dashicons dashicons-warning flaggedc-admin-icon-warning' title='{$post_type_icon_title}'></span>";
            //$this->actions['view']    = '';
            //$this->actions['edit']    = '';
            $this->post_type_exists   = true;
            $this->post_type_modified = false;
        }

        // post - active, but content name does not match current name of post
        // post type has been modified since this content was flagged
        elseif ( get_post_type( $this->id ) != $this->name )
        {
            $post_type_icon_title     = __( 'The post type has been modified. This was flagged as a ', 'flagged-content-pro' );
            $this->name               = get_post_type( $this->id );
            $this->label_singular     = get_post_type_object( $this->name )->labels->singular_name;
            $this->label_plural       = get_post_type_object( $this->name )->labels->name;
            $this->title              = get_the_title( $this->id );
            $this->view_url           = get_post_permalink( $this->id );
            $this->edit_url           = get_edit_post_link( $this->id );
            $this->title_link         = sprintf( '<a href="%s" target="_blank">%s</a>', $this->edit_url, $this->title );
            $this->title_icon         = "<span class='dashicons dashicons-warning flaggedc-admin-icon-warning' title='{$post_type_icon_title} {$this->name}'></span>";
            $this->actions['view']    = "<a href='{$this->view_url}' target='_blank'>" . __( 'View', 'flagged-content-pro' ) . '</a>';
            $this->actions['edit']    = "<a href='{$this->edit_url}' target='_blank'>" . __( 'Edit', 'flagged-content-pro' ) . '</a>';
            $this->post_type_exists   = true;
            $this->post_type_modified = true;
        }

        // post - active
        else
        {
            $this->name               = get_post_type( $this->id );
            $this->label_singular     = get_post_type_object( $this->name )->labels->singular_name;
            $this->label_plural       = get_post_type_object( $this->name )->labels->name;
            $this->title              = get_the_title( $this->id );
            $this->view_url           = get_post_permalink( $this->id );
            $this->edit_url           = get_edit_post_link( $this->id );
            $this->title_link         = sprintf( '<a href="%s" target="_blank">%s</a>', $this->edit_url, $this->title );
            $this->title_icon         = '';
            $this->actions['view']    = "<a href='{$this->view_url}' target='_blank'>" . __( 'View', 'flagged-content-pro' ) . '</a>';
            $this->actions['edit']    = "<a href='{$this->edit_url}' target='_blank'>" . __( 'Edit', 'flagged-content-pro' ) . '</a>';
            $this->post_type_exists   = true;
            $this->post_type_modified = false;
        }
    }
}