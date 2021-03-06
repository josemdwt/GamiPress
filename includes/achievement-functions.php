<?php
/**
 * Achievement Functions
 *
 * @package     GamiPress\Achievement_Functions
 * @since       1.0.0
 */
// Exit if accessed directly
if( !defined( 'ABSPATH' ) ) exit;

/**
 * Check if post is a registered GamiPress achievement.
 *
 * @since  1.0.0
 *
 * @param  object|int $post Post object or ID.
 * @return bool             True if post is an achievement, otherwise false.
 */
function gamipress_is_achievement( $post = null ) {

	// Assume we are working with an achievement object
	$return = true;

	// If post type is NOT a registered achievement type, it cannot be an achievement
	if ( ! in_array( get_post_type( $post ), gamipress_get_achievement_types_slugs() ) ) {
		$return = false;
	}

	// If we pass both previous tests, this is a valid achievement (with filter to override)
	return apply_filters( 'gamipress_is_achievement', $return, $post );
}

/**
 * Get GamiPress Achievement Types
 *
 * Returns a multidimensional array of slug, single name and plural name for all achievement types.
 *
 * @since  1.0.0
 * @return array An array of our registered achievement types
 */
function gamipress_get_achievement_types() {
	return GamiPress()->achievement_types;
}

/**
 * Get GamiPress Achievement Type Slugs
 *
 * @since  1.0.0
 * @return array An array of all our registered achievement type slugs (empty array if none)
 */
function gamipress_get_achievement_types_slugs() {

	// Assume we have no registered achievement types
	$achievement_type_slugs = array();

	// If we do have any achievement types, loop through each and add their slug to our array
	foreach ( GamiPress()->achievement_types as $slug => $data ) {
		$achievement_type_slugs[] = $slug;
	}

	// Finally, return our data
	return $achievement_type_slugs;

}

/**
 * Get an array of achievements
 *
 * @since  1.0.0
 * @param  array $args An array of our relevant arguments
 * @return array       An array of the queried achievements
 */
function gamipress_get_achievements( $args = array() ) {

	// Setup our defaults
	$defaults = array(
		'post_type'                => gamipress_get_achievement_types_slugs(),
		'suppress_filters'         => false,
		'achievement_relationship' => 'any',
	);

	$args = wp_parse_args( $args, $defaults );

	// Hook join functions for joining to P2P table to retrieve the parent of an achievement
	if ( isset( $args['parent_of'] ) ) {
		add_filter( 'posts_join', 'gamipress_get_achievements_parents_join' );
		add_filter( 'posts_where', 'gamipress_get_achievements_parents_where', 10, 2 );
	}

	// Hook join functions for joining to P2P table to retrieve the children of an achievement
	if ( isset( $args['children_of'] ) ) {
		add_filter( 'posts_join', 'gamipress_get_achievements_children_join', 10, 2 );
		add_filter( 'posts_where', 'gamipress_get_achievements_children_where', 10, 2 );
		add_filter( 'posts_orderby', 'gamipress_get_achievements_children_orderby' );
	}

	// Get our achievement posts
	$achievements = get_posts( $args );

	// Remove all our filters
	remove_filter( 'posts_join', 'gamipress_get_achievements_parents_join' );
	remove_filter( 'posts_where', 'gamipress_get_achievements_parents_where' );
	remove_filter( 'posts_join', 'gamipress_get_achievements_children_join' );
	remove_filter( 'posts_where', 'gamipress_get_achievements_children_where' );
	remove_filter( 'posts_orderby', 'gamipress_get_achievements_children_orderby' );

	return $achievements;
}

/**
 * Modify the WP_Query Join filter for achievement children
 *
 * @since  1.0.0
 * @param  string $join         The query "join" string
 * @param  object $query_object The complete query object
 * @return string 				The updated "join" string
 */
function gamipress_get_achievements_children_join( $join = '', $query_object = null ) {
	global $wpdb;
	$join .= " LEFT JOIN $wpdb->p2p AS p2p ON p2p.p2p_from = $wpdb->posts.ID";
	if ( isset( $query_object->query_vars['achievement_relationship'] ) && $query_object->query_vars['achievement_relationship'] != 'any' )
		$join .= " LEFT JOIN $wpdb->p2pmeta AS p2pm1 ON p2pm1.p2p_id = p2p.p2p_id";
	$join .= " LEFT JOIN $wpdb->p2pmeta AS p2pm2 ON p2pm2.p2p_id = p2p.p2p_id";
	return $join;
}

/**
 * Modify the WP_Query Where filter for achievement children
 *
 * @since  1.0.0
 * @param  string $where        The query "where" string
 * @param  object $query_object The complete query object
 * @return string 				The updated query "where" string
 */
