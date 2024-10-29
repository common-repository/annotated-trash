<?php

/*
Plugin Name: Annotated Trash
Plugin URI: http://annotatedtrash.info
Description: Why is that comment in the trash? If you co-blog, you've probably asked this. The Annotated Trash plugin helps answer that question.
Version: 0.1.1
Author: Melissa 'elky' Draper
Author URI: http://geekosophical.net
License: AGPL2
	
	Copyright (C) 2011 Melissa 'elky' Draper melissa-at-meldraweb-dot-com

	This program is free software: you can redistribute it and/or modify
	it under the terms of the GNU Affero General Public License as
	published by the Free Software Foundation, either version 3 of the
	License, or (at your option) any later version.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU Affero General Public License for more details.

	You should have received a copy of the GNU Affero General Public License
	along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

// Enable internationalisation of our plugin.
if ( ! load_plugin_textdomain( 'annotatedtrash', '/wp-content/languages/' ) )
	load_plugin_textdomain( 'annotatedtrash', '/wp-content/plugins/annotatedtrash/international/' );

// Add a column to the comments table
add_filter( 'manage_edit-comments_columns', 'annotatedtrash_comment_column', 1 );

// Put some content in our new comment column
add_action( 'manage_comments_custom_column', 'annotatedtrash_comment_column_show', 5, 2 );

// Do some processing when the status of the comment changes
add_action( 'transition_comment_status', 'annotatedtrash_transition_comment_status', 10, 3 );


/**
 * Add a column to the 'trashed' comments page for annotations
 *
 * Add a column to the 'trashed' comments page for managing annotations.
 *	 The name includes a save button which will appear in the header/footer of the table.
 *
 * @package Annotated Trash
 * @since 0.1
 *
 * @param	array	$defaults  The array of default table columns
 * @return   array			   The array of table columns with the new one included
 */
function annotatedtrash_comment_column( $defaults ) {
	// We only care about the ?comment_status=trash page for this.
	if ( $_GET[comment_status] == 'trash' ) {
		// And we shall call it Annotations, and care for it and love it as our very own.
		// We'll also tack on a save button so people can save what they write. Generous, aren't we.
		$defaults['annotate'] = __( 'Annotations', 'annotatedtrash' ) . '<input type="submit" name="save-annotations" value="' . __( 'Save', 'annotatedtrash' ) . '" class="annotate-button button-secondary" />';
	}
	return $defaults;
}

/**
 * Build the display for the annotate column
 *
 * Build the display for the annotate column. Information includes the person who trashed the comment,
 *	 the most recent annotator, and the annotation content. A textarea is provided to update the annotation.
 *
 * @package Annotated Trash
 * @since 0.1
 *
 * @param	string	  $column_name  The name of the column specified in annotatedtrash_comment_column()
 * @param	integer   $comment_id   The ID of the comment evaluated
 */
