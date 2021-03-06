<?php
/**
 * Points-related Functions
 *
 * @package     GamiPress\Points_Functions
 * @since       1.0.0
 */
// Exit if accessed directly
if( !defined( 'ABSPATH' ) ) exit;

/**
 * Return an user's points
 *
 * @since 1.0.0
 *
 * @param  integer $user_id      The given user's ID
 * @return integer $user_points  The user's current points
 */
function gamipress_get_user_points( $user_id = 0, $points_type = '' ) {

	// Use current user's ID if none specified
	if ( ! $user_id )
		$user_id = wp_get_current_user()->ID;

    // Default points
    $user_meta = '_gamipress_points';

    if( ! empty( $points_type ) ) {
        $user_meta = "_gamipress_{$points_type}_points";
    }

	// Return our user's points as an integer (sanely falls back to 0 if empty)
	return absint( get_user_meta( $user_id, $user_meta, true ) );
}

/**
 * Award points to an user
 *
 * @since 1.3.6
 *
 * @param integer 			$user_id 		The given user's ID
 * @param integer 			$points 		The points the user is being awarded
 * @param string|WP_Post 	$points_type 	The points type
 * @param array 			$args			Array of extra arguments
 *
 * @return integer                 The user's updated points total
 */
function gamipress_award_points_to_user( $user_id = 0, $points = 0, $points_type = '', $args = array() ) {

	// If points are negative, turn them to positive
	if( $points < 0 ) {
		$points *= -1;
	}

	// Initialize args
	$args = wp_parse_args( $args, array(
		'admin_id' => 0,
		'achievement_id' => null,
		'reason' => '',
	) );

	// Use current user's ID if none specified
	if ( ! $user_id )
		$user_id = get_current_user_id();

	// If the points type is a WP_Post, then get the slug
	if( is_object( $points_type ) )
		$points_type = $points_type->post_name;

	// Available action for triggering other processes
	do_action( 'gamipress_award_points_to_user', $user_id, $points, $points_type, $args );

	return gamipress_update_user_points( $user_id, $points, $args['admin_id'], $args['achievement_id'], $points_type, $args['reason'] );

}

/**
 * Deduct points to an user
 *
 * @since 1.3.6
 *
 * @param integer 			$user_id 		The given user's ID
 * @param integer 			$points 		The points the user is being awarded
 * @param string|WP_Post 	$points_type 	The points type
 * @param array 			$args			Array of extra arguments
 *
 * @return integer                 The user's updated points total
 */
function gamipress_deduct_points_to_user( $user_id = 0, $points = 0, $points_type = '', $args = array() ) {

	// If points are positive, turn them to negative
	if( $points > 0 ) {
		$points *= -1;
	}

	// Initialize args
	$args = wp_parse_args( $args, array(
		'admin_id' => 0,
		'achievement_id' => null,
		'reason' => '',
	) );

	// Use current user's ID if none specified
	if ( ! $user_id )
		$user_id = get_current_user_id();

	// If the points type is a WP_Post, then get the slug
	if( is_object( $points_type ) )
		$points_type = $points_type->post_name;

	// Available action for triggering other processes
	do_action( 'gamipress_deduct_points_to_user', $user_id, $points, $points_type, $args );

	return gamipress_update_user_points( $user_id, $points, $args['admin_id'], $args['achievement_id'], $points_type, $args['reason'] );

}

/**
 * Posts a log entry when a user earns points
 *
 * @since  1.0.0
 * @updated 1.3.6 Added $reason parameter
 *
 * @param  integer 			$user_id        	The given user's ID
 * @param  integer 			$new_points     	The new points the user is being awarded/deducted
 * @param  integer 			$admin_id       	If being awarded by an admin, the admin's user ID
 * @param  integer 			$achievement_id 	The achievement that generated the points movement, if applicable
 * @param  string|WP_Post  	$points_type    	The points type
 * @param  string  			$reason    			Custom reason to override default log pattern
 *
 * @return integer                 				The user's updated points total
 */
