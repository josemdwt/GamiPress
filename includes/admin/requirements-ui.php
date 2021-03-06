<?php
/**
 * Requirements UI
 *
 * @package     GamiPress\Admin\Requirements_UI
 * @since       1.0.0
 */
// Exit if accessed directly
if( !defined( 'ABSPATH' ) ) exit;

/**
 * Add Points Awards and Steps meta boxes
 *
 * @since  1.0.5
 *
 * @return void
 */
function gamipress_add_requirements_ui_meta_box() {

    // Points Awards
    add_meta_box( 'gamipress-requirements-ui', __( 'Automatic Points Awards', 'gamipress' ), 'gamipress_requirements_ui_meta_box', 'points-type', 'advanced', 'default' );

    // Steps
    foreach ( gamipress_get_achievement_types_slugs() as $achievement_type ) {
        add_meta_box( 'gamipress-requirements-ui', __( 'Required Steps', 'gamipress' ), 'gamipress_requirements_ui_meta_box', $achievement_type, 'advanced', 'default' );
    }

    // Rank Requirements
    foreach ( gamipress_get_rank_types_slugs() as $rank_type ) {
        add_meta_box( 'gamipress-requirements-ui', __( 'Rank Requirements', 'gamipress' ), 'gamipress_requirements_ui_meta_box', $rank_type, 'advanced', 'default' );
    }

}
add_action( 'add_meta_boxes', 'gamipress_add_requirements_ui_meta_box' );

/**
 * Renders the HTML for meta box, refreshes whenever a new point award is added
 *
 * @since  1.0.5
 *
 * @param  WP_Post $post The current post object
 *
 * @return void
 */
function gamipress_requirements_ui_meta_box( $post = null ) {

    // Define the requirement type to use
    $requirement_type = gamipress_requirements_ui_get_requirement_type( $post );

    // Setup the requirement object based on the requirement type
    $requirement_types = gamipress_get_requirement_types();
    $requirement_type_object = $requirement_types[$requirement_type];

    // Get assigned requirements
    $assigned_requirements = gamipress_get_assigned_requirements( $post->ID, $requirement_type );

    if( ! $assigned_requirements ) {
        $assigned_requirements = array();
    }

    // Loop through each requirement and set the sort order
    foreach ( $assigned_requirements as $assigned_requirement ) {
        $assigned_requirement->order = gamipress_get_requirement_menu_order( $assigned_requirement->ID );
    }

    // Sort the requirements by their order
    uasort( $assigned_requirements, 'gamipress_compare_requirements_order' );

    switch( $requirement_type ) {
        case 'points-award':
            echo '<p>' .
                __( 'Define the automatic ways an user could retrieve an amount of this points type. Use the "Label" field to optionally customize the titles of each one.', 'gamipress' )
            . '</p>';
            break;
        case 'step':
            echo '<p>' .
                __( 'Define the required "steps" for this achievement to be considered complete. Use the "Label" field to optionally customize the titles of each step.', 'gamipress' )
            . '</p>';
            break;
        case 'rank-requirement':
            echo '<p>' .
                __( 'Define the required requirements for this rank to be considered that user is ranked on it. Use the "Label" field to optionally customize the titles of each requirement.', 'gamipress' )
                . '<br>'
                . __( '<strong>Important!</strong> User will not earn this requirements until be ranked on the previous rank.', 'gamipress' )
            . '</p>';
            break;
    }

    do_action( 'gamipress_before_requirements_list', $requirement_type, $requirement_type_object, $assigned_requirements );

    // Concatenate our requirements output
    echo '<ul id="requirements-list">';
    foreach ( $assigned_requirements as $requirement ) {
        gamipress_requirement_ui_html( $requirement->ID, $post->ID );
    }
    echo '</ul>';

    // Render our buttons ?>
    <input style="margin-right: 1em" class="button" type="button" onclick="gamipress_add_requirement(<?php echo $post->ID; ?>);" value="<?php printf( __( 'Add New %s', 'gamipress' ), $requirement_type_object['singular_name'] ); ?>">
    <input class="button-primary" type="button" onclick="gamipress_update_requirements();" value="<?php printf( __( 'Save All %s', 'gamipress' ), $requirement_type_object['plural_name'] ); ?>">
    <span class="spinner requirements-spinner"></span>
    <?php
}