function gamipress_get_achievements_children_where( $where = '', $query_object ) {
	global $wpdb;
	if ( isset( $query_object->query_vars['achievement_relationship'] ) && $query_object->query_vars['achievement_relationship'] == 'required' )
		$where .= " AND p2pm1.meta_key ='Required'";

	if ( isset( $query_object->query_vars['achievement_relationship'] ) && $query_object->query_vars['achievement_relationship'] == 'optional' )
		$where .= " AND p2pm1.meta_key ='Optional'";
	// ^^ TODO, add required and optional. right now just returns all achievements.
	$where .= " AND p2pm2.meta_key ='order'";
	$where .= $wpdb->prepare( ' AND p2p.p2p_to = %d', $query_object->query_vars['children_of'] );
	return $where;
}

/**
 * Modify the WP_Query OrderBy filter for achievement children
 *
 * @since  1.0.0
 * @param  string $orderby The query "orderby" string
 * @return string 		   The updated "orderby" string
 */
function gamipress_get_achievements_children_orderby( $orderby = '' ) {
	return $orderby = 'p2pm2.meta_value ASC';
}

/**
 * Modify the WP_Query Join filter for achievement parents
 *
 * @since  1.0.0
 * @param  string $join The query "join" string
 * @return string 	    The updated "join" string
 */
function gamipress_get_achievements_parents_join( $join = '' ) {
	global $wpdb;
	$join .= " LEFT JOIN $wpdb->p2p AS p2p ON p2p.p2p_to = $wpdb->posts.ID";
	return $join;
}

/**
 * Modify the WP_Query Where filter for achievement parents
 *
 * @since  1.0.0
 * @param  string $where The query "where" string
 * @param  object $query_object The complete query object
 * @return string        appended sql where statement
 */
function gamipress_get_achievements_parents_where( $where = '', $query_object = null ) {
	global $wpdb;
	$where .= $wpdb->prepare( ' AND p2p.p2p_from = %d', $query_object->query_vars['parent_of'] );
	return $where;
}

/**
 * Get an achievement's parent posts
 *
 * @since  1.0.0
 * @param  integer     $achievement_id The given achievment's post ID
 * @return object|bool                 The post object of the achievement's parent, or false if none
 */
function gamipress_get_parent_of_achievement( $achievement_id = 0 ) {

	// Grab the current post ID if no achievement_id was specified
	if ( ! $achievement_id ) {
		global $post;
		$achievement_id = $post->ID;
	}

	// Grab our achievement's parent
	$parents = gamipress_get_achievements( array( 'parent_of' => $achievement_id ) );

	// If it has a parent, return it, otherwise return false
	if ( ! empty( $parents ) )
		return $parents[0];
	else
		return false;
}

/**
 * Get an achievement's children posts
 *
 * @since  1.0.0
 * @param  integer $achievement_id The given achievment's post ID
 * @return array                   An array of our achievment's children (empty if none)
 */
function gamipress_get_children_of_achievement( $achievement_id = 0 ) {

	// Grab the current post ID if no achievement_id was specified
	if ( ! $achievement_id ) {
		global $post;
		$achievement_id = $post->ID;
	}

	// Grab and return our achievement's children
	return gamipress_get_achievements( array( 'children_of' => $achievement_id, 'achievement_relationship' => 'required' ) );
}

/**
 * Check if the achievement's child achievements must be earned sequentially
 *
 * @since  1.0.0
 * @param  integer $achievement_id The given achievment's post ID
 * @return bool                    True if steps are sequential, false otherwise
 */
function gamipress_is_achievement_sequential( $achievement_id = 0 ) {

	// Grab the current post ID if no achievement_id was specified
	if ( ! $achievement_id ) {
		global $post;
		$achievement_id = $post->ID;
	}

	// If our achievement requires sequential steps, return true, otherwise false
	if ( get_post_meta( $achievement_id, '_gamipress_sequential', true ) )
		return true;
	else
		return false;
}

/**
 * Check if user has already earned an achievement the maximum number of times
 *
 * @since  1.0.0
 * @param  integer $user_id        The given user's ID
 * @param  integer $achievement_id The given achievement's post ID
 * @return bool                    True if we've exceed the max possible earnings, false if we're still eligable
 */
