<?php

	/**
	 * Most Popular Posts
	 *
	 * @version 1.1
	 * @author Corneliu Cirlan (cornel@twocsoft.com)
	 * @link http://www.TwoCSoft.com/
	 */

	if (!class_exists("MostReadPosts")):

		class MostReadPosts
		{
			/**
			 * Post view key
			 *
			 * @since 1.0
			 */
			const COUNT_KEY = "view-count";

		
			/**
			 * Post view slug
			 * 
			 * @since 1.0
			 */
			const COUNT_SLUG = "view_count";

		
			/**
			 * Constructor
			 * 
			 * @since 1.0
			 */
			public function __construct()
			{
				// To keep the count accurate, lets get rid of prefetching
				remove_action('wp_head', 'adjacent_posts_rel_link_wp_head', 10, 0);

				// Hook into content to add data
				add_action('the_content', array($this, 'updateContent'));

				// insert ajax callback
				add_action('wp_head', array($this, 'ajaxCallback'));

				// update post view via AJAX for logged in users
				add_action('wp_ajax_'.self::COUNT_KEY, array($this, 'updateViewCount'));
					
				// update post view via AJAX only for non registered users
				add_action('wp_ajax_nopriv_'.self::COUNT_KEY, array($this, 'updateViewCount'));

				add_filter('manage_posts_columns', array($this, 'viewCountColumnHead'));
				add_action('manage_posts_custom_column', array($this, 'viewCountColumnContent'), 10, 2);

				// Customize the column
				add_action('admin_head', array($this, 'customizeColumn'));

				// sortable column
				add_action('manage_edit-post_sortable_columns', array($this, 'sortableViewCount'));
				add_action('pre_get_posts', array($this, 'sortMetaKey'));
			}


			/**
			 * AJAX callback to process data
			 *
			 * @since 1.0
			 */
			public function ajaxCallback()
			{
				// if user logged in and has administrative rights, exit
				if (is_user_logged_in() && current_user_can('edit_posts')) return;

				?>
				<script type="text/javascript">
					var ajaxurl = '<?php echo admin_url("admin-ajax.php"); ?>';
					
					jQuery(document).ready(function($) {
						var postID = $('#<?php echo self::COUNT_KEY ?>').val();

						$.post(ajaxurl, {action: '<?php echo self::COUNT_KEY ?>', id: postID}, function(data, textStatus, xhr) {
							console.log(data);
						});
					});
				</script>
				<?php
			}


			/**
			 * Update value or create the key
			 *
			 * @since 1.0
			 */
			public function updateViewCount()
			{
				// get post ID
				$postID = intval($_POST['id']);
				
				// update post key value
				$count = get_post_meta($postID, self::COUNT_KEY, true);
				if ($count == ''):
						$count = 1;
						delete_post_meta($postID, self::COUNT_KEY);
						add_post_meta($postID, self::COUNT_KEY, $count);
					else:
						$count++;
						update_post_meta($postID, self::COUNT_KEY, $count);
				endif;

				// terminate
				die("Key Updated");
			}


			/**
			 * Hook into the_content to add necessary data
			 *
			 * @since 1.0
			 */
			public function updateContent($content)
			{
				// if user logged in and has administrative rights, exit
				if (is_user_logged_in() && current_user_can('edit_posts')) return $content;

				if (is_singular('post'))
					$content = '<input type="hidden" name="'.self::COUNT_KEY.'" id="'.self::COUNT_KEY.'" value="'.get_the_id().'" />'.$content;

				return $content;
			}


			/**
			 * Add new admin column
			 *
			 * @since 1.0
			 */
			public function viewCountColumnHead($defaults) {
				$defaults[self::COUNT_SLUG] = __('Views');
				return $defaults;
			}


			/**
			 * Print custom column' value
			 *
			 * @since 1.0
			 */
			public function viewCountColumnContent($column_name, $postID) {
				if ($column_name == self::COUNT_SLUG) {
					echo get_post_meta($postID, self::COUNT_KEY, true) != '' ? get_post_meta($postID, self::COUNT_KEY, true) : '0';
				}
			}


			/**
			 * Custom CSS for the column
			 *
			 * @since 1.0
			 */
			public function customizeColumn()
			{
				?>
				<style type="text/css" media="screen">
					.column-<?php echo self::COUNT_SLUG; ?> {
						width: 50px;
						width: 5rem;
					}
				</style>
				<?php
			}


			/**
			 * Set column sortable
			 *
			 * @since 1.0
			 */
			public function sortableViewCount($columns)
			{
				$columns[self::COUNT_SLUG] = self::COUNT_KEY;

				return $columns;
			}


			/**
			 * Set custom meta key for sorting
			 *
			 * @since 1.0
			 */
			public function sortMetaKey($query)
			{
				// exit if not admin page
				if (!is_admin()) return;

				$orderby = $query->get('orderby');

				if (self::COUNT_KEY == $orderby):
					$query->set('meta_key', self::COUNT_KEY);
					$query->set('orderby', 'meta_value_num');
				endif;
			}
		}

		/**
		 * Create new instance
		 */
		new MostReadPosts();

	endif;