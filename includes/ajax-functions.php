<?php
/**
 * Ajax Functions
 *
 * @package     GamiPress\Ajax_Functions
 * @since       1.0.0
 */
// Exit if accessed directly
if( !defined( 'ABSPATH' ) ) exit;

/**
 * Ajax Helper for returning achievements
 *
 * @since 1.0.0
 * @return void
 */
function gamipress_ajax_get_achievements() {

	// Setup our AJAX query vars
	$type       = isset( $_REQUEST['type'] )       ? $_REQUEST['type']       : false;
	$limit      = isset( $_REQUEST['limit'] )      ? $_REQUEST['limit']      : false;
	$offset     = isset( $_REQUEST['offset'] )     ? $_REQUEST['offset']     : false;
	$filter     = isset( $_REQUEST['filter'] )     ? $_REQUEST['filter']     : false;
	$search     = isset( $_REQUEST['search'] )     ? $_REQUEST['search']     : false;
	$current_user    = isset( $_REQUEST['current_user'] )    ? $_REQUEST['current_user']    : false;
	$user_id    = isset( $_REQUEST['user_id'] )    ? $_REQUEST['user_id']    : false;
	$orderby    = isset( $_REQUEST['orderby'] )    ? $_REQUEST['orderby']    : false;
	$order      = isset( $_REQUEST['order'] )      ? $_REQUEST['order']      : false;
	$wpms       = isset( $_REQUEST['wpms'] )       ? $_REQUEST['wpms']       : false;
	$include    = isset( $_REQUEST['include'] )    ? $_REQUEST['include']    : array();
	$exclude    = isset( $_REQUEST['exclude'] )    ? $_REQUEST['exclude']    : array();

	// Force to set current user as user ID
	if( $current_user ) {
		$user_id = get_current_user_id();
	}

	// Get the current user if one wasn't specified
	if( ! $user_id ) {
		$user_id = get_current_user_id();
	}

	// Setup template vars
	$template_args = array(
		'thumbnail' => isset( $_REQUEST['thumbnail'] ) ? $_REQUEST['thumbnail'] : 'yes',
		'excerpt'	=> isset( $_REQUEST['excerpt'] ) ? $_REQUEST['excerpt'] : 'yes',
		'steps'	    => isset( $_REQUEST['steps'] ) ? $_REQUEST['steps'] : 'yes',
		'earners'	=> isset( $_REQUEST['earners'] ) ? $_REQUEST['earners'] : 'no',
		'toggle'	=> isset( $_REQUEST['toggle'] ) ? $_REQUEST['toggle'] : 'yes',
		'user_id' 	=> $user_id, // User ID on achievement is used to meet to which user apply earned checks
	);

	// Convert $type to properly support multiple achievement types
	if ( 'all' == $type ) {
		$type = gamipress_get_achievement_types_slugs();
	} else {
		$type = explode( ',', $type );
	}

	// Build $include array
	if ( ! is_array( $include ) ) {
		$include = explode( ',', $include );
	}

	// Build $exclude array
	if ( ! is_array( $exclude ) ) {
		$exclude = explode( ',', $exclude );
	}

    // Initialize our output and counters
    $achievements = '';
    $achievement_count = 0;
    $query_count = 0;

    // Grab our hidden achievements (used to filter the query)
	$hidden = gamipress_get_hidden_achievement_ids( $type );

	// If we're polling all sites, grab an array of site IDs
	if( $wpms && $wpms != 'false' )
		$sites = gamipress_get_network_site_ids();
	// Otherwise, use only the current site
	else
		$sites = array( get_current_blog_id() );

	// Loop through each site (default is current site only)
	foreach( $sites as $site_blog_id ) {

		// If we're not polling the current site, switch to the site we're polling
		if ( get_current_blog_id() != $site_blog_id ) {
			switch_to_blog( $site_blog_id );
		}

		// Grab user earned achievements (used to filter the query)
		$earned_ids = gamipress_get_user_earned_achievement_ids( $user_id, $type );

		// Query Achievements
		$args = array(
			'post_type'      =>	$type,
			'orderby'        =>	$orderby,
			'order'          =>	$order,
			'posts_per_page' =>	$limit,
			'offset'         => $offset,
			'post_status'    => 'publish',
			'post__not_in'   => array_diff( $hidden, $earned_ids )
		);

		// Filter - query completed or non completed achievements
		if ( $filter == 'completed' ) {
			$args[ 'post__in' ] = $earned_ids;
		}elseif( $filter == 'not-completed' ) {
			$args[ 'post__not_in' ] = array_merge( $hidden, $earned_ids );
		}

		// Include certain achievements
		if ( ! empty( $include ) ) {
			$args[ 'post__not_in' ] = array_diff( $args[ 'post__not_in' ], $include );
			$args[ 'post__in' ] = array_merge( $args[ 'post__in' ], $include  );
		}

		// Exclude certain achievements
		if ( ! empty( $exclude ) ) {
			$args[ 'post__not_in' ] = array_merge( $args[ 'post__not_in' ], $exclude );
		}

		// Search
		if ( $search ) {
			$args[ 's' ] = $search;
		}

		// Loop Achievements
		$achievement_posts = new WP_Query( $args );
		$query_count = absint( $achievement_posts->found_posts );
		while ( $achievement_posts->have_posts() ) : $achievement_posts->the_post();

			$achievements .= gamipress_render_achievement( get_the_ID(), $template_args );

			$achievement_count++;

		endwhile;

		// Sanity helper: if we're filtering for complete and we have no
		// earned achievements, $achievement_posts should definitely be false
		/*if ( 'completed' == $filter && empty( $earned_ids ) )
			$achievements = '';*/

		// Display a message for no results
		if ( empty( $achievements ) ) {
			$current = current( $type );
			// If we have exactly one achivement type, get its plural name, otherwise use "achievements"
			$post_type_plural = ( 1 == count( $type ) && ! empty( $current ) ) ? get_post_type_object( $current )->labels->name : __( 'achievements' , 'gamipress' );

			// Setup our completion message
			$achievements .= '<div class="gamipress-no-results">';

			if ( 'completed' == $filter ) {
				$achievements .= '<p>' . sprintf( __( 'No completed %s to display at this time.', 'gamipress' ), strtolower( $post_type_plural ) ) . '</p>';
			} else {
				$achievements .= '<p>' . sprintf( __( 'No %s to display at this time.', 'gamipress' ), strtolower( $post_type_plural ) ) . '</p>';
			}

			$achievements .= '</div><!-- .gamipress-no-results -->';
		}

		if ( get_current_blog_id() != $site_blog_id ) {
			// Come back to current blog
			restore_current_blog();
		}

	}

	// Send back our successful response
	wp_send_json_success( array(
		'message'     => $achievements,
		'offset'      => $offset + $limit,
		'query_count' => $query_count,
		'achievement_count' => $achievement_count,
		'type'        => $type,
	) );
}
add_action( 'wp_ajax_gamipress_get_achievements', 'gamipress_ajax_get_achievements' );
add_action( 'wp_ajax_nopriv_gamipress_get_achievements', 'gamipress_ajax_get_achievements' );

