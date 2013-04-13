<?php 

/*
*  acf_field_group
*
*  @description: controller for editing a field group
*  @since: 3.6
*  @created: 25/01/13
*/

class acf_field_group
{
	
	var $settings;
	
	
	/*
	*  __construct
	*
	*  @description: 
	*  @since 3.1.8
	*  @created: 23/06/12
	*/
	
	function __construct()
	{
		// actions
		add_action('admin_enqueue_scripts', array($this,'admin_enqueue_scripts'));
		
		
		// filters
		add_filter('acf/get_field_groups', array($this, 'get_field_groups'), 1, 1);
		add_filter('acf/field_group/get_fields', array($this, 'get_fields'), 5, 2);
		add_filter('acf/field_group/get_location', array($this, 'get_location'), 5, 2);
		add_filter('acf/field_group/get_options', array($this, 'get_options'), 5, 2);
		add_filter('acf/field_group/get_next_field_id', array($this, 'get_next_field_id'), 5, 1);
		
		
		// save
		add_filter('name_save_pre', array($this, 'name_save_pre'));
		add_action('save_post', array($this, 'save_post'));
		
		
		// ajax
		add_action('wp_ajax_acf/field_group/render_options', array($this, 'ajax_render_options'));
		add_action('wp_ajax_acf/field_group/render_location', array($this, 'ajax_render_location'));
		
	}
	
	
	/*
	*  get_field_groups
	*
	*  @description: 
	*  @since: 3.6
	*  @created: 27/01/13
	*/
	
	function get_field_groups( $array )
	{
		// cache
		$cache = wp_cache_get( 'field_groups', 'acf' );
		if( $cache )
		{
			return $cache;
		}
		
		
		// get acf's
		$posts = get_posts(array(
			'numberposts' 	=> -1,
			'post_type' 	=> 'acf',
			'orderby' 		=> 'menu_order title',
			'order' 		=> 'asc',
			'suppress_filters' => false,
		));

		
		// populate acfs
		if( $posts ){ foreach( $posts as $post ){
			
			 $array[] = array(
				'id' => $post->ID,
				'title' => $post->post_title,
				'menu_order' => $post->menu_order,
			);
			
		}}

		
		// set cache
		wp_cache_set( 'field_groups', $array, 'acf' );
				
				
		return $array;
	}
	
	
	/*
	*  get_fields
	*
	*  @description: returns all fields for a field group
	*  @since: 3.6
	*  @created: 26/01/13
	*/
	
	function get_fields( $fields, $post_id )
	{
		// global
		global $wpdb;
		
		
		// loaded by PHP already?
		if( !empty($fields) )
		{
			return $fields;	
		}

		
		// get field from postmeta
		$rows = $wpdb->get_results( $wpdb->prepare("SELECT meta_key FROM $wpdb->postmeta WHERE post_id = %d AND meta_key LIKE %s", $post_id, 'field\_%'), ARRAY_A);
		
		
		if( $rows )
		{
			foreach( $rows as $row )
			{
				$field = apply_filters('acf/load_field', false, $row['meta_key'], $post_id );
	
			 	$fields[ $field['order_no'] ] = $field;
			}
		 	
		 	// sort
		 	ksort( $fields );
	 	}
	 	
	 	
	 	
	 	// return
		return $fields;
		
	}
	
	
	/*
	*  get_location
	*
	*  @description: 
	*  @since: 3.6
	*  @created: 26/01/13
	*/
	
