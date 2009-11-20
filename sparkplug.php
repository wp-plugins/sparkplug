<?php
/*
Plugin Name: Sparkplug
Plugin URI: http://dentedreality.com.au/projects/wp-sparkplug/
Description: Gives you a widget (and a template tag/function) to display a <a href="http://en.wikipedia.org/wiki/Sparklines">sparkline</a> showing the number of posts per day for the current view. Works on home, tags, categories and authors pages Uses <a href="http://omnipotent.net/jquery.sparkline/">jQuery Sparklines</a>.
Author: Beau Lebens
Version: 1.1
Author URI: http://dentedreality.com.au/

@todo add actions to clear cached data on post publishing (selective, based on cat/tag/author)
@todo handle long periods properly (collecting posts from each month in range...)
*/

// Need jQuery and the Sparkline JS files to 
wp_enqueue_script( 'jquery' );
if ( defined( WPMU_PLUGIN_URL ) ) // May as well make it MU-friendly while we're at it
	wp_enqueue_script( 'jquery-sparkline', WPMU_PLUGIN_URL . '/sparkplug/jquery.sparkline.min.js' );
else
	wp_enqueue_script( 'jquery-sparkline', plugins_url( '/sparkplug/jquery.sparkline.min.js' ) );

/**
 * Get the data for the entire blog, so that we have the "baseline" data to
 * chart.
 * 
 * @param int $days worth of data to get
 * @param array $data to merge with
 * @return array containing data you sent + global data
**/
function sparkplug_get_home_data( $days ) {
	$dates = date_dates_between( strtotime( '-' . $days . ' days' ), time() );
	sort($dates);
	$data = array();
	$args = array( 'year' => date( 'Y' ), 'monthnum' => date( 'm' ) );

	if ( function_exists( 'pp_get_category_id' ) ) // Prologue Projects compat
		$args['cat'] = pp_get_category_id( 'updates' );

	$raw = get_posts( $args );
	if ( date( 'd' ) < $days ) {
		// We need more than we would have gotten from this month, so get previous month as well
		$args['year'] = date( 'Y', time() - ( $days * 24 * 60 * 60 ) );
		$args['monthnum'] = date( 'm', time() - ( $days * 24 * 60 * 60 ) );
		$raw = array_merge( $raw, get_posts( $args ) );
	}
	
	if ( count( $raw ) ) {
		// Process results into useful array
		$already = array();
		foreach ( $raw as $r ) {
			$d = trim( substr( $r->post_date, 0, 10 ) );
			if ( in_array( $r->ID, $already ) || !in_array( $d, $dates ) )
				continue; // Skip it if we already included it
			if ( isset( $data[$d] ) ) {
				$data[$d]++;
			} else {
				$data[$d] = 1;
			}
			$already[] = $r->ID;
		}
	}
	
	return $data;
}

/**
 * Clean up an array of data so that all dates are shown, even if zero.
 * Data passed by reference, so updated "in place"
**/
function sparkplug_cleanup_data( &$data, $days = 30, $generated = true ) {
	// Now fill out the array with all days
	$dates = date_dates_between( strtotime( '-' . $days . ' days' ), time() );
	sort($dates);
	foreach ( $dates as $date ) {
		if ( empty( $data[$date] ) ) {
			$data[$date] = '0';
		}
	}

	// When was this chunk of data generated?
	if ( $generated )
		$data['generated'] = date( 'Y-m-d H:i:s' );
		
	ksort($data);
}

