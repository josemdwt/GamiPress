<?php
/**
 * Content Filters
 *
 * @package     GamiPress\Content_Filters
 * @since       1.0.0
 */
// Exit if accessed directly
if( !defined( 'ABSPATH' ) ) exit;

/**
 * Add filters to remove stuff from our singular pages and add back in how we want it
 *
 * @since 1.0.0
 */
function gamipress_do_single_filters() {

	// Check we're in the right place
	if ( gamipress_is_single_achievement() || gamipress_is_single_rank() ) {

		// Filter out the post title
		// add_filter( 'the_title', 'gamipress_remove_to_reformat_entries_title', 10, 2 );

		// and filter out the post image
		add_filter( 'post_thumbnail_html', 'gamipress_remove_to_reformat_entries_title', 10, 2 );

	}
}
add_action( 'wp_enqueue_scripts', 'gamipress_do_single_filters' );

/**
 * Filter out the post title/post image and add back (later) how we want it
 *
 * @since 1.0.0
 *
 * @param  string  $html The page content prior to filtering
 * @param  integer $id   The page id
 *
 * @return string        The page content after being filtered
 */
function gamipress_remove_to_reformat_entries_title( $html = '', $id = 0 ) {

	// Remove, but only on the main loop!
	if ( gamipress_is_single_achievement( $id ) || gamipress_is_single_rank( $id ) )
		return '';

	// Nothing to see here... move along
	return $html;
}

/**
 * Filter achievement content to add our removed content back
 *
 * @since  1.0.0
 *
 * @param  string $content The page content
 *
 * @return string          The page content after reformat
 */
function gamipress_reformat_entries( $content ) {

	if ( gamipress_is_single_achievement( get_the_ID() ) ) {

		// Filter content, but only is a single achievement!
		return gamipress_apply_single_template( $content, 'single-achievement' );

	} else if ( gamipress_is_single_rank( get_the_ID() ) ) {

		// Filter content, but only is a single rank!
		return gamipress_apply_single_template( $content, 'single-rank' );
	}

	return $content;
}
add_filter( 'the_content', 'gamipress_reformat_entries' );

/**
 * Apply the given single template
 *
 * @since  1.3.1
 *
 * @param string $content
 * @param string $single_template
 *
 * @return string
 */
function gamipress_apply_single_template( $content, $single_template = '' ) {

	global $gamipress_template_args;

	// Now that we're where we want to be, tell the filters to stop removing
	$GLOBALS['gamipress_reformat_content'] = true;

	// Initialize GamiPress template args global
	$gamipress_template_args = array();

	$gamipress_template_args['original_content'] = $content;

	ob_start();

	// Try to load single-{template}-{post_type}.php, if not exists load single-{template}.php
	gamipress_get_template_part( $single_template, get_post_type( get_the_ID() ) );

	$new_content = ob_get_clean();

	// Ok, we're done reformatting
	$GLOBALS['gamipress_reformat_content'] = false;

	return $new_content;

}

/**
 * Helper function tests that we're in the main loop
 *
 * @since  1.3.1
 *
 * @param  bool|integer $id The page id
 *
 * @return boolean     A boolean determining if the function is in the main loop
 */
function gamipress_is_single_achievement( $id = false ) {

	$slugs = gamipress_get_achievement_types_slugs();

	// only run our filters on the achievement singular page
	if ( is_admin() || empty( $slugs ) || ! is_singular( $slugs ) )
		return false;
	// w/o id, we're only checking template context
	if ( ! $id )
		return true;

	// Checks several variables to be sure we're in the main loop (and won't effect things like post pagination titles)
	return ( ( $GLOBALS['post']->ID == $id ) && in_the_loop() && empty( $GLOBALS['gamipress_reformat_content'] ) );

}

/**
 * Generate HTML markup for an points type's points awards
 *
 * This will generate an unorderd list (<ul>) if steps are non-sequential
 * and an ordered list (<ol>) if steps require sequentiality.
 *
 * @since  1.0.0
 *
 * @param  array   $points_awards   An points type's points awards
 * @param  integer $user_id         The given user's ID
 * @param  array   $template_args   The given template args
 *
 * @return string                   The markup for our list
 */
