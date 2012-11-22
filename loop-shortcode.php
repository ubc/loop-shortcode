<?php
/*
* Plugin Name: Loop Shortcode
* Plugin URI: 
* Description: A [loop] shortcode plugin.
* Version: 0.1
* Author: UBC CMS
* Author URI: 
*
*
* This program is free software; you can redistribute it and/or modify it under the terms of the GNU
* General Public License as published by the Free Software Foundation; either version 2 of the License,
* or (at your option) any later version.
*
* This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without
* even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
*
* You should have received a copy of the GNU General Public License along with this program; if not, write
* to the Free Software Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA 02110-1301 USA
*

* @license http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
*/

class CTLT_Loop_Shortcode {
	
	public $odd_or_even = 0;
	
	/**
	 * __construct function.
	 * 
	 * @access public
	 * @return void
	 */
	public function __construct() {
	
		/* Register shortcodes on 'init'. */
		add_action( 'init', array( &$this, 'register_shortcode' ) );
		
		/* Apply filters to the column content. */
		add_filter( 'loop_content', 'wpautop' );
		add_filter( 'loop_content', 'shortcode_unautop' );
		add_filter( 'loop_content', 'do_shortcode' );
		
	}
	
	/**
	 * register_shortcode function.
	 * 
	 * @access public
	 * @return void
	 */
	public function register_shortcode() {
		
		add_shortcode( 'loop', array( &$this, 'loop_shortcode' ) );
	}
	
	/**
	 * loop_shortcode function.
	 * 
	 * @access public
	 * @param mixed $atts
	 * @param mixed $content (default: null)
	 * @return void
	 */
	public function loop_shortcode( $atts, $content = null ) {
	
		$atts['pagination'] = ( isset($atts['pagination']) ? (bool)$atts['pagination']: false );
		extract(shortcode_atts(array(
				"query" => '',	
				"rss" => '',
				"view" =>'default',
				"pagination" => false,
				"num" => 5,
				"error"=>'',
				'taxonomy'=>''
			), $atts));
			
		
		if($query=='' && $rss=='') { return '<p class="no-data">'._e('Please specify a query for your [loop] shortcode.', 'hybrid').'</p>'; }
		
		
		// THIS IS A HACK TO GET RID OF TRAILING OPEN <p> TAGS
		$test_p_tag = substr($content, -3);
		if($test_p_tag == "<p>") { $content = substr($content, 0, -3); }
		// END HACK
		
		ob_start();
		
		if($query!=''):
			clf_base_loop_wploop($query, $content,$view,$pagination);
		elseif($rss!=''):
			$rss = html_entity_decode($rss); 
			clf_base_loop_rssloop($rss, $content,$view,$num);
		endif;
		
		return ob_get_clean();
	}




}

$clf_base_odd_or_even = 0;
add_shortcode("odd-even","clf_base_odd_even_shortcode");
function clf_base_odd_even_shortcode(){
	global $clf_base_odd_or_even;
	if ( $clf_base_odd_or_even % 2 )
		return 'odd';
	else
		return 'even alt';

}

add_shortcode("post-class","clf_base_post_class_shortcode");
function clf_base_post_class_shortcode() {
	ob_start();
		hybrid_entry_class();
	return ob_get_clean();
}



