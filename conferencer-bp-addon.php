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
		
		
		add_action('admin_enqueue_scripts', array( $this, 'my_admin_enqueue'));
		add_action( 'init', array( $this, 'register_scripts_and_styles' ) );
		add_action('wp_enqueue_scripts', array( $this, 'my_front_enqueue'));
		
		add_action( 'admin_init', array( $this, 'themeblvd_redirect_admin') );
		
		add_action('wp_ajax_get_joinleave_buttons_array', array( $this, 'get_joinleave_buttons_array') );           // for logged in user  
		if (class_exists('rsBuddypressActivityRefresh')){ // add activy refresh for non-logged in users
			add_action('wp_ajax_nopriv_rs_bp_activity_refresh', array('rsBuddypressActivityRefresh', 'ajaxRefresh'));
		}
		if (class_exists('BuddyPress')){
			add_action( 'bp_init', array( $this, 'bp_select_links_in_profile'), 0 );
			add_filter( 'bp_get_group_description', 'do_shortcode' );
			add_action( 'xprofile_updated_profile', array( $this, 'update_extra_profile_fields'),10, 3 );
			add_filter( 'user_contactmethods', array( $this, 'add_hide_profile_fields'),10,1);
			add_action( 'wp_ajax_xprofile_detect_blog_rss', array( $this, 'xprofile_detect_blog_rss'),0,99);
			add_action( 'bp_core_activated_user', array( $this, 'bpdev_add_user_to_registering_blog'));
			//if (class_exists('MailPress')){
				add_action( 'bp_notification_settings', array( $this, 'newsletter_subscription_notification_settings') );
			//}
		}
		if (class_exists('MailPress')){
			wp_cache_add_non_persistent_groups(array('mp_addons', 'mp_mail', 'mp_user', 'mp_field', 'mp_form'));
		}
	} /* __construct() */

	function bp_load_plugin_textdomain() {
		load_textdomain( 'conferencer_bp_addon',  $this->directory_path . '/languages/conferencer-bp-addon.mo' );
		load_textdomain( 'buddypress',  $this->directory_path . '/languages/buddypress-en_US.mo' );
		load_textdomain( 'bp-ass',  $this->directory_path . '/languages/bp-ass-en_US.mo' );
		load_textdomain('cc', $this->directory_path . '/languages/cc-en_US.mo');
	}

	/**
	 * Include our plugin dependencies
	 *
	 * @since 1.0.0
	 */
	public function includes() {
		if ( $this->meets_requirements() ) {
			require_once(sprintf("%s/includes/Conferencer_Shortcode_Agenda_Custom.php", $this->directory_path));
		}

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
	
	function newsletter_subscription_notification_settings() {
		global $bp ;?>
		<table class="notification-settings zebra" id="groups-notification-settings">
		<thead>
			<tr>
				<th class="icon"></th>
				<th class="title">Newsletter Subscription</th>
			</tr>
		</thead>
		<tbody>
			<tr>
				<td></td>
				<td><?php $this->get_mailpress_mlink(bp_core_get_user_email( $bp->loggedin_user->userdata->ID )); ?></td>
			</tr>
		</tbody>
		</table>	
	<?php
	}
	// http://wordpress.org/support/topic/some-very-useful-tips-for-mailpress
	public function get_mailpress_mlink($user_email) {
		if(class_exists('MailPress')){
			echo 'To manage your newsletter subscription goto <a href="'.MP_User::get_unsubscribe_url(MP_User::get_key_by_email($user_email)).'">Manage Newsletter Subscriptions</a>';
		}
	}

	
	/**
	 * Redirect back to homepage and not allow access to 
	 * WP admin for Subscribers.
	 */
	function themeblvd_redirect_admin(){
		if ( ! current_user_can( 'edit_posts' ) && is_admin() && $_SERVER['PHP_SELF'] != '/wp-admin/admin-ajax.php' ){
			wp_redirect( bp_core_get_user_domain(bp_loggedin_user_id()) );
			exit;		
		}
	}
	
	//http://buddydev.com/buddypress/adding-users-to-the-blog-on-which-they-register-in-a-buddypress-wordpress-multisite-based-social-network/ 
	function bpdev_add_user_to_registering_blog( $user_id ) {
		$blog_id = get_current_blog_id();
		if( !is_user_member_of_blog($user_id, $blog_id ) )
		add_user_to_blog( $blog_id, $user_id, get_option('default_role') );
	}
	
	// Handle what's linked in user profile https://gist.github.com/modemlooper/4574785
	function bp_select_links_in_profile() {
	  add_filter( 'bp_get_the_profile_field_value', array($this, 'bp_links_in_profile'), 10, 3 );
	}
	
	
	
	// function to handle links in user profile (removing hyperlink search to bio 
	function bp_links_in_profile( $val, $type, $key ) {
		$field = new BP_XProfile_Field( $key );
		$field_name = $field->name;
		if(  strtolower( $field_name ) == 'bio' ) {
			$val = strip_tags( $val );
		}
		return $val;
	}
	
	// remove and add some wp_usermeta 
	function add_hide_profile_fields( $contactmethods ) {
		unset($contactmethods['aim']);
		unset($contactmethods['jabber']);
		unset($contactmethods['yim']);
		$contactmethods['fwp_twitter'] = 'Twitter';
		$contactmethods['fwp_delicious'] = 'Delicious ID';
		$contactmethods['fwp_diigo'] = 'Diigo ID';
		$contactmethods['fwp_slideshare'] = 'Slideshare ID';
		$contactmethods['blog'] = 'Blog';
		$contactmethods['blogrss'] = 'Blog RSS Feed';
	return $contactmethods;
	}
	
	
	// handling registration of a blog feed with feedwordpress and listed status
	function update_extra_profile_fields($user_id, $posted_field_ids, $errors) {
		// There are errors
		if ( empty( $errors ) ) {
			// Reset the errors var
			$errors = false;
			// Now we've checked for required fields, lets save the values and sync some data to WP_User for FeedWordPress.
			foreach ( (array) $posted_field_ids as $field_id ) {
				$field = new BP_XProfile_Field($field_id);
				switch ($field->name) {
					case('Blog'):
						$blogurl = $_POST['field_'.$field_id];
						update_user_meta($user_id, 'blog', $blogurl);
						break;
					case('Blog RSS'):
						$blogrss = $_POST['field_'.$field_id];
						update_user_meta($user_id, 'blogrss', $blogrss);
						break;
					case('Searchable on members list'):
						$onlist = $_POST['field_'.$field_id];
						break;
					case('Twitter'):
						update_user_meta($user_id, 'fwp_twitter', $_POST['field_'.$field_id]);
						break;
					case('Delicious ID'):
						update_user_meta($user_id, 'fwp_delicious', $_POST['field_'.$field_id]);
						break;
					case('Diigo ID'):
						update_user_meta($user_id, 'fwp_diigo', $_POST['field_'.$field_id]);
						break;
					case('Slideshare ID'):
						update_user_meta($user_id, 'fwp_slideshare', $_POST['field_'.$field_id]);
						break;
				}
			}
		}
		/*if (!$onlist && subscriber_type($user_id) == "subscriber"){
			// http://wordpress.stackexchange.com/a/4727
			$u = new WP_User( $user_id );
			// Remove role
			$u->remove_role( "subscriber" );
			// Add role
			$u->add_role( 'subscriber-unlisted' );
		} elseif ($onlist && subscriber_type($user_id) == "subscriber-unlisted"){
			$u = new WP_User( $user_id );
			$u->remove_role( "subscriber-unlisted" );
			$u->add_role( 'subscriber' );
		}
		*/
		$linkid = get_user_meta($user_id, 'fwp_link_id_'.get_current_blog_id(), true);
		if ($blogrss != "" && $blogurl == "") $blogurl = $blogrss;
		if ($blogrss != "" && $blogurl != ""){ 
			$newid = Conferencer_BP_Addon::make_fwp_link($user_id, $blogurl, $blogrss, $linkid);
			update_user_meta($user_id, 'fwp_link_id_'.get_current_blog_id(), $newid);
		}
	}
	
	// add to wp_link (used by FWP for list of syndicated sites
	public function make_fwp_link($user_id, $blogurl, $blogrss, $linkid = false) {
		// a lot of this was inspired by http://wrapping.marthaburtis.net/2012/08/22/perfecting-the-syndicated-blog-sign-up/
		remove_filter('pre_link_rss', 'wp_filter_kses');
		remove_filter('pre_link_url', 'wp_filter_kses');
		// Get contributors category 
		$mylinks_categories = get_terms('link_category', 'name__like=Contributors');
		$contrib_cat = intval($mylinks_categories[0]->term_id);
		
		$link_notes = 'map authors: name\n*\n'.$user_id;
		$new_link = array(
				'link_name' => $blogurl,
				'link_url' => $blogurl,
				'link_category' => $contrib_cat,
				'link_rss' => $blogrss
				);
		if( !function_exists( 'wp_insert_link' ) ) {
			include_once( ABSPATH . '/wp-admin/includes/bookmark.php' );	
		}
		if (!($linkid)) { // if no link insert new link
			$linkid = wp_insert_link($new_link);
			// update new link with notes
		} else {
			//update existing link
			$new_link['link_id'] = $linkid;
			$linkid = wp_insert_link($new_link);
		}
		// update notes in db as wp_insert_link escapes serialisation
		global $wpdb;
		$esc_link_notes = $wpdb->escape($link_notes);
		$result = $wpdb->query("
			UPDATE $wpdb->links
			SET link_notes = \"".$esc_link_notes."\" 
			WHERE link_id='$linkid'
		");
		return $linkid;
	}
	
	
	// function to detect blog rss feed from url
	public function xprofile_detect_blog_rss() {
		$url = trim($_POST['blog']);
		// if not valid url try adding http
		if (!filter_var($url, FILTER_VALIDATE_URL, FILTER_FLAG_HOST_REQUIRED)){
			$url = "http://".$url;
		}
		$id = $_POST['id'];
		$output = '<select name="'.$id.'" id="'.$id.'">';
		// stolen from Alan Levine (@cogdog)
		if($html = @DOMDocument::loadHTML(file_get_contents($url))) {
	  
			$xpath = new DOMXPath($html);
			$options = false;
			 
			// find RSS 2.0 feeds
			$feeds = $xpath->query("//head/link[@href][@type='application/rss+xml']/@href");
			foreach($feeds as $feed) {
				//$results[] = $feed->nodeValue;
				$urlStr = $feed->nodeValue;
				$parsed = parse_url($urlStr);
				if (empty($parsed['scheme'])) $urlStr = untrailingslashit($url).$urlStr;
				$options .= '<option value="'.$urlStr.'">'.$urlStr.'</option>';
			}
	  
			 // find Atom feeds
			$feeds = $xpath->query("//head/link[@href][@type='application/atom+xml']/@href");
			foreach($feeds as $feed) {
				//$results[] = $feed->nodeValue;
				$urlStr = $feed->nodeValue;
				$parsed = parse_url($urlStr);
				if (empty($parsed['scheme'])) $urlStr = untrailingslashit($url).$urlStr;
				$options .= '<option value="'.$urlStr.'">'.$urlStr.'</option>';
			}
			
		}
		
		$options .= '<option value="Other">Other</option>';
		$output .= $options.'</select>';
		$output .= '<input id="other_feed" name="other_feed" type="text" placeholder="Enter other feed" />';
		
		echo $output;
		exit();
	}
	
	/*// adding custom jQuery to profile edit page
	function conc_wp_profile_edit() {
		wp_enqueue_script(
			'profile-edit',
			get_stylesheet_directory_uri() . '/js/profile.js',
			array( 'jquery' )
		);  
	}
	add_filter( 'bp_before_profile_edit_content', 'conc_wp_profile_edit');*/

	public function get_plugin_url(){
		return 	plugin_basename( __FILE__ );
	}

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
		$session_meta = do_shortcode('[session_meta post_id='.$id.']');
		$rec = "";
		$youtube_id = get_post_meta($id, 'conc_wp_live', true);
		if ($youtube_id && strlen($youtube_id) > 2) {
			$help_url = "http://altc.alt.ac.uk/conference/2014/help/online-participation/";
			$instruc = sprintf( __('Please <a href="%s">register</a>/login and follow this session to comment on this video (<a href="%s">Help</a>).<br/>Contributing via Twitter and have a question during the live session use the tags #altc #Q', 'conferencer_bp_addon'), bp_get_signup_page(), $help_url) ;
			//$rec = '<a href="'.$islive.'" class="islive">RECORDING</a>';
			$youtube = sprintf('<div class="youtube"><iframe width="540" height="340" src="//www.youtube.com/embed/%s?enablejsapi=1" frameborder="0" allowfullscreen="allowfullscreen"></iframe></div><div class="youtube_info">%s</div>', $youtube_id, $instruc ) ;
		}	
		$chat_id = get_post_meta($id, 'conc_wp_chat', true);
		$chat_embed = "";
		if($chat_id){
			$chat_embed = '<iframe width="450" height="350" frameborder="0" scrolling="no" marginheight="0" marginwidth="0" allowtransparency="true" src="http://chatroll.com/embed/chat/altc?id=Kex3IVaS50M&platform=html&w=$0"></iframe>'; //do_shortcode("[chatroll width='100%' height='350' id='Kex3IVaS50M' name='altc' apikey='kyhdrdzoyxlbhh3p']");
		}
		
		if (!is_admin()){
			$content = '<div style="float:right;">'.$this->google_calendar_link($id).$this->ics_calendar_link($id).'</div>'
					   .$session_meta.'<div style="clear:both">'.$content.$youtube.$chat_embed.'</div>';
		} else {
			$content = $session_meta;
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
		if(isset($wp_query->query_vars['ical']) && ($wp_query->query_vars['ical']=="1" || $wp_query->query_vars['ical']=="all")) {
			//send headers
			if (isset($wp_query->query_vars['sessionid']) && $wp_query->query_vars['sessionid']!== ""){
				$filename = $this->get_the_slug($wp_query->query_vars['sessionid']);
			} else {
				$filename = bp_core_get_username($wp_query->query_vars['user_id']);
			}
			header('Content-type: text/calendar; charset=utf-8');
			header('Content-Disposition: inline; filename="'.$wp_query->query_vars['sessionid'].'.ics"');
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
		wp_dequeue_script( 'jquery-timeago-js');
		wp_dequeue_script( 'rs-bp-activity-refresh-ajax-js');
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
		wp_dequeue_script( 'conferencer' );\
		wp_dequeue_script( 'jquery-timeago-js');
		wp_dequeue_script( 'rs-bp-activity-refresh-ajax-js' );
		wp_enqueue_script( 'jquery-address' );
		wp_enqueue_script( 'conferencer-addon' );
		wp_enqueue_style( 'conferencer-addon-style' );
	}
	
	function register_scripts_and_styles() {
		wp_enqueue_script( 'con-jquery-timeago-js', $this->directory_url . '/js/jquery.timeago.js', array( 'jquery' ) );
		wp_register_script( 'jquery-address', $this->directory_url . '/js/jquery.address-1.5.min.js', array( 'jquery' ));
		wp_register_script( 'conferencer-addon', $this->directory_url . '/js/conferencer-addon.js', array( 'jquery' ), '1.0.74');
		wp_register_style( 'conferencer-addon-style', $this->directory_url . '/css/style.css', NULL, '1.0.51' );
	}

	function add_query_vars($aVars) {
		$aVars[] = 'ical';
		$aVars[] = 'sessionid'; 
		return $aVars;
	}
	
	function get_joinleave_buttons_array(){
		$user_id = get_current_user_id();
		if(!$user_id){
			echo('false');
			die();
			return;
		} 
		
		$gids = explode(",",$_POST['gids']);
		$buts = array();
		/*$user_groups = groups_get_groups(array('user_id' => $user_id,
											   'populate_extras' => false,
											   'per_page' => -1));
		echo json_encode($user_groups );
		die();*/		
		//groups_is_user_member( $user_id, $group_id );							   
		foreach($gids as $group_id){
			$group = groups_get_group( array( 'group_id' => $gid ) );
			$group_url = trailingslashit( bp_get_root_domain() . '/' . bp_get_groups_root_slug() . '/' . groups_get_slug($group_id) . '/' );
			if (groups_is_user_member( $user_id, $group_id )){
				$buts[$group_id] = '<a id="group-' . esc_attr( $group_id ) . '" class="leave-group" rel="leave" title="' . __( 'Leave Group', 'buddypress' ) . '" href="' . wp_nonce_url( $group_url . 'leave-group', 'groups_leave_group' ) . '">' . __( 'Leave Group', 'buddypress' ) . '</a>';
			} else {
				$buts[$group_id] = '<a id="group-' . esc_attr( $group_id ) . '" class="join-group" rel="join" title="' . __( 'Join Group', 'buddypress' ) . '" href="' . wp_nonce_url( $group_url . 'join', 'groups_join_group' ) . '">' . __( 'Join Group', 'buddypress' ) . '</a>';	
			}
		}
		echo json_encode($buts);
		die();
	}
	
	public function bp_group_join_button_from_id($group_id){
			if ($group_id != '45'){
				return;	
			}
			global $bp;
			$group = groups_get_group( array( 'group_id' => $group_id ) );
			return ($group);
			if (empty($group))
				return false;
			if ( !is_user_logged_in() || bp_group_is_user_banned( $group ) )
				return false;
	
			// Group creation was not completed or status is unknown
			if ( !$group->status )
				return false;
			
			// Already a member
			if ( isset( $group->is_member ) && $group->is_member ) {
	
				// Stop sole admins from abandoning their group
				$group_admins = groups_get_group_admins( $group->id );
				if ( 1 == count( $group_admins ) && $group_admins[0]->user_id == bp_loggedin_user_id() )
					return false;
	
				$button = array(
					'id'                => 'leave_group',
					'component'         => 'groups',
					'must_be_logged_in' => true,
					'block_self'        => false,
					'wrapper_class'     => 'group-button prog ' . $group->status,
					'wrapper_id'        => 'groupbutton-' . $group->id,
					'link_href'         => wp_nonce_url( bp_get_group_permalink( $group ) . 'leave-group', 'groups_leave_group' ),
					'link_text'         => __( 'Leave Group', 'buddypress' ),
					'link_title'        => __( 'Leave Group', 'buddypress' ),
					'link_class'        => 'group-button leave-group',
				);
	
			// Not a member
			} else {
	
				// Show different buttons based on group status
				switch ( $group->status ) {
					case 'hidden' :
						return false;
						break;
	
					case 'public':
						$button = array(
							'id'                => 'join_group',
							'component'         => 'groups',
							'must_be_logged_in' => true,
							'block_self'        => false,
							'wrapper_class'     => 'group-button prog ' . $group->status,
							'wrapper_id'        => 'groupbutton-' . $group->id,
							'link_href'         => wp_nonce_url( bp_get_group_permalink( $group ) . 'join', 'groups_join_group' ),
							'link_text'         => __( 'Join Group', 'buddypress' ),
							'link_title'        => __( 'Join Group', 'buddypress' ),
							'link_class'        => 'group-button join-group',
						);
						break;
	
					case 'private' :
						return false;
						break;
	
				}
			}
		return (bp_get_button( apply_filters( 'bp_get_group_join_button', $button ) ));
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

/*
Plugin Name: Custom Profile Filters for BuddyPress
Plugin URI: http://dev.commons.gc.cuny.edu
Description: Changes the way that profile data fields get filtered into clickable URLs.
Version: 0.3.1
Author: Boone Gorges
Author URI: http://teleogistic.net
*/
$no_link_fields           = array( // Enter the field ID of any field that you want to appear as plain, non-clickable text. Don't forget to separate with commas.
	'Skype ID ',
	'Phone',
	'IM'
);
$social_networking_fields = array( // Enter the field ID of any field that prompts for the username to a social networking site, followed by the URL format for profiles on that site, with *** in place of the user name. Thus, since the URL for the profile of awesometwitteruser is twitter.com/awesometwitteruser, you should enter 'Twitter' => 'twitter.com/***'. Don't forget: 1) Leave out the 'http://', 2) Separate items with commas
	'Twitter' => 'twitter.com/***',
	'Delicious ID' => 'delicious.com/***',
	'Diigo ID' => 'diigo.com/user/***',
	'YouTube ID' => 'youtube.com/***',
	'Flickr ID' => 'flickr.com/***',
	'Slideshare ID' => 'slideshare.net/***',
	'FriendFeed ID' => 'friendfeed.com/***',
	'LinkedIn' => 'linkedin.com/in/***',
	'Google+' => 'plus.google.com/***',
);
/* Only load the BuddyPress plugin functions if BuddyPress is loaded and initialized. */
function custom_profile_filters_for_buddypress_init() {
	require( dirname( __FILE__ ) . '/includes/custom-profile-filters-for-buddypress-bp-functions.php' );
}

add_action( 'bp_init', 'custom_profile_filters_for_buddypress_init' );