function gamipress_achievement_user_exceeded_max_earnings( $user_id = 0, $achievement_id = 0 ) {

	$max_earnings = get_post_meta( $achievement_id, '_gamipress_maximum_earnings', true);

	// Infinite maximum earnings check
    if( $max_earnings === '-1' || empty( $max_earnings ) ) {
		return false;
	}

	// If the achievement has an earning limit, and we've earned it before...
	if ( $max_earnings && $user_has_achievement = gamipress_get_user_achievements( array( 'user_id' => absint( $user_id ), 'achievement_id' => absint( $achievement_id ) ) ) ) {
		// If we've earned it as many (or more) times than allowed,
		// then we have exceeded maximum earnings, thus true
		if ( count( $user_has_achievement ) >= $max_earnings ) {
			return true;
		}
	}

	// The post has no limit, or we're under it
	return false;
}

/**
 * Helper function for building an object for our achievement
 *
 * @since  1.0.0
 * @param  integer $achievement_id The given achievement's post ID
 * @param  string  $context        The context in which we're creating this object
 * @return object                  Our object containing only the relevant bits of information we want
 */
function gamipress_build_achievement_object( $achievement_id = 0, $context = 'earned' ) {

	// Grab the new achievement's $post data, and bail if it doesn't exist
	$achievement = get_post( $achievement_id );
	if ( is_null( $achievement ) )
		return false;

	// Setup a new object for the achievement
	$achievement_object                 = new stdClass;
	$achievement_object->ID             = $achievement_id;
	$achievement_object->post_type      = $achievement->post_type;
	$achievement_object->points         = absint( get_post_meta( $achievement_id, '_gamipress_points', true ) );
	$achievement_object->points_type    = get_post_meta( $achievement_id, '_gamipress_points_type', true );

	// Store the current timestamp differently based on context
	if ( 'earned' == $context ) {
		$achievement_object->date_earned = time();
	} elseif ( 'started' == $context ) {
		$achievement_object->date_started = $achievement_object->last_activity_date = time();
	}

	// Return our achievement object, available filter so we can extend it elsewhere
	return apply_filters( 'achievement_object', $achievement_object, $achievement_id, $context );

}

/**
 * Get an array of post IDs for achievements that are marked as "hidden"
 *
 * @since  1.0.0
 * @param  string $achievement_type Limit the array to a specific type of achievement
 * @return array                    An array of hidden achivement post IDs
 */
function gamipress_get_hidden_achievement_ids( $achievement_type = '' ) {

	// Assume we have no hidden achievements
	$hidden_ids = array();

	// Grab our hidden achievements
	$hidden_achievements = get_posts( array(
		'post_type'      => $achievement_type,
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'meta_key'       => '_gamipress_hidden',
		'meta_value'     => 'hidden'
	) );

	foreach ( $hidden_achievements as $achievement )
		$hidden_ids[] = $achievement->ID;

	// Return our results
	return $hidden_ids;
}

/**
 * Get an array of post IDs for achievements that are marked as "hidden"
 *
 * @since  1.0.0
 * @param  integer $achievement_id Limit the array to a specific id of achievement
 * @return array  An array of hidden achivement post IDs
 */

function gamipress_get_hidden_achievement_by_id( $achievement_id ) {

	// Grab our hidden achievements
	global $wpdb;

	//Get hidden achievement posts.
	$hidden_achievements = $wpdb->get_results( $wpdb->prepare(
		"SELECT * FROM {$wpdb->posts} AS p
                                 JOIN {$wpdb->postmeta} AS pm
                                 ON p.ID = pm.post_id
                                 WHERE p.ID = %d
                                 AND pm.meta_key = '_gamipress_hidden'
                                 AND pm.meta_value = 'hidden'
                                 ",
		$achievement_id));

	// Return our results
	return $hidden_achievements;
}

/**
 * Get an array of post IDs for a user's earned achievements
 *
 * @since  1.0.0
 * @param  integer $user_id          The given user's ID
 * @param  string  $achievement_type Limit the array to a specific type of achievement
 * @return array                     Our user's array of earned achivement post IDs
 */
function gamipress_get_user_earned_achievement_ids( $user_id = 0, $achievement_type = '' ) {

	// Assume we have no earned achievements
	$earned_ids = array();

	// Grab our earned achievements
	$earned_achievements = gamipress_get_user_achievements( array(
		'user_id'          => $user_id,
		'achievement_type' => $achievement_type,
		'display' => true
	) );

	foreach ( $earned_achievements as $achievement )
		$earned_ids[] = $achievement->ID;

	return $earned_ids;

}

/**
 * Get an array of unique achievement types a user has earned
 *
 * @since  1.0.0
 *
 * @param  int  $user_id The ID of the user earning the achievement
 * @return array 		 The array of achievements the user has earned
 */
function gamipress_get_user_earned_achievement_types( $user_id = 0 ){

	$achievements = gamipress_get_user_achievements( array( 'user_id' => $user_id ) );

	if( ! $achievements ) {
		return array();
	}

	$achievement_types = wp_list_pluck( $achievements, 'post_type' );

	return array_unique( $achievement_types );
}

