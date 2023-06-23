<?php
/*
* Plugin Name: Loop Shortcode
* Plugin URI:
* Description: A [loop] shortcode plugin.
* Version: 1.1
* Author: UBC CMS
* Author URI:http://cms.ubc.ca
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


/**
 * CTLT_Loop_Shortcode class.
 */
class CTLT_Loop_Shortcode {

	public $loop_type = null;
	public $odd_or_even = 0;
	public $content = '';
	public $loop_attributes = array();
	public $error = null;
	public $loop_query;
	public $total_pages;
	public $json_output = array();
	public $grid_column = 0;
	public $counter = 0;

	/**
	 * __construct function.
	 *
	 * @access public
	 * @return void
	 */
	public function __construct() {

		/* Register shortcodes on 'init'. */
		add_action( 'init', array( &$this, 'register_shortcode' ), 20 );

		/* Apply filters to the column content. */
		add_filter( 'loop_content', 'wpautop' );
		add_filter( 'loop_content', 'shortcode_unautop' );
		add_filter( 'loop_content', 'do_shortcode' );
		add_filter( 'loop_content', array( &$this, 'remove_wanted_p' ) );

		add_filter( 'post_thumbnail_html',  array( &$this,'feed_post_thumbnail_html' ) , 10 , 5 );
		add_filter( 'the_author', array( &$this,'feed_post_author' ) , 10 , 5);
		add_filter( 'the_modified_author', array( &$this,'feed_post_author' ) , 10 , 5);
		add_filter( 'get_the_author_display_name' , array( &$this,'feed_post_author' ) , 10 , 5);

		add_filter( 'the_author_posts_link' , array( &$this,'feed_post_author_link' ) , 10 , 5);
		add_filter( 'author_link' , array( &$this,'feed_post_author_link' ) , 10 , 5);


		//  There is no point trying to get links to categories or tags from feed since you can't get one reliably. =(

	}

	function remove_wanted_p( $content ){

		$content = trim($content);

		// remove the opening <p> tag
		if( strcasecmp(substr($content, 0, 3), '<p>') === 0 || strcasecmp(substr($content, 0, 3), '<P>') === 0 )
			$content = substr($content, 3);
		// remove the closing </p> tag
		if( strcasecmp(substr($content, -4), '</p>') === 0 || strcasecmp(substr($content, -4), '</P>') === 0)
			$content = substr($content, 0, -4);

		return $content;
	}

	/**
	 * has_shortcode function.
	 *
	 * @access public
	 * @param mixed $shortcode
	 * @return bool
	 */
	function has_shortcode( $shortcode ) {
		global $shortcode_tags;

		return ( in_array( $shortcode, array_keys ($shortcode_tags ) ) ? true : false);
	}

	/**
	 * add_shortcode function.
	 *
	 * @access public
	 * @param mixed $shortcode
	 * @param mixed $shortcode_function
	 * @return void
	 */
	function add_shortcode( $shortcode, $shortcode_function ) {

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
		$this->add_shortcode( 'odd-even', 'odd_even_shortcode' );
	}

	/**
	 * odd_even_shortcode function.
	 *
	 * @access public
	 * @return string
	 */
	public function odd_even_shortcode(){

		if ( $this->odd_or_even % 2 )
			return 'odd';
		else
			return 'even alt';

	}