	function get_location( $location, $post_id )
	{
		// loaded by PHP already?
		if( !empty($location) )
		{
			return $location;	
		}
		
		
		// defaults
		$location = array(
		 	'rules'		=>	array(),
		 	'allorany'	=>	'all', 
		 );
		
		
		// vars
		$allorany = get_post_meta($post_id, 'allorany', true);
		if( $allorany )
		{
			$location['allorany'] = $allorany;
		}
		
		
		// get all fields
	 	$rules = get_post_meta($post_id, 'rule', false);
	 	

	 	if($rules)
	 	{
	 		
		 	foreach($rules as $rule)
		 	{
		 		// if field group was duplicated, it may now be a serialized string!
		 		$rule = maybe_unserialize($rule);
		
		
		 		$location['rules'][ $rule['order_no'] ] = $rule;
		 	}
	 	}
	 	
	 	
	 	// sort
	 	ksort($location['rules']);
	 	
	 	
	 	// return fields
		return $location;
	}
	
	
	/*
	*  get_options
	*
	*  @description: 
	*  @since: 3.6
	*  @created: 26/01/13
	*/
	
	function get_options( $options, $post_id )
	{
		// loaded by PHP already?
		if( !empty($options) )
		{
			return $options;	
		}
		
		
		// defaults
	 	$options = array(
	 		'position'			=>	'normal',
	 		'layout'			=>	'no_box',
	 		'hide_on_screen'	=>	array(),
	 	);
	 	
	 	
	 	// vars
	 	$position = get_post_meta($post_id, 'position', true);
	 	if( $position )
		{
			$options['position'] = $position;
		}
		
		$layout = get_post_meta($post_id, 'layout', true);
	 	if( $layout )
		{
			$options['layout'] = $layout;
		}
		
		$hide_on_screen = get_post_meta($post_id, 'hide_on_screen', true);
	 	if( $hide_on_screen )
		{
			$hide_on_screen = maybe_unserialize($hide_on_screen);
			$options['hide_on_screen'] = $hide_on_screen;
		}
		
	 	
	 	// return
	 	return $options;
	}
	
	
	/*
	*  validate_page
	*
	*  @description: 
	*  @since 3.2.6
	*  @created: 23/06/12
	*/
	
	function validate_page()
	{
		// global
		global $pagenow, $typenow;
		

		// vars
		$return = false;
		
		
		// validate page
		if( in_array( $pagenow, array('post.php', 'post-new.php') ) )
		{
		
			// validate post type
			if( $typenow == "acf" )
			{
				$return = true;
			}
			
		}
		
		
		// return
		return $return;
	}
	
	
	/*
	*  admin_enqueue_scripts
	*
	*  @description: run after post query but before any admin script / head actions. A good place to register all actions.
	*  @since: 3.6
	*  @created: 26/01/13
	*/
	
	function admin_enqueue_scripts()
	{
		// validate page
		if( ! $this->validate_page() ){ return; }
		
		
		// settings
		$this->settings = apply_filters('acf/get_info', 'all');
		
		
		// no autosave
		wp_dequeue_script( 'autosave' );
		
		
		// custom scripts
		wp_enqueue_script(array(
			'acf-field-group',
		));
		
		
		// custom styles
		wp_enqueue_style(array(
			'acf-global',
			'acf-field-group',
		));
		
		
		// actions
		do_action('acf/field_group/admin_enqueue_scripts');
		add_action('admin_head', array($this,'admin_head'));
		
	}
	
	
	/*
	*  admin_head
	*
	*  @description: 
	*  @since 3.1.8
	*  @created: 23/06/12
	*/
	
	function admin_head()
	{
		global $post;
		
		
		// add js vars
		echo '<script type="text/javascript">
			acf.nonce = "' . wp_create_nonce( 'acf_nonce' ) . '";
			acf.post_id = ' . $post->ID . ';
		</script>';
		
		
		do_action('acf/field_group/admin_head'); // new action
		
		
		// add metaboxes
		add_meta_box('acf_fields', __("Fields",'acf'), array($this, 'html_fields'), 'acf', 'normal', 'high');
		add_meta_box('acf_location', __("Location",'acf'), array($this, 'html_location'), 'acf', 'normal', 'high');
		add_meta_box('acf_options', __("Options",'acf'), array($this, 'html_options'), 'acf', 'normal', 'high');
		
		
		// add screen settings
		add_filter('screen_settings', array($this, 'screen_settings'), 10, 1);
	}
	
	
	/*
	*  html_fields
	*
	*  @description: 
	*  @since 1.0.0
	*  @created: 23/06/12
	*/
	