/**
 * Return post assigned requirements
 *
 * @since 1.0.0
 *
 * @param null $post_id
 * @param $requirement_type
 *
 * @return array|bool
 */
function gamipress_get_assigned_requirements( $post_id = null, $requirement_type ) {
    global $post;

    if( $post_id === null ) {
        $post_id = $post->ID;
    }

    if( $requirement_type === 'points-award' ) {
        // Grab points type's requirements
        return gamipress_get_points_type_points_awards( $post_id );
    } else {
        // Grab post's requirements (valid for achievement's steps and rank's requirements)
        return get_posts( array(
            'post_type'           => $requirement_type,
            'posts_per_page'      => -1,
            'suppress_filters'    => false,
            'connected_direction' => 'to',
            'connected_type'      => $requirement_type . '-to-' . get_post_type( $post_id ),
            'connected_items'     => $post_id,
        ));
    }

    return false;
}

/**
 * Helper function for generating the HTML output for configuring a given requirement
 *
 * @since  1.0.5
 *
 * @param  integer $requirement_id The given requirement's ID
 * @param  integer $post_id The given requirement's parent $post ID
 *
 * @return string           The concatenated HTML input for the requirement
 */
function gamipress_requirement_ui_html( $requirement_id = 0, $post_id = 0 ) {

    // Grab our requirement's requirements and measurement
    $requirements      = gamipress_get_requirement_object( $requirement_id );
    $requirement_type  = get_post_type( $requirement_id );
    $count             = ! empty( $requirements['count'] ) ? $requirements['count'] : 1;
    $limit             = ! empty( $requirements['limit'] ) ? $requirements['limit'] : 1;
    $limit_type        = ! empty( $requirements['limit_type'] ) ? $requirements['limit_type'] : 'unlimited';
    ?>

    <li class="requirement-row requirement-<?php echo $requirement_id; ?>" data-requirement-id="<?php echo $requirement_id; ?>">

        <div class="requirement-handle"></div>

        <a class="delete-requirement" href="javascript: gamipress_delete_requirement( <?php echo $requirement_id; ?> );">
            <span class="dashicons dashicons-no-alt"></span>
        </a>

        <input type="hidden" name="requirement_id" value="<?php echo $requirement_id; ?>" />
        <input type="hidden" name="requirement_type" value="<?php echo $requirement_type; ?>" />
        <input type="hidden" name="post_id" value="<?php echo $post_id; ?>" />
        <input type="hidden" name="order" value="<?php echo absint( gamipress_get_requirement_menu_order( $requirement_id ) ); ?>" />

        <label for="select-trigger-type-<?php echo $requirement_id; ?>"><?php _e( 'When', 'gamipress' ); ?>:</label>

        <?php do_action( 'gamipress_requirement_ui_html_after_require_text', $requirement_id, $post_id ); ?>

        <select id="select-trigger-type-<?php echo $requirement_id; ?>" class="select-trigger-type" data-requirement-id="<?php echo $requirement_id; ?>">
            <?php
            $activity_triggers = gamipress_get_activity_triggers();

            // Grouped activity triggers
            foreach ( $activity_triggers as $group => $group_triggers ) : ?>
                <optgroup label="<?php echo esc_attr( $group ); ?>">
                    <?php foreach( $group_triggers as $trigger => $label ) : ?>
                        <option value="<?php echo esc_attr( $trigger ); ?>" <?php selected( $requirements['trigger_type'], $trigger, true ); ?>><?php echo esc_html( $label ); ?></option>
                    <?php endforeach; ?>
                </optgroup>
            <?php endforeach; ?>
        </select>

        <?php do_action( 'gamipress_requirement_ui_html_after_trigger_type', $requirement_id, $post_id ); ?>

        <input type="number" name="requirement-points-required" id="requirement-<?php echo $requirement_id; ?>-points-required" class="points-required" value="<?php echo ( $requirements['points_required'] === 0 ? 1 : $requirements['points_required'] ); ?>" />

        <?php do_action( 'gamipress_requirement_ui_html_after_points_required', $requirement_id, $post_id ); ?>

        <select class="select-points-type-required select-points-type-required-<?php echo $requirement_id; ?>">
            <option value=""><?php _e( 'Default points', 'gamipress'); ?></option>
            <?php foreach ( gamipress_get_points_types() as $slug => $data ) :

                echo '<option value="' . $slug . '" ' . selected( $requirements['points_type_required'], $slug, false ) . '>' . $data['plural_name'] . '</option>';

            endforeach; ?>
        </select>

        <?php do_action( 'gamipress_requirement_ui_html_after_points_type_required', $requirement_id, $post_id ); ?>

        <select class="select-rank-type-required select-rank-type-required-<?php echo $requirement_id; ?>">
            <?php foreach ( gamipress_get_rank_types() as $slug => $data ) :

                echo '<option value="' . $slug . '" ' . selected( $requirements['rank_type_required'], $slug, false ) . '>' . $data['singular_name'] . '</option>';

            endforeach; ?>
        </select>

        <?php do_action( 'gamipress_requirement_ui_html_after_rank_type_required', $requirement_id, $post_id ); ?>

        <select class="select-rank-required select-rank-required-<?php echo $requirement_id; ?>">
            <option value=""><?php _e( 'Choose a rank', 'gamipress'); ?></option>
        </select>

        <?php do_action( 'gamipress_requirement_ui_html_after_rank_required', $requirement_id, $post_id ); ?>

        <select class="select-achievement-type select-achievement-type-<?php echo $requirement_id; ?>">
            <option value=""><?php _e( 'Choose an achievement type', 'gamipress'); ?></option>
            <?php foreach ( gamipress_get_achievement_types() as $slug => $data ) :

                echo '<option value="' . $slug . '" ' . selected( $requirements['achievement_type'], $slug, false ) . '>' . $data['plural_name'] . '</option>';

            endforeach; ?>
        </select>

        <?php do_action( 'gamipress_requirement_ui_html_after_achievement_type', $requirement_id, $post_id ); ?>

        <select class="select-achievement-post select-achievement-post-<?php echo $requirement_id; ?>">
            <option value=""><?php _e( 'Choose an achievement', 'gamipress'); ?></option>
        </select>

        <select class="select-post select-post-<?php echo $requirement_id; ?>">
            <?php if( ! empty( $requirements['achievement_post'] ) ) : ?>
                <option value="<?php esc_attr_e( $requirements['achievement_post'] ); ?>" selected="selected"><?php echo get_post_field( 'post_title', $requirements['achievement_post'] ); ?></option>
            <?php endif; ?>
        </select>

        <?php do_action( 'gamipress_requirement_ui_html_after_achievement_post', $requirement_id, $post_id ); ?>

        <input class="required-count" type="number" min="1" value="<?php echo $count; ?>" placeholder="1">
        <span class="required-count-text"><?php _e( 'time(s)', 'gamipress' ); ?></span>

        <?php do_action( 'gamipress_requirement_ui_html_after_count', $requirement_id, $post_id ); ?>

        <span class="limit-text"><?php _e( 'limited to', 'gamipress' ); ?></span>
        <input class="limit" type="number" min="1" value="<?php echo $limit; ?>" placeholder="1">
        <select class="limit-type">
            <option value="unlimited" <?php selected( $limit_type, 'unlimited' ); ?>><?php _e( 'Unlimited', 'gamipress' ); ?></option>
            <option value="daily" <?php selected( $limit_type, 'daily' ); ?>><?php _e( 'Per Day', 'gamipress' ); ?></option>
            <option value="weekly" <?php selected( $limit_type, 'weekly' ); ?>><?php _e( 'Per Week', 'gamipress' ); ?></option>
            <option value="monthly" <?php selected( $limit_type, 'monthly' ); ?>><?php _e( 'Per Month', 'gamipress' ); ?></option>
            <option value="yearly" <?php selected( $limit_type, 'yearly' ); ?>><?php _e( 'Per Year', 'gamipress' ); ?></option>
        </select>

        <?php do_action( 'gamipress_requirement_ui_html_after_limit', $requirement_id, $post_id ); ?>

        <?php if( $requirement_type === 'points-award' ) :
            $points                 = ! empty( $requirements['points'] ) ? $requirements['points'] : 1;
            $points_singular_name   = get_post_field( 'post_title', $post_id );
            $post_name              = get_post_field( 'post_name', $post_id );
            $points_type            = $requirements['points_type'];
            $maximum_earnings       = absint( $requirements['maximum_earnings'] );

            // Ensure points type is properly set, if not force update it
            if( $points_type !== $post_name ) {
                update_post_meta( $requirement_id, '_gamipress_points_type', $post_name );
                $points_type = $post_name;
            }

            if( ! $points_singular_name ) {
                $points_singular_name = __( 'point(s)', 'gamipress' );
            } else {
                $points_singular_name = strtolower( $points_singular_name . '(s)' );
            }
            ?>
            <div class="requirement-awards">
                <label for="requirement-<?php echo $requirement_id; ?>-points"><?php _e( 'Earn', 'gamipress' ); ?>:</label>
                <input type="number" name="requirement-points" id="requirement-<?php echo $requirement_id; ?>-points" class="points" value="<?php echo $points; ?>" />
                <span class="points-text"><?php echo $points_singular_name; ?></span>
                <input type="hidden" name="points_type" value="<?php echo $points_type; ?>">


                <span class="maximum-earnings-text"><?php _e( 'with a maximum number of times to earn it of', 'gamipress' ); ?></span>
                <input type="number" min="0" name="requirement-maximum-earnings" id="requirement-<?php echo $requirement_id; ?>-maximum-earnings" class="maximum-earnings" value="<?php echo $maximum_earnings; ?>" />
                <span class="maximum-earnings-notice"><?php _e( '(0 for no maximum)', 'gamipress' ); ?></span>
            </div>

            <?php do_action( 'gamipress_requirement_ui_html_after_points', $requirement_id, $post_id ); ?>
        <?php endif; ?>

        <div class="requirement-title">
            <label for="requirement-<?php echo $requirement_id; ?>-title"><?php _e( 'Label', 'gamipress' ); ?>:</label>
            <input type="text" name="requirement-title" id="requirement-<?php echo $requirement_id; ?>-title" class="title" value="<?php echo get_the_title( $requirement_id ); ?>" />
        </div>
    </li>
    <?php
}