	/**
	 * loop_shortcode function.
	 *
	 * @access public
	 * @param mixed $atts
	 * @param mixed $content (default: null)
	 * @return mixed
	 */
	public function loop_shortcode( $atts, $content = null ) {
		global $wp_query;
		// $atts['pagination'] = ( isset($atts['pagination']) ? (bool)$atts['pagination']: false );

		// $this->loop_attributes = array(); // always start with an empty array


		$is_page = $wp_query->is_page;
		$is_single = $wp_query->is_single;
		$is_singular = $wp_query->is_singular;
		$wp_query->is_page = false;
		$wp_query->is_single = false;

		$wp_query->is_singular = false;
		$this->content = $content;

		$this->loop_attributes = shortcode_atts(array(
				"query" 		=> '',
				"rss" 			=> '',
				"view" 			=> 'default',
				"pagination" 	=> false,
				"num" 			=> 10,
				"error"			=> '',
				"taxonomy"		=> '',
				'grid_column'	=> 0,
				'json_var'  	=> 'loop_json',
				'author'	 	=> '',
				'time_after' 	=> '',
				'time_before'	=> '',
				'time_inclusive'=> true
			), $atts );
		if( in_array( $this->loop_attributes['pagination'], array( 'false','0','null', false ) ) )
			$this->loop_attributes['pagination'] = false;

		if( in_array( $this->loop_attributes['time_inclusive'], array( 'false','0','null', false ) ) )
			$this->loop_attributes['time_inclusive'] = false;

		if( empty( $this->loop_attributes['query'] ) && empty( $this->loop_attributes['rss'] ) ) {
			return '<span class="error no-data">'.__('Please specify a query for your [ loop ] shortcode.', 'loop-shortcode').'</span>';
		}

		if($this->loop_attributes['grid_column'])
			$this->grid_column = $this->loop_attributes['grid_column'];

		if ( !empty($this->loop_attributes['error']) )
			$this->error = $this->loop_attributes['error'];

		ob_start();

		if( !empty( $this->loop_attributes['query'] ) ):

			$this->wp_loop();

		elseif( !empty($this->loop_attributes['rss']) ):
			$this->loop_attributes['rss'] = html_entity_decode( $this->loop_attributes['rss'] );

			$this->rss_loop();
		endif;
		// revert back to normal
		$wp_query->is_singular = $is_singular;
		$wp_query->is_page = $is_page;
		$wp_query->is_single = $is_single;
		return 	ob_get_clean();
	}