/**
 * Returns achievements that may be earned when the given achievement is earned.
 *
 * @since  1.0.0
 * @param  integer $achievement_id The given achievement's post ID
 * @return array                   An array of achievements that are dependent on the given achievement
 */
function gamipress_get_dependent_achievements( $achievement_id = 0 ) {
	global $wpdb;

	// Grab the current achievement ID if none specified
	if ( ! $achievement_id ) {
		global $post;
		$achievement_id = $post->ID;
	}

	// Grab posts that can be earned by unlocking the given achievement
	$specific_achievements = $wpdb->get_results( $wpdb->prepare(
		"
		SELECT *
		FROM   $wpdb->posts as posts,
		       $wpdb->p2p as p2p
		WHERE  posts.ID = p2p.p2p_to
		       AND p2p.p2p_from = %d
		",
		$achievement_id
	) );

	// Grab posts triggered by unlocking any/all of the given achievement's type
	$type_achievements = $wpdb->get_results( $wpdb->prepare(
		"
		SELECT *
		FROM   $wpdb->posts as posts,
		       $wpdb->postmeta as meta
		WHERE  posts.ID = meta.post_id
		       AND meta.meta_key = '_gamipress_achievement_type'
		       AND meta.meta_value = %s
		",
		get_post_type( $achievement_id )
	) );

	// Merge our dependent achievements together
	$achievements = array_merge( $specific_achievements, $type_achievements );

	// Available filter to modify an achievement's dependents
	return apply_filters( 'gamipress_dependent_achievements', $achievements, $achievement_id );
}

/**
 * Returns achievements that must be earned to earn given achievement.
 *
 * @since  1.0.0
 * @param  integer $achievement_id The given achievement's post ID
 * @return array                   An array of achievements that are dependent on the given achievement
 */
function gamipress_get_required_achievements_for_achievement( $achievement_id = 0 ) {
	global $wpdb;

	// Grab the current achievement ID if none specified
	if ( ! $achievement_id ) {
		global $post;
		$achievement_id = $post->ID;
	}

	// Don't retrieve requirements if achievement is not earned by steps
	if ( get_post_meta( $achievement_id, '_gamipress_earned_by', true ) !== 'triggers' )
		return false;

	// Grab our requirements for this achievement
	$requirements = $wpdb->get_results( $wpdb->prepare(
		"
		SELECT   *
		FROM     $wpdb->posts as posts
		         LEFT JOIN $wpdb->p2p as p2p
		                   ON p2p.p2p_from = posts.ID
		         LEFT JOIN $wpdb->p2pmeta AS p2pmeta
		                   ON p2p.p2p_id = p2pmeta.p2p_id
		WHERE    p2p.p2p_to = %d
		         AND p2pmeta.meta_key = %s
		ORDER BY CAST( p2pmeta.meta_value as SIGNED ) ASC
		",
		$achievement_id,
		'order'
	) );

	return $requirements;
}

/**
 * Returns achievements that may be earned when the given achievement is earned.
 *
 * @since   1.0.0
 * @updated 1.3.1 added steps with required points
 * @updated 1.3.2 improved query
 *
 * @return array An array of achievements that are dependent on the given achievement
 */
function gamipress_get_points_based_achievements() {

	global $wpdb;

	$achievements = get_transient( 'gamipress_points_based_achievements' );

	if ( empty( $achievements ) ) {

		// Grab posts that can be earned by unlocking the given achievement
		$achievements = $wpdb->get_results( $wpdb->prepare(
			"SELECT *
			FROM   $wpdb->posts as posts
			INNER JOIN {$wpdb->postmeta} AS m1
			ON ( posts.ID = m1.post_id )
			INNER JOIN $wpdb->postmeta AS m2
			ON ( posts.ID = m2.post_id )
			WHERE (
					( m1.meta_key = %s AND m1.meta_value = %s )
					OR ( m2.meta_key = %s AND m2.meta_value = %s )
				)",
			'_gamipress_trigger_type', 'earn-points',	// Requirements based on earn points
			'_gamipress_earned_by', 'points'			// Achievements earned by points
		) );

		// Store these posts to a transient for 1 days
		set_transient( 'gamipress_points_based_achievements', $achievements, 60*60*24 );
	}

	return (array) maybe_unserialize( $achievements );
}

/**
 * Destroy the points-based achievements transient if we edit a points-based achievement
 *
 * @since 1.0.0
 * @param integer $post_id The given post's ID
 */