function gamipress_get_points_awards_for_points_types_list_markup( $points_awards = array(), $user_id = 0, $template_args = array() ) {

	// If we don't have any points awards, or our points awards aren't an array, return nothing
	if ( ! $points_awards || ! is_array( $points_awards ) )
		return null;

	$count = count( $points_awards );

	// If we have no points awards, return nothing
	if ( ! $count )
		return null;

	// Grab the current user's ID if none was specifed
	if ( ! $user_id )
		$user_id = get_current_user_id();

	// Setup our variables
	$output = '';
	$post_type_object = get_post_type_object( 'points-award' );
	$points_type = gamipress_get_points_award_points_type( $points_awards[0]->ID );

	if( $points_type ) {
		$plural_name = get_post_meta( '_gamipress_plural_name', $points_type->ID, true );

		if( ! $plural_name ) {
			$plural_name = $points_type->post_title;
		}

		$points_awards_heading = sprintf( __( '%1$d %2$s Awards', 'gamipress' ), $count, $plural_name ); // 2 Credits Awards
	} else {
		$points_awards_heading = sprintf( __( '%1$d %2$s', 'gamipress' ), $count, $post_type_object->labels->name ); // 2 Points Awards
	}



	$output .= '<h4>' . apply_filters( 'gamipress_points_awards_heading', $points_awards_heading, $points_awards ) . '</h4>';
	$output .= '<ul class="gamipress-points-awards">';

	// Concatenate our output
	foreach ( $points_awards as $points_award ) {

		// Check if user has earned this points award, and add an 'earned' class
		$earned_status = 'user-has-not-earned';

		$maximum_earnings = absint( get_post_meta( $points_award->ID, '_gamipress_maximum_earnings', true ) );

		// An unlimited maximum of earnings means points awards could be earned anyway
		if( $maximum_earnings > 0 ) {
			$earned_times = count( gamipress_get_user_achievements( array(
				'user_id' => absint( $user_id ),
				'achievement_id' => absint( $points_award->ID ),
				'since' => absint( gamipress_achievement_last_user_activity( $points_award->ID, $user_id ) )
			) ) );

			// User has earned it more times than required times, so is earned
			if( $earned_times >= $maximum_earnings ) {
				$earned_status = 'user-has-earned';
			}
		}

		$title = $points_award->post_title;

		// If points award doesn't have a title, then try to build one
		if( empty( $title ) ) {
			$title = gamipress_build_requirement_title( $points_award->ID );
		}

		$output .= '<li class="'. apply_filters( 'gamipress_points_award_class', $earned_status, $points_award, $user_id, $template_args ) .'">'. apply_filters( 'gamipress_points_award_title_display', $title, $points_award, $user_id, $template_args ) . '</li>';
	}

	$output .= '</ul><!-- .gamipress-points-awards -->';

	// Return our output
	return $output;

}

/**
 * Filter our points awards titles to link to achievements and achievement type archives
 *
 * @since  1.0.0
 * @param  string $title 			Our points award title
 * @param  object $points_award  	Our points award's post object
 * @return string        			Our potentially updated title
 */
function gamipress_points_award_link_title_to_achievement( $title = '', $points_award = null ) {

	// Grab our points award requirements
	$points_award_requirements = gamipress_get_points_award_requirements( $points_award->ID );

	// Setup a URL to link to a specific achievement or an achievement type
	if ( ! empty( $points_award_requirements['achievement_post'] ) )
		$url = get_permalink( $points_award_requirements['achievement_post'] );
	// elseif ( ! empty( $points_award_requirements['achievement_type'] ) )
	//  $url = get_post_type_archive_link( $points_award_requirements['achievement_type'] );

	// If we have a URL, update the title to link to it
	if ( isset( $url ) && ! empty( $url ) )
		$title = '<a href="' . esc_url( $url ) . '">' . $title . '</a>';

	return $title;
}
add_filter( 'gamipress_points_award_title_display', 'gamipress_points_award_link_title_to_achievement', 10, 2 );

/**
 * Gets achievement's required steps and returns HTML markup for these steps
 *
 * @since  1.0.0
 * @param  integer $achievement_id The given achievement's post ID
 * @param  integer $user_id        A given user's ID
 *
 * @return string                  The markup for our list
 */