/**
 * Define the requirement type to use
 *
 * @since 1.3.1
 *
 * @param WP_Post $post
 *
 * @return string
 */
function gamipress_requirements_ui_get_requirement_type( $post ) {

    $requirement_type = '';

    if( $post->post_type === 'points-type' ) {
        $requirement_type = 'points-award';
    } else if( gamipress_is_achievement( $post ) ) {
        $requirement_type = 'step';
    } else if( gamipress_is_rank( $post ) ) {
        $requirement_type = 'rank-requirement';
    }

    return apply_filters( 'gamipress_requirements_ui_get_requirement_type', $requirement_type, $post );
}

/**
 * Get all the requirements of a given points award
 *
 * @since  1.0.0
 *
 * @param  integer $point_award_id The given requirement's post ID
 *
 * @return array|bool       An array of all the requirement requirements if it has any, false if not
 */
function gamipress_get_points_award_requirements( $point_award_id = 0 ) {

    $requirements = gamipress_get_requirement_object( $point_award_id );

    return apply_filters( 'gamipress_get_points_award_requirements', $requirements, $point_award_id );
}

/**
 * Get all the requirements of a given step
 *
 * @since  1.0.0
 *
 * @param  integer $step_id The given requirement's post ID
 *
 * @return array|bool       An array of all the requirement requirements if it has any, false if not
 */