function gamipress_bust_points_based_achievements_cache( $post_id ) {

	$post = get_post( $post_id );

	if (
		gamipress_is_achievement( $post )
		&& (
			'points' == get_post_meta( $post_id, '_gamipress_earned_by', true )
			|| ( isset( $_POST['_gamipress_earned_by'] ) && 'points' == $_POST['_gamipress_earned_by'] )
		)
	) {

		// If the post is one of our achievement types and the achievement is awarded by minimum points, delete the transient
		delete_transient( 'gamipress_points_based_achievements' );

	} else if(
		in_array( get_post_type( $post_id ), gamipress_get_requirement_types_slugs() )
		&& 'earn-points' == get_post_meta( $post_id, '_gamipress_trigger_type', true )
	) {

		// If the post is one of our requirement types and the trigger type is a points based one, delete the transient
		delete_transient( 'gamipress_points_based_achievements' );

	}

}
add_action( 'save_post', 'gamipress_bust_points_based_achievements_cache' );
add_action( 'trash_post', 'gamipress_bust_points_based_achievements_cache' );

/**
 * Returns achievements that may be earned when the given achievement is earned.
 *
 * @since   1.3.1
 * @updated 1.3.2 improved query
 *
 * @return array An array of achievements that are dependent on the given achievement
 */
function gamipress_get_rank_based_achievements() {

	global $wpdb;

	$achievements = get_transient( 'gamipress_rank_based_achievements' );

	if ( empty( $achievements ) ) {

		// Grab posts that can be earned by unlocking the given achievement
		$achievements = $wpdb->get_results( $wpdb->prepare(
			"SELECT *
			FROM   $wpdb->posts as posts
			INNER JOIN {$wpdb->postmeta} AS m1
			ON ( posts.ID = m1.post_id )
			INNER JOIN $wpdb->postmeta AS m2
			ON ( posts.ID = m2.post_id )
			WHERE (
					( m1.meta_key = %s AND m1.meta_value = %s )
					OR ( m2.meta_key = %s AND m2.meta_value = %s )
				)",
			'_gamipress_trigger_type', 'earn-rank',	// Requirements based on earn rank
			'_gamipress_earned_by', 'rank'			// Achievements earned by rank
		) );

		// Store these posts to a transient for 1 days
		set_transient( 'gamipress_rank_based_achievements', $achievements, 60*60*24 );
	}

	return (array) maybe_unserialize( $achievements );

}

/**
 * Destroy the rank-based achievements transient if we edit a rank-based achievement
 *
 * @deprecated Removed the transient usage since 1.3.2
 *
 * @since 1.3.1
 *
 * @param integer $post_id The given post's ID
 */
function gamipress_bust_rank_based_achievements_cache( $post_id ) {

	$post = get_post($post_id);

	if (
		gamipress_is_achievement( $post )
		&& (
			'rank' == get_post_meta( $post_id, '_gamipress_earned_by', true )
			|| ( isset( $_POST['_gamipress_earned_by'] ) && 'rank' == $_POST['_gamipress_earned_by'] )
		)
	) {

		// If the post is one of our achievement types, and the achievement is awarded by a rank, delete the transient
		delete_transient( 'gamipress_rank_based_achievements' );

	} else if(
		in_array( get_post_type( $post_id ), gamipress_get_requirement_types_slugs() )
		&& 'earn-points' == get_post_meta( $post_id, '_gamipress_trigger_type', true )
	) {

		// If the post is one of our requirement types and the trigger type is a points based one, delete the transient
		delete_transient( 'gamipress_rank_based_achievements' );

	}

}
add_action( 'save_post', 'gamipress_bust_rank_based_achievements_cache' );
add_action( 'trash_post', 'gamipress_bust_rank_based_achievements_cache' );

/**
 * Helper function to retrieve an achievement post thumbnail
 *
 * Falls back to achievement type's thumbnail.
 *
 * @since  1.0.0
 *
 * @param  integer $post_id    The achievement's post ID
 * @param  string  $image_size The name of a registered custom image size
 * @param  string  $class      A custom class to use for the image tag
 *
 * @return string              Our formatted image tag
 */