function gamipress_get_required_achievements_for_achievement_list( $achievement_id = 0, $user_id = 0 ) {

	// Grab the current post ID if no achievement_id was specified
	if ( ! $achievement_id ) {
		global $post;
		$achievement_id = $post->ID;
	}

	// Grab the current user's ID if none was specifed
	if ( ! $user_id )
		$user_id = wp_get_current_user()->ID;

	// Grab our achievement's required steps
	$steps = gamipress_get_required_achievements_for_achievement( $achievement_id );

	// Return our markup output
	return gamipress_get_required_achievements_for_achievement_list_markup( $steps, $user_id );

}

/**
 * Generate HTML markup for an achievement's required steps
 *
 * This will generate an unorderd list (<ul>) if steps are non-sequential
 * and an ordered list (<ol>) if steps require sequentiality.
 *
 * @since  1.0.0
 *
 * @param  array   	$steps           An achievement's required steps
 * @param  integer 	$achievement_id  The given achievement's ID
 * @param  integer 	$user_id         The given user's ID
 * @param  array	$template_args   The given template args
 *
 * @return string                   The markup for our list
 */
function gamipress_get_required_achievements_for_achievement_list_markup( $steps = array(), $achievement_id = 0, $user_id = 0, $template_args = array() ) {

	// If we don't have any steps, or our steps aren't an array, return nothing
	if ( ! $steps || ! is_array( $steps ) )
		return null;

	// Grab the current post ID if no achievement_id was specified
	if ( ! $achievement_id ) {
		global $post;
		$achievement_id = $post->ID;
	}

	$count = count( $steps );

	// If we have no steps, return nothing
	if ( ! $count )
		return null;

	// Grab the current user's ID if none was specifed
	if ( ! $user_id )
		$user_id = get_current_user_id();

	// Setup our variables
	$output = '';
	$container = gamipress_is_achievement_sequential() ? 'ol' : 'ul';

	$post_type_object = get_post_type_object( 'step' );

	$output .= '<h4>' . apply_filters( 'gamipress_steps_heading', sprintf( __( '%1$d Required %2$s', 'gamipress' ), $count, $post_type_object->labels->name ), $steps ) . '</h4>';
	$output .= '<' . $container .' class="gamipress-required-achievements">';

	// Concatenate our output
	foreach ( $steps as $step ) {

		// Check if user has earned this step, and add an 'earned' class
		$earned_status = gamipress_get_user_achievements( array(
			'user_id' => absint( $user_id ),
			'achievement_id' => absint( $step->ID ),
			'since' => absint( gamipress_achievement_last_user_activity( $achievement_id, $user_id ) )
		) ) ? 'user-has-earned' : 'user-has-not-earned';

		$title = $step->post_title;

		// If step doesn't have a title, then try to build one
		if( empty( $title ) ) {
			$title = gamipress_build_requirement_title( $step->ID );
		}

		$output .= '<li class="'. apply_filters( 'gamipress_step_class', $earned_status, $step, $user_id, $template_args ) .'">'. apply_filters( 'gamipress_step_title_display', $title, $step, $user_id, $template_args ) . '</li>';
	}

	$output .= '</'. $container .'><!-- .gamipress-required-achievements -->';

	// Return our output
	return $output;

}

/**
 * Filter our step titles to link to achievements and achievement type archives
 *
 * @since  1.0.0
 * @param  string $title Our step title
 * @param  object $step  Our step's post object
 * @return string        Our potentially updated title
 */
function gamipress_step_link_title_to_achievement( $title = '', $step = null ) {

	// Grab our step requirements
	$step_requirements = gamipress_get_step_requirements( $step->ID );

	// Setup a URL to link to a specific achievement or an achievement type
	if ( ! empty( $step_requirements['achievement_post'] ) )
		$url = get_permalink( $step_requirements['achievement_post'] );
	// elseif ( ! empty( $step_requirements['achievement_type'] ) )
	//  $url = get_post_type_archive_link( $step_requirements['achievement_type'] );

	// If we have a URL, update the title to link to it
	if ( isset( $url ) && ! empty( $url ) )
		$title = '<a href="' . esc_url( $url ) . '">' . $title . '</a>';

	return $title;
}
add_filter( 'gamipress_step_title_display', 'gamipress_step_link_title_to_achievement', 10, 2 );

/**
 * Generate markup for an achievement's points output
 *
 * @since  1.0.0
 * @param  integer $achievement_id The given achievment's ID
 * @return string                  The HTML markup for our points
 */
