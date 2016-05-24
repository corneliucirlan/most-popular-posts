<?php

	/**
	 * Most Popular Posts
	 *
	 * @version 1.3
	 * @author Corneliu Cirlan (corneliu@corneliucirlan.com)
	 * @link http://www.corneliucirlan.com/
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
			 * Plugin slug
			 *
			 * @since 1.2
			 */
			const CC_MRP_SLUG = 'most-read-posts';


			/**
			 * Settings group name
			 *
			 * @since 1.3
			 */
			const CC_MRP_GROUP = 'cc-mrp-group';


			/**
			 * Settings name
			 *
			 * @since 1.3
			 */
			const CC_MRP_SETTINGS = 'cc-mrp-settings';


			/**
			 * Settings general section
			 *
			 * @since 1.3
			 */
			const CC_MRP_SETTINGS_GENERAL = 'cc-mrp-general';


			/**
			 * Global settings
			 *
			 * @since 1.3
			 */
			private $settings;

		
			/**
			 * Constructor
			 * 
			 * @since 1.0
			 */
			public function __construct()
			{
				// get plugin settings
				$this->settings = get_option(self::CC_MRP_SETTINGS);

				// Register plugin settings page
				add_action('admin_menu', array($this, 'registerSettingsPage'));

				// Register plugin settings
				add_action('admin_init', array($this, 'registerSettings'));

				// To keep the count accurate, lets get rid of prefetching
				remove_action('wp_head', 'adjacent_posts_rel_link_wp_head', 10, 0);

				// Hook into content to add data
				add_action('the_content', array($this, 'updateContent'));

				// Insert ajax callback
				add_action('wp_head', array($this, 'ajaxCallback'));

				// Update post view via AJAX for logged in users
				add_action('wp_ajax_'.self::COUNT_KEY, array($this, 'updateViewCount'));
					
				// update post view via AJAX for non registered users
				add_action('wp_ajax_nopriv_'.self::COUNT_KEY, array($this, 'updateViewCount'));

				// Enable views column on selected post types
				add_action('wp_loaded', function(){
			        $postTypes = get_post_types(array('public' => true), 'names'); 
					$settings = get_option(self::CC_MRP_SETTINGS)['cc-mrp-post-types'];
					
					if (is_array($settings)):
						foreach ($postTypes as $type):
							if (in_array($type, $settings)):
								add_filter('manage_'.$type.'_posts_columns', array($this, 'viewCountColumnHead'), 10);
								add_action('manage_'.$type.'_posts_custom_column', array($this, 'viewCountColumnContent'), 10, 2);
							endif;
						endforeach;
					endif;
				});
				
				// Customize the column
				add_action('admin_head', array($this, 'customizeColumn'));

				// Sortable column
				add_action('manage_edit-post_sortable_columns', array($this, 'sortableViewCount'));
				add_action('pre_get_posts', array($this, 'sortMetaKey'));
			}


			/**
			 * Register settings
			 *
			 * @since 1.2
			 */
			public function registerSettings()
			{
				// register settings group
				register_setting(self::CC_MRP_GROUP, self::CC_MRP_SETTINGS, array($this, 'validateInput'));

				// add settings section
				add_settings_section(self::CC_MRP_SETTINGS_GENERAL, __('General settings'), '', self::CC_MRP_GROUP);

				// add settings field
				add_settings_field('cc-mrp-post-types', __('Post types'), array($this, 'renderPostTypesField'), self::CC_MRP_GROUP, self::CC_MRP_SETTINGS_GENERAL);

				// select roles to disable on
				add_settings_field('cc-mrp-user-roles', __('Disable for'), array($this, 'renderUserRoles'), self::CC_MRP_GROUP, self::CC_MRP_SETTINGS_GENERAL);
			}


			/**
			 * Register settings page
			 *
			 * @since 1.2
			 */
			public function registerSettingsPage()
			{
				add_options_page(__('Most Read Posts'), __('Most Read Posts'), 'manage_options', self::CC_MRP_SLUG, array($this, 'renderSettingsPage'));
			}


			/**
			 * Render settings page
			 *
			 * @since 1.2
			 */
			public function renderSettingsPage()
			{
				?>
				<div class="wrap">
					<h2><?php _e('Most Read Posts') ?></h2>

					<form method='post' action='options.php'>

						<?php settings_fields(self::CC_MRP_GROUP); ?>
						<?php do_settings_sections(self::CC_MRP_GROUP); ?>

						<!-- Submit button -->
						<?php submit_button() ?>
					</form>

				</div>
				<?php
			}


			/**
			 * Render post types fields
			 * 
			 * @since 1.2
			 */
			public function renderPostTypesField()
			{
				// get all available post types
				$postTypes = get_post_types(array('public' => true), 'objects');
				
				// get checked post types
				$settings = $this->settings['cc-mrp-post-types'];
				
				foreach ($postTypes as $key => $value):
					?>
					<label>
						<input type="checkbox" name="cc-mrp-post-types[]" value="<?php echo $key ?>" <?php echo is_array($settings) && in_array($key, $settings) ? 'checked' : '' ?> />
						<?php echo $value->labels->name ?>
					</label>&nbsp;&nbsp;&nbsp;
					<?php
				endforeach;
			}


			/**
			 * Disable for this roles
			 *
			 * @since 1.3
			 */
			public function renderUserRoles()
			{
				// get all WP roles
				$roles = get_editable_roles();

				// get checked user roles
				$settings = $this->settings['cc-mrp-user-roles'];

				foreach ($roles as $key => $value):
					?>
					<label>
						<input type="checkbox" name="cc-mrp-user-roles[]" value="<?php echo $key ?>" <?php echo is_array($settings) && in_array($key, $settings) ? 'checked' : '' ?> />
						<?php echo $value['name'] ?>
					</label>&nbsp;&nbsp;&nbsp;
					<?php
				endforeach;
			}


			/**
			 * Sanitize settings
			 *
			 * @since 1.2
			 */
			public function validateInput($input)
			{
				// get post types
				$input['cc-mrp-post-types'] = $_POST['cc-mrp-post-types'];
				
				// get user roles
				$input['cc-mrp-user-roles'] = $_POST['cc-mrp-user-roles'];

				return $input;
			}


			/**
			 * AJAX callback to process data
			 *
			 * @since 1.0
			 */
			public function ajaxCallback()
			{
				// If user is looged in
				if (is_user_logged_in()):
					
					// Get user roles
					$userRoles = $this->settings['cc-mrp-user-roles'];

					// Get post types
					$postTypes = $this->settings['cc-mrp-post-types'];

					// Get current user roles
					$currentUser = wp_get_current_user();

					// If user is on the disabled list, exit
					foreach ($currentUser->roles as $userRole)
						if (is_array($userRoles) && in_array($userRole, $userRoles))
							return;
				endif;
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
				// Get post types
				$postTypes = $this->settings['cc-mrp-post-types'];

				// If user is looged in
				if (is_user_logged_in()):
					
					// Get user roles
					$userRoles = $this->settings['cc-mrp-user-roles'];


					// Get current user roles
					$currentUser = wp_get_current_user();

					// If user is on the disabled list, exit
					foreach ($currentUser->roles as $userRole)
						if (is_array($userRoles) && in_array($userRole, $userRoles))
							return $content;
				endif;

				// Check if single post and if checked to be monitored
				if (is_singular() && is_array($postTypes) && in_array(get_post_type(), $postTypes))
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
