<?php
/**
 * The WordPress Plugin Boilerplate.
 *
 * A foundation off of which to build well-documented WordPress plugins that
 * also follow WordPress Coding Standards and PHP best practices.
 *
 * @package   Plugin_Name
 * @author    mhawksey <martin.hawksey@alt.ac.uk>
 * @license   GPL-2.0+
 * @link      http://alt.ac.uk
 * @copyright 2014 Association for Learning Technology
 *
 * @wordpress-plugin
 * Plugin Name:       Conferencer BuddyPress Add-on
 * Plugin URI:        https://github.com/mhawksey/conferencer-bp-addon
 * Description:       Conferencer Add-on to integrate with BuddyPress
 * Version:           1.0.0
 * Author:            mhawksey
 * Author URI:        http://mashe.hawksey.info
 * Text Domain:       conferencer-bp-addon-locale
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Domain Path:       /languages
 * GitHub Plugin URI: https://github.com/mhawksey/conferencer-bp-addon
 * WordPress-Plugin-Boilerplate: v2.6.1
 */
 
 /**
 * Our main plugin instantiation class
 *
 * This contains important things that our relevant to
 * our add-on running correctly. Things like registering
 * custom post types, taxonomies, posts-to-posts
 * relationships, and the like.
 *
 * @since 1.0.0
 */
class Conferencer_BP_Addon {
	public $depend = array('Conferencer' => 'https://wordpress.org/plugins/conferencer/',
						   'BuddyPress' => 'http://wordpress.org/plugins/buddypress/');
	/**
	 * Get everything running.
	 *
	 * @since 1.0.0
	 */
	function __construct() {

		// Define plugin constants
		$this->basename       = plugin_basename( __FILE__ );
		$this->directory_path = plugin_dir_path( __FILE__ );
		$this->directory_url  = plugins_url( dirname( $this->basename ) );
		
		add_action ( 'plugins_loaded', array( $this, 'bp_load_plugin_textdomain'), 9 );

		// Run our activation and deactivation hooks
		register_activation_hook( __FILE__, array( $this, 'activate' ) );
		register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );

		// If Conferencer is unavailable, deactivate our plugin
		add_action( 'admin_notices', array( $this, 'maybe_disable_plugin' ) );

		// Include our other plugin files
		add_action( 'init', array( $this, 'includes' ) );
		add_action( 'admin_init', array($this, 'session_sync_init') );
		
		// hook add_query_vars function into query_vars
		add_filter('query_vars', array( $this, 'add_query_vars'));
		add_action ( 'parse_query', array( $this, 'ical_event') );
		
		add_shortcode( 'session-content', array( $this, 'session_content_func') );
		add_filter( 'bp_get_group_description', 'do_shortcode' );
		
		add_action('admin_enqueue_scripts', array( $this, 'my_admin_enqueue'));
		add_action( 'init', array( $this, 'register_scripts_and_styles' ) );
		add_action('wp_enqueue_scripts', array( $this, 'my_front_enqueue'));