function gamipress_achievement_points_markup( $achievement_id = 0 ) {

	// Grab the current post ID if no achievement_id was specified
	if ( ! $achievement_id ) {
		global $post;
		$achievement_id = $post->ID;
	}

    $points_types = gamipress_get_points_types();
    $points_type = get_post_meta( $achievement_id, '_gamipress_points_type', true );

    // Default points label
    $points_label = __( '%d Points', 'gamipress' );

    if( isset( $points_types[$points_type] ) ) {
        // Points type label
        $points_label = '%d ' . $points_types[$points_type]['plural_name'];
    }

	// Return our markup
	return ( $points = get_post_meta( $achievement_id, '_gamipress_points', true ) ) ? '<div class="gamipress-achievement-points gamipress-achievement-points-type-' . $points_type . '">' . sprintf( $points_label, $points ) . '</div>' : '';
}

/**
 * Adds "earned"/"not earned" post_class based on viewer's status
 *
 * @param  array $classes Post classes
 *
 * @return array          Updated post classes
 */
function gamipress_add_earned_class_single( $classes = array() ) {

	if( is_singular( gamipress_get_achievement_types_slugs() ) ) {
		// check if current user has earned the achievement they're viewing
		$classes[] = gamipress_get_user_achievements( array( 'user_id' => get_current_user_id(), 'achievement_id' => get_the_ID() ) ) ? 'user-has-earned' : 'user-has-not-earned';
	}

	return $classes;

}
add_filter( 'post_class', 'gamipress_add_earned_class_single' );

/**
 * Returns a message if user has earned the achievement.
 *
 * @since  1.0.0
 *
 * @param  integer $achievement_id Achievement ID.
 * @param  integer $user_id        User ID.
 *
 * @return string                  HTML Markup.
 */
function gamipress_render_earned_achievement_text( $achievement_id = 0, $user_id = 0 ) {

	$earned_message = '';

	if ( gamipress_has_user_earned_achievement( $achievement_id, $user_id ) ) {

		$earned_message .= '<div class="gamipress-achievement-earned"><p>' . __( 'You have earned this achievement!', 'gamipress' ) . '</p></div>';

		if ( $congrats_text = get_post_meta( $achievement_id, '_gamipress_congratulations_text', true ) ) {
			$earned_message .= '<div class="gamipress-achievement-congratulations">' . wpautop( $congrats_text ) . '</div>';
		}
	}

	return apply_filters( 'gamipress_earned_achievement_message', $earned_message, $achievement_id, $user_id );
}

/**
 * Check if user has earned a given achievement.
 *
 * @since  1.0.0
 *
 * @param  integer $achievement_id Achievement ID.
 * @param  integer $user_id        User ID.
 *
 * @return bool                    True if user has earned the achievement, otherwise false.
 */
function gamipress_has_user_earned_achievement( $achievement_id = 0, $user_id = 0 ) {

	$earned_achievements = gamipress_get_user_achievements( array( 'user_id' => absint( $user_id ), 'achievement_id' => absint( $achievement_id ) ) );
	$earned_achievement = ! empty( $earned_achievements );

	return apply_filters( 'gamipress_has_user_earned_achievement', $earned_achievement, $achievement_id, $user_id );

}

/**
 * Get current page post id
 *
 * @since  1.0.0
 *
 * @return integer
 */
function gamipress_get_current_page_post_id() {

	global $posts;

	$current_post_id = null;

	foreach($posts as $post){
		if($post->post_type != 'page') {
			//Get current page achievement id
			$current_post_id = $post->ID;
		}
	}

	//Return current post id
    return $current_post_id;
}


/**
 * Hide the hidden achievement post link from next post link
 *
 * @since  1.0.0
 *
 * @param $link
 *
 * @return string
 */
function gamipress_hide_next_hidden_achievement_link($link) {

	if($link) {

		// Get current achievement id
		$achievement_id = gamipress_get_current_page_post_id();

		// Get post link , without hidden achievement
		$link = gamipress_get_post_link_without_hidden_achievement( $achievement_id, 'next' );

	}

	return $link;

}
add_filter('next_post_link', 'gamipress_hide_next_hidden_achievement_link');

/**
 * Hide the hidden achievement post link from previous post link
 *
 * @since  1.0.0
 *
 * @param $link
 *
 * @return string
 */