function gamipress_get_step_requirements( $step_id = 0 ) {

    $requirements = gamipress_get_requirement_object( $step_id );

    return apply_filters( 'gamipress_get_step_requirements', $requirements, $step_id );
}

/**
 * Get all the requirements of a given rank requirement
 *
 * @since  1.3.1
 *
 * @param  integer $rank_requirement_id The given requirement's post ID
 *
 * @return array|bool       An array of all the requirement requirements if it has any, false if not
 */
function gamipress_get_rank_requirement_requirements( $rank_requirement_id = 0 ) {

    $requirements = gamipress_get_requirement_object( $rank_requirement_id );

    return apply_filters( 'gamipress_get_rank_requirement_requirements', $requirements, $rank_requirement_id );
}

/**
 * AJAX Handler for adding a new requirement
 *
 * @since 1.0.0
 */
function gamipress_add_requirement_ajax_handler() {

    $post = get_post( $_POST['post_id'] );

    $requirement_type = gamipress_requirements_ui_get_requirement_type( $post );

    // Create a new requirement post and grab it's ID
    $requirement_id = wp_insert_post( array(
        'post_type'   => $requirement_type,
        'post_status' => 'publish'
    ) );

    // Output the edit requirement html to insert into the requirements meta box
    gamipress_requirement_ui_html( $requirement_id, $post->ID );

    // Create the P2P connection from the requirement to the achievement
    $p2p_id = p2p_create_connection(
        $requirement_type . '-to-' . $post->post_type,
        array(
            'from' => $requirement_id,
            'to'   => $post->ID,
            'meta' => array(
                'date' => current_time( 'mysql' )
            )
        )
    );

    // Add relevant meta to our P2P connection
    p2p_add_meta( $p2p_id, 'order', '0' );

    // Die here, because it's AJAX
    die;
}
add_action( 'wp_ajax_gamipress_add_requirement', 'gamipress_add_requirement_ajax_handler' );

