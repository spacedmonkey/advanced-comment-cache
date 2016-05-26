<?php
/**
 * Cache wp_comment_query output in object cache.
 *
 *
 * @package   Advanced_Comment_Cache
 * @author    Jonathan Harris <jon@spacedmonkey.co.uk>
 * @license   GPL-2.0+
 * @link      http://www.jonathandavidharris.co.uk/
 * @copyright 2016 Spacedmonkey
 *
 * @wordpress-plugin
 * Plugin Name:        Advanced Comment Cache
 * Plugin URI:         https://www.github.com/spacedmonkey/advanced-comment-cache
 * Description:        Cache wp_comment_query.
 * Version:            1.1.3
 * Author:             Jonathan Harris
 * Author URI:         http://www.jonathandavidharris.co.uk/
 * License:            GPL-2.0+
 * License URI:        http://www.gnu.org/licenses/gpl-2.0.txt
 * GitHub Plugin URI:  https://www.github.com/spacedmonkey/advanced-comment-cache
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}


// Do not re register the class
if ( ! class_exists( 'Advanced_Comment_Cache' ) ) {

	/**
	 * Class Advanced_Comment_Cache
	 */
	class Advanced_Comment_Cache {

		/**
		 * @var string
		 */
		public $cache_group = 'acc-comments';

		/**
		 * @var bool
		 */
		public $is_cached = false;

		/**
		 * @var string
		 */
		public $cache_key = '';

		/**
		 * @var string
		 */
		public $last_changed = '';

		function __construct() {
			add_action( 'pre_get_comments', array( $this, 'pre_get_comments' ) );
			add_action( 'the_comments', array( $this, 'the_comments' ), 10, 2 );
			add_action( 'clean_comment_cache', array( $this, 'clean_comment_cache' ) );
		}


		/**
		 * @param $query
		 */
		function pre_get_comments( &$query ) {

			$this->cache_key    = md5( serialize( wp_array_slice_assoc( $query->query_vars, array_keys( $query->query_var_defaults ) ) ) );
			$this->last_changed = wp_cache_get( 'last_changed', $this->cache_group );
			if ( ! $this->last_changed ) {
				$this->last_changed = microtime();
				wp_cache_set( 'last_changed', $this->last_changed, $this->cache_group );
			}

			$cache_key   = "get_comment_ids:$this->cache_key:$this->last_changed";
			$comment_ids = wp_cache_get( $cache_key, $this->cache_group );
			if ( false === $comment_ids ) {
				$this->is_cached = false;
				remove_filter( 'found_comments_query', '_return_empty_string' );
			} else {
				$this->is_cached = true;
				add_filter( 'found_comments_query', '_return_empty_string' );
			}

			if ( is_array( $comment_ids ) ) {

				$last_changed = wp_cache_get( 'last_changed', 'comment' );
				if ( ! $last_changed ) {
					$last_changed = microtime();
					wp_cache_set( 'last_changed', $last_changed, 'comment' );
				}
				$cache_key = "get_comment_ids:$this->cache_key:$last_changed";
				wp_cache_add( $cache_key, $comment_ids, 'comment' );
				array_map( array( $this, "prime_comment_cache" ), $comment_ids );
			}
		}

		/**
		 * @param $_comments
		 * @param $query
		 */
		function the_comments( $_comments, &$query ) {

			$cache_key1 = "get_comment_counts:$this->cache_key:$this->last_changed";
			$cache_key2 = "get_comment_ids:$this->cache_key:$this->last_changed";
			if ( ! $this->is_cached ) {
				$counts = array( 'found_comments' => $query->found_comments, 'max_num_pages' => $query->max_num_pages );
				wp_cache_add( $cache_key1, $counts, $this->cache_group );
				wp_cache_add( $cache_key2, wp_list_pluck( $_comments, 'comment_ID' ), $this->cache_group );
			} else {
				$counts = wp_cache_get( $cache_key1, $this->cache_group );
				if ( is_array( $counts ) ) {
					$query->found_comments = $counts['found_comments'];
					$query->max_num_pages  = $counts['max_num_pages'];
				}
			}

			return $_comments;
		}

		/**
		 * @param $id
		 */
		function prime_comment_cache( $id ) {
			$_comment = wp_cache_get( $id, $this->cache_group );
			if ( false === $_comment ) {
				$_comment = get_comment( $id );
				wp_cache_add( $id, $_comment, $this->cache_group );
			}
			wp_cache_add( $id, $_comment, 'comment' );
		}


		/**
		 * @param $id
		 */
		function clean_comment_cache( $id ) {
			$this->last_changed = microtime();
			wp_cache_delete( $id, $this->cache_group );
			wp_cache_set( 'last_changed', $this->last_changed, $this->cache_group );
		}

	}

	global $advanced_comment_cache_object;
	$advanced_comment_cache_object = new Advanced_Comment_Cache();
}