function gamipress_hide_previous_hidden_achievement_link($link) {

	if($link) {

		//Get current achievement id
		$achievement_id = gamipress_get_current_page_post_id();

		//Get post link , without hidden achievement
		$link = gamipress_get_post_link_without_hidden_achievement($achievement_id, 'prev');

	}

	return $link;

}

add_filter('previous_post_link', 'gamipress_hide_previous_hidden_achievement_link');


/**
 * Get post link without hidden achievement link
 *
 * @param $achievement_id
 * @param $rel
 * @return string
 */
function gamipress_get_post_link_without_hidden_achievement($achievement_id, $rel) {


	$link = null;

	$post = get_post($achievement_id);

	//Check the ahievement
	$achievement_id = ( gamipress_is_achievement($post) )? $post->ID : "";

	//Get next post id without hidden achievement id
	$next_post_id = gamipress_get_next_previous_achievement_id($achievement_id, $rel);

	if ($next_post_id)
		//Generate post link
		$link = gamipress_generate_post_link_by_post_id($next_post_id, $rel);


	return $link;

}

/**
 * Get next or previous post id , without hidden achievement id
 *
 * @param $achievement_id
 * @param $rel
 * @return integer
 */
function gamipress_get_next_previous_achievement_id( $achievement_id , $rel ){

	$nested_post_id = null;

	$access = false;

	// Redirecting user page based on achievements
	$post = get_post( absint( $achievement_id ));

	//Get hidden achievements ids
	$hidden = gamipress_get_hidden_achievement_ids( $post->post_type );

	// Fetching achievement types
	$param = array(
		'posts_per_page'   => -1, // All achievements
		'offset'           => 0,  // Start from first achievement
		'post_type'=> $post->post_type, // set post type as achievement to filter only achievements
		'orderby' => 'ID',
		'order' => 'ASC',
	);

	$param['order'] = ($rel == 'next') ? 'ASC' : 'DESC';


	$achievement_types = get_posts($param);

	foreach ($achievement_types as $achievement){

		$check = false;

		//Compare next achievement
		if($achievement->ID > $achievement_id && $rel == 'next') {
			$check = true;
		}

		//Compare previous achievement
		if($achievement->ID < $achievement_id && $rel == 'prev') {
			$check = true;
		}

		if($check){
			// Checks achievement in hidden achievements
			if (in_array($achievement->ID, $hidden)) {
				continue;
			} else {
				$access = true;
			}
		}

		if($access) {
			// Get next or previous achievement without hidden achievements
			if (!in_array($achievement->ID, $hidden) && !$nested_post_id) {
				$nested_post_id = $achievement->ID;
			}
		}
	}

	// return next or previous achievement without hidden achievement id
	return $nested_post_id;

}

/**
 * Generate the post link based on custom post object
 *
 * @param $post_id
 * @param $rel
 *
 * @return string
 */
function gamipress_generate_post_link_by_post_id( $post_id , $rel) {

	global $post;

	if( ! empty($post_id) )
		$post = get_post($post_id);

    //Title of the post
	$title = get_the_title( $post->ID );

	if ( empty( $post->post_title ) && $rel == 'next')
		$title = __( 'Next Post' );

	if ( empty( $post->post_title ) && $rel == 'prev')
		$title = __( 'Previous Post' );


	$rel =  ($rel == 'prev') ? 'prev' : 'next';

	$nav_prev = ($rel == 'prev') ? '<span class="meta-nav">←</span> ' : '';
	$nav_next = ($rel == 'next') ? ' <span class="meta-nav">→</span>' : '';

	// Build link
	$link = '<a href="' . get_permalink( $post ) . '" rel="'.$rel.'">' . $nav_prev . $title . $nav_next. '</a>';

	return $link;

}

/**
 * Filters the log title.
 *
 * @param string $title The log title.
 * @param int    $id    The log ID.
 *
 * @return string 		The formatted title
 */
function gamipress_log_title_format( $title, $id = null ) {

	if( $id === null ) {
		return $title;
	}

	if( empty( $title ) ) {
		$title = gamipress_get_parsed_log( $id );
	}

	return $title;
}
add_filter( 'gamipress_render_log_title', 'gamipress_log_title_format', 10, 2 );