/**
 * AJAX Handler for deleting a requirement
 *
 * @since 1.0.0
 */
function gamipress_delete_requirement_ajax_handler() {
    wp_delete_post( $_POST['requirement_id'] );
    die;
}
add_action( 'wp_ajax_gamipress_delete_requirement', 'gamipress_delete_requirement_ajax_handler' );

/**
 * AJAX Handler for saving all requirements
 *
 * @since 1.0.5
 */
function gamipress_update_requirements_ajax_handler() {

    // Only continue if we have any requirements
    if ( isset( $_POST['requirements'] ) ) {

        // Grab our $wpdb global
        global $wpdb;

        // Setup an array for storing all our requirement titles
        // This lets us dynamically update the Label field when requirements are saved
        $new_titles = array();

        // Loop through each of the created requirements
        foreach ( $_POST['requirements'] as $key => $requirement ) {

            // Grab all of the relevant values of that requirement
            $requirement_id         = $requirement['requirement_id'];
            $requirement_type       = get_post_type( $requirement_id );
            $required_count         = ( ! empty( $requirement['required_count'] ) ) ? absint( $requirement['required_count'] ) : 1;
            $points_required        = ( ! empty( $requirement['points_required'] ) ) ? absint( $requirement['points_required'] ) : 1;
            $points_type_required   = ( ! empty( $requirement['points_type_required'] ) ) ? $requirement['points_type_required'] : '';
            $rank_type_required     = ( ! empty( $requirement['rank_type_required'] ) ) ? $requirement['rank_type_required'] : '';
            $rank_required          = ( ! empty( $requirement['rank_required'] ) ) ? absint( $requirement['rank_required'] ) : 0;
            $limit                  = ( ! empty( $requirement['limit'] ) ) ? absint( $requirement['limit'] ) : 1;
            $limit_type             = ( ! empty( $requirement['limit_type'] ) ) ? $requirement['limit_type'] : 'unlimited';
            $trigger_type           = $requirement['trigger_type'];
            $achievement_type       = $requirement['achievement_type'];

            // Clear all relation data
            $wpdb->query( $wpdb->prepare( "DELETE FROM $wpdb->p2p WHERE p2p_to=%d", $requirement_id ) );
            delete_post_meta( $requirement_id, '_gamipress_achievement_post' );

            // Connect the achievement with the requirement
            if( $trigger_type === 'specific-achievement' ) {
                p2p_create_connection(
                    $requirement['achievement_type'] . '-to-' . $requirement_type,
                    array(
                        'from' => absint( $requirement['achievement_post'] ),
                        'to'   => $requirement_id,
                        'meta' => array(
                            'date' => current_time('mysql')
                        )
                    )
                );
            }

            // Specific activity trigger type
            if( in_array( $trigger_type, array_keys( gamipress_get_specific_activity_triggers() ) ) ) {
                $achievement_post_id = absint( $requirement['achievement_post'] );

                // Update achievement post to check it on rules engine
                update_post_meta( $requirement_id, '_gamipress_achievement_post', $achievement_post_id );
            }

            // Update the requirement order
            p2p_update_meta( gamipress_get_requirement_connection_id( $requirement_id ), 'order', $key );

            // Update our relevant meta
            update_post_meta( $requirement_id, '_gamipress_points_required', $points_required );
            update_post_meta( $requirement_id, '_gamipress_points_type_required', $points_type_required );
            update_post_meta( $requirement_id, '_gamipress_rank_type_required', $rank_type_required );
            update_post_meta( $requirement_id, '_gamipress_rank_required', $rank_required );
            update_post_meta( $requirement_id, '_gamipress_count', $required_count );
            update_post_meta( $requirement_id, '_gamipress_limit', $limit );
            update_post_meta( $requirement_id, '_gamipress_limit_type', $limit_type );
            update_post_meta( $requirement_id, '_gamipress_trigger_type', $trigger_type );
            update_post_meta( $requirement_id, '_gamipress_achievement_type', $achievement_type );

            // Specific points award data
            if( $requirement_type === 'points-award' ) {
                $points           = ( ! empty( $requirement['points'] ) ) ? absint( $requirement['points'] ) : 1;
                $points_type      = ( ! empty( $requirement['points_type'] ) ) ? $requirement['points_type'] : '';
                $maximum_earnings = ( ! $requirement['maximum_earnings'] !== "" ) ? absint( $requirement['maximum_earnings'] ) : 1;

                update_post_meta( $requirement_id, '_gamipress_points', $points );
                update_post_meta( $requirement_id, '_gamipress_points_type', $points_type );
                update_post_meta( $requirement_id, '_gamipress_maximum_earnings', $maximum_earnings );
            }

            // Action to store custom requirement data
            do_action( 'gamipress_ajax_update_requirement', $requirement_id, $requirement );

            // Update our original post with the new title
            $post_title = ! empty( $requirement['title'] ) ? $requirement['title'] : gamipress_build_requirement_title( $requirement_id, $requirement );
            wp_update_post( array( 'ID' => $requirement_id, 'post_title' => $post_title ) );

            // Add the title to our AJAX return
            $new_titles[$requirement_id] = stripslashes( $post_title );

        }

        // Send back all our requirement titles
        echo json_encode( $new_titles );

    }

    // We're done here.
    die;

}
add_action( 'wp_ajax_gamipress_update_requirements', 'gamipress_update_requirements_ajax_handler' );