	/**
	 * wp_loop function.
	 *
	 * @access public
	 * @return void
	 */
	function wp_loop(){

		$this->loop_type = 'wp';
		// de-funkify $query - taken from http://digwp.com/2010/01/custom-query-shortcode/ needed to get it working better ideas ?
		$query = html_entity_decode( $this->loop_attributes['query'] );

		$query = preg_replace_callback(
			'~&#x0*([0-9a-f]+);~i',
			function( $matches ) {
				return chr( hexdec( $matches[1] ) );
			},
			$query
		);

		$query = preg_replace_callback(
			'~&#0*([0-9]+);~',
			function( $matches ) {
				return chr( $matches[1] );
			},
			$query
		);

		if( strpos( $query, 'posts_per_page=' ) === false ):
			 $query .= "&posts_per_page=".$this->loop_attributes['num'];
		endif;
		if( $this->loop_attributes['pagination'] ):
			$query .= "&paged=".get_query_var( 'paged' );
		endif;

		if( $this->loop_attributes['author'] ):

			switch( $this->loop_attributes['author'] ) {
				case 'current_user':

					$current_user = wp_get_current_user();

					if($current_user->ID > 0 ) {
						$query .= "&author=".$current_user->ID;
					} else {
						$this->show_error();
						return;
					}
				break;
				default:
					if( is_numeric( $this->loop_attributes['author']) ) {
						$query .= '&author='.$this->loop_attributes['author'];
					} else {
						$query .= '&author_name='.$this->loop_attributes['author'];
					}
				break;
			}

		endif;

		$query_array =  wp_parse_args( $query );

		$items_to_convert_csv_to_array = array(
			'category__and',
			'post__in',
			'post__not_in',
			'post_parent__in',
			'post_parent__not_in',
			'category__not_in'
		);

		foreach ( $items_to_convert_csv_to_array as $id => $item ) {

			if ( ! isset( $query_array[ $item ] ) ) {
				continue;
			}

			$value_to_convert = $query_array[ $item ];
			$usable_array     = str_getcsv( trim( $value_to_convert, "'" ), ',' );

			$query_array[ $item ] = $usable_array;
		}

		if ( isset( $query_array['tag'] ) && strpos( $query_array['tag'], 'current_post' ) !== false ) {
			$current_post       = get_the_id();
			$query_array['tag'] = str_replace( 'current_post', $current_post, $query_array['tag'] );
		}

		if( $this->loop_attributes['time_before'] || $this->loop_attributes['time_after']):
			$query_array['date_query'] = array(
								array(
									'after'     => $this->loop_attributes['time_after'],
									'before'    => $this->loop_attributes['time_before'],
									'inclusive' => $this->loop_attributes['time_inclusive'],
								),
							);
		endif;

		// Attempt to stop recursion by never allowing a post to call itself.
		global $post;
		$mainPostID = $post->ID;

		$query_array['post__not_in'] = array( $mainPostID );

		$this->loop_query = new WP_Query( $query_array );

		$this->total_pages = $this->loop_query->max_num_pages;

		if ( $this->loop_query->have_posts() ) :
			while ( $this->loop_query->have_posts() ) : $this->loop_query->the_post();

				$this->display_output();
				$this->odd_or_even++;
				$this->counter++;

			endwhile;
			// output json
			if( !empty( $this->json_output ) && 'json' == $this->loop_attributes['view'] ):

				echo '<script type="text/javascript" >';
				echo 'var '.$this->loop_attributes['json_var'].' = '.json_encode( $this->json_output );
				echo '</script>';
				$this->json_output = array();
			endif;

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
		$this->loop_type = 'rss';

		$rss_mock_query = new WP_Query('cat=111111111111111111');

		$rss = fetch_feed( $this->loop_attributes['rss'] );
		$num = $this->loop_attributes['num'];

		$paged = get_query_var( 'paged' );

		if($paged > 0 )
			$paged--;
		else
			$paged = 0;

		$start = $num*$paged;

		// todo: make pagination work for rss as well

		if (!is_wp_error( $rss ) ) :

			$maxitems = $rss->get_item_quantity();
			$rss_items = $rss->get_items($start, $num);

		endif;

		$this->total_pages = ceil ( $maxitems / $num );
		$found_posts = 0;
		foreach ($rss_items as $item):


			$post = (object) null;
			$post->ID = $item->get_id();
			$post->post_type = 'post';
			$post->is_loop_shortcode_feed = true;
			$post->post_title = $item->get_title();
			$post->guid = $item->get_permalink();
			$post->post_content = apply_filters('the_content', $item->get_content() );
			$post->post_excerpt = apply_filters('the_excerpt', $item->get_description() );
			$post->taxonomy = array();// ();
			$post->post_date = $item->get_date('Y-m-d H:i:s');
			$post->post_content_filtered = $item;
			$rss_mock_query->post = $post;

			array_push($rss_mock_query->posts, $post);
			$found_posts++;

		endforeach;

		// $rss_mock_query->post_count = $maxitems;
		$rss_mock_query->found_posts = ''. $found_posts;
		$rss_mock_query->post_count = $num;


		if ( $rss_mock_query->have_posts() ) :
			while ( $rss_mock_query->have_posts() ) : $rss_mock_query->the_post();

				$this->display_output();
				$this->odd_or_even++;
				$this->counter++;

			endwhile;

			$this->paginate();

		else:
			$this->show_error();
		endif;

		wp_reset_query();
	}

	/**
	 * paginate function.
	 *
	 * @access public
	 * @param mixed $loop_query
	 * @return void
	 */
	function paginate() {

		if( !$this->loop_attributes['pagination'] )
			return;

		global $wp_query, $wp_rewrite;

		$wp_query->query_vars['paged'] > 1 ? $current = $wp_query->query_vars['paged'] : $current = 1;

		$pagination = array(
			'before' => '',
			'after'  => '',
			'base' => @add_query_arg('page','%#%'),
			'format' => '',
			'total' => $this->total_pages,
			'current' => $current,
			'show_all' => false,
			'type' => 'list',
			'next_text' => '&raquo;',
			'prev_text' => '&laquo;'
		);

		if( !empty( $this->loop_query->query_vars['s'] ) ):
			$pagination['add_args'] = array( 's' => get_query_var( 's' ) );
		endif;

		if( $wp_rewrite->using_permalinks() )
			$pagination['base'] = user_trailingslashit( trailingslashit( remove_query_arg( 's', get_pagenum_link( 1 ) ) ) . 'page/%#%/', 'paged' );

		$pagination = apply_filters( "loop-shortcode-pagination", $pagination );

 		echo $pagination['before'];
		echo paginate_links( $pagination );
		echo $pagination['after'];

	}