	function html_fields()
	{
		include( $this->settings['path'] . 'core/views/meta_box_fields.php' );
	}
	
	
	/*
	*  html_location
	*
	*  @description: 
	*  @since 1.0.0
	*  @created: 23/06/12
	*/

	function html_location()
	{
		include( $this->settings['path'] . 'core/views/meta_box_location.php' );
	}
	
	
	/*
	*  html_options
	*
	*  @description: 
	*  @since 1.0.0
	*  @created: 23/06/12
	*/
	
	function html_options()
	{
		include( $this->settings['path'] . 'core/views/meta_box_options.php' );
	}
	
	
	/*
	*  screen_settings
	*
	*  @description: 
	*  @since: 3.6
	*  @created: 26/01/13
	*/
	
	function screen_settings( $current )
	{
	    $current .= '<h5>' . __("Fields",'acf') . '</h5>';
	    
	    $current .= '<div class="show-field_key">Show Field Key:';
	    	 $current .= '<label class="show-field_key-no"><input checked="checked" type="radio" value="0" name="show-field_key" /> No</label>';
	    	 $current .= '<label class="show-field_key-yes"><input type="radio" value="1" name="show-field_key" /> Yes</label>';
		$current .= '</div>';
	    
	    return $current;
	}
	
	
	/*
	*  ajax_render_options
	*
	*  @description: creates the HTML for a field's options (field group edit page)
	*  @since 3.1.6
	*  @created: 23/06/12
	*/
	
	function ajax_render_options()
	{
		// vars
		$options = array(
			'field_key' => '',
			'field_type' => '',
			'post_id' => 0,
			'nonce' => ''
		);
		
		// load post options
		$options = array_merge($options, $_POST);
		
		
		// verify nonce
		if( ! wp_verify_nonce($options['nonce'], 'acf_nonce') )
		{
			die(0);
		}
		
		
		// required
		if( ! $options['field_type'] )
		{
			die(0);
		}
		
		
		// find key (not actual field key, more the html attr name)
		$options['field_key'] = str_replace("fields[", "", $options['field_key']);
		$options['field_key'] = str_replace("][type]", "", $options['field_key']) ;
		
		
		// render options
		$field = array(
			'type' => $options['field_type'],
			'name' => $options['field_key']
		);
		do_action('acf/create_field_options', $field );
		
		
		die();
		
	}
	
	
	/*
	*  ajax_render_location
	*
	*  @description: creates the HTML for the field group location metabox. Called from both Ajax and PHP
	*  @since 3.1.6
	*  @created: 23/06/12
	*/
	
