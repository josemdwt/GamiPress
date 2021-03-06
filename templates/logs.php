<?php
/**
 * Logs template
 *
 * This template can be overridden by copying it to yourtheme/gamipress/logs.php
 */
global $gamipress_template_args;

// Shorthand
$a = $gamipress_template_args;

?>

<div class="gamipress-logs">

    <?php
    /**
     * Before render logs list
     *
     * @param $template_args array Template received arguments
     */
    do_action( 'gamipress_before_render_logs_list', $a ); ?>

    <?php foreach( $a['query']->get_results() as $log ) : ?>

        <?php
        /**
         * Before render log
         *
         * @param $log_id           integer The Log ID
         * @param $template_args    array   Template received arguments
         */
        do_action( 'gamipress_before_render_log', $log->log_id, $a ); ?>

        <div id="gamipress-log-<?php echo $log->log_id; ?>" class="gamipress-log"><?php echo apply_filters( 'gamipress_render_log_title', $log->title, $log->log_id ); ?></div>

        <?php
        /**
         * After render log
         *
         * @param $log_id           integer The Log ID
         * @param $template_args    array   Template received arguments
         */
        do_action( 'gamipress_after_render_log', $log->log_id, $a ); ?>

    <?php endforeach; ?>

    <?php
    /**
     * After render logs list
     *
     * @param $template_args array Template received arguments
     */
    do_action( 'gamipress_after_render_logs_list', $a ); ?>

</div>