/**
 * Output the chart via widget. Grabs data as required etc.
**/
function widget_sparkplug( $args ) {
	global $sparkplug_done;
	
	if ( $sparkplug_done )
		return; // Can only have 1 per page
	
	$defaults = array( 'before_widget' => '<li>', 'after_widget' => '</li>' );
	$opts = get_option( 'sparkplug_options' );
	$args = array_merge( $defaults, $opts, $args );
	extract( $args );
	
	// Handle defaults (may be set via args)
	// Gives you a fair amount of control over the display style/content
	$do_total     = isset( $do_total ) ? $do_total : 'yes'; // This specified whether we should include the line for "all posts" on non-home views
	$days         = isset( $days ) && (int) $days <= 90 ? (int) $days : 30; // How many days to show
	$barColor     = isset( $barColor ) ? $barColor : '#0066cc';
	$barWidth     = isset( $barWidth ) ? $barWidth : 4;
	$barHeight    = isset( $barHeight ) ? $barHeight : '20px';
	$minSpotColor = isset( $minSpotColor ) ? $minSpotColor : 'false'; // Show a spot at the lowest point on the line (use STRING false to disable)
	$maxSpotColor = isset( $maxSpotColor ) ? $maxSpotColor : 'false'; // Show a spot at the highest point on the line (use STRING false to disable)
	$spotRadius   = isset( $spotRadius ) ? $spotRadius : 'false'; // How big should the spot be? (us STRING false to disable)
	$fillColor    = isset( $fillColor ) ? $fillColor : 'false'; // Hex to color in under the line (not recommended) or STRING false to not color
	$lineColor    = isset( $lineColor ) ? $lineColor : '#bdbdbd';
	$defaultPixelsPerValue = isset( $defaultPixelsPerValue ) ? $defaultPixelsPerValue : '4';
	
	if ( is_singular() )
		return; // Doesn't make sense to show on a singular anything
	
	// These vars are all for Prologue Projects compat
	global $is_author, $is_category, $is_tag, $mode, $is_front_page;
	global $author_id, $category_id, $tag_id, $project_id, $query_categories;
	$is_project = $project_id ? true : false;
	$mode = !empty( $mode ) ? $mode : 'updates';
	
	// Figure out what we're looking at
	$view   = false;
	$option = false;
	$id     = false;
	if ( is_author() || $is_author ) {
		$view = 'author';
		$param = 'author';
		if ( $is_author ) // Prologue Projects compat
			$id = $author_id;
		else
			$id = get_the_author_ID();
	} else if ( is_tag() || $is_tag ) { // @todo figure out why tag queries are returning everything
		$view = 'tag';
		$param = 'tag_id';
		if ( $is_tag ) { // Prolog Projects compat
			$id = $tag_id;
		} else {
			global $wp_query;
			$id = $wp_query->get_queried_object();
			$id = $id->term_id;
		}
	} else if ( is_front_page() || $is_front_page ) {
		if ( $is_front_page && function_exists( 'pp_get_category_id' ) ) { // Prologue Projects compat
			$view = 'pp_home';
			$id = pp_get_category_id( 'updates' );
			$param = 'cat';
		} else {
			$view = 'home';
			$id = '';
		}
	} else if ( is_category() || $is_project ) {
		$view = 'category';
		if ( $is_project ) { // Prologue Projects compat
			if ( count( $query_categories ) > 1 ) {
				$param = 'category__and';
				$id = $query_categories;
			} else {
				$param = 'cat';
				$id = $query_categories[0];
			}
		} else {
			global $wp_query;
			$id = $wp_query->get_queried_object();
			$id = $id->cat_ID;
			$param = 'cat';
		}
	} else {
		return; // Unsupported view
	}
	
	// This will contain whatever JS is required to generate this specific
	// sparkline chart
	$sparkline_js = '';
	
	// Check for cached data for this view
	$view_option = "sparkplug_{$view}_" . ( is_array( $id ) ? implode( '_', $id ) : $id );
	
	if ( !$data = get_option( $view_option ) )
		$data = false;
		
	// Check if the data we have has "expired"
	if ( isset( $data['generated'] ) && $data['generated'] < date( 'Y-m-d H:i:s', strtotime( 'yesterday' ) ) )
		$data = false;
	
	// Make sure it contains the right number of days
	if ( $days != ( count( $data ) - 1 ) )
			$data = false;
	
	// Now see if we need to load data
	if ( false === $data ) {
		$dates = date_dates_between( strtotime( '-' . $days . ' days' ), time() );
		sort($dates);
		$data = array();
		// Query for # posts based on current view
		switch ( $view ) {
			case 'author':
			case 'category':
			case 'pp_home':
				$args = array( $param => $id, 'year' => date( 'Y' ), 'monthnum' => date( 'm' ) );
				$raw = get_posts( $args );
				if ( date( 'd' ) < $days ) {
					// We need more than we would have gotten from this month, so get previous month as well
					$args['year'] = date( 'Y', time() - ( $days * 24 * 60 * 60 ) );
					$args['monthnum'] = date( 'm', time() - ( $days * 24 * 60 * 60 ) );
					$raw = array_merge( $raw, get_posts( $args ) );
				}
				if ( count( $raw ) ) {
					// Process results into useful array
					$already = array();
					foreach ( $raw as $r ) {
						$d = trim( substr( $r->post_date, 0, 10 ) );
						if ( in_array( $r->ID, $already ) || !in_array( $d, $dates ) )
							continue; // Skip it if we already included it
						if ( isset( $data[$d] ) ) {
							$data[$d]++;
						} else {
							$data[$d] = 1;
						}
						$already[] = $r->ID;
					}
				}
				break;
			case 'tag':
				// Can't seem to do a tag + date query, so getting all, then only using ones with certain tags
				$args = array( 'year' => date( 'Y' ), 'monthnum' => date( 'm' ) );
				$all_raw = get_posts( $args );
				if ( date( 'd' ) < $days ) {
					// We need more than we would have gotten from this month, so get previous month as well
					$args['year'] = date( 'Y', time() - ( $days * 24 * 60 * 60 ) );
					$args['monthnum'] = date( 'm', time() - ( $days * 24 * 60 * 60 ) );
					$all_raw = array_merge( $all_raw, get_posts( $args ) );
				}
				
				if ( count( $all_raw ) ) {
					$raw = array();
					foreach ( $all_raw as $post ) {
						setup_postdata( $post );
						$tags = wp_get_post_tags( $post->ID );
						foreach ( $tags as $tag ) {
							if ( $tag->term_id == $id) {
								$raw[] = $post;
							}
						}
					}
					
					if ( !count( $raw ) ) {
						$data = array();
						break;
					}
					
					// Process results into useful array
					$already = array();
					foreach ( $raw as $r ) {
						$d = trim( substr( $r->post_date, 0, 10 ) );
						if ( in_array( $r->ID, $already ) || !in_array( $d, $dates ) )
							continue; // Skip it if we already included it
						if ( isset( $data[$d] ) ) {
							$data[$d]++;
						} else {
							$data[$d] = 1;
						}
						$already[] = $r->ID;
					}
				}
				break;
			case 'home':
			default:
				$data = sparkplug_get_home_data( $days );
		}
		// Now fill out the array with all days
		sparkplug_cleanup_data( $data, $days );
		update_option( $view_option, $data );
	}
	unset( $data['generated'] );
	
	// Now generate the JS for the current view chart
	ksort($data);
	$sparkline_js .= "jQuery('#sparkplug-chart').sparkline([" . implode( ',', $data ) . "], { type: 'bar', barColor: '$barColor', barWidth: $barWidth, height: '$barHeight', chartRangeMin: 0 });\n";

	// Check if we're looking at the home view and add the line for that dataset
	if ( 'home' != $view && 'pp_home' != $view && 'updates' == $mode && 'yes' == $do_total ) {
		$blog_data = false;
		// We are not, so we need to load the home data as well to provide
		// a comparison line on our chart
		if ( !$blog_data = get_option( 'sparkplug_home_') )
			$blog_data = false;
		
		// Check if the data we have has "expired"
		if ( isset( $blog_data['generated'] ) && $blog_data['generated'] < date( 'Y-m-d H:i:s', strtotime( 'yesterday' ) ) )
			$blog_data = false;
		
		// Make sure it contains the right number of days
		if ( $days != ( count( $data ) - 1 ) )
				$data = false;
		
		// Now see if we need to load data
		if ( false === $blog_data ) {
			$blog_data = sparkplug_get_home_data( $days );
			sparkplug_cleanup_data( $blog_data, $days );
			update_option( 'sparkplug_home_', $blog_data );
		}
		unset( $blog_data['generated'] );
		
		// Now generate the JS for the chart
		ksort($blog_data);
		$sparkline_js .= "\t\tjQuery('#sparkplug-chart').sparkline([" . implode( ',', $blog_data ) . "], { composite: true, type: 'line', minSpotColor: $minSpotColor, maxSpotColor: $maxSpotColor, defaultPixelsPerValue: $defaultPixelsPerValue, spotRadius: $spotRadius, fillColor: $fillColor, lineColor: '$lineColor' });\n";
	}
	
	// Write out the JS to generate the chart
	echo $before_widget; ?>
	<script type="text/javascript">
	jQuery(document).ready(function(){
		<?php echo $sparkline_js; ?>
	});
	</script>
	<span id="sparkplug-chart" class="sparkplug widget"></span>
	<?php echo $after_widget;
	$sparkplug_done = true;
}