	/**
	 * display_output function.
	 *
	 * @access public
	 * @return mixed
	 */
	function display_output(){
		global $post;

		if( !$post->ID )
			return '';

		if( $this->content  ):

			echo apply_filters( 'loop_content', $this->content );

		else:
			switch( $this->loop_attributes['view'] ){

				case "archive":
					$this->archive_output();
				break;

				case "list":
					$this->list_output();
				break;

				case "grid":
					$this->grid_output();
				break;

				case 'json':
					$this->json_output();
				break;

				case "full":
				default:
					$this->full_output();
				break;

			}



		endif;

	}

	function show_error(){ ?>
		<p class="no-data">
			<?php
			if( empty( $this->error ) ):
				 _e('Sorry, no posts matched your criteria.', 'loop-shortcode');
			 else:
			 	echo wp_kses_post( $this->error );
			 endif;
				?>
		</p><!-- .no-data -->
		<?php
	}

	function grid_output() {


		if( 0 == $this->grid_column )
			$this->grid_column = 4;

		if( 0 == $this->counter%$this->grid_column ):
			if(0 != $this->counter ): ?>
			</div>
			<?php endif; ?>
			<div class="expand row-fluid" role="main">
		<?php
		endif;
		if(  $this->counter%$this->grid_column);
		$span = ( 12/ $this->grid_column );
		?>
	      <div class="span<?php echo $span; ?> loop-grid-item">
	        <div class="thumbnail">
	          <!-- <img alt="300x200"  style="width: 300px; height: 200px;" src=""> -->
	          <div class="caption loop-grid-caption">
	            <h3 class="loop-gird-title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
	            <div class="loop-grid-excerpt"><?php the_excerpt(); ?></div>
	        </div>
	        </div>
	      </div>
	<?php


	}
	/**
	 * archive_output function.
	 *
	 * @access public
	 * @return void
	 */
	function archive_output() { ?>

	<div id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
	<?php if( function_exists( 'do_atomic' ) ): ?>
			<?php do_atomic( 'before_entry' ); // hybrid_before_entry ?>

				<div class="entry-content">
					<?php the_excerpt( sprintf( __( 'Continue reading %1$s', 'hybrid' ), the_title( ' "', '"', false ) ) ); ?>
					<?php wp_link_pages( array( 'before' => '<p class="page-links pages">' . __( 'Pages:', 'hybrid' ), 'after' => '</p>' ) ); ?>
				</div><!-- .entry-content -->

				<?php do_atomic( 'after_entry' ); // hybrid_after_entry ?>

			<?php else: ?>
			<div class="entry-content">
				<div class="entry-header">
					<h2 class="post-title entry-title"><a href="<?php the_permalink(); ?>" title="<?php the_title_attribute(); ?>"><?php the_title(); ?></a></h2>
				</div>
				<?php the_excerpt(); ?>
				<?php wp_link_pages( array( 'before' => '<div class="page-links pages">' . __( 'Pages:', 'loop-shortcode' ), 'after' => '</div>' ) ); ?>
				<div class="entry-meta">
				<?php $this->entry_meta(); ?>
				<?php edit_post_link( __( 'Edit', 'loop-shortcode' ), '<span class="edit-link">', '</span>' ); ?>

				</div><!-- .entry-meta -->

			</div><!-- .entry-content -->
			<?php endif;  ?>
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
			<?php if( function_exists( 'do_atomic' ) ): ?>
			<?php do_atomic( 'before_entry' ); // hybrid_before_entry ?>

				<div class="entry-content">
					<?php the_content( sprintf( __( 'Continue reading %1$s', 'hybrid' ), the_title( ' "', '"', false ) ) ); ?>
					<?php wp_link_pages( array( 'before' => '<p class="page-links pages">' . __( 'Pages:', 'hybrid' ), 'after' => '</p>' ) ); ?>
				</div><!-- .entry-content -->

				<?php do_atomic( 'after_entry' ); // hybrid_after_entry ?>

			<?php else: ?>
			<div class="entry-content">
				<div class="entry-header">
					<h2 class="post-title entry-title"><a href="<?php the_permalink(); ?>" title="<?php the_title_attribute(); ?>"><?php the_title(); ?></a></h2>
				</div>
				<?php the_content(); ?>
				<?php wp_link_pages( array( 'before' => '<div class="page-links pages">' . __( 'Pages:', 'loop-shortcode' ), 'after' => '</div>' ) ); ?>
				<div class="entry-meta">
				<?php $this->entry_meta(); ?>
				<?php edit_post_link( __( 'Edit', 'loop-shortcode' ), '<span class="edit-link">', '</span>' ); ?>

				</div><!-- .entry-meta -->

			</div><!-- .entry-content -->
			<?php endif; ?>
		</div><!-- .hentry -->
		<?php
	}

