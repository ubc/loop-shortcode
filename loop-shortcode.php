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
	public $content = '';
	public $loop_attributes = array();
	public $error = null;
	
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
	
	function has_shortcode( $shortcode ){
		global $shortcode_tags;
		return ( in_array( $shortcode, $shortcode_tags ) ? true : false);
	}
	
	function add_shortcode( $shortcode, $shortcode_function ){
	
		if( !$this->has_shortcode( $shortcode ) )
			add_shortcode( $shortcode, array( &$this, $shortcode_function ) );
		
	}
	
	/**
	 * register_shortcode function.
	 * 
	 * @access public
	 * @return void
	 */
	public function register_shortcode() {
		
		/* don't do anything if the shortcode exists already */
		$this->add_shortcode( 'loop', 'loop_shortcode' );
		
		
		
		
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
	
		// $atts['pagination'] = ( isset($atts['pagination']) ? (bool)$atts['pagination']: false );
		
		// $this->loop_attributes = array(); // always start with an empty array
		
		$this->content = $content;
		$this->loop_attributes = shortcode_atts(array(
				"query" => '',	
				"rss" 	=> '',
				"view" 	=>'default',
				"pagination" => false,
				"num" 	=> 5,
				"error"	=>'',
				"taxonomy"=>''
			), $atts );
			
		
		if( empty( $this->loop_attributes['query'] ) && empty( $this->loop_attributes['rss'] ) ) {
			return '<span class="error no-data">'.__('Please specify a query for your [loop] shortcode.', 'loop-shortcode').'</span>';
		}
		
		ob_start();
		
		if( !empty( $this->loop_attributes['query'] ) ):
			
			$this->wp_loop();
			
		elseif( $rss!=''):
			$rss = html_entity_decode( $rss );
			
			$this->rss_loop();
		endif;
				
		return 	ob_get_clean();
	}
	
	
	/**
	 * wp_loop function.
	 * 
	 * @access public
	 * @return void
	 */
	function wp_loop(){
		
		// de-funkify $query - taken from http://digwp.com/2010/01/custom-query-shortcode/ needed to get it working better ideas ?
		$query = html_entity_decode( $this->loop_attributes['query'] );
		$query = preg_replace('~&#x0*([0-9a-f]+);~ei', 'chr(hexdec("\\1"))', $query);
		$query = preg_replace('~&#0*([0-9]+);~e', 'chr(\\1)', $query);
		
		if( $this->loop_attributes['pagination'] ):
			$query .= "&paged=".get_query_var( 'paged' );
		endif;
		
		$loop_query = new WP_Query( $query );
		
		if ( $loop_query->have_posts() ) : 
			while ( $loop_query->have_posts() ) : $loop_query->the_post();
				
				$this->display_output();
				$this->odd_or_even++;
				
			endwhile; 
			
			$this->paginate();
			
		else: 
			$this->show_error();
		endif;
				
		wp_reset_query();
		
	}
	
	/**
	 * rss_loop function.
	 * 
	 * @access public
	 * @return void
	 */
	function rss_loop(){
		global $clf_base_odd_or_even;
	
		$rss_mock_query = new WP_Query('cat=111111111111111111');
		
		$rss = fetch_feed( $this->loop_attributes['rss'] );
		
		// todo: make pagination work for rss as well 
		
		if (!is_wp_error( $rss ) ) : 
			
			$maxitems = $rss->get_item_quantity( $this->loop_attributes['num'] );
			
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
				
				$this->display_output();
				$this->odd_or_even++;
				
			endwhile; 
			
			
			$this->paginate();
			
		else: 
			$this->show_error();
		endif;
		
		wp_reset_query();

	
	}
	
		
	/**
	 * display_output function.
	 * 
	 * @access public
	 * @return void
	 */
	function display_output(){
		
		if( $this->content ):
			
			echo apply_filters( $this->content );
					
		else:
			switch( $this->loop_attributes['view'] ){
				
				case "archive":
					$this->archive_output();
				break;
				
				case "list":
					$this->list_output();
				break;
				case "full":
					$this->full_output();
				break;
				default:
					$this->default_output();
				break;
			
			}
			
			
		
		endif;
	
	}
	
	function show_error(){
		?>
		<p class="no-data">
			<?php 
			if( empty( $this->error ) ):
				 _e('Sorry, no posts matched your criteria.', 'loop-shortcode');
			 else:
			 	echo $this->error;	
			 endif;
				?>
		</p><!-- .no-data -->
		<?php 
	}
	
	/**
	 * default_output function.
	 * 
	 * @access public
	 * @return void
	 */
	function default_output() {
		

	}
	
	/**
	 * archive_output function.
	 * 
	 * @access public
	 * @return void
	 */
	function archive_output() {
	
		?>
		<div id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
			<h2 class="post-title entry-title"><a href="<?php the_permalink(); ?>" title="<?php the_title_attribute(); ?>"><?php the_title(); ?></a></h2>
			
			<div class="entry-content">
				<?php the_excerpt(); ?>
			</div><!-- .entry-content -->
				
		</div><!-- .hentry -->
		<?php 
	}
	
	
	/**
	 * list_output function.
	 * 
	 * @access public
	 * @return void
	 */
	function list_output() {
		?>
		<li><a href="<?php the_permalink(); ?>" title="<?php the_title_attribute(); ?>"><?php the_title(); ?></a></li>
		<?php 
	}
	
	
	/**
	 * full_output function.
	 * 
	 * @access public
	 * @return void
	 */
	function full_output() { ?>
		<div id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
			<h2 class="post-title entry-title"><a href="<?php the_permalink(); ?>" title="<?php the_title_attribute(); ?>"><?php the_title(); ?></a></h2>
			
			<div class="entry-content">
				<?php the_content(); ?>
			</div><!-- .entry-content -->
				
		</div><!-- .hentry -->
		<?php 
	}
	
	/**
	 * paginate function.
	 * 
	 * @access public
	 * @param mixed $loop_query
	 * @return void
	 */
	function paginate() {
		
		if( $this->loop_attributes['pagination'] ):
			
			
			
		endif;
	}
	
	
	
	

}

new CTLT_Loop_Shortcode();
