<?php
/*
Plugin Name: Calendar Widget with Posts
Plugin URI: https://wordpress.org/plugins/calendar-widget-with-posts/
Description: This calendar widget will show list posts of the day when mouse hover.
Version: 1.0
Author: Lam Hoang
Author URI: https://nknhatrang.com
*/


?>

<?php
class cwp_widget_plugin extends WP_Widget {
  /**
   * Ensure that the ID attribute only appears in the markup once
   *
   * @since 4.4.0
   * @var int
   */
  private static $instance = 0;

  /**
   * Sets up a new Calendar widget instance.
   *
   * @since 2.8.0
   */
  public function __construct() {
      $widget_ops = array(
          'classname'                   => 'cwp_widget_plugin',
          'description'                 => __( 'Calendar with Posts Widget.' ),
          'customize_selective_refresh' => true,
      );
      parent::__construct( 'cwp_widget_plugin', __( 'Calendar with posts' ), $widget_ops );
  }
  /**
   * Outputs the content for the current Calendar widget instance.
   *
   * @since 2.8.0
   *
   * @param array $args     Display arguments including 'before_title', 'after_title',
   *                        'before_widget', and 'after_widget'.
   * @param array $instance The settings for the particular instance of the widget.
   */
  public function widget( $args, $instance ) {
      $title = ! empty( $instance['title'] ) ? $instance['title'] : '';
      $category_query = $instance['category'];
      $bg_color = $instance['bg_color'];
      $font_color = $instance['font_color'];
      /** This filter is documented in wp-includes/widgets/class-wp-widget-pages.php */
      $title = apply_filters( 'widget_title', $title, $instance, $this->id_base );

      echo $args['before_widget'];
      if ( $title ) {
          echo $args['before_title'] . $title . $args['after_title'];
      }
      if ( 0 === self::$instance ) {
          echo '<div id="calendar_wrap" class="calendar_wrap">';
      } else {
          echo '<div class="calendar_wrap">';
      }
      get_my_calendar($category_query, $bg_color, $font_color);
      echo '</div>';
      echo $args['after_widget'];

      self::$instance++;
  }

  /**
   * Handles updating settings for the current Calendar widget instance.
   *
   * @since 2.8.0
   *
   * @param array $new_instance New settings for this instance as input by the user via
   *                            WP_Widget::form().
   * @param array $old_instance Old settings for this instance.
   * @return array Updated settings to save.
   */
  public function update( $new_instance, $old_instance ) {
      $instance          = $old_instance;
      $instance['title'] = sanitize_text_field( $new_instance['title'] );
      $instance['category'] = $new_instance['category'];
      $instance['bg_color'] = sanitize_text_field($new_instance['bg_color']);
      $instance['font_color'] = sanitize_text_field($new_instance['font_color']);

      return $instance;
  }

  /**
   * Outputs the settings form for the Calendar widget.
   *
   * @since 2.8.0
   *
   * @param array $instance Current settings.
   */
  public function form( $instance ) {
      $instance = wp_parse_args( (array) $instance, array( 'title' => '' ) );
      ?>
      <p><label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e( 'Title:' ); ?></label>
      <input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" type="text" value="<?php echo esc_attr( $instance['title'] ); ?>" /></p>

      <p><label for="<?php echo $this->get_field_id( 'category' ); ?>"><?php _e( 'Select category', 'textdomain' ); ?>:</label><?php wp_dropdown_categories( array( 'show_option_none' =>' ','name' => $this->get_field_name( 'category' ), 'selected' => $instance['category'] ) ); ?> </p>

      <p><label for="<?php echo $this->get_field_id( 'bg_color' ); ?>"><?php _e( 'Background color:' ); ?></label>
      <input type="color" id="<?php echo $this->get_field_id( 'bg_color' ); ?>" name="<?php echo $this->get_field_name( 'bg_color' ); ?>" type="text" value="<?php echo esc_attr( $instance['bg_color'] ); ?>" /></p>

      <p><label for="<?php echo $this->get_field_id( 'font_color' ); ?>"><?php _e( 'Font color:' ); ?></label>
      <input type="color" id="<?php echo $this->get_field_id( 'font_color' ); ?>" name="<?php echo $this->get_field_name( 'font_color' ); ?>" type="text" value="<?php echo esc_attr( $instance['font_color'] ); ?>" /></p>

      <?php
  }

}