/**
 * Generate a requirement title based on his configuration
 *
 * @since 1.0.5
 *
 * @param $requirement_id   integer The requirement ID
 * @param $requirement      array The requirement array object (Optional)
 *
 * @return string
 */
function gamipress_build_requirement_title( $requirement_id, $requirement = array() ) {

    if( empty( $requirement ) ) {
        $requirement = gamipress_get_requirement_object( $requirement_id );
    }

    $requirement_type       = get_post_type( $requirement_id );
    $points_required        = ( ! empty( $requirement['points_required'] ) ) ? absint( $requirement['points_required'] ) : 1;
    $points_type_required   = ( ! empty( $requirement['points_type_required'] ) ) ? $requirement['points_type_required'] : '';
    $rank_type_required     = ( ! empty( $requirement['rank_type_required'] ) ) ? $requirement['rank_type_required'] : '';
    $rank_required          = ( ! empty( $requirement['rank_required'] ) ) ? absint( $requirement['rank_required'] ) : 0;
    $required_count         = ( ! empty( $requirement['required_count'] ) ) ? absint( $requirement['required_count'] ) : 1;
    $limit                  = ( ! empty( $requirement['limit'] ) ) ? absint( $requirement['limit'] ) : 1;
    $limit_type             = ( ! empty( $requirement['limit_type'] ) ) ? $requirement['limit_type'] : 'unlimited';
    $trigger_type           = $requirement['trigger_type'];
    $achievement_type       = $requirement['achievement_type'];

    // Flip between our requirement types and make an appropriate connection
    switch ( $trigger_type ) {
        case 'earn-points':
            $points_types = gamipress_get_points_types();

            $points_types[''] = array(
                'singular_name' => __( 'Point', 'gamipress' ),
                'plural_name' => __( 'Points', 'gamipress' ),
            );

            $singular = strtolower( $points_types[$points_type_required]['singular_name'] );
            $plural = strtolower( $points_types[$points_type_required]['plural_name'] );

            $title = sprintf( __( 'Earn %d %s', 'gamipress' ), $points_required, _n( $singular, $plural, $points_required ) );
            break;
        case 'earn-rank':
            $rank = get_post( $rank_required );

            $title = sprintf( __( 'Reach %s %s', 'gamipress' ), ( $rank ? gamipress_get_rank_type_singular( $rank->post_type ) : '' ), ( $rank ? $rank->post_title : '' ) );
            break;
        // Connect the requirement to ANY of the given achievement type
        case 'any-achievement':
            $title = sprintf( __( 'Unlock any %s', 'gamipress' ), $achievement_type );
            break;
        case 'all-achievements':
            $title = sprintf( __( 'Unlock all %s', 'gamipress' ), $achievement_type );
            break;
        case 'specific-achievement':
            $title = 'Unlock "' . get_the_title( $requirement['achievement_post'] ) . '"';
            break;
        default:
            $title = gamipress_get_activity_trigger_label( $trigger_type );
            break;

    }

    // Specific activity trigger type
    if( in_array( $trigger_type, array_keys( gamipress_get_specific_activity_triggers() ) ) ) {
        $achievement_post_id = absint( $requirement['achievement_post'] );

        if( $achievement_post_id ) {
            // Filtered title
            $title = sprintf( gamipress_get_specific_activity_trigger_label( $trigger_type ),  get_the_title( $achievement_post_id ) );
        }
    }

    // Filter to completely override activity trigger label
    $title = apply_filters( 'gamipress_activity_trigger_label', $title, $requirement_id, $requirement );

    // Prepend "%d %s for" with the points to title
    if( $requirement_type === 'points-award' ) {
        $points           = ( ! empty( $requirement['points'] ) ) ? absint( $requirement['points'] ) : 1;
        $points_type      = ( ! empty( $requirement['points_type'] ) ) ? $requirement['points_type'] : '';
        //$maximum_earnings = ( ! empty( $requirement['maximum_earnings'] ) ) ? absint( $requirement['maximum_earnings'] ) : 1;

        if ( $points > 0 ) {
            $points_types = gamipress_get_points_types();

            if( isset( $points_types[$points_type] ) ) {
                $points_label = strtolower( _n( $points_types[$points_type]['singular_name'], $points_types[$points_type]['plural_name'], $points ) );

                $title = sprintf( __( '%1$s %2$s', 'gamipress' ), sprintf( __( '%d %s for', 'gamipress' ), $points, $points_label ), lcfirst( $title ) );
            }
        }
    }

    if( $trigger_type !== 'earn-points' && $trigger_type !== 'earn-rank' ) {
        // Add "%d time(s)" to title
        $title = sprintf( __( '%1$s %2$s', 'gamipress' ), $title, sprintf( _n( '%d time', '%d times', $required_count ), $required_count ) );
    }

    // Add "(limited to %d per %s)" to title if is limited
    if( $limit_type !== 'unlimited' ) {

        $limit_type_label = '';

        switch( $limit_type ) {
            case 'daily':
                $limit_type_label = __( 'day', 'gamipress' );
                break;
            case 'weekly':
                $limit_type_label = __( 'week', 'gamipress' );
                break;
            case 'monthly':
                $limit_type_label = __( 'month', 'gamipress' );
                break;
            case 'yearly':
                $limit_type_label = __( 'year', 'gamipress' );
                break;
        }

        $title = sprintf( __( '%1$s %2$s', 'gamipress' ), $title, sprintf( __( '(limited to %d per %s)', 'gamipress' ), $limit, $limit_type_label ) );
    }

    // Available hook for custom Activity Triggers
    return apply_filters( 'gamipress_requirement_title', $title, $requirement_id, $requirement );

}