function gamipress_get_achievement_post_thumbnail( $post_id = 0, $image_size = 'gamipress-achievement', $class = 'gamipress-achievement-thumbnail' ) {

	// Get our achievement thumbnail
	$image = get_the_post_thumbnail( $post_id, $image_size, array( 'class' => $class ) );

	// If we don't have an image...
	if ( ! $image ) {

		// Grab our achievement type's post thumbnail
		$achievement = get_page_by_path( get_post_type(), OBJECT, 'achievement-type' );
		$image = is_object( $achievement ) ? get_the_post_thumbnail( $achievement->ID, $image_size, array( 'class' => $class ) ) : false;

		// If we still have no image
		if ( ! $image ) {

			// If we already have an array for image size
			if ( is_array( $image_size ) ) {
				// Write our sizes to an associative array
				$image_sizes['width'] = $image_size[0];
				$image_sizes['height'] = $image_size[1];

			// Otherwise, attempt to grab the width/height from our specified image size
			} else {
				global $_wp_additional_image_sizes;
				if ( isset( $_wp_additional_image_sizes[$image_size] ) )
					$image_sizes = $_wp_additional_image_sizes[$image_size];
			}

			// If we can't get the defined width/height, set our own
			if ( empty( $image_sizes ) ) {
				$image_sizes = array(
					'width'  => 100,
					'height' => 100
				);
			}

			// Available filter: 'gamipress_default_achievement_post_thumbnail'
			$default_thumbnail = apply_filters( 'gamipress_default_achievement_post_thumbnail', '', $achievement, $image_sizes );

			if( ! empty( $default_thumbnail ) ) {
				$image = '<img src="' . $default_thumbnail . '" width="' . $image_sizes['width'] . '" height="' . $image_sizes['height'] . '" class="' . $class . '">';
			}

		}
	}

	// Finally, return our image tag
	return $image;
}

/**
 * Get an array of all users who have earned a given achievement
 *
 * @since  1.0.0
 * @param  integer $achievement_id The given achievement's post ID
 * @return array                   Array of user objects
 */