?>

<?php
add_action( 'widgets_init', 'register_mywidget' );

function register_mywidget() {
    register_widget( 'cwp_widget_plugin' );
}
calendar_widget_css();
?>

<?php

/**
 * Display calendar with days that have posts as links.
 *
 * The calendar is cached, which will be retrieved, if it exists. If there are
 * no posts for the month, then it will not be displayed.
 *
 * @since 1.0.0
 *
 * @global wpdb      $wpdb      WordPress database abstraction object.
 * @global int       $m
 * @global int       $monthnum
 * @global int       $year
 * @global WP_Locale $wp_locale WordPress date and time locale object.
 * @global array     $posts
 *
 * @param bool $initial Optional, default is true. Use initial calendar names.
 * @param bool $echo    Optional, default is true. Set to false for return.
 * @return void|string Void if `$echo` argument is true, calendar HTML if `$echo` is false.
 */
function get_my_calendar( $category_query, $bg_color, $font_color, $initial = true, $echo = true ) {
	global $wpdb, $m, $monthnum, $year, $wp_locale, $posts;

	$key   = md5( $m . $monthnum . $year );
	$cache = wp_cache_get( 'get_calendar', 'calendar' );

	if ( $cache && is_array( $cache ) && isset( $cache[ $key ] ) ) {
		/** This filter is documented in wp-includes/general-template.php */
		$output = apply_filters( 'get_calendar', $cache[ $key ] );

		if ( $echo ) {
			echo $output;
			return;
		}

		return $output;
	}

	if ( ! is_array( $cache ) ) {
		$cache = array();
	}

	// Quick check. If we have no posts at all, abort!
	if ( ! $posts ) {
		$gotsome = $wpdb->get_var( "SELECT 1 as test FROM $wpdb->posts WHERE post_type = 'post' AND post_status = 'publish' LIMIT 1" );
		if ( ! $gotsome ) {
			$cache[ $key ] = '';
			wp_cache_set( 'get_calendar', $cache, 'calendar' );
			return;
		}
	}

	if ( isset( $_GET['w'] ) ) {
		$w = (int) $_GET['w'];
	}
	// week_begins = 0 stands for Sunday.
	$week_begins = (int) get_option( 'start_of_week' );

	// Let's figure out when we are.
	if ( ! empty( $monthnum ) && ! empty( $year ) ) {
		$thismonth = zeroise( intval( $monthnum ), 2 );
		$thisyear  = (int) $year;
	} elseif ( ! empty( $w ) ) {
		// We need to get the month from MySQL.
		$thisyear = (int) substr( $m, 0, 4 );
		// It seems MySQL's weeks disagree with PHP's.
		$d         = ( ( $w - 1 ) * 7 ) + 6;
		$thismonth = $wpdb->get_var( "SELECT DATE_FORMAT((DATE_ADD('{$thisyear}0101', INTERVAL $d DAY) ), '%m')" );
	} elseif ( ! empty( $m ) ) {
		$thisyear = (int) substr( $m, 0, 4 );
		if ( strlen( $m ) < 6 ) {
			$thismonth = '01';
		} else {
			$thismonth = zeroise( (int) substr( $m, 4, 2 ), 2 );
		}
	} else {
		$thisyear  = current_time( 'Y' );
		$thismonth = current_time( 'm' );
	}

	$unixmonth = mktime( 0, 0, 0, $thismonth, 1, $thisyear );
	$last_day  = gmdate( 't', $unixmonth );

	// Get the next and previous month and year with at least one post.
	$previous = $wpdb->get_row(
		"SELECT MONTH(post_date) AS month, YEAR(post_date) AS year
		FROM $wpdb->posts
		WHERE post_date < '$thisyear-$thismonth-01'
		AND post_type = 'post' AND post_status = 'publish'
			ORDER BY post_date DESC
			LIMIT 1"
	);
	$next     = $wpdb->get_row(
		"SELECT MONTH(post_date) AS month, YEAR(post_date) AS year
		FROM $wpdb->posts
		WHERE post_date > '$thisyear-$thismonth-{$last_day} 23:59:59'
		AND post_type = 'post' AND post_status = 'publish'
			ORDER BY post_date ASC
			LIMIT 1"
	);

	/* translators: Calendar caption: 1: Month name, 2: 4-digit year. */
	$calendar_caption = _x( '%1$s %2$s', 'calendar caption' );
	$calendar_output  = '<table id="wp-calendar" class="wp-calendar-table">
	<caption>' . sprintf(
    $calendar_caption,
		$wp_locale->get_month( $thismonth ),
		gmdate( 'Y', $unixmonth )
	) .'</caption>
	<thead>
	<tr>';

	$myweek = array();

	for ( $wdcount = 0; $wdcount <= 6; $wdcount++ ) {
		$myweek[] = $wp_locale->get_weekday( ( $wdcount + $week_begins ) % 7 );
	}

	foreach ( $myweek as $wd ) {
		$day_name         = $initial ? $wp_locale->get_weekday_initial( $wd ) : $wp_locale->get_weekday_abbrev( $wd );
		$wd               = esc_attr( $wd );
		$calendar_output .= "\n\t\t<th scope=\"col\" title=\"$wd\">$day_name</th>";
	}

	$calendar_output .= '
	</tr>
	</thead>
	<tbody>
	<tr>';

	$daywithpost = array();

	// Get days with posts.
	$dayswithposts = $wpdb->get_results(
		"SELECT DISTINCT DAYOFMONTH(post_date)
		FROM $wpdb->posts WHERE post_date >= '{$thisyear}-{$thismonth}-01 00:00:00'
		AND post_type = 'post' AND post_status = 'publish'
		AND post_date <= '{$thisyear}-{$thismonth}-{$last_day} 23:59:59'",
		ARRAY_N
	);
	if ( $dayswithposts ) {
		foreach ( (array) $dayswithposts as $daywith ) {
			$daywithpost[] = $daywith[0];
		}
	}

	// See how much we should pad in the beginning.
	$pad = calendar_week_mod( gmdate( 'w', $unixmonth ) - $week_begins );
	if ( 0 != $pad ) {
		$calendar_output .= "\n\t\t" . '<td colspan="' . esc_attr( $pad ) . '" class="pad">&nbsp;</td>';
	}

	$newrow      = false;
	$daysinmonth = (int) gmdate( 't', $unixmonth );

	for ( $day = 1; $day <= $daysinmonth; ++$day ) {
		if ( isset( $newrow ) && $newrow ) {
			$calendar_output .= "\n\t</tr>\n\t<tr>\n\t\t";
		}
		$newrow = false;

    if ( current_time( 'j' ) == $day &&
			current_time( 'm' ) == $thismonth &&
			current_time( 'Y' ) == $thisyear ) {
			$calendar_output .= '<td id="today"><div class="tooltip">';
		} else {
			$calendar_output .= '<td><div class="tooltip">';
		}

		if ( in_array( $day, $daywithpost ) ) {

      // query post of day
      $args = array(
        'date_query' => array(
          array(
            'year'  => $thisyear,
            'month' => $thismonth,
            'day'   => $day,
          ),
        ),
        'cat' => $category_query,
      );
      $the_query = new WP_Query( $args );
      // $bg_color = get_background_color();
      // $font_color = "000000";
      // The Loop
      if ( $the_query->have_posts() ) {
        $calendar_output .= $day ;
        $calendar_output.= '<span class="tooltiptext" data-html="true" data-toggle="tooltip" style="background-color:' . $bg_color . ';"> <ul style="list-style-type:none;">';
        while ( $the_query->have_posts() ) {
          $the_query->the_post();
          $calendar_output .= '<li><a href="'. get_the_permalink() . '" style="color:'. $font_color . ';">' . get_the_title() . '</a></li>';
        }
        $calendar_output .= '</ul></span>';
      }
      else {
        $calendar_output .= $day;
      }
      wp_reset_postdata();
		}
    else {
			$calendar_output .= $day;
		}
		$calendar_output .= '</div></td>';

		if ( 6 == calendar_week_mod( gmdate( 'w', mktime( 0, 0, 0, $thismonth, $day, $thisyear ) ) - $week_begins ) ) {
			$newrow = true;
		}
	}

	$pad = 7 - calendar_week_mod( gmdate( 'w', mktime( 0, 0, 0, $thismonth, $day, $thisyear ) ) - $week_begins );
	if ( 0 != $pad && 7 != $pad ) {
		$calendar_output .= "\n\t\t" . '<td class="pad" colspan="' . esc_attr( $pad ) . '">&nbsp;</td>';
	}
	$calendar_output .= "\n\t</tr>\n\t</tbody>";

	$calendar_output .= "\n\t</table>";

	$calendar_output .= '<nav aria-label="' . __( 'Previous and next months' ) . '" class="wp-calendar-nav">';

	if ( $previous ) {
		$calendar_output .= "\n\t\t" . '<span class="wp-calendar-nav-prev"><a href="' . get_month_link( $previous->year, $previous->month ) . '">&laquo; ' .
			$wp_locale->get_month_abbrev( $wp_locale->get_month( $previous->month ) ) .
		'</a></span>';
	} else {
		$calendar_output .= "\n\t\t" . '<span class="wp-calendar-nav-prev">&nbsp;</span>';
	}

	$calendar_output .= "\n\t\t" . '<span class="pad">&nbsp;</span>';

	if ( $next ) {
		$calendar_output .= "\n\t\t" . '<span class="wp-calendar-nav-next"><a href="' . get_month_link( $next->year, $next->month ) . '">' .
			$wp_locale->get_month_abbrev( $wp_locale->get_month( $next->month ) ) .
		' &raquo;</a></span>';
	} else {
		$calendar_output .= "\n\t\t" . '<span class="wp-calendar-nav-next">&nbsp;</span>';
	}

	$calendar_output .= '
	</nav>';

	$cache[ $key ] = $calendar_output;
	wp_cache_set( 'get_calendar', $cache, 'calendar' );

	if ( $echo ) {
		/**
		 * Filters the HTML calendar output.
		 *
		 * @since 3.0.0
		 *
		 * @param string $calendar_output HTML output of the calendar.
		 */
		echo apply_filters( 'get_calendar', $calendar_output );
		return;
	}
	/** This filter is documented in wp-includes/general-template.php */
	return apply_filters( 'get_calendar', $calendar_output );
}

/**
 * Purge the cached results of get_calendar.
 *
 * @see get_calendar
 * @since 2.1.0
 */
function delete_get_my_calendar_cache() {
	wp_cache_delete( 'get_my_calendar', 'calendar' );
}

function calendar_widget_css(){
  echo "
<style type='text/css'>
/* Tooltip container */
.tooltip {
  position: relative;
  display: inline-block;
  border-bottom: 1px dotted black; /* If you want dots under the hoverable text */
}

/* Tooltip text */
.tooltip .tooltiptext {
  visibility: hidden;
  width: max-content;
  color: #fff;
  text-align: left;
  padding: 2px 2px;
  border-radius: 2px;
  /* Position the tooltip text - see examples below! */
  position: absolute;
  z-index: 1;
}
.tooltiptext ul {
  margin: 1em;
}

/* Show the tooltip text when you mouse over the tooltip container */
.tooltip:hover .tooltiptext {
  visibility: visible;
}
</style>";
}
?>