/**
 * Gets rank's required steps and returns HTML markup for these steps
 *
 * @since  1.0.0
 * @param  integer $rank_id 		The given rank's post ID
 * @param  integer $user_id        	A given user's ID
 *
 * @return string                  	The markup for our list
 */
function gamipress_get_rank_requirements_list( $rank_id = 0, $user_id = 0 ) {

	// Grab the current post ID if no rank_id was specified
	if ( ! $rank_id ) {
		global $post;
		$rank_id = $post->ID;
	}

	// Grab the current user's ID if none was specifed
	if ( ! $user_id )
		$user_id = wp_get_current_user()->ID;

	// Grab our rank's required requirements
	$requirements = gamipress_get_rank_requirements( $rank_id );

	// Return our markup output
	return gamipress_get_rank_requirements_list_markup( $requirements, $user_id );

}

/**
 * Generate HTML markup for an achievement's required steps
 *
 * This will generate an unorderd list (<ul>) if steps are non-sequential
 * and an ordered list (<ol>) if steps require sequentiality.
 *
 * @since  1.3.1
 *
 * @param  array   	$requirements    An rank's required requirements
 * @param  integer 	$rank_id  The given rank's ID
 * @param  integer 	$user_id         The given user's ID
 * @param  array	$template_args   The given template args
 *
 * @return string                   The markup for our list
 */
function gamipress_get_rank_requirements_list_markup( $requirements = array(), $rank_id = 0, $user_id = 0, $template_args = array() ) {

	// If we don't have any steps, or our steps aren't an array, return nothing
	if ( ! $requirements || ! is_array( $requirements ) )
		return null;

	// Grab the current post ID if no achievement_id was specified
	if ( ! $rank_id ) {
		global $post;
		$rank_id = $post->ID;
	}

	$count = count( $requirements );

	// If we have no steps, return nothing
	if ( ! $count )
		return null;

	// Grab the current user's ID if none was specifed
	if ( ! $user_id )
		$user_id = get_current_user_id();

	// Setup our variables
	$output = '';
	$container = gamipress_is_achievement_sequential() ? 'ol' : 'ul';

	$post_type_object = get_post_type_object( 'rank-requirement' );

	$output .= '<h4>' . apply_filters( 'gamipress_rank_requirements_heading', $count . ' ' . _n( 'Requirement', 'Requirements', $count, 'gamipress' ), $requirements ) . '</h4>';
	$output .= '<' . $container .' class="gamipress-required-requirements">';

	// Concatenate our output
	foreach ( $requirements as $requirement ) {

		// check if user has earned this Achievement, and add an 'earned' class
		$earned_status = gamipress_get_user_achievements( array(
			'user_id' => absint( $user_id ),
			'achievement_id' => absint( $requirement->ID ),
			'since' => absint( gamipress_achievement_last_user_activity( $requirement->ID, $user_id ) )
		) ) ? 'user-has-earned' : 'user-has-not-earned';

		$title = $requirement->post_title;

		// If step doesn't have a title, then try to build one
		if( empty( $title ) ) {
			$title = gamipress_build_requirement_title( $requirement->ID );
		}

		$output .= '<li class="'. apply_filters( 'gamipress_rank_requirement_class', $earned_status, $requirement, $user_id, $template_args ) .'">'. apply_filters( 'gamipress_rank_requirement_title_display', $title, $requirement, $user_id, $template_args ) . '</li>';
	}

	$output .= '</'. $container .'><!-- .gamipress-requirements -->';

	// Return our output
	return $output;

}

/**
 * Filter our step titles to link to achievements and achievement type archives
 *
 * @since  1.3.1
 *
 * @param  string $title Our rank requirement title
 * @param  object $rank_requirement  Our rank requirement's post object
 *
 * @return string        Our potentially updated title
 */
function gamipress_rank_requirement_link_title_to_achievement( $title = '', $rank_requirement = null ) {

	// Grab our rank requirement requirements
	$requirements = gamipress_get_rank_requirement_requirements( $rank_requirement->ID );

	// Setup a URL to link to a specific achievement or an achievement type
	if ( ! empty( $requirements['achievement_post'] ) )
		$url = get_permalink( $requirements['achievement_post'] );
	// elseif ( ! empty( $requirements['achievement_type'] ) )
	//  $url = get_post_type_archive_link( $requirements['achievement_type'] );

	// If we have a URL, update the title to link to it
	if ( isset( $url ) && ! empty( $url ) )
		$title = '<a href="' . esc_url( $url ) . '">' . $title . '</a>';

	return $title;
}
add_filter( 'gamipress_rank_requirement_title_display', 'gamipress_rank_requirement_link_title_to_achievement', 10, 2 );