	function ajax_render_location($options = array())
	{
		// defaults
		$defaults = array(
			'key' => null,
			'value' => null,
			'param' => null,
		);
		
		$is_ajax = false;
		if( isset($_POST['nonce']) && wp_verify_nonce($_POST['nonce'], 'acf_nonce') )
		{
			$is_ajax = true;
		}
		
		
		// Is AJAX call?
		if( $is_ajax )
		{
			$options = array_merge($defaults, $_POST);
		}
		else
		{
			$options = array_merge($defaults, $options);
		}
		
		// vars
		$choices = array();
		
		
		// some case's have the same outcome
		if($options['param'] == "page_parent")
		{
			$options['param'] = "page";
		}

		
		switch($options['param'])
		{
			case "post_type":
				
				// all post types except attachment
				$choices = apply_filters('acf/get_post_types', array(), array('attachment'));

				break;
			
			
			case "page":
				
				$post_types = get_post_types( array('capability_type'  => 'page') );
				unset( $post_types['attachment'], $post_types['revision'] , $post_types['nav_menu_item'], $post_types['acf']  );
				
				if( $post_types )
				{
					foreach( $post_types as $post_type )
					{
						$pages = get_pages(array(
							'numberposts' => -1,
							'post_type' => $post_type,
							'sort_column' => 'menu_order',
							'order' => 'ASC',
							'post_status' => array('publish', 'private', 'draft', 'inherit', 'future'),
							'suppress_filters' => false,
						));
						
						if( $pages )
						{
							$choices[$post_type] = array();
							
							foreach($pages as $page)
							{
								$title = '';
								$ancestors = get_ancestors($page->ID, 'page');
								if($ancestors)
								{
									foreach($ancestors as $a)
									{
										$title .= '- ';
									}
								}
								
								$title .= apply_filters( 'the_title', $page->post_title, $page->ID );
								
								
								// status
								if($page->post_status != "publish")
								{
									$title .= " ($page->post_status)";
								}
								
								$choices[$post_type][$page->ID] = $title;
								
							}
							// foreach($pages as $page)
						}
						// if( $pages )
					}
					// foreach( $post_types as $post_type )
				}
				// if( $post_types )
				
				break;
			
			
			case "page_type" :
				
				$choices = array(
					'front_page'	=>	__("Front Page",'acf'),
					'posts_page'	=>	__("Posts Page",'acf'),
					'top_level'		=>	__("Top Level Page (parent of 0)",'acf'),
					'parent'		=>	__("Parent Page (has children)",'acf'),
					'child'			=>	__("Child Page (has parent)",'acf'),
				);
								
				break;
				
			case "page_template" :
				
				$choices = array(
					'default'	=>	__("Default Template",'acf'),
				);
				
				$templates = get_page_templates();
				foreach($templates as $k => $v)
				{
					$choices[$v] = $k;
				}
				
				break;
			
			case "post" :
				
				$post_types = get_post_types( array('capability_type'  => 'post') );
				unset( $post_types['attachment'], $post_types['revision'] , $post_types['nav_menu_item'], $post_types['acf']  );
				
				if( $post_types )
				{
					foreach( $post_types as $post_type )
					{
						
						$posts = get_posts(array(
							'numberposts' => '-1',
							'post_type' => $post_type,
							'post_status' => array('publish', 'private', 'draft', 'inherit', 'future'),
							'suppress_filters' => false,
						));
						
						if( $posts)
						{
							$choices[$post_type] = array();
							
							foreach($posts as $post)
							{
								$title = apply_filters( 'the_title', $post->post_title, $post->ID );
								
								// status
								if($post->post_status != "publish")
								{
									$title .= " ($post->post_status)";
								}
								
								$choices[$post_type][$post->ID] = $title;

							}
							// foreach($posts as $post)
						}
						// if( $posts )
					}
					// foreach( $post_types as $post_type )
				}
				// if( $post_types )
				
				
				break;
			
			case "post_category" :
				
				$category_ids = get_all_category_ids();
		
				foreach($category_ids as $cat_id) 
				{
				  $cat_name = get_cat_name($cat_id);
				  $choices[$cat_id] = $cat_name;
				}
				
				break;
			
			case "post_format" :
				
				$choices = get_post_format_strings();
								
				break;
			
			case "user_type" :
				
				global $wp_roles;
				
				$choices = $wp_roles->get_names();
								
				break;
			
			case "taxonomy" :
				
				$choices = array();
				$simple_value = true;
				$choices = apply_filters('acf/get_taxonomies_for_select', $choices, $simple_value);
								
				break;
			
			case "ef_taxonomy" :
				
				$choices = array('all' => __('All', 'acf'));
				$taxonomies = get_taxonomies( array('public' => true), 'objects' );
				
				foreach($taxonomies as $taxonomy)
				{
					$choices[ $taxonomy->name ] = $taxonomy->labels->name;
				}
				
				// unset post_format (why is this a public taxonomy?)
				if( isset($choices['post_format']) )
				{
					unset( $choices['post_format']) ;
				}
			
								
				break;
			
			case "ef_user" :
				
				global $wp_roles;
				
				$choices = array_merge( array('all' => __('All', 'acf')), $wp_roles->get_names() );
			
				break;
				
				
			case "ef_media" :
				
				$choices = array('all' => __('All', 'acf'));
			
				break;
				
		}
		
		
		// allow custom location rules
		$choices = apply_filters( 'acf/location/rule_values/' . $options['param'], $choices );
							
		
		// create field
		do_action('acf/create_field', array(
			'type'	=>	'select',
			'name'	=>	'location[rules][' . $options['key'] . '][value]',
			'value'	=>	$options['value'],
			'choices' => $choices,
		));
		
		
		// ajax?
		if( $is_ajax )
		{
			die();
		}
								
	}	
	
	
	/*
	*  name_save_pre
	*
	*  @description: intercepts the acf post obejct and adds an "acf_" to the start of 
	*				 it's name to stop conflicts between acf's and page's urls
	*  @since 1.0.0
	*  @created: 23/06/12
	*/
		
