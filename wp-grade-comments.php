<?php
/*
Plugin Name: WP Grade Comments
Version: 1.1.1
Description: Grades and private comments for WordPress blog posts. Built for the City Tech OpenLab.
Author: Boone Gorges
Author URI: http://boone.gorg.es
Plugin URI: http://openlab.citytech.cuny.edu
Text Domain: wp-grade-comments
*/

define( 'OLGC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'OLGC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

if ( is_admin() ) {
	require OLGC_PLUGIN_DIR . '/includes/admin.php';
}

/**
 * Load textdomain.
 *
 * @since 1.0.0
 */
function olgc_load_plugin_textdomain() {
	load_plugin_textdomain( 'wp-grade-comments' );
}
add_action( 'init', 'olgc_load_plugin_textdomain' );

/**
 * Markup for the checkboxes on the Leave a Comment section.
 *
 * @since 1.0.0
 */
function olgc_leave_comment_checkboxes() {
	if ( ! olgc_is_instructor() ) {
		return;
	}

	?>
	<div class="olgc-checkboxes">
		<label for="olgc-private-comment"><?php _e( 'Make this comment private.', 'wp-grade-comments' ) ?></label> <input type="checkbox" name="olgc-private-comment" id="olgc-private-comment" value="1" />
		<br />
		<label for="olgc-add-a-grade"><?php _e( 'Add a grade.', 'wp-grade-comments' ) ?></label> <input type="checkbox" name="olgc-add-a-grade" id="olgc-add-a-grade" value="1" />
		<br />
	</div>
	<?php
}
add_action( 'comment_form_logged_in_after', 'olgc_leave_comment_checkboxes' );

/**
 * Markup for the grade box on the Leave a Comment section.
 *
 * @since 1.0.0
 *
 * @param array $args Arguments from `comment_form()`.
 * @return array
 */
function olgc_leave_comment_after_comment_fields( $args ) {
	if ( ! olgc_is_instructor() ) {
		return $args;
	}

	$args['comment_notes_after'] .= '
	<div class="olgc-grade-entry">
		<label for="olgc-grade">' . __( 'Grade:', 'wp-grade-comments' ) . '</label> <input type="text" maxlength="5" name="olgc-grade" id="olgc-grade" />
	</div>

	<div class="olgc-privacy-description">
		' . __( 'NOTE: Private response and grade will only be visible to instructors and the post\'s author.', 'wp-grade-comments' ) . '
	</div>' . wp_nonce_field( 'olgc-grade-entry-' . get_the_ID(), '_olgc_nonce', false, false );

	return $args;
}
add_filter( 'comment_form_defaults', 'olgc_leave_comment_after_comment_fields', 1000 );

/**
 * Catch and save values after comment submit.
 *
 * @since 1.0.0
 *
 * @param int        $comment_id ID of the comment.
 * @param WP_Comment $comment    Comment object.
 */
function olgc_insert_comment( $comment_id, $comment ) {
	// Private
	$is_private = olgc_is_instructor() && ! empty( $_POST['olgc-private-comment'] );
	if ( ! $is_private && ! empty( $comment->comment_parent ) ) {
		$is_private = (bool) get_comment_meta( $comment->comment_parent, 'olgc_is_private', true );
	}

	if ( $is_private ) {
		update_comment_meta( $comment_id, 'olgc_is_private', '1' );
	}

	// Grade
	if ( olgc_is_instructor() && wp_verify_nonce( $_POST['_olgc_nonce'], 'olgc-grade-entry-' . $comment->comment_post_ID ) && ! empty( $_POST['olgc-add-a-grade'] ) && ! empty( $_POST['olgc-grade'] ) ) {
		$grade = wp_unslash( $_POST['olgc-grade'] );
		update_comment_meta( $comment_id, 'olgc_grade', $grade );
	}
}
add_action( 'wp_insert_comment', 'olgc_insert_comment', 10, 2 );

/**
 * Add 'Private' message, grade, and gloss to comment text.
 *
 * @since 1.0.0
 *
 * @param string     $text    Comment text.
 * @param WP_Comment $comment Comment object.
 * @return string
 */