/**
 * AJAX Helper for selecting users in Shortcode Embedder
 *
 * @since 1.0.0
 */
function gamipress_ajax_get_users() {

	// If no query was sent, die here
	if ( ! isset( $_REQUEST['q'] ) ) {
		$_REQUEST['q'] = '';
	}

	global $wpdb;

	// Pull back the search string
	$search = esc_sql( like_escape( $_REQUEST['q'] ) );

	$sql = "SELECT ID, user_login FROM {$wpdb->users}";

	// Build our query
	if ( !empty( $search ) ) {
		$sql .= " WHERE user_login LIKE '%{$search}%'";
	}

	if( empty( $_REQUEST['q'] ) ) {
		$sql .= " LIMIT 10";
	}

	// Fetch our results (store as associative array)
	$results = $wpdb->get_results( $sql, 'ARRAY_A' );

	// Return our results
	wp_send_json_success( $results );
}
add_action( 'wp_ajax_gamipress_get_users', 'gamipress_ajax_get_users' );

/**
 * AJAX Helper for selecting posts
 *
 * @since 1.0.0
 */
function gamipress_ajax_get_posts() {
	global $wpdb;

	// Pull back the search string
	$search = isset( $_REQUEST['q'] ) ? like_escape( $_REQUEST['q'] ) : '';

	// Post type conditional
	$post_type = ( isset( $_REQUEST['post_type'] ) && ! empty( $_REQUEST['post_type'] ) ? $_REQUEST['post_type'] :  array( 'post', 'page' ) );

	if ( is_array( $post_type ) ) {
		$post_type = sprintf( 'AND p.post_type IN(\'%s\')', implode( "','", $post_type ) );
	} else {
		$post_type = sprintf( 'AND p.post_type = \'%s\'', $post_type );
	}

	// Check for extra conditionals
	$where = '';

	if( isset( $_REQUEST['trigger_type'] ) ) {

		$query_args = array();
		$trigger_type = $_REQUEST['trigger_type'];

		$query_args = gamipress_get_specific_activity_triggers_query_args( $query_args, $trigger_type );

		if( isset( $query_args ) ) {

			if( is_array( $query_args ) ) {
				// If is an array of conditionals, then build the new conditionals
				foreach( $query_args as $field => $value ) {
					$where .= " AND p.{$field} = '$value'";
				}
			} else {
				$where = $query_args;
			}

		}
	}

	$results = $wpdb->get_results( $wpdb->prepare(
		"
		SELECT p.ID, p.post_title
		FROM   $wpdb->posts AS p
		WHERE  1=1
			   {$post_type}
		       {$where}
			   AND p.post_title LIKE %s
		       AND p.post_status IN( 'publish', 'inherit' )
		",
		"%%{$search}%%"
	) );

	// Return our results
	wp_send_json_success( $results );
}
add_action( 'wp_ajax_gamipress_get_posts', 'gamipress_ajax_get_posts' );

