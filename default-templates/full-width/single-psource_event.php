<?php
global $blog_id, $wp_query, $booking, $post, $current_user;
$event = new Eab_EventModel($post);
get_header();
?>
       
        
<?php
	the_post();
	$start_day = date_i18n('m', strtotime(get_post_meta($post->ID, 'psource_event_start', true)));
?>
	<div id="primary">
		<div id="content" role="main">
			
<div class="event <?php echo Eab_Template::get_status_class($post); ?>" id="psourceevents-wrapper">
	<div id="psourceents-single">
		<div class="psourceevents-header">
			<h2><?php echo $event->get_title(); ?></h2><br />
			<div class="psourceevents-contentmeta" style="clear:both">
				<?php echo Eab_Template::get_event_details($event); ?>
			</div>
		</div>
		<?php echo Eab_Template::get_error_notice(); ?>
		<div id="psourceevents-left">	
			<div id="psourceevents-tickets" class="psourceevents-box">
				<?php
                    	if ($event->is_premium() && $event->user_is_coming() && !$event->user_paid()) { 
                    ?>
					<div id="psourceevents-payment">
						<a href="" id="psourceevents-notpaid-submit">You haven't paid for this event</a>
					</div>
					<?php echo Eab_Template::get_payment_forms($event); ?>
					<?php } ?>
			</div>
			<div id="psourceevents-content" class="psourceevents-box">
				<div class="psourceevents-boxheader">
					<h3>Über dieses Ereignis:</h3>
				</div>
					<div class="psourceevents-boxinner">
					<?php 
						add_filter('agm_google_maps-options', 'eab_autoshow_map_off', 99);
						the_content();
						remove_filter('agm_google_maps-options', 'eab_autoshow_map_off');
					?>
					</div>
					<div><?php echo Eab_Template::get_inline_rsvps($event); ?></div>
			</div>
		</div>
		<div id="psourceevents-right">
			<div id="psourceevents-attending" class="psourceevents-box">
				<?php echo Eab_Template::get_rsvp_form($event); ?>
			</div>
			<?php if ($event->has_venue_map()) { ?>
			<div id="psourceevents-googlemap" class="psourceevents-box">
				<div class="psourceevents-boxheader">
					<h3>Veranstaltungs-Map</h3>
				</div>
					<div class="psourceevents-boxinner">
					<?php echo $event->get_venue_location(Eab_EventModel::VENUE_AS_MAP, array('width' => '99%')); ?>
					</div>
			</div>
			<?php } ?>
			<div id="psourceevents-host" class="psourceevents-box">
				<div class="psourceevents-boxheader">
				<h3>Dein Gastgeber : <?php the_author_meta('display_name'); ?></h3>
				</div>
					<div class="psourceevents-boxinner">
					<p>
						<?php the_author_meta('description'); ?>
					</p>
					</div>
			</div>
		</div>
	</div>
</div>

<div style="clear:both"><?php comments_template( '', true ); ?></div>

		</div>
	</div>
        
        
<?php get_footer('event'); ?>