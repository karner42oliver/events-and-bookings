<?php
global $booking, $wpdb, $wp_query;
get_header( 'event' );
?>
	<div id="primary">
		<div id="content" role="main">
            <div id="psourceevents-wrapper">
                <h2><?php _e('Events', 'eab'); ?></h2>
                <hr/>
                <?php if ( !have_posts() ) : ?>
                    <p><?php $event_ptype = get_post_type_object( 'psource_event' ); echo $event_ptype->labels->not_found; ?></p>
                <?php else: ?>
                    <div class="psourceevents-list">
                   
                    <?php while ( have_posts() ) : the_post(); ?>
                        <div class="event <?php echo Eab_Template::get_status_class($post); ?>">
                            <div class="psourceevents-header">
                                <h3><?php echo Eab_Template::get_event_link($post); ?></h3>
                                <a href="<?php the_permalink(); ?>" class="psourceevents-viewevent"><?php _e('View event', 'eab'); ?></a>
                            </div>
                            <?php
                                echo Eab_Template::get_event_details($post);
                            ?>
                            <?php
                                echo Eab_Template::get_rsvp_form($post);
                            ?>
                            <hr />
                        </div>
                    <?php endwhile; ?>
                    </div>
                <?php endif; ?>
            </div>
            <?php posts_nav_link(); ?>
        </div>
	</div>
<?php get_sidebar( 'event' ); ?>
<?php get_footer( 'event' ); ?>