function gamipress_update_user_points( $user_id = 0, $new_points = 0, $admin_id = 0, $achievement_id = null, $points_type = '', $reason = '' ) {

	// Use current user's ID if none specified
	if ( ! $user_id )
		$user_id = get_current_user_id();

	// If the points type is a WP_Post, then get the slug
	if( is_object( $points_type ) )
		$points_type = $points_type->post_name;

	// Grab the user's current points
	$current_points = gamipress_get_user_points( $user_id, $points_type );

	// If we're getting an admin ID, $new_points is actually the final total, so subtract the current points
	if ( $admin_id ) {
		$new_points = $new_points - $current_points;
	}

    // Default points
    $user_meta = '_gamipress_points';

    if( ! empty( $points_type ) ) {
        $user_meta = "_gamipress_{$points_type}_points";
    }

	// Update our user's total
	$total_points = max( $current_points + $new_points, 0 );
	update_user_meta( $user_id, $user_meta, $total_points );

	// Available action for triggering other processes
	do_action( 'gamipress_update_user_points', $user_id, $new_points, $total_points, $admin_id, $achievement_id, $points_type, $reason );

	// Maybe award some points-based achievements
	foreach ( gamipress_get_points_based_achievements() as $achievement ) {
		gamipress_maybe_award_achievement_to_user( $achievement->ID, $user_id );
	}

	return $total_points;
}

/**
 * Log a user's updated points
 *
 * @since 	1.0.0
 * @updated 1.3.6 Support for deduct/revoke points
 *
 * @param integer $user_id        The user ID
 * @param integer $new_points     Points added/deducted to the user's total
 * @param integer $total_points   The user's updated total points
 * @param integer $admin_id       An admin ID (if admin-awarded)
 * @param integer $achievement_id The associated achievement ID
 * @param string  $points_type    The points type
 * @param string  $reason         Custom reason to override default log pattern
 */
function gamipress_log_user_points( $user_id, $new_points, $total_points, $admin_id, $achievement_id, $points_type = '', $reason = '' ) {

    $log_meta = array(
        'achievement_id' => $achievement_id,
        'points' => number_format( $new_points ),
        'points_type' => $points_type,
        'total_points' => number_format( $total_points ),
    );

    $access = 'public';

	// Alter our log pattern if this was an admin action
	if ( $admin_id ) {

		$access = 'private';
		$log_meta['admin_id'] = $admin_id;

		if( $new_points > 0 ) {
			// Points awarded
			$type = 'points_award';
			$log_meta['pattern'] = gamipress_get_option( 'points_awarded_log_pattern', __( '{admin} awarded {user} {points} {points_type} for a new total of {total_points} {points_type}', 'gamipress' ) );
		} else {
			// Points revoked
			$type = 'points_revoke';
			$log_meta['pattern'] = gamipress_get_option( 'points_revoked_log_pattern', __( '{admin} revoked {user} {points} {points_type} for a new total of {total_points} {points_type}', 'gamipress' ) );
		}

    } else {

		if( $new_points > 0 ) {
			// Points earned
			$type = 'points_earn';
			$log_meta['pattern'] = gamipress_get_option( 'points_earned_log_pattern', __( '{user} earned {points} {points_type} for a new total of {total_points} {points_type}', 'gamipress' ) );
		} else {
			// Points deducted
			$type = 'points_deduct';
			$log_meta['pattern'] = gamipress_get_option( 'points_deducted_log_pattern', __( '{user} deducted {points} {points_type} for a new total of {total_points} {points_type}', 'gamipress' ) );
		}

    }

	if( ! empty( $reason ) ) {
		$log_meta['pattern'] = $reason;
	}

	// Create the log entry
	gamipress_insert_log( $type, $user_id, $access, $log_meta );

}
add_action( 'gamipress_update_user_points', 'gamipress_log_user_points', 10, 6 );