function olgc_add_private_info_to_comment_text( $text, $comment ) {
	global $pagenow;

	// Grade has its own column on edit-comments.php.
	$grade = '';
	if ( 'edit-comments.php' !== $pagenow && ( olgc_is_instructor() || olgc_is_author() ) ) {
		$grade = get_comment_meta( $comment->comment_ID, 'olgc_grade', true );
		if ( $grade ) {
			$text .= '<div class="olgc-grade-display"><span class="olgc-grade-label">' . __( 'Grade (Private):', 'wp-grade-comments' ) . '</span> ' . esc_html( $grade ) . '</div>';
		}
	}

	$is_private = get_comment_meta( $comment->comment_ID, 'olgc_is_private', true );
	if ( $is_private ) {
		$text = '<strong class="olgc-private-notice">' . __( '(Private)', 'wp-grade-comments' ) . '</strong> ' . $text;
	}

	$gloss = '';
	if ( $grade && $is_private ) {
		$gloss = __( 'NOTE: Private response and grade are visible only to instructors and to the post\'s author.', 'wp-grade-comments' );
	} else if ( $is_private ) {
		$gloss = __( 'NOTE: Private response is visible only to instructors and to the post\'s author.', 'wp-grade-comments' );
	}

	if ( $gloss ) {
		$text .= '<p class="olgc-privacy-description">' . $gloss . '</p>';
	}

	return $text;
}
add_filter( 'get_comment_text', 'olgc_add_private_info_to_comment_text', 100, 2 ); // Late to avoid kses

/**
 * Add a "Private" label to the Reply button on reply comments.
 *
 * @since 1.0.0
 *
 * @param array      $args    Arguments passed to `comment_reply_link()`.
 * @param WP_Comment $comment Comment object.
 */
function olgc_add_private_label_to_comment_reply_link( $args, $comment ) {
	$is_private = get_comment_meta( $comment->comment_ID, 'olgc_is_private', true );
	if ( $is_private ) {
		$args['reply_text']    = '(Private) ' . $args['reply_text'];
		$args['reply_to_text'] =  '(Private) ' . $args['reply_to_text'];
	}

	return $args;
}
add_filter( 'comment_reply_link_args', 'olgc_add_private_label_to_comment_reply_link', 10, 2 );

/**
 * Ensure that private comments are only included for the proper users.
 *
 * @since 1.0.0
 *
 * @param array            $clauses       SQL clauses from the comment query.
 * @param WP_Comment_Query $comment_query Comment query object.
 * @return array
 */
function olgc_filter_private_comments( $clauses, $comment_query ) {
	$post_id = 0;
	if ( ! empty( $comment_query->query_vars['post_id'] ) ) {
		$post_id = $comment_query->query_vars['post_id'];
	} else if ( ! empty( $comment_query->query_vars['post_ID'] ) ) {
		$post_id = $comment_query->query_vars['post_ID'];
	}

	// Unfiltered
	if ( olgc_is_instructor() || olgc_is_author( $post_id ) ) {
		return $clauses;
	}

	$pc_ids = olgc_get_inaccessible_comments( get_current_user_id(), $post_id );

	// WP_Comment_Query sucks
	if ( ! empty( $pc_ids ) ) {
		$clauses['where'] .= ' AND comment_ID NOT IN (' . implode( ',', $pc_ids ) . ')';
	}

	return $clauses;
}
add_filter( 'comments_clauses', 'olgc_filter_private_comments', 10, 2 );

/**
 * Filter comments out of comment feeds.
 *
 * @since 1.0.2
 *
 * @param string $where WHERE clause from comment feed query.
 * @return string
 */
function olgc_filter_comments_from_feed( $where ) {
	$pc_ids = olgc_get_inaccessible_comments( get_current_user_id(), get_queried_object_id() );
	if ( $pc_ids ) {
		$where .= ' AND comment_ID NOT IN (' . implode( ',', array_map( 'intval', $pc_ids ) ) . ')';
	}

	return $where;
}
add_filter( 'comment_feed_where', 'olgc_filter_comments_from_feed' );

/**
 * Get inaccessible comments for a user.
 *
 * Optionally by post ID.
 *
 * @since 1.0.0
 *
 * @param int $user_id ID of the user.
 * @param int $post_id Optional. ID of the post.
 * @return array Array of comment IDs.
 */
function olgc_get_inaccessible_comments( $user_id, $post_id = 0 ) {
	// Get a list of private comments
	remove_filter( 'comments_clauses', 'olgc_filter_private_comments', 10, 2 );
	$comment_args = array(
		'meta_query' => array(
			array(
				'key'   => 'olgc_is_private',
				'value' => '1',
			),
		),
		'status' => 'any',
	);

	if ( ! empty( $post_id ) ) {
		$comment_args['post_id'] = $post_id;
	}

	$private_comments = get_comments( $comment_args );
	add_filter( 'comments_clauses', 'olgc_filter_private_comments', 10, 2 );

	// Filter out the ones that are written by the logged-in user, as well
	// as those that are attached to a post that the user is the author of
	$pc_ids = array();
	foreach ( $private_comments as $private_comment ) {
		if ( $user_id && ! empty( $private_comment->user_id ) && $user_id == $private_comment->user_id ) {
			continue;
		}

                if ( $user_id ) {
                        $comment_post = get_post( $private_comment->comment_post_ID );
                        if ( $user_id == $comment_post->post_author ) {
                                continue;
                        }
                }

		$pc_ids[] = $private_comment->comment_ID;

	}

	$pc_ids = wp_parse_id_list( $pc_ids );

	return $pc_ids;
}