function clf_base_loop_rssloop($url, $content, $view,$num) {
	global $clf_base_odd_or_even;
	
	$rss_mock_query = new WP_Query('cat=111111111111111111');
	
	$rss = fetch_feed($url);
	if (!is_wp_error( $rss ) ) : 
		$maxitems = $rss->get_item_quantity($num);
		$rss_items = $rss->get_items(0, $maxitems);
		
	endif;
	
	$found_posts = 0;
	foreach ($rss_items as $item):
	
		
		$post = (object) null;
		$post->ID = $item->get_id();
		$post->type = 'feed_item';
		$post->post_title = $item->get_title();
		$post->guid = $item->get_permalink();
		$post->post_content = $item->get_content();
		$post->post_excerpt = $item->get_description();
		$post->post_date = $item->get_date('Y-m-d H:i:s');
		$post->post_content_filtered = $item;
		$rss_mock_query->post = $post;
		
		array_push($rss_mock_query->posts, $post);
		$found_posts++;
		endforeach;
		$rss_mock_query->post_count = $found_posts;
		$rss_mock_query->found_posts = ''.$found_posts;
		
		if ( $rss_mock_query->have_posts() ) : 
			while ( $rss_mock_query->have_posts() ) : $rss_mock_query->the_post();
				$clf_base_odd_or_even++;
				if($content):
					//echo "<!-- begin raw -->".$content."<!-- end raw -->";
					echo do_shortcode( trim($content) );
				else:
					clf_base_loop_default_output($view);
				endif;
			endwhile; 
		else: 
		?>
			<p class="no-data">
			<?php if(empty($error)):
					 _e('Sorry, no posts matched your criteria.', 'hybrid');
				 else:
				 	echo $error;	
				 endif;
			?>
			</p><!-- .no-data -->
		<?php
		endif;
	wp_reset_query();

}

function clf_base_loop_wploop($query, $content,$view,$pagination = false) {
	global $clf_base_odd_or_even;
	// de-funkify $query - taken from http://digwp.com/2010/01/custom-query-shortcode/ needed to get it working better ideas ?
	$query = html_entity_decode($query);
	$query = preg_replace('~&#x0*([0-9a-f]+);~ei', 'chr(hexdec("\\1"))', $query);
	$query = preg_replace('~&#0*([0-9]+);~e', 'chr(\\1)', $query);
	if($pagination):
		$query .= "&paged=".get_query_var('paged');
	endif;
	$loop_query = new WP_Query($query);
	
	if ( $loop_query->have_posts() ) : 
		while ( $loop_query->have_posts() ) : $loop_query->the_post();
			$clf_base_odd_or_even++;
			if($content):
				
				echo do_shortcode( $content );
			else:
				
				clf_base_loop_default_output($view);
			endif;
		endwhile; 
		
	else: 
	?>
		<p class="no-data">
		<?php if(empty($error)):
					 _e('Sorry, no posts matched your criteria.', 'hybrid');
				 else:
				 	echo $error;	
				 endif;
			?></p><!-- .no-data -->

	<?php
	endif;
	if($pagination):
			echo "<div class='loop-pagination pagination'>";
			clf_base_paginate($loop_query);
			echo "</div>";
	endif;
	wp_reset_query();
}

function clf_base_paginate($loop_query = false) {
	global $wp_query, $wp_rewrite;
	if($loop_query):
		$loop_query->query_vars['paged'] > 1 ? $current = $loop_query->query_vars['paged'] : $current = 1;
		
		$pagination = array(
			'base' => @add_query_arg('page','%#%'),
			'format' => '',
			'total' => $loop_query->max_num_pages,
			'current' => $current,
			'show_all' => true,
			'type' => 'list',
			'next_text' => '&raquo;',
			'prev_text' => '&laquo;'
		);
		if( !empty($loop_query->query_vars['s']) ):
			$pagination['add_args'] = array( 's' => get_query_var( 's' ) );
		endif;
	else:
		$wp_query->query_vars['paged'] > 1 ? $current = $wp_query->query_vars['paged'] : $current = 1;
		$pagination = array(
			'base' => @add_query_arg('page','%#%'),
			'format' => '',
			'total' => $wp_query->max_num_pages,
			'current' => $current,
			'show_all' => true,
			'type' => 'list',
			'next_text' => '&raquo;',
			'prev_text' => '&laquo;'
		);
		if( !empty($wp_query->query_vars['s']) )
			$pagination['add_args'] = array( 's' => get_query_var( 's' ) );

 	endif;
	 
	if( $wp_rewrite->using_permalinks() )
		$pagination['base'] = user_trailingslashit( trailingslashit( remove_query_arg( 's', get_pagenum_link( 1 ) ) ) . 'page/%#%/', 'paged' );
 
	echo paginate_links( $pagination );
}
/**
 * clf_base_loop_default_output function.
 * 
 * @access public
 * @param mixed $view
 * @return void
 */