function annotatedtrash_comment_column_show( $column_name, $comment_id ) {
	if ( $column_name === 'annotate' ) {

		// Who are you? Who am I? Who are any of us, really?
		global $current_user;
		get_currentuserinfo();

		// Respect the time/date prefs in the system. We're nice like that.
		$date_format = get_option( 'date_format' );
		$time_format = get_option( 'time_format' );

		// Grab the meta data for this comment.
		$comment_meta = get_comment_meta( $comment_id, null, true );

		// Check we have permission to moderate comments,
		// and check that the save-annotations form button was clicked.
		if ( $current_user->allcaps['moderate_comments'] == 1 && isset( $_GET['save-annotations'] ) ) {
			// Does the comment already have annotation meta stored against it?
			if ( isset( $comment_meta['annotation_text'][0] ) ) {
				// Get rid of the old data
				delete_comment_meta( $comment_id, 'annotation_text' );
				delete_comment_meta( $comment_id, 'annotation_author' );
				delete_comment_meta( $comment_id, 'annotation_date' );
			}
			// And add the new data
			add_comment_meta( $comment_id, 'annotation_text', $_GET['annotation_text_' . $comment_id] );
			add_comment_meta( $comment_id, 'annotation_author', $current_user->ID );
			add_comment_meta( $comment_id, 'annotation_date', time() );
		}

		// Re-fetch the comment meta that we just saved so we can update the display.
		$comment_meta = get_comment_meta( $comment_id, null, true );

		// Who is responsible for trashing this comment?
		// If the trashing has occured since we installed Annotated Trash, then we have it recorded.
		if ( isset( $comment_meta['trasher'][0] ) ) {
			$trasher_info = get_userdata( $comment_meta['trasher'][0] );
			$trasher_link = get_author_posts_url( $trasher_info->ID,$trasher_info->user_login );
			$trasher = '<a href="' . $trasher_link . '">' . $trasher_info->user_login . '</a>';
		} else {
			// If it was trashed before we installed Annotated Trash, then it won't be stored.
			$trasher = 'unknown';
			// Unless we had akismet installed!
			$akismet_meta = array_pop( get_comment_meta( $comment_id, 'akismet_history' ) );
			if ( is_array( $akismet_meta ) ) {
				$akismet_trasher = get_userdatabylogin( $akismet_meta['user'] );
				$trasher_link = get_author_posts_url( $akismet_trasher->ID,$akismet_trasher->user_login );
				$trasher = '<a href="' . $trasher_link . '">' . $akismet_trasher->user_login . '</a>';
			}
		}

		// Time to dob in the trasher. This is the person who moved the comment here.
		echo __( 'Moved to trash by', 'annotatedtrash' ) . ' ' . $trasher . ' ' . __( 'on', 'annotatedtrash' ) . ' ' . date( $date_format . ' @ ' . $time_format,  $comment_meta['_wp_trash_meta_time'][0] ) . '<br/>';

		// We want to know who annotated this comment last, so grab this via the meta data info.
		$user_info = get_userdata( $comment_meta['annotation_author'][0]);

		// And make it a nice link to the annotator's user page.
		$annotator_link = get_author_posts_url( $user_info->ID,$user_info->user_login );
		$annotator = '<a href="' . $annotator_link . '">' . $user_info->user_login . '</a>';

		// If we already have an annotation for this comment, show it.
		if ( isset( $comment_meta['annotation_text'][0] ) ) {
			echo __( 'Annotated by', 'annotatedtrash' ) . ' ' . $annotator . ' ' . __( 'on', 'annotatedtrash' ) . ' ' . date( $date_format . ' @ ' . $time_format,  $comment_meta['annotation_date'][0]) . '<br/><br/>';
			echo __( 'Current annotation', 'annotatedtrash' ) . ':<br/>';
			echo '<div style="margin-left:1em;" class="commentannotation"> '. nl2br( $comment_meta['annotation_text'][0] ) . '</div>';
		}

		// And finally give people somewhere to add an annotation. Make it small because it's expandable if they want to write a novel.
		echo __( 'This is here because', 'annotatedtrash' ) . ':<br/><textarea rows="2" name="annotation_text_' . $comment_id . '">' . $comment_meta['annotation_text'][0] . '</textarea>';
	}
}

/**
 * Process the transition of comments going in and out of the trash.
 *
 * Process the transition of comments going in and out of the trash. If going out of the trash
 *	 delete all our comment meta. If going in to the trash, store the trashing user's id.
 *
 * @package Annotated Trash
 * @since 0.1
 *
 * @param	string	$new_status   The new status for the comment.
 * @param	string	$old_status   The old status for the comment.
 * @param	object	$comment	  The comment data object.
 */
function annotatedtrash_transition_comment_status( $new_status, $old_status, $comment ) {
	// Did we just move this out of the trash?
	if ( $old_status == 'trash' ) {
		// Start the debate over from the start! Whee!
		delete_comment_meta( $comment->comment_ID, 'annotation_text' );
		delete_comment_meta( $comment->comment_ID, 'annotation_author' );
		delete_comment_meta( $comment->comment_ID, 'annotation_date' );
		delete_comment_meta( $comment->comment_ID, 'trasher' );
	}

	// We just moved this to the trash, so lets put a name to it.
	if ( $new_status == 'trash' ) {
		// We need to know the current user again so we can dob them in.
		global $current_user;
		get_currentuserinfo();
		// And cram it in the comment meta db table
		add_comment_meta( $comment->comment_ID, 'trasher', $current_user->ID );
	}
}

?>