/**
 * Get the sort order for a given requirement
 *
 * @since  1.0.5
 *
 * @param  integer $requirement_id The given requirement's post ID
 *
 * @return integer          The requirement's sort order
 */
function gamipress_get_requirement_menu_order( $requirement_id = 0 ) {

    global $wpdb;

    $p2p_id = $wpdb->get_var( $wpdb->prepare( "SELECT p2p_id FROM $wpdb->p2p WHERE p2p_from = %d", $requirement_id ) );

    $menu_order = $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM $wpdb->p2pmeta WHERE p2p_id=%d AND meta_key='order'", $p2p_id ) );

    if ( ! $menu_order || $menu_order === 'NaN' ) {
        $menu_order = '0';
    }

    return $menu_order;

}

/**
 * Helper function for comparing our requirement sort order (used in uasort() in gamipress_create_points_awards_meta_box())
 *
 * @since  1.0.5
 *
 * @param  integer $requirement_x The order number of our given requirement
 * @param  integer $requirement_y The order number of the requirement we're comparing against
 *
 * @return integer        0 if the order matches, -1 if it's lower, 1 if it's higher
 */
function gamipress_compare_requirements_order( $requirement_x = 0, $requirement_y = 0 ) {

    if ( $requirement_x->order == $requirement_y->order ) {
        return 0;
    }

    return ( $requirement_x->order < $requirement_y->order ) ? -1 : 1;

}