		// add open badges logging
		/*add_action( 'init', array( $this, 'open_badges_log_post_type' ) );

		add_action( 'init', array( $this, 'register_scripts_and_styles' ) );

		add_shortcode( 'badgeos_backpack_push', array(&$this, 'badgeos_backpack_push_shortcode') );
		add_shortcode( 'badgeos_backpack_registered_email', array(&$this, 'badgeos_backpack_reg_email_shortcode') );
		if (get_option('open_badges_issuer_public_evidence')){
			add_filter('badgeos_public_submissions', array(&$this, 'set_public_badge_submission'), 999, 1);
		}

		add_action( 'wp_ajax_open_badges_recorder', array(&$this, 'badgeos_ajax_open_badges_recorder'));
		//add_action( 'wp_ajax_open_badges_recorder', 'badgeos_ajax_open_badges_recorder');
		// not doing it this way as achievement ids are handled differently
		//add_filter('badgeos_render_achievement', array( $this, 'badgeos_render_openbadge_button'), 10 ,2);
		*/
	} /* __construct() */

	function bp_load_plugin_textdomain() {
		load_textdomain( 'conferencer_bp_addon',  plugin_dir_path( __FILE__ ) . '/languages/conferencer-bp-addon.mo' );
		load_textdomain( 'buddypress',  plugin_dir_path( __FILE__ ) . '/languages/buddypress-en_US.mo' );
	}

	/**
	 * Include our plugin dependencies
	 *
	 * @since 1.0.0
	 */
	public function includes() {

		// If BadgeOS is available...
		/*if ( $this->meets_requirements() ) {
			// add custom JSON API controllers
			add_filter('json_api_controllers', array(&$this,'add_badge_controller'));
			add_filter('json_api_badge_controller_path', array(&$this,'set_badge_controller_path'));
		}
		// Initialize Settings
		require_once(sprintf("%s/includes/settings.php", $this->directory_path));
		$BadgeOS_OpenBadgesIssuer_Settings = new BadgeOS_OpenBadgesIssuer_Settings();

		// Add logging functions
		require_once(sprintf("%s/includes/logging-functions.php", $this->directory_path));
		$BadgeOS_OpenBadgesIssuer_Logging = new BadgeOS_OpenBadgesIssuer_Logging();
		*/

	} /* includes() */



	/**
	 * Include our session shortcode
	 *
	 * @since 1.0.0
	 */
	public function session_content_func( $atts=array() ) {
		extract($atts );
		$p = get_post($id);
		$post_content = $p->post_content;
		$new_excerpt = generate_excerpt($id);
		if (strlen($new_excerpt)>140){
			$content = '<div class="excerpt">'.$new_excerpt.'... <a href="" class="read">Read More</a>  </div>
					<div class="content">'.wpautop($post_content).' <a href="" class="read-less">Read Less</a></div>';
		} else {
			$content = '<div class="content">'.wpautop($post_content).'</div>';
		}
		$prefix = do_shortcode('[session_meta post_id='.$id.']');
		$rec = "";
		$islive = get_post_meta($id, 'conc_wp_live', true);
		if ($islive && strlen($islive) > 2) $rec = '<a href="'.$islive.'" class="islive">RECORDING</a>';
		
		if (!is_admin()){
			$content = $prefix.$content.'<div>'.$rec.$this->google_calendar_link($id).$this->ics_calendar_link($id).'</div>';
		} else {
			$content = $prefix;
		}
		
		// return apply_filters( 'session-content-shortcodes-content', apply_filters( 'the_content', $content ), $p );
		return $content;
	}
	
	function render_calendar($id){ 
		return '<div style="float:right">'.$this->google_calendar_link($id).$this->ics_calendar_link($id).'</div>';
	}

	function ics_calendar_link($id){ 
		$img_url = $this->directory_url . '/images/icons/add-to-calendar.png';
		return '<div class="calendar_button"><a href="?ical=1&sessionid='.$id.'" title="Download .ics file for your calendar software" onclick="_gaq.push([\'_trackEvent\', \'Calendar\', \'iCal\', \''.$this->get_the_slug($id).'\']);"><img src="'.esc_url($img_url).'" alt="0" border="0" title="Download event details calendar button"></a></div>';
	}
	
	/**
	 * Generates an ics file for a single event 
	 * Modified from http://wordpress.org/plugins/events-manager/
	 */
	function ical_event($wp_query){
		if(isset($wp_query->query_vars['ical']) && $wp_query->query_vars['ical']=="1") {
			//send headers
			header('Content-type: text/calendar; charset=utf-8');
			header('Content-Disposition: inline; filename="'.$this->get_the_slug($wp_query->query_vars['sessionid']).'.ics"');
			load_template( $this->directory_path . 'includes/ical.php');
			exit();
		}
	}
	
	function google_calendar_link($id){
		$post = get_post($id);
		Conferencer::add_meta($post);
		// Modified from http://wordpress.org/plugins/events-manager/ em-events.php
		//get dates
		$dateStart	= date('Ymd\THis\Z',get_post_meta($post->time_slot, '_conferencer_starts', true) - (60*60*get_option('gmt_offset')) ); 
		$dateEnd = date('Ymd\THis\Z',get_post_meta($post->time_slot, '_conferencer_ends', true) - (60*60*get_option('gmt_offset')));
		//build url
		$gcal_url = 'http://www.google.com/calendar/event?action=TEMPLATE&text=event_name&dates=start_date/end_date&details=post_content&location=location_name&trp=false&sprop=event_url&sprop=name:blog_name';
		$gcal_url = str_replace('event_name', urlencode($post->post_title), $gcal_url);
		$gcal_url = str_replace('start_date', urlencode($dateStart), $gcal_url);
		$gcal_url = str_replace('end_date', urlencode($dateEnd), $gcal_url);
		$gcal_url = str_replace('location_name', urlencode(get_the_title($post->room)), $gcal_url);
		$gcal_url = str_replace('blog_name', urlencode(get_bloginfo()), $gcal_url);
		$gcal_url = str_replace('event_url', urlencode(get_permalink($id)), $gcal_url);
		//calculate URL length so we know how much we can work with to make a description.
		if( !empty($post->post_excerpt) ){
			$gcal_url_description = $post->post_excerpt;
		}else{
			$matches = explode('<!--more', $post->post_content);
			$gcal_url_description = strip_tags(wp_kses_data($matches[0]));
			
		}
		$gcal_url_length = strlen($gcal_url) - 9;
		$gcal_url_description = strip_tags(do_shortcode("[session_meta
								post_id='$post->ID'
								show='room,track'
								room_prefix='Room: '
								room_suffix='\n'
								track_prefix='Track: '
								link_all=false]"))."\n\n".generate_excerpt($post);
		if( strlen($gcal_url_description) + $gcal_url_length > 1350 ){
			$gcal_url_description = substr($gcal_url_description, 0, 1380 - $gcal_url_length - 3 ).'...';
		}	
		$gcal_url_description .= "\n\n".get_permalink($id);
	
		$gcal_url = str_replace('post_content', urlencode($gcal_url_description), $gcal_url);
		//get the final url
		$replace = $gcal_url;
		//if( $result == '#_EVENTGCALLINK' ){
			$img_url = 'www.google.com/calendar/images/ext/gc_button2.gif';
			$img_url = is_ssl() ? 'https://'.$img_url:'http://'.$img_url;
			$replace = '<div class="calendar_button"><a href="'.$replace.'" target="_blank" title="Add to your Google Calendar"  onclick="_gaq.push([\'_trackEvent\', \'Calendar\', \'Google\', \''.$this->get_the_slug($id).'\']);"><img src="'.esc_url($img_url).'" alt="0" border="0" title="Add event to your Google Calendar button"></a></div>';
		//}	
		return $replace;
	}
	// http://www.tcbarrett.com/2013/05/wordpress-how-to-get-the-slug-of-your-post-or-page/#.UexMz41ORsk
	function get_the_slug( $id=null ){
	  if( empty($id) ):
		global $post;
		if( empty($post) )
		  return ''; // No global $post var available.
		$id = $post->ID;
	  endif;
	  $slug = basename( get_permalink($id) );
	  return $slug;
	}
	
	function session_sync_init() {
		if ( current_user_can( 'delete_posts' ) ){
			add_action('new_to_publish', array( $this, 'session_group_create'));		
			add_action('draft_to_publish', array( $this, 'session_group_create'));		
			add_action('pending_to_publish', array( $this, 'session_group_create'));
			add_action('before_delete_post', array( $this, 'session_group_delete'));
			add_action('save_post', array( $this, 'session_group_save'));
		}
	}
	
	function session_group_save($post_id){
		// If this isn't a 'session' post, don't update it.
		if ( $_POST['post_type'] != 'session') return;
		if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
		$group_id = get_post_meta($post_id, 'con_group', true);
		if (!$group_id) return;
		$slug = $_POST['post_name'];
		$group = groups_get_group( array( 'group_id' => $group_id ) );
 		$group_name = get_the_title( $post_id );
		groups_edit_base_group_details ($group_id, $group_name, $group->description, false );
		
		global $bp, $wpdb;
		$slug = $_POST['post_name'];
		if ( $group_id && $slug ) {
			$sql = $wpdb->prepare( "UPDATE {$bp->groups->table_name} SET slug = %s WHERE id = %d", $slug, $group_id );
			return $wpdb->query( $sql );
		}
	}
	
	function session_group_delete($post_id){
		global $post_type;
		if ( $post_type != 'session' ) return;
		$group_id = get_post_meta($post_id, 'con_group', true);
		if ($group_id){
			groups_delete_group($group_id);
		}
	}
	
	function session_group_create($post){
		$post_id = $post->ID;
		if ($post->post_type == 'session') {
			$new_group = new BP_Groups_Group;
	 
			$new_group->creator_id = 1;
			$new_group->name = $post->post_title;
			$new_group->slug = $post->post_name;
			$new_group->description = '[session-content id='.$post_id.']';
			$new_group->status = 'public';
			$new_group->is_invitation_only = 0;
			$new_group->enable_wire = 1;
			$new_group->enable_forum = 0;
			$new_group->enable_photos = 1;
			$new_group->photos_admin_only = 1;
			$new_group->date_created = current_time('mysql');
			$new_group->total_member_count = 0;
	 
			$new_group -> save();
		 
			$group_id = $new_group->id;
			
			groups_update_groupmeta( $group_id, 'total_member_count', 0 );
			groups_update_groupmeta( $group_id, 'last_activity', current_time('mysql') );
			groups_update_groupmeta( $group_id, 'ass_default_subscription', 'dig');
			groups_update_groupmeta( $group_id, 'invite_status', 'members' );
			groups_accept_invite(1, $group_id );
			groups_promote_member(1, $group_id, 'admin');
			
			
			add_post_meta($post_id, 'con_group', $group_id, true);
		}
		return true;
	}
	
	// patch for conferencer to load jQuery 1.7.2 in reordering page (.curCSS depreciated in jQuery 1.8)
	// http://wordpress.stackexchange.com/a/7282
	function my_admin_enqueue($hook_suffix) {
		if($hook_suffix == 'conferencer_page_conferencer_reordering') {
			// http://wordpress.org/support/topic/error-has-no-method-curcss#post-3964638
			wp_deregister_script('jquery');
			wp_deregister_script( 'jquery-ui-core' );
			wp_deregister_script( 'jquery-ui-draggable' );
			wp_register_script('jquery', ("http://ajax.googleapis.com/ajax/libs/jquery/1.7.2/jquery.min.js"), false, '1.7.2', false);
			wp_enqueue_script('jquery');
		}
		if($hook_suffix == 'toplevel_page_bp-groups'){
			echo "<style>#bp-groups-form span.delete, .add-new-h2, #bp_groups_name {display:none}</style>";	
		}
	}
	
	function my_front_enqueue() {
		wp_enqueue_script('conferencer-addon');
	}
	
	function register_scripts_and_styles() {
		wp_register_script( 'conferencer-addon', $this->directory_url . '/js/conferencer-addon.js', array( 'jquery' ), '1.0.4');
	}

	function add_query_vars($aVars) {
		$aVars[] = 'ical';
		$aVars[] = 'sessionid'; 
		return $aVars;
	}

	/**
	* Register controllers for custom JSON_API end points.
	*
	* @since 1.0.0
	* @param object $controllers JSON_API.
	* @return object $controllers.
	*/
	public function add_badge_controller($controllers) {
	  $controllers[] = 'badge';
	  return $controllers;
	}
	
	/**
	* Register controllers define path custom JSON_API end points.
	*
	* @since 1.0.0
	*/
	public function set_badge_controller_path() {
	  return sprintf("%s/api/badge.php", $this->directory_path);
	}


	/**
	 * Activation hook for the plugin.
	 *
	 * @since 1.0.0
	 */
	public function activate() {

		// If BadgeOS is available, run our activation functions
		/*
		if ( $this->meets_requirements() ) {
			$json_api_controllers = explode(",", get_option( 'json_api_controllers' ));
			if(!in_array('badge',$json_api_controllers)){
				$json_api_controllers[] = 'badge';
				JSON_API::save_option('json_api_controllers', implode(',', $json_api_controllers));
			}

			// Do some activation things

		}
		*/
	} /* activate() */

	/**
	 * Deactivation hook for the plugin.
	 *
	 * Note: this plugin may auto-deactivate due
	 * to $this->maybe_disable_plugin()
	 *
	 * @since 1.0.0
	 */
	public function deactivate() {

		// Do some deactivation things.

	} /* deactivate() */

	/**
	 * Check if BadgeOS is available
	 *
	 * @since  1.0.0
	 * @return bool True if BadgeOS is available, false otherwise
	 */
	public function meets_requirements() {
		foreach ($this->depend as $class => $url){ 
			if (!class_exists($class)){
				return false;	
			}
		}
		return true;
	} /* meets_requirements() */

	/**
	 * Potentially output a custom error message and deactivate
	 * this plugin, if we don't meet requriements.
	 *
	 * This fires on admin_notices.
	 *
	 * @since 1.0.0
	 */
	public function maybe_disable_plugin() {

		if ( ! $this->meets_requirements() ) {
			// Display our error
			echo '<div id="message" class="error">';
			foreach ($this->depend as $class => $url){ 
				if ( !class_exists($class)) {
					$extra = sprintf('<a href="%s">%s</a>', $url, $class); 
					echo '<p>' . sprintf( __( 'Conferencer BP Add-on requires %s and has been <a href="%s">deactivated</a>. Please install and activate %s and then reactivate this plugin.', 'conferencer_addon' ),  $extra, admin_url( 'plugins.php' ), $extra ) . '</p>';
				}
			}
			echo '</div>';

			// Deactivate our plugin
			deactivate_plugins( $this->basename );
		}

	} /* maybe_disable_plugin() */

} /* BadgeOS_Addon */

// Instantiate our class to a global variable that we can access elsewhere
$GLOBALS['conferencer_bp_addon'] = new Conferencer_BP_Addon();