/**
 * Get GamiPress Points Types
 *
 * Returns a multidimensional array of slug, single name and plural name for all points types.
 *
 * @since  1.0.0
 *
 * @return array An array of our registered points types
 */
function gamipress_get_points_types() {
    return GamiPress()->points_types;
}

/**
 * Get GamiPress Points Type Slugs
 *
 * @since  1.0.0
 * @return array An array of all our registered points type slugs (empty array if none)
 */
function gamipress_get_points_types_slugs() {
    // Assume we have no registered points types
    $points_type_slugs = array();

    // If we do have any points types, loop through each and add their slug to our array
    foreach ( GamiPress()->points_types as $slug => $data ) {
        $points_type_slugs[] = $slug;
    }

    // Finally, return our data
    return $points_type_slugs;
}

/**
 * Get Points Type Points Awards
 *
 * @since  1.0.0
 *
 * @param  integer|string $points_type The points type's post ID or the points type slug
 * @return array|bool                  Array of WP_Post of the points awards, or false if none
 */
function gamipress_get_points_type_points_awards( $points_type = 0 ) {

	// Try to find the points type by slug
	if( ! is_numeric( $points_type ) && ! empty( $points_type ) ) {
		$points_types = gamipress_get_points_types();

		if( isset( $points_types[$points_type] ) ) {
			$points_type = $points_types[$points_type]['ID'];
		}
	}

	// Grab the current post ID if no points_type_id was specified
	if ( ! $points_type ) {
		global $post;
		$points_type = $post->ID;
	}

	$points_awards = get_posts( array(
		'post_type'           => 'points-award',
		'posts_per_page'      => -1,
		'suppress_filters'    => false,
		'connected_direction' => 'to',
		'connected_type'      => 'points-award-to-points-type',
		'connected_items'     => $points_type,
	) );

	// If it has a points type, return it, otherwise return false
	if ( ! empty( $points_awards ) )
		return $points_awards;
	else
		return false;

}

/**
 * Get Points Award Points Type
 *
 * @since  1.0.0
 *
 * @param  integer     $points_award_id The given points award's post ID
 * @return object|bool                 The post object of the points type, or false if none
 */
function gamipress_get_points_award_points_type( $points_award_id = 0 ) {
	// Grab the current post ID if no points_award_id was specified
	if ( ! $points_award_id ) {
		global $post;
		$points_award_id = $post->ID;
	}

    $points_type = get_posts( array(
        'post_type'           => 'points-type',
        'posts_per_page'      => 1,
        'connected_direction' => 'from',
        'connected_type'      => 'points-award-to-points-type',
        'connected_items'     => $points_award_id,
    ) );

    // If it has a points type, return it, otherwise return false
    if ( ! empty( $points_type ) )
        return $points_type[0];
    else
        return false;
}

/**
 * Update all dependent data if points type name has changed.
 *
 * @since  1.0.0
 *
 * @param  array $data      Post data.
 * @param  array $post_args Post args.
 * @return array            Updated post data.
 */
function gamipress_maybe_update_points_type( $data = array(), $post_args = array() ) {

	// Bail if not is a points type
	if( $post_args['post_type'] !== 'points-type') {
		return $data;
	}

	// If user set an empty slug, then generate it
	if( empty( $post_args['post_name'] ) ) {
		$post_args['post_name'] = wp_unique_post_slug(
			sanitize_title( $post_args['post_title'] ),
			$post_args['ID'],
			$post_args['post_status'],
			$post_args['post_type'],
			$post_args['post_parent']
		);
	}

	if ( gamipress_points_type_changed( $post_args ) ) {

		$original_type = get_post( $post_args['ID'] )->post_name;
		$new_type = $post_args['post_name'];

		$data['post_name'] = gamipress_update_points_types( $original_type, $new_type );

		add_filter( 'redirect_post_location', 'gamipress_points_type_rename_redirect', 99 );

	}

	return $data;
}
add_filter( 'wp_insert_post_data' , 'gamipress_maybe_update_points_type' , 99, 2 );