/**
 * AJAX Helper for selecting posts in Shortcode Embedder
 *
 * @since 1.0.0
 */
function gamipress_ajax_get_achievements_options() {
	global $wpdb;

	// Pull back the search string
	$search = isset( $_REQUEST['q'] ) ? like_escape( $_REQUEST['q'] ) : '';
	$achievement_types = isset( $_REQUEST['post_type'] ) && 'all' !== $_REQUEST['post_type']
		? array( esc_sql( $_REQUEST['post_type'] ) )
		: gamipress_get_achievement_types_slugs();
	$post_type = sprintf( 'AND p.post_type IN(\'%s\')', implode( "','", $achievement_types ) );

	$results = $wpdb->get_results( $wpdb->prepare(
		"
		SELECT p.ID, p.post_title
		FROM   $wpdb->posts AS p 
		JOIN $wpdb->postmeta AS pm
		ON p.ID = pm.post_id
		WHERE  p.post_title LIKE %s
		       {$post_type}
		       AND p.post_status = 'publish'
		       AND pm.meta_key = %s
		       AND pm.meta_value = %s
		",
		"%%{$search}%%",
		"_gamipress_hidden",
		"show"
	) );

	// Return our results
	wp_send_json_success( $results );
}
add_action( 'wp_ajax_gamipress_get_achievements_options', 'gamipress_ajax_get_achievements_options' );

/**
 * AJAX helper for getting our posts and returning select options
 *
 * @since   1.0.0
 * @updated 1.0.5
 * @updated 1.3.0
 * @updated 1.3.5 Make function accessible through gamipress_get_achievements_options_html action
 */
function gamipress_achievement_post_ajax_handler() {

	$selected = '';

    // If requirement_id requested, then retrieve the selected option from this requirement
    if( isset( $_REQUEST['requirement_id'] ) && ! empty( $_REQUEST['requirement_id'] ) ) {

		$requirements = gamipress_get_requirement_object( $_REQUEST['requirement_id'] );

		$selected = isset( $requirements['achievement_post'] ) ? $requirements['achievement_post'] : '';
    } else if( isset( $_REQUEST['selected'] ) && ! empty( $_REQUEST['selected'] ) ) {
		$selected = $_REQUEST['selected'];
	}

	$achievement_type = $_REQUEST['achievement_type'];
	$exclude_posts = isset( $_REQUEST['excluded_posts'] ) ? (array) $_REQUEST['excluded_posts'] : array();

    // If we don't have an achievement type, bail now
    if ( empty( $achievement_type ) ) {
        die();
    }

	$achievement_types = gamipress_get_achievement_types();

	if( ! isset( $achievement_types[$achievement_type] ) ) {
		return;
	}

	$singular_name = ! empty( $achievement_types[$achievement_type]['singular_name'] ) ? $achievement_types[$achievement_type]['singular_name'] : __( 'Achievement', 'gamipress' );

    // Grab all our posts for this achievement type
    $achievements = get_posts( array(
        'post_type'      => $achievement_type,
        'post__not_in'   => $exclude_posts,
        'posts_per_page' => -1,
        'orderby'        => 'title',
        'order'          => 'ASC',
    ));

    // Setup our output
    $output = '<option value="">' . sprintf( __( 'Choose the %s', 'gamipress' ), $singular_name ) . '</option>';
    foreach ( $achievements as $achievement ) {
        $output .= '<option value="' . $achievement->ID . '" ' . selected( $selected, $achievement->ID, false ) . '>' . $achievement->post_title . '</option>';
    }

    // Send back our results and die like a man
    echo $output;
    die();

}
add_action( 'wp_ajax_gamipress_requirement_achievement_post', 'gamipress_achievement_post_ajax_handler' );
add_action( 'wp_ajax_gamipress_get_achievements_options_html', 'gamipress_achievement_post_ajax_handler' );