/**
 * Filter comment count. Not cool.
 *
 * @since 1.0.0
 *
 * @param int $count   Comment counte.
 * @param int $post_id ID of the post.
 * @return int
 */
function olgc_get_comments_number( $count, $post_id = 0 ) {
	if ( empty( $post_id ) ) {
		return $count;
	}

	$cquery = new WP_Comment_Query();
	$comments_for_post = $cquery->query( array(
		'post_id' => $post_id,
		'count' => true,
	) );
	$count = $comments_for_post;

	return $count;
}
add_filter( 'get_comments_number', 'olgc_get_comments_number', 10, 2 );

/**
 * Enqueue assets.
 *
 * @since 1.0.0
 */
function olgc_enqueue_assets() {
	wp_enqueue_style( 'wp-grade-comments', OLGC_PLUGIN_URL . 'assets/css/wp-grade-comments.css' );
	wp_enqueue_script( 'wp-grade-comments', OLGC_PLUGIN_URL . 'assets/js/wp-grade-comments.js', array( 'jquery' ) );
}
add_action( 'comment_form_before', 'olgc_enqueue_assets' );

/**
 * Is the current user the course instructor?
 *
 * @since 1.0.0
 *
 * @return bool
 */
function olgc_is_instructor() {
	$is_admin = current_user_can( 'manage_options' );

	/**
	 * Filters whether the current user is an "instructor" for the purposes of grade comments.
	 *
	 * @param bool $is_admin By default, `current_user_can( 'manage_options' )`.
	 */
	return apply_filters( 'olgc_is_instructor', $is_admin );
}

/**
 * Is the current user the post author?
 *
 * @since 1.0.0
 *
 * @param int $post_id Optional. ID of the post. Defaults to current post ID.
 * @return bool
 */
function olgc_is_author( $post_id = null ) {
	if ( $post_id ) {
		$post = get_post( $post_id );
	} else {
		$post = get_queried_object();
	}

	if ( ! is_a( $post, 'WP_Post' ) ) {
		return false;
	}

	return is_user_logged_in() && get_current_user_id() == $post->post_author;
}

/**
 * Prevent non-instructors from editing comments that are private or have grades.
 *
 * @since 1.0.2
 */
function olgc_prevent_edit_comment_for_olgc_comments( $caps, $cap, $user_id, $args ) {
	if ( 'edit_comment' === $cap && ! olgc_is_instructor( $user_id ) ) {
		$comment_id = $args[0];
		$is_private = get_comment_meta( $comment_id, 'olgc_is_private', true );
		$grade      = get_comment_meta( $comment_id, 'olgc_grade', true );
		if ( $is_private || $grade ) {
			$caps = array( 'do_not_allow' );
		}
	}

	return $caps;

}
add_filter( 'map_meta_cap', 'olgc_prevent_edit_comment_for_olgc_comments', 10, 4 );

/**
 * Prevent private comments from appearing in BuddyPress activity streams.
 *
 * For now, we are going with the sledgehammer of deleting the comment altogether. In the
 * future, we could use hide_sitewide.
 *
 * @since 1.0.0
 *
 * @param int $comment_id ID of the comment.
 */
function olgc_prevent_private_comments_from_creating_bp_activity_items( $comment_id ) {
	$is_private = get_comment_meta( $comment_id, 'olgc_is_private', true );

	if ( ! $is_private ) {
		return;
	}

	if ( 'comment_post' === current_action() ) {
		remove_action( 'comment_post', 'bp_blogs_record_comment', 10, 2 );
		remove_action( 'comment_post', 'bp_activity_post_type_comment', 10, 2 );
	} else if ( 'edit_comment' === current_action() ) {
		remove_action( 'edit_comment', 'bp_blogs_record_comment', 10 );
		remove_action( 'edit_comment', 'bp_activity_post_type_comment', 10 );
	}
}
add_action( 'comment_post', 'olgc_prevent_private_comments_from_creating_bp_activity_items', 0 );
add_action( 'edit_comment', 'olgc_prevent_private_comments_from_creating_bp_activity_items', 0 );