/**
 * Register this widget so the user can manage it
**/
function widget_sparkplug_register() {
	wp_register_sidebar_widget( 'sparkplug', 'Sparkplug', 'widget_sparkplug', array( 'description' => 'Display a sparkline chart showing post frequency for the current author/tag/category/blog.' ) );
	wp_register_widget_control( 'sparkplug', 'Sparkplug', 'widget_sparkplug_control' );
}
add_action( 'init', 'widget_sparkplug_register' );

/**
 * These are the options presented to the user for customizing the widget
**/
function widget_sparkplug_control() {
	if ( !empty( $_POST['sidebar'] ) ) {
		$sparkie = array(
						'days' => $_POST['sparkplug_days'],
						'barColor' => $_POST['sparkplug_barColor'],
						'do_total' => !empty( $_POST['sparkplug_do_total'] ) ? 'yes' : 'no',
						'lineColor' => $_POST['sparkplug_lineColor'] 
					);
		update_option( 'sparkplug_options', $sparkie );
	}
	
	$sparks = get_option( 'sparkplug_options' );
	
	$days      = !empty( $sparks['days'] ) ? $sparks['days'] : 30;
	$barColor  = !empty( $sparks['barColor'] ) ? $sparks['barColor'] : '#0066cc';
	$lineColor = !empty( $sparks['lineColor'] ) ? $sparks['lineColor'] : '#bdbdbd';
	$do_total  = !empty( $sparks['do_total'] ) ? $sparks['do_total'] : 'yes';
	?>
	<p><label for="sparkplug_days"><?php _e( 'Days to show:', 'sparkplug' ) ?><input type="text" name="sparkplug_days" id="sparkplug_days" value="<?php echo $days; ?>" class="widefat" /></label></p>
	<p><label for="sparkplug_barColor"><?php _e( 'Bar Color:', 'sparkplug' ) ?><input type="text" name="sparkplug_barColor" id="sparkplug_barColor" value="<?php echo $barColor; ?>" class="widefat" /></label></p>
	<p><label for="sparkplug_do_total"><?php _e( 'Overlay line everywhere but home:', 'sparkplug' ) ?><input type="checkbox" name="sparkplug_do_total" id="sparkplug_do_total" value="true"<?php echo 'yes' == $do_total ? ' checked="checked"' : '' ?> /></label></p>
	<p><label for="sparkplug_lineColor"><?php _e( 'Line Color:', 'sparkplug' ) ?><input type="text" name="sparkplug_lineColor" id="sparkplug_lineColor" value="<?php echo $lineColor; ?>" class="widefat" /></label></p>
	<?php
}

/**
 * A simplified "template tag" to use in templates
**/
function sparkplug( $args = array() ) {
	if ( !isset( $args['before'] ) )
		$args['before'] = '';
	if ( !isset( $args['after'] ) )
		$args['after'] = '';
	widget_sparkplug( $args );
}

/**
 * Grab an array containing YYYY-MM-DD dates between 2 given dates
**/
function date_dates_between( $date1, $date2 ) {
	if ( is_int( $date1 ) ) {
		$date1 = date( 'Y-m-d H:i:s', $date1 );
		$date2 = date( 'Y-m-d H:i:s', $date2 );
	}
	$dates = array();
	$date  = substr( $date1, 0, 10 );

	while ( $date <= $date2 ) {
		$dates[] = $date;
		$date = date( 'Y-m-d', mktime( 0, 0, 0, substr( $date, 5, 2 ), substr( $date, 8, 2 ) + 1, substr( $date, 0, 4 ) ) );
	}

	return $dates;
}

?>