	function name_save_pre($name)
	{
		// validate
		if( !isset($_POST['post_type']) || $_POST['post_type'] != 'acf' ) 
		{
			return $name;
		}
		
		
		// need a title
		if( !$_POST['post_title'] )
		{
			$_POST['post_title'] = 'Unnamed Field Group';
		}
		
		
        $name = 'acf_' . sanitize_title_with_dashes($_POST['post_title']);
        
        
        return $name;
	}
	
	
	/*
	*  save_post
	*
	*  @description: Saves the field / location / option data for a field group
	*  @since 1.0.0
	*  @created: 23/06/12
	*/
	
	function save_post($post_id)
	{
		// do not save if this is an auto save routine
		if( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE )
		{
			return $post_id;
		}
		
		
		// verify nonce
		if( !isset($_POST['acf_nonce']) || !wp_verify_nonce($_POST['acf_nonce'], 'field_group') )
		{
			return $post_id;
		}
		
		
		// only save once! WordPress save's a revision as well.
		if( wp_is_post_revision($post_id) )
		{
	    	return $post_id;
        }
		
		
		/*
		*  save fields
		*/
		
		// vars
		$dont_delete = array();
		
		if( isset($_POST['fields']) && is_array($_POST['fields']) )
		{
			$i = -1;


			// remove clone field
			unset( $_POST['fields']['field_clone'] );
			
			

			// loop through and save fields
			foreach( $_POST['fields'] as $key => $field )
			{
				$i++;
				
				
				// order + key
				$field['order_no'] = $i;
				$field['key'] = $key;
				
				
				// save
				do_action('acf/update_field', $field, $post_id );
				
				
				// add to dont delete array
				$dont_delete[] = $field['key'];
			}
		}
		unset( $_POST['fields'] );
		
		
		// delete all other field
		$keys = get_post_custom_keys($post_id);
		foreach( $keys as $key )
		{
			if( strpos($key, 'field_') !== false && !in_array($key, $dont_delete) )
			{
				// this is a field, and it wasn't found in the dont_delete array
				do_action('acf/delete_field', $post_id, $key);
			}
		}
		
		
		/*
		*  save location rules
		*/
		
		if( isset($_POST['location']) && is_array($_POST['location']) )
		{
			update_post_meta($post_id, 'allorany', $_POST['location']['allorany']);
			
			delete_post_meta($post_id, 'rule');
			if( $_POST['location']['rules'] )
			{
				foreach($_POST['location']['rules'] as $k => $rule)
				{
					$rule['order_no'] = $k;
					add_post_meta($post_id, 'rule', $rule);
				}
			}
			unset( $_POST['location'] );
		}
		
		
		
		
		
		/*
		*  save options
		*/
		
		if( isset($_POST['options']) && is_array($_POST['options']) )
		{
			update_post_meta($post_id, 'position', $_POST['options']['position']);
			update_post_meta($post_id, 'layout', $_POST['options']['layout']);
			update_post_meta($post_id, 'hide_on_screen', $_POST['options']['hide_on_screen']);
		}

		
		unset( $_POST['options'] );
	
		
	}
	
			
}

new acf_field_group();

?>