/**
 * Check if a points type name has changed.
 *
 * @since  1.0.0
 *
 * @param  array $post_args Post args.
 * @return bool             True if name has changed, otherwise false.
 */
function gamipress_points_type_changed( $post_args = array() ) {
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return false;
	}

	$original_post = ( !empty( $post_args['ID'] ) && isset( $post_args['ID'] ) ) ? get_post( $post_args['ID'] ) : null;
	$status = false;
	if ( is_object( $original_post ) ) {
		if (
			'points-type' === $post_args['post_type']
			&& $original_post->post_status !== 'auto-draft'
			&& ! empty( $original_post->post_name )
			&& $original_post->post_name !== $post_args['post_name']
		) {
			$status = true;
		}
	}

	return $status;
}

/**
 * Replace all instances of one points type with another.
 *
 * @since  1.0.0
 *
 * @param  string $original_type Original points type.
 * @param  string $new_type      New points type.
 * @return string                New points type.
 */
function gamipress_update_points_types( $original_type = '', $new_type = '' ) {

	// Sanity check to prevent alterating core posts
	if ( empty( $original_type ) || in_array( $original_type, array( 'post', 'page', 'attachment', 'revision', 'nav_menu_item' ) ) ) {
		return $new_type;
	}

	gamipress_update_points_awards_points_type( $original_type, $new_type );
	gamipress_update_user_meta_points_types( $original_type, $new_type );
	gamipress_flush_rewrite_rules();

	return $new_type;
}

/**
 * Replace all user metas with old points type with the new one.
 *
 * @since  1.0.0
 *
 * @param  string 	$original_type Original points type.
 * @param  string 	$new_type      New points type.
 * @return integer                 User metas updated count.
 */
function gamipress_update_user_meta_points_types( $original_type = '', $new_type = '' ) {

	global $wpdb;

	return $wpdb->get_results( $wpdb->prepare(
		"
		UPDATE $wpdb->usermeta
		SET meta_key = %s
		WHERE meta_key = %s
		",
		"_gamipress_{$new_type}_points",
		"_gamipress_{$original_type}_points"
	) );

}

/**
 * Replace all posts metas with old points type with the new one.
 *
 * @since  1.2.7
 *
 * @param  string 	$original_type Original points type.
 * @param  string 	$new_type      New points type.
 * @return integer                 Post metas updated count.
 */
function gamipress_update_points_awards_points_type( $original_type = '', $new_type = '' ) {

	global $wpdb;

	return $wpdb->get_results( $wpdb->prepare(
		"
		UPDATE $wpdb->postmeta
		SET meta_value = %s
		WHERE meta_key = %s
		AND meta_value = %s
		",
		$new_type,
		"_gamipress_points_type",
		"$original_type"
	) );

}


/**
 * Redirect to include custom rename message.
 *
 * @since  1.0.0
 *
 * @param  string $location Original URI.
 * @return string           Updated URI.
 */
function gamipress_points_type_rename_redirect( $location = '' ) {

	remove_filter( 'redirect_post_location', __FUNCTION__, 99 );

	return add_query_arg( 'message', 99, $location );

}

/**
 * Filter the "post updated" messages to include support for points types.
 *
 * @since 1.0.0
 *
 * @param array $messages Array of messages to display.
 *
 * @return array $messages Compiled list of messages.
 */
function gamipress_points_type_update_messages( $messages ) {

	$messages['points-type'] = array_fill( 1, 10, __( 'Points Type saved successfully.', 'gamipress' ) );
	$messages['points-type']['99'] = sprintf( __('Points Type renamed successfully. <p>All user points of this type have been updated <strong>automatically</strong>.</p> All shortcodes, %s, and URIs that reference the old points type slug must be updated <strong>manually</strong>.', 'gamipress'), '<a href="' . esc_url( admin_url( 'widgets.php' ) ) . '">' . __( 'widgets', 'gamipress' ) . '</a>' );

	return $messages;

}
add_filter( 'post_updated_messages', 'gamipress_points_type_update_messages' );