function clf_base_loop_default_output($view) {
	switch($view)
	{
	case "full":
		clf_base_loop_output_full();
	break;
	
	case "archive":
		clf_base_loop_output_archive();
	break;
	
	case "list":
		clf_base_loop_output_list();
	break;
	
	default:
		clf_base_loop_output_full();
	}
}
/**
 * clf_base_loop_output_full function.
 * 
 * @access public
 * @return void
 */
function clf_base_loop_output_full() {
	if($post->ID == "rss"): ?>
		<div class="<?php hybrid_entry_class(); ?>">
	
		<?php hybrid_before_entry(); // Before entry hook ?>
		
		<div class="entry-content">
			<?php the_content( sprintf( __('Continue reading %1$s', 'hybrid'), the_title( ' "', '"', false ) ) ); ?>
		</div><!-- .entry-content -->
		
		</div><!-- .hentry -->
	
	<?php else: ?>
		<div id="post-<?php the_ID(); ?>" class="<?php hybrid_entry_class(); ?>">
	
		<?php hybrid_before_entry(); // Before entry hook ?>
		
		<div class="entry-content">
			<?php the_content( sprintf( __('Continue reading %1$s', 'hybrid'), the_title( ' "', '"', false ) ) ); ?>
			<?php wp_link_pages( array( 'before' => '<p class="pages">' . __('Pages:', 'hybrid'), 'after' => '</p>' ) ); ?>
		</div><!-- .entry-content -->
		
		<?php hybrid_after_entry(); // After entry hook ?>
	
		</div><!-- .hentry -->
		<?php 
	endif;

}
/**
 * clf_base_loop_output_archive function.
 * 
 * @access public
 * @return void
 */
function clf_base_loop_output_archive() {
	global $post;
	if($post->ID == "rss"): ?>
	
		<div class="<?php hybrid_entry_class(); ?>">
		
	
		<?php hybrid_before_entry(); // Before entry hook ?>
	
		<div class="entry-summary">
			<?php the_excerpt(); ?>
		</div><!-- .entry-summary -->
	
	
		</div><!-- .hentry -->
	<?php else: ?>
		<div id="post-<?php the_ID(); ?>" class="<?php hybrid_entry_class(); ?>">
		
		<?php get_the_image( array( 'custom_key' => array( 'Thumbnail' ), 'size' => 'thumbnail' ) ); ?> <!-- thumbnail -->
	
		<?php hybrid_before_entry(); // Before entry hook ?>
	
		<div class="entry-summary">
			<?php the_excerpt(); ?>
		</div><!-- .entry-summary -->
	
		<?php hybrid_after_entry(); // After entry hook ?>
	
		</div><!-- .hentry -->
	<?php
	endif;
}


function clf_base_loop_output_list() {
?>
	<li  class="<?php echo clf_base_odd_even_shortcode(); ?>"><a href="<?php the_permalink();?> "><?php the_title(); ?></a></li>
<?php
}




// Hacks permalinks so that the permalink() template function will work when displaying RSS entries.
function clf_base_loop_permalink_filter($permalink) {
	global $post;
	if($post->type=='feed_item') { $permalink = $post->guid; }
	return $permalink;
}
add_filter('post_link', 'clf_base_loop_permalink_filter');



add_filter('post_thumbnail_html', 'clf_base_loop_post_thumbnail_html',10,5);
function clf_base_loop_post_thumbnail_html($html, $post_id, $post_thumbnail_id, $size, $attr ) {
	global $post;
	if($post->type=='feed_item'):
		if($enclosure = $post->post_content_filtered->get_enclosure()):
			
			if($size == 'post-thumbnail'){
				$html = '<img class="feed-thumb post-thumbnail" src="'.$enclosure->thumbnails[0].'" />';
			
			}else{
				$html = '<img class="feed-image post-full"src="'.$enclosure->link.'" alt="" />';
			}
			
		endif;
		
	endif;
	
	return $html;
}

/** 
 * Gets post tags in plain text
 */
function ah_arts_get_plain_tags() {

	$posttags = get_the_tags();
	if ($posttags) {
		foreach($posttags as $tag) {
			$htmlstr .= $tag->name . ' '; 
		}
	}
	return $htmlstr;
}
add_shortcode('the_plain_tags','ah_arts_get_plain_tags');