/**
 * AJAX Helper for selecting ranks in achievement earned by
 *
 * @since 1.3.1
 */
function gamipress_ajax_get_ranks_options_html() {
	global $wpdb;

	// Post type conditional
	$post_type = ( isset( $_REQUEST['post_type'] ) && ! empty( $_REQUEST['post_type'] ) ? $_REQUEST['post_type'] :  gamipress_get_rank_types_slugs() );

	if ( is_array( $post_type ) ) {
		$post_type = sprintf( 'AND p.post_type IN(\'%s\')', implode( "','", $post_type ) );
		$singular_name = __( 'Rank', 'gamipress' );
	} else {
		$singular_name = gamipress_get_rank_type_singular( $post_type );
		$post_type = sprintf( 'AND p.post_type = \'%s\'', $post_type );
	}

	$selected = '';

	// If requirement_id requested, then retrieve the selected option from this requirement
	if( isset( $_REQUEST['requirement_id'] ) && ! empty( $_REQUEST['requirement_id'] ) ) {

		$requirements = gamipress_get_requirement_object( $_REQUEST['requirement_id'] );

		$selected = isset( $requirements['rank_required'] ) ? $requirements['rank_required'] : '';
	} else if( isset( $_REQUEST['selected'] ) && ! empty( $_REQUEST['selected'] ) ) {
		$selected = $_REQUEST['selected'];
	}

	$ranks = $wpdb->get_results( $wpdb->prepare(
		"SELECT p.ID, p.post_title
		FROM {$wpdb->posts} AS p
		WHERE p.post_status = %s
			{$post_type}
		ORDER BY menu_order DESC",
		'publish'
	) );

	// Setup our output
	$output = '<option value="">' . sprintf( __( 'Choose the %s', 'gamipress' ), $singular_name ) . '</option>';
	foreach ( $ranks as $rank ) {
		$output .= '<option value="' . $rank->ID . '" ' . selected( $selected, $rank->ID, false ) . '>' . $rank->post_title . '</option>';
	}

	// Send back our results and die like a man
	echo $output;
	die();
}
add_action( 'wp_ajax_gamipress_get_ranks_options_html', 'gamipress_ajax_get_ranks_options_html' );

/**
 * AJAX Helper for selecting ranks in Shortcode Embedder
 *
 * @since 1.3.1
 */
function gamipress_ajax_get_ranks_options() {
	global $wpdb;

	// Pull back the search string
	$search = isset( $_REQUEST['q'] ) ? like_escape( $_REQUEST['q'] ) : '';

	// Post type conditional
	$post_type = ( isset( $_REQUEST['post_type'] ) && ! empty( $_REQUEST['post_type'] ) ? $_REQUEST['post_type'] : gamipress_get_rank_types_slugs() );

	if ( is_array( $post_type ) ) {
		$post_type = sprintf( 'AND p.post_type IN(\'%s\')', implode( "','", $post_type ) );
	} else {
		$post_type = sprintf( 'AND p.post_type = \'%s\'', $post_type );
	}

	$ranks = $wpdb->get_results( $wpdb->prepare(
		"SELECT p.ID, p.post_title
		FROM {$wpdb->posts} AS p
		WHERE p.post_status = %s
			{$post_type}
		 AND p.post_title LIKE %s
		ORDER BY menu_order DESC",
		'publish',
		"%%{$search}%%"
	) );

	// Return our results
	wp_send_json_success( $ranks );
}
add_action( 'wp_ajax_gamipress_get_ranks_options', 'gamipress_ajax_get_ranks_options' );