/**
 * Helper function tests that we're in the main loop
 *
 * @since  1.3.1
 *
 * @param  bool|integer $id The page id
 *
 * @return boolean     A boolean determining if the function is in the main loop
 */
function gamipress_is_single_rank( $id = false ) {

	$slugs = gamipress_get_rank_types_slugs();

	// only run our filters on the rank singular page
	if ( is_admin() || empty( $slugs ) || ! is_singular( $slugs ) )
		return false;
	// w/o id, we're only checking template context
	if ( ! $id )
		return true;

	// Checks several variables to be sure we're in the main loop (and won't effect things like post pagination titles)
	return ( ( $GLOBALS['post']->ID == $id ) && in_the_loop() && empty( $GLOBALS['gamipress_reformat_content'] ) );

}

/**
 * Returns a message if user has earned the rank.
 *
 * @since  1.3.1
 *
 * @param  integer $rank_id 		Rank ID.
 * @param  integer $user_id        	User ID.
 *
 * @return string                  	HTML Markup.
 */
function gamipress_render_earned_rank_text( $rank_id = 0, $user_id = 0 ) {

	$earned_message = '';

	if ( gamipress_has_user_earned_rank( $rank_id, $user_id ) ) {

		$earned_message .= '<div class="gamipress-rank-earned"><p>' . __( 'You have reached this rank!', 'gamipress' ) . '</p></div>';

		if ( $congrats_text = get_post_meta( $rank_id, '_gamipress_congratulations_text', true ) ) {
			$earned_message .= '<div class="gamipress-rank-congratulations">' . wpautop( $congrats_text ) . '</div>';
		}
	}

	return apply_filters( 'gamipress_earned_rank_message', $earned_message, $rank_id, $user_id );

}

/**
 * Check if user has earned a given achievement.
 *
 * @since  1.3.1
 *
 * @param  integer $rank_id 		Rank ID.
 * @param  integer $user_id        	User ID.
 *
 * @return bool                    	True if user has earned the rank, otherwise false.
 */
function gamipress_has_user_earned_rank( $rank_id = 0, $user_id = 0 ) {

	$earned_achievements = gamipress_get_user_achievements( array( 'user_id' => absint( $user_id ), 'achievement_id' => absint( $rank_id ) ) );
	$earned_achievement = ! empty( $earned_achievements );

	return apply_filters( 'gamipress_has_user_earned_rank', $earned_achievement, $rank_id, $user_id );

}

/**
 * Auto flush permalinks wth a soft flush when a 404 error is detected on an GamiPress page
 *
 * Taken from Easy Digital Downloads
 *
 * @since 1.3.3
 * @return string
 */
function gamipress_refresh_permalinks_on_bad_404() {

	global $wp;

	if( ! is_404() ) {
		return;
	}

	if( isset( $_GET['gamipress-flush'] ) ) {
		return;
	}

	if( false === get_transient( 'gamipress_refresh_404_permalinks' ) ) {

		$gamipress_slugs = array();

		$gamipress_slugs = array_merge( $gamipress_slugs, gamipress_get_achievement_types() );
		$gamipress_slugs = array_merge( $gamipress_slugs, gamipress_get_points_types() );
		$gamipress_slugs = array_merge( $gamipress_slugs, gamipress_get_rank_types() );

		$parts = explode( '/', $wp->request );

		$is_a_gamipress_page = false;

		foreach( $parts as $part ) {

			if( isset( $gamipress_slugs[$part] ) ) {
				$is_a_gamipress_page = true;
				break;
			}

		}

		if( ! $is_a_gamipress_page ) {
			return;
		}

		flush_rewrite_rules( false );

		set_transient( 'gamipress_refresh_404_permalinks', 1, HOUR_IN_SECONDS * 12 );

		wp_redirect( home_url( add_query_arg( array( 'gamipress-flush' => 1 ), $wp->request ) ) ); exit;

	}
}
add_action( 'template_redirect', 'gamipress_refresh_permalinks_on_bad_404' );