	function json_output() {
		$tags = array();
		$categories = array();

		// get the post tags
		$post_tags = get_the_tags( get_the_ID() );
		if ($post_tags) {
			foreach($post_tags as $tag) {
				$tags[]=  array( 'name' => $tag->name, 'url' => get_tag_link($tag->term_id) , 'slug' => $tag->slug );
			}
		}
		// get the post categories
		$post_categories = get_the_category( get_the_ID() );
		if ($post_categories) {
			foreach( $post_categories as $categorie) {
				$categories[]=  array( 'name' => $categorie->name, 'url' => get_category_link($categorie->term_id) , 'slug' => $categorie->slug );
			}
		}

		$meta = get_post_meta( get_the_ID() );
		if ( $meta['_thumbnail_id'] ) {
			$img = wp_get_attachment_image_src( $meta['_thumbnail_id'][0], 'full' );
		} else {
			$img = '';
		}

		$this->json_output[] = array(
				'title'		=> get_the_title(),
			   	'content' 	=> get_the_content(),
			   	'excerpt'   => get_the_excerpt(),
			   	'author'  	=> get_the_author(),
			   	'date'    	=> get_the_date('U')*1000, // milliseconds since Unix Epoch (January 1 1970 00:00:00 GMT)
			   	'meta'    	=> $meta,
			   	'tags' 		=> $tags,
			   	'categories' => $categories,
			   	'image' 	=> $img[0]
		);


	}

	/**
	 * entry_meta function.
	 *
	 * @access public
	 * @return void
	 */
	function entry_meta(){

	}

	/* helper filters */

	/**
	 * feed_post_thumbnail_html function.
	 *
	 * @access public
	 * @param mixed $html
	 * @param mixed $post_id
	 * @param mixed $post_thumbnail_id
	 * @param mixed $size
	 * @param mixed $attr
	 * @return void
	 */
	function feed_post_thumbnail_html( $html, $post_id, $post_thumbnail_id, $size, $attr ) {
		global $post;
		if( !isset( $post->is_loop_shortcode_feed) )
			return $html;

			if($enclosure = $post->post_content_filtered->get_enclosure()):

				if($size == 'post-thumbnail') {
					$html = '<img class="feed-thumb post-thumbnail" src="'.$enclosure->thumbnails[0].'" />';

				} else {
					$html = '<img class="feed-image post-full"src="'.$enclosure->link.'" alt="" />';
				}

			endif;

		return $html;
	}

	/**
	 * feed_post_author function.
	 *
	 * @access public
	 * @param mixed $author
	 * @return void
	 */
	function feed_post_author( $author ) {
		global $post;

		if( !isset( $post->is_loop_shortcode_feed) )
			return $author;

		$rss_author = $post->post_content_filtered->get_author();
		return $rss_author->get_name();

	}

	/**
	 * feed_post_author_link function.
	 *
	 * @access public
	 * @param mixed $author_link
	 * @return void
	 */
	function feed_post_author_link( $author_link ){
		global $post;
		if( !isset( $post->is_loop_shortcode_feed) )
			return $author_link;

		$rss_author = $post->post_content_filtered->get_author();


		if(  $rss_author->get_link() )
			return $rss_author->get_link();

		return $post->guid;
	}

}

new CTLT_Loop_Shortcode();