function gamipress_get_achievement_earners( $achievement_id = 0 ) {

	global $wpdb;

	// If not properly upgrade to required version fallback to compatibility function
	if( ! is_gamipress_upgraded_to( '1.2.8' ) ) {
		return gamipress_get_achievement_earners_old( $achievement_id );
	}

	// Setup CT object
	$ct_table = ct_setup_table( 'gamipress_user_earnings' );

	$earners = $wpdb->get_col( "
		SELECT u.user_id
		FROM {$ct_table->db->table_name} AS u
		WHERE u.post_id = {$achievement_id}
		GROUP BY u.user_id
	" );

	$earned_users = array();

	foreach( $earners as $earner_id ) {
		if ( gamipress_has_user_earned_achievement( $achievement_id, $earner_id ) ) {
			$earned_users[] = new WP_User( $earner_id );
		}
	}

	return $earned_users;

}

/**
 * Build an unordered list of users who have earned a given achievement
 *
 * @since  1.0.0
 * @param  integer $achievement_id The given achievement's post ID
 * @return string                  Concatenated markup
 */
function gamipress_get_achievement_earners_list( $achievement_id = 0 ) {

	// Grab our users
	$earners = gamipress_get_achievement_earners( $achievement_id );
	$output = '';

	// Only generate output if we have earners
	if ( ! empty( $earners ) )  {
		// Loop through each user and build our output
		$output .= '<h4>' . apply_filters( 'gamipress_earners_heading', __( 'People who have earned this:', 'gamipress' ) ) . '</h4>';

		$output .= '<ul class="gamipress-achievement-earners-list achievement-' . $achievement_id . '-earners-list">';

		foreach ( $earners as $user ) {

			$user_content = '<li><a href="' . get_author_posts_url( $user->ID ) . '">' . get_avatar( $user->ID ) . '</a></li>';

			$output .= apply_filters( 'gamipress_get_achievement_earners_list_user', $user_content, $user->ID );

		}

		$output .= '</ul>';
	}

	// Return our concatenated output
	return apply_filters( 'gamipress_get_achievement_earners_list', $output, $achievement_id, $earners );
}

/**
 * Flush rewrite rules whenever an achievement type is published.
 *
 * @since 1.0.0
 *
 * @param string $new_status New status.
 * @param string $old_status Old status.
 * @param object $post       Post object.
 */
function gamipress_flush_rewrite_on_published_achievement( $new_status, $old_status, $post ) {
	if ( 'achievement-type' === $post->post_type && 'publish' === $new_status && 'publish' !== $old_status ) {
		gamipress_flush_rewrite_rules();
	}
}
add_action( 'transition_post_status', 'gamipress_flush_rewrite_on_published_achievement', 10, 3 );

/**
 * Update all dependent data if achievement type name has changed.
 *
 * @since  1.0.0
 *
 * @param  array $data      Post data.
 * @param  array $post_args Post args.
 * @return array            Updated post data.
 */
function gamipress_maybe_update_achievement_type( $data = array(), $post_args = array() ) {

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

    if ( gamipress_achievement_type_changed( $post_args ) ) {

        $original_type = get_post( $post_args['ID'] )->post_name;
        $new_type = $post_args['post_name'];

        $data['post_name'] = gamipress_update_achievement_types( $original_type, $new_type );

        add_filter( 'redirect_post_location', 'gamipress_achievement_type_rename_redirect', 99 );

    }

	return $data;
}
add_filter( 'wp_insert_post_data' , 'gamipress_maybe_update_achievement_type' , 99, 2 );

/**
 * Check if an achievement type name has changed.
 *
 * @since  1.0.0
 *
 * @param  array $post_args Post args.
 * @return bool             True if name has changed, otherwise false.
 */
function gamipress_achievement_type_changed( $post_args = array() ) {

	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return false;
	}

	$original_post = ( !empty( $post_args['ID'] ) && isset( $post_args['ID'] ) ) ? get_post( $post_args['ID'] ) : null;
	$status = false;

	if ( is_object( $original_post ) ) {
		if (
			'achievement-type' === $post_args['post_type']
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
 * Replace all instances of one achievement type with another.
 *
 * @since  1.0.0
 *
 * @param  string $original_type Original achievement type.
 * @param  string $new_type      New achievement type.
 * @return string                New achievement type.
 */
function gamipress_update_achievement_types( $original_type = '', $new_type = '' ) {

	// Sanity check to prevent alterating core posts
	if ( empty( $original_type ) || in_array( $original_type, array( 'post', 'page', 'attachment', 'revision', 'nav_menu_item' ) ) ) {
		return $new_type;
	}

	gamipress_update_achievements_achievement_types( $original_type, $new_type );
	gamipress_update_p2p_achievement_types( $original_type, $new_type );
	gamipress_update_earned_meta_achievement_types( $original_type, $new_type );
	gamipress_update_active_meta_achievement_types( $original_type, $new_type );
	gamipress_flush_rewrite_rules();

	return $new_type;

}

/**
 * Change all achievements of one type to a new type.
 *
 * @since 1.0.0
 *
 * @param string $original_type Original achievement type.
 * @param string $new_type      New achievement type.
 */
function gamipress_update_achievements_achievement_types( $original_type = '', $new_type = '' ) {

	$items = get_posts( array(
		'posts_per_page' => -1,
		'post_status'    => 'any',
		'post_type'      => $original_type,
		'fields'         => 'id',
	) );

	foreach ( $items as $item ) {
		set_post_type( $item->ID, $new_type );
	}

}

/**
 * Change all p2p connections of one achievement type to a new type.
 *
 * @since 1.0.0
 *
 * @param string $original_type Original achievement type.
 * @param string $new_type      New achievement type.
 */
function gamipress_update_p2p_achievement_types( $original_type = '', $new_type = '' ) {

	global $wpdb;

	$p2p_relationships = array(
		"step-to-{$original_type}" => "step-to-{$new_type}",
		"{$original_type}-to-step" => "{$new_type}-to-step",
		"{$original_type}-to-points-award" => "{$new_type}-to-points-award",
	);

	foreach ( $p2p_relationships as $old => $new ) {
		$wpdb->query( $wpdb->prepare( "UPDATE $wpdb->p2p SET p2p_type = %s WHERE p2p_type = %s", $new, $old ) );
	}

}

/**
 * Change all earned meta from one achievement type to another.
 *
 * @since 1.0.0
 *
 * @param string $original_type Original achievement type.
 * @param string $new_type      New achievement type.
 */
function gamipress_update_earned_meta_achievement_types( $original_type = '', $new_type = '' ) {

	// If not properly upgrade to required version fallback to compatibility function
	if( ! is_gamipress_upgraded_to( '1.2.8' ) ) {
		gamipress_update_earned_meta_achievement_types_old( $original_type, $new_type );
		return;
	}

	// Setup CT object
	$ct_table = ct_setup_table( 'gamipress_user_earnings' );

	$ct_table->db->update(
		array(
			'post_type' => $new_type
		),
		array(
			'post_type' => $original_type
		)
	);

}

/**
 * Change all active meta from one achievement type to another.
 *
 * @since 1.0.0
 *
 * @param string $original_type Original achievement type.
 * @param string $new_type      New achievement type.
 */
function gamipress_update_active_meta_achievement_types( $original_type = '', $new_type = '' ) {

	$metas = gamipress_get_unserialized_achievement_metas( '_gamipress_active_achievements', $original_type );

	if ( ! empty( $metas ) ) {
		foreach ( $metas as $meta ) {
			$meta->meta_value = gamipress_update_meta_achievement_types( $meta->meta_value, $original_type, $new_type );

			update_user_meta( $meta->user_id, $meta->meta_key, $meta->meta_value );
		}
	}

}

/**
 * Get unserialized user achievement metas.
 *
 * @since  1.0.0
 *
 * @param  string $meta_key      Meta key.
 * @param  string $original_type Achievement type.
 *
 * @return array                 User achievement metas.
 */
function gamipress_get_unserialized_achievement_metas( $meta_key = '', $original_type = '' ) {
	$metas = gamipress_get_achievement_metas( $meta_key, $original_type );

	if ( ! empty( $metas ) ) {
		foreach ( $metas as $key => $meta ) {
			$metas[ $key ]->meta_value = maybe_unserialize( $meta->meta_value );
		}
	}

	return $metas;

}

/**
 * Get serialized user achievement metas.
 *
 * @since  1.0.0
 *
 * @param  string $meta_key      Meta key.
 * @param  string $original_type Achievement type.
 * @return array                 User achievement metas.
 */
function gamipress_get_achievement_metas( $meta_key = '', $original_type = '' ) {

	global $wpdb;

	return $wpdb->get_results( $wpdb->prepare(
		"
		SELECT *
		FROM   $wpdb->usermeta
		WHERE  meta_key = %s
		       AND meta_value LIKE '%%%s%%'
		",
		$meta_key,
		$original_type
	) );

}

/**
 * Change user achievement meta from one achievement type to another.
 *
 * @since 1.0.0
 *
 * @param array  $achievements  Array of achievements.
 * @param string $original_type Original achievement type.
 * @param string $new_type      New achievement type.
 *
 * @return array $achievements
 */
function gamipress_update_meta_achievement_types( $achievements = array(), $original_type = '', $new_type = '' ) {

	if ( is_array( $achievements ) && ! empty( $achievements ) ) {

		foreach ( $achievements as $key => $achievement ) {
			if ( $achievement->post_type === $original_type ) {
				$achievements[ $key ]->post_type = $new_type;
			}
		}

	}

	return $achievements;
}

/**
 * Redirect to include custom rename message.
 *
 * @since  1.0.0
 *
 * @param  string $location Original URI.
 * @return string           Updated URI.
 */
function gamipress_achievement_type_rename_redirect( $location = '' ) {

	remove_filter( 'redirect_post_location', __FUNCTION__, 99 );

	return add_query_arg( 'message', 99, $location );

}

/**
 * Filter the "post updated" messages to include support for achievement types.
 *
 * @since 1.0.0
 *
 * @param array $messages Array of messages to display.
 *
 * @return array $messages Compiled list of messages.
 */
function gamipress_achievement_type_update_messages( $messages ) {

	$messages['achievement-type'] = array_fill( 1, 10, __( 'Achievement Type saved successfully.', 'gamipress' ) );
	$messages['achievement-type']['99'] = sprintf( __('Achievement Type renamed successfully. <p>All achievements of this type, and all active and earned user achievements, have been updated <strong>automatically</strong>.</p> All shortcodes, %s, and URIs that reference the old achievement type slug must be updated <strong>manually</strong>.', 'gamipress'), '<a href="' . esc_url( admin_url( 'widgets.php' ) ) . '">' . __( 'widgets', 'gamipress' ) . '</a>' );

	return $messages;

}
add_filter( 'post_updated_messages', 'gamipress_achievement_type_update_messages' );

/**
 * Log a user's achivements earns/awards
 *
 * @since 1.0.0
 *
 * @param integer $user_id        The user ID
 * @param integer $achievement_id The associated achievement ID
 * @param integer $admin_id       An admin ID (if admin-awarded)
 */
function gamipress_log_user_achievement_award( $user_id, $achievement_id, $admin_id = 0 ) {

    $post_type = get_post_type( $achievement_id );

	$log_meta = array(
		'achievement_id' => $achievement_id,
	);

	$access = 'public';

	// Alter our log pattern if this was an admin action
	if ( $admin_id ) {
		$type = 'achievement_award';
		$access = 'private';

		$log_meta['pattern'] =  gamipress_get_option( 'achievement_awarded_log_pattern', __( '{admin} awarded {user} with the the {achievement} {achievement_type}', 'gamipress' ) );
		$log_meta['admin_id'] = $admin_id;
	} else {
		$type = 'achievement_earn';

        if( $post_type === 'step' || $post_type === 'points-award' ) {
            $log_meta['pattern'] = gamipress_get_option( 'requirement_complete_log_pattern', __( '{user} completed the {achievement_type} {achievement}', 'gamipress' ) );
        } else {
            $log_meta['pattern'] = gamipress_get_option( 'achievement_earned_log_pattern', __( '{user} unlocked the {achievement} {achievement_type}', 'gamipress' ) );
        }
	}

	// Create the log entry
    gamipress_insert_log( $type, $user_id, $access, $log_meta );

}
