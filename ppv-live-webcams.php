<?php
/*
Plugin Name: Video PPV Live Webcams
Plugin URI: http://www.videowhisper.com/?p=WordPress-PPV-Live-Webcams
Description: VideoWhisper PPV Live Webcams
Version: 1.3.2
Author: VideoWhisper.com
Author URI: http://www.videowhisper.com/
Contributors: videowhisper, VideoWhisper.com
*/

if (!class_exists("VWliveWebcams"))
{
	class VWliveWebcams {

		function VWliveWebcams() { //constructor

		}

		//! Plugin Hooks

		function init()
		{
			//setup post
			VWliveWebcams::webcam_post();

		}

		function plugins_loaded()
		{
			//settings link in plugins view
			$plugin = plugin_basename(__FILE__);
			add_filter("plugin_action_links_$plugin",  array('VWliveWebcams','settings_link') );

			$options = get_option('VWliveWebcamsOptions');

			add_filter( 'login_redirect', array('VWliveWebcams','login_redirect'), 10, 3 );

			//webcam post handling
			add_filter("the_content", array('VWliveWebcams','the_content'));
			add_filter('pre_get_posts', array('VWliveWebcams','pre_get_posts'));

			//shortcodes
			add_shortcode('videowhisper_webcams', array( 'VWliveWebcams', 'videowhisper_webcams'));
			add_shortcode('videowhisper_webcams_performer', array( 'VWliveWebcams', 'videowhisper_webcams_performer'));
			add_shortcode('videowhisper_messenger', array( 'VWliveWebcams', 'videowhisper_messenger'));


			//web app ajax calls
			add_action( 'wp_ajax_vmls', array('VWliveWebcams','vmls_callback') );
			add_action( 'wp_ajax_nopriv_vmls', array('VWliveWebcams','vmls_callback') );

			add_action( 'wp_ajax_vmls_cams', array('VWliveWebcams','vmls_cams_callback') );
			add_action( 'wp_ajax_nopriv_vmls_cams', array('VWliveWebcams','vmls_cams_callback') );

			//sql fast session processing tables
			//check db
			$vmls_db_version = "1.2";

			global $wpdb;

			$table_name = $wpdb->prefix . "vw_vmls_sessions";
			$table_name4 = $wpdb->prefix . "vw_vmls_private";

			$installed_ver = get_option( "vmls_db_version" );

			if( $installed_ver != $vmls_db_version )
			{
				$wpdb->flush();

				$sql = "DROP TABLE IF EXISTS `$table_name`;
		CREATE TABLE `$table_name` (
		  `id` int(11) NOT NULL auto_increment,
		  `session` varchar(64) NOT NULL,
		  `username` varchar(64) NOT NULL,
		  `room` varchar(64) NOT NULL,
		  `message` text NOT NULL,
		  `sdate` int(11) NOT NULL,
		  `edate` int(11) NOT NULL,
		  `status` tinyint(4) NOT NULL,
		  `type` tinyint(4) NOT NULL,
		  PRIMARY KEY  (`id`),
		  KEY `status` (`status`),
		  KEY `type` (`type`),
		  KEY `room` (`room`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Video Whisper: Sessions - 2015@videowhisper.com' AUTO_INCREMENT=1 ;

		DROP TABLE IF EXISTS `$table_name4`;
		CREATE TABLE `$table_name4` (
		  `id` int(11) NOT NULL auto_increment,
  		  `room` varchar(64) NOT NULL,
		  `performer` varchar(64) NOT NULL,
		  `client` varchar(64) NOT NULL,
		  `pid` int(11) NOT NULL,
		  `cid` int(11) NOT NULL,
		  `psdate` int(11) NOT NULL,
		  `pedate` int(11) NOT NULL,
		  `csdate` int(11) NOT NULL,
		  `cedate` int(11) NOT NULL,
		  `status` tinyint(4) NOT NULL,
		  PRIMARY KEY  (`id`),
		  KEY `room` (`room`),
		  KEY `performer` (`performer`),
		  KEY `client` (`client`),
		  KEY `status` (`status`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Video Whisper: Private Sessions - 2015@videowhisper.com' AUTO_INCREMENT=1 ;
		";

				require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
				dbDelta($sql);

				if (!$installed_ver) add_option("vmls_db_version", $vmls_db_version);
				else update_option( "vmls_db_version", $vmls_db_version );

				$wpdb->flush();

			}


		}

		/*
	 //remove widgets
		function sidebars_widgets( $sidebars_widgets )
		{
			if (!is_single()) return $sidebars_widgets;

			$options = get_option('VWliveWebcamsOptions');

			$postID = get_the_ID();
			if (! get_post_type( $postID ) == $options['custom_post']) return $sidebars_widgets;

			// foreach ($sidebars_widgets as $key=>$value) unset($sidebars_widgets[$key]);
			$sidebars_widgets = array( false );

			return $sidebars_widgets;
		}

	 //remove sidebar
		function get_sidebar( $name )
		{
			if (!is_single())  return $name;

			$options = get_option('VWliveWebcamsOptions');

			$postID = get_the_ID();
			if (! get_post_type( $postID ) == $options['custom_post']) return $name;

			// Avoid recurrsion: remove itself
			remove_filter( current_filter(), __FUNCTION__ );
			return get_sidebar( 'webcams' );
		}
*/

		//! Webcam Post Type

		function webcam_post() {

			$options = get_option('VWliveWebcamsOptions');

			//only if missing
			if (post_type_exists($options['custom_post'])) return;

			$labels = array(
				'name'                => _x( 'Webcams', 'Post Type General Name', 'live_webcams' ),
				'singular_name'       => _x( 'Webcam', 'Post Type Singular Name', 'live_webcams' ),
				'menu_name'           => __( 'Webcams', 'live_webcams' ),
				'parent_item_colon'   => __( 'Parent Webcam:', 'live_webcams' ),
				'all_items'           => __( 'All Webcams', 'live_webcams' ),
				'view_item'           => __( 'View Webcam', 'live_webcams' ),
				'add_new_item'        => __( 'Add New Webcam', 'live_webcams' ),
				'add_new'             => __( 'New Webcam', 'live_webcams' ),
				'edit_item'           => __( 'Edit Webcam', 'live_webcams' ),
				'update_item'         => __( 'Update Webcam', 'live_webcams' ),
				'search_items'        => __( 'Search Webcams', 'live_webcams' ),
				'not_found'           => __( 'No webcams found', 'live_webcams' ),
				'not_found_in_trash'  => __( 'No webcams found in Trash', 'live_webcams' ),
			);

			$args = array(
				'label'               => __( 'webcam', 'live_webcams' ),
				'description'         => __( 'Live Webcams', 'live_webcams' ),
				'labels'              => $labels,
				'supports'            => array( 'title', 'editor', 'author', 'thumbnail', 'comments', 'custom-fields', 'page-attributes', ),
				'taxonomies'          => array( 'category', 'post_tag' ),
				'hierarchical'        => false,
				'public'              => true,
				'show_ui'             => true,
				'show_in_menu'        => true,
				'show_in_nav_menus'   => true,
				'show_in_admin_bar'   => true,
				'menu_position'       => 5,
				'can_export'          => true,
				'has_archive'         => true,
				'exclude_from_search' => false,
				'publicly_queryable'  => true,
				'menu_icon' => 'dashicons-video-alt2',
				'capability_type'     => 'post',
			);

			register_post_type( $options['custom_post'], $args );
			flush_rewrite_rules();
		}


		function the_content($content)
		{

			$options = get_option('VWliveWebcamsOptions');

			if (!is_single()) return $content;
			$postID = get_the_ID() ;
			if (get_post_type( $postID ) != $options['custom_post']) return $content;

			$stream = sanitize_file_name(get_the_title($postID));

			$addCode = '[videowhisper_messenger]';

			//set thumb
			$dir = $options['uploadsPath']. "/_snapshots";
			$thumbFilename = "$dir/$stream.jpg";

			//only if file exists and missing post thumb
			if ( file_exists($thumbFilename) && !get_post_thumbnail_id( $postID ))
			{
				$wp_filetype = wp_check_filetype(basename($thumbFilename), null );

				$attachment = array(
					'guid' => $thumbFilename,
					'post_mime_type' => $wp_filetype['type'],
					'post_title' => preg_replace( '/\.[^.]+$/', '', basename( $thumbFilename, ".jpg" ) ),
					'post_content' => '',
					'post_status' => 'inherit'
				);

				$attach_id = wp_insert_attachment( $attachment, $thumbFilename, $postID );
				set_post_thumbnail($postID, $attach_id);

				require_once( ABSPATH . 'wp-admin/includes/image.php' );
				$attach_data = wp_generate_attachment_metadata( $attach_id, $thumbFilename );
				wp_update_attachment_metadata( $attach_id, $attach_data );
			}

			$maxViewers =  get_post_meta($postID, 'maxViewers', true);
			if (!is_array($maxViewers))
				if ($maxViewers>0)
				{
					$maxDate = (int) get_post_meta($postID, 'maxDate', true);
					$addCode .= __('Maximum viewers','livestreaming') . ': ' . $maxViewers;
					if ($maxDate) $addCode .= ' on ' . date("F j, Y, g:i a", $maxDate);
				}

			return $addCode . $content;
		}

		function pre_get_posts($query)
		{

			//add webcams to post listings
			if(is_category() || is_tag())
			{
				$query_type = get_query_var('post_type');

				$options = get_option('VWliveWebcamsOptions');


				if($query_type)
				{
					if (in_array('post',$query_type) && !in_array($options['custom_post'], $query_type))
						$query_type[] = $options['custom_post'];

				}
				else  //default
					{
					$query_type = array('post', $options['custom_post']);
				}

				$query->set('post_type', $query_type);
			}

			return $query;
		}

		function webcamPost()
		{
			$options = get_option('VWliveWebcamsOptions');

			$webcamName =  $options['webcamName'];
			if (!$webcamName) $webcamName='user_nicename';

			global $current_user, $wpdb;
			get_currentuserinfo();
			if ($current_user->$webcamName) $post_title = $current_user->$webcamName;
			if (!$post_title) $post_title = $current_user->user_login;

			$pid = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_title = '%s' AND post_type='" . $options['custom_post'] . "'", $post_title ));

			if (!$pid)
			{
				$post = array(
					'post_name'      => sanitize_title_with_dashes($post_title),
					'post_title'     => $post_title,
					'post_author'    => $current_user->ID,
					'post_type'      => $options['custom_post'],
					'post_status'    => 'publish',
				);

				$pid = wp_insert_post($post);
				//update_post_meta($pid, 'rdate', time());
				//update_post_meta($pid, 'viewers', 0);
			}

			return $pid;
		}


		function single_template($single_template)
		{

			if (!is_single())  return $single_template;

			$options = get_option('VWliveWebcamsOptions');

			$postID = get_the_ID();
			if (get_post_type( $postID ) != $options['custom_post']) return $single_template;

			$single_template = get_template_directory() . '/' . $options['postTemplate'];

			return $single_template;
		}


		//! Shortcode Implementation

		function videowhisper_webcams($atts)
		{
			$options = get_option('VWliveWebcamsOptions');

			//shortocode attributes
			$atts = shortcode_atts(
				array(
					'perPage'=>$options['perPage'],
					'ban' => '0',
					'perrow' => '',
					'order_by' => 'edate',
					'category_id' => '',
					'select_category' => '1',
					'select_order' => '1',
					'select_page' => '1',
					'include_css' => '1',
					'url_vars' => '1',
					'url_vars_fixed' => '1',
					'id' => ''
				), $atts, 'videowhisper_webcams');

			$id = $atts['id'];
			if (!$id) $id = uniqid();

			//get vars from url
			if ($atts['url_vars'])
			{
				$cid = (int) $_GET['cid'];
				if ($cid)
				{
					$atts['category_id'] = $cid;
					if ($atts['url_vars_fixed']) $atts['select_category'] = '0';
				}
			}

			//ajax url
			$ajaxurl = admin_url() . 'admin-ajax.php?action=vmls_cams&pp=' . $atts['perPage']. '&pr=' . $atts['perrow'] . '&ob=' . $atts['order_by'] . '&cat=' . $atts['category_id'] . '&sc=' . $atts['select_category'] . '&so=' . $atts['select_order'] . '&sp=' . $atts['select_page']. '&id=' .$id;

			if ($atts['ban']) $ajaxurl .= '&ban=' . $atts['ban'];


			$htmlCode = <<<HTMLCODE
<script>
var aurl$id = '$ajaxurl';
var \$j = jQuery.noConflict();

	function loadWebcams$id(message){

	if (message)
	if (message.length > 0)
	{
	  \$j("#videowhisperWebcams$id").html(message);
	}


		\$j.ajax({
			url: aurl$id,
			success: function(data) {
				\$j("#videowhisperWebcams$id").html(data);
			}
		});
	}

	\$j(function(){
		loadWebcams$id();
		setInterval("loadWebcams$id()", 10000);
	});

</script>

<div id="videowhisperWebcams$id">
    Loading Live Webcams...
</div>
HTMLCODE;

			if ($atts['include_css']) $htmlCode .= html_entity_decode(stripslashes($options['customCSS']));

			return $htmlCode;
		}


		function videowhisper_messenger($atts)
		{
			$stream = ''; //room name

			$options = get_option('VWliveWebcamsOptions');

			//username used with application
			$userName =  $options['userName'];
			if (!$userName) $userName='user_nicename';
			global $current_user;
			get_currentuserinfo();
			if ($current_user->$userName) $username=sanitize_file_name($current_user->$userName);

			$postID = 0;

			//1. webcam post
			$postID = get_the_ID();
			if (is_single())
				if (get_post_type( $postID ) == $options['custom_post']) $stream = get_the_title($postID);


				$atts = shortcode_atts(array('room' => $stream), $atts, 'videowhisper_messenger');

			//2. shortcode param
			if (!$stream) $stream = $atts['room'];


			$stream = sanitize_file_name($stream);

			if (!$stream) return "<div class='error'>Can't load application: Missing room name!</div>";

			$swfurl = plugin_dir_url(__FILE__) . "videowhisper/videomessenger.swf?room=" . urlencode($stream);
			$swfurl .= "&prefix=" . urlencode(admin_url() . 'admin-ajax.php?action=vmls&task=');
			$swfurl .= '&extension='.urlencode('_none_');
			$swfurl .= '&ws_res=' . urlencode( plugin_dir_url(__FILE__) . 'videowhisper/');

			$bgcolor="#333333";

			$htmlCode = <<<HTMLCODE
<div id="videowhisper_container">
<object width="100%" height="100%" type="application/x-shockwave-flash" data="$swfurl">
<param name="movie" value="$swfurl"></param><param bgcolor="$bgcolor"><param name="scale" value="noscale" /> </param><param name="salign" value="lt"></param><param name="allowFullScreen"
value="true"></param><param name="allowscriptaccess" value="always"></param>
</object>
</div>

<br style="clear:both" />

<style type="text/css">
<!--

#videowhisper_container
{
width: 100%;
height: 700px;
border: solid 3px #999;
}

-->
</style>

HTMLCODE;

			return $htmlCode;
		}

		function vmls_cams_callback()
		{
			//ajax called

			//cam meta:
			//edate s
			//viewers n
			//maxViewers n
			//maxDate s
			//hasSnapshot 1

			$options = get_option('VWliveWebcamsOptions');

			//widget id
			$id = sanitize_file_name($_GET['id']);

			//pagination
			$perPage = (int) $_GET['pp'];
			if (!$perPage) $perPage = $options['perPage'];

			$page = (int) $_GET['p'];
			$offset = $page * $perPage;

			$perRow = (int) $_GET['pr'];

			//admin side
			$ban = (int) $_GET['ban'];

			//
			$category = (int) $_GET['cat'];

			//order
			$order_by = sanitize_file_name($_GET['ob']);
			if (!$order_by) $order_by = 'edate';

			//options
			$selectCategory = (int) $_GET['sc'];
			$selectOrder = (int) $_GET['so'];
			$selectPage = (int) $_GET['sp'];

			//output clean
			ob_clean();

			//thumbs dir
			$dir = $options['uploadsPath']. "/_thumbs";

			$ajaxurl = admin_url() . 'admin-ajax.php?action=vmls_cams&pp=' . $perPage .  '&pr=' .$perRow. '&sc=' . $selectCategory . '&so=' . $selectOrder . '&sp=' . $selectPage .  '&id=' . $id;
			if ($ban) $ajaxurl .= '&ban=' . $ban; //admin side


			//show header option controls

			$ajaxurlP = $ajaxurl . '&p='.$page;
			$ajaxurlC = $ajaxurl . '&cat=' . $category ;
			$ajaxurlO = $ajaxurl . '&ob='. $order_by;
			$ajaxurlCO = $ajaxurl . '&cat=' . $category . '&ob='.$order_by ;

			echo '<div class="videowhisperListOptions">';

			if ($selectCategory)
			{
				echo '<div class="videowhisperDropdown">' . wp_dropdown_categories('echo=0&name=category' . $id . '&hide_empty=1&class=videowhisperSelect&show_option_all=' . __('All', 'livestreaming') . '&selected=' . $category).'</div>';
				echo '<script>var category' . $id . ' = document.getElementById("category' . $id . '"); 			category' . $id . '.onchange = function(){aurl' . $id . '=\'' . $ajaxurlO.'&cat=\'+ this.value; loadWebcams' . $id . '(\'Loading category...\')}
			</script>';
			}

			if ($selectOrder)
			{
				echo '<div class="videowhisperDropdown"><select class="videowhisperSelect" id="order_by' . $id . '" name="order_by' . $id . '" onchange="aurl' . $id . '=\'' . $ajaxurlC.'&ob=\'+ this.value; loadWebcams' . $id . '(\'Ordering webcams..\')">';
				echo '<option value="">' . __('Order By', 'livestreaming') . ':</option>';

				echo '<option value="post_date"' . ($order_by == 'post_date'?' selected':'') . '>' . __('Creation Date', 'livestreaming') . '</option>';

				echo '<option value="edate"' . ($order_by == 'edate'?' selected':'') . '>' . __('Broadcast Recently', 'livestreaming') . '</option>';

				echo '<option value="viewers"' . ($order_by == 'viewers'?' selected':'') . '>' . __('Current Viewers', 'livestreaming') . '</option>';

				echo '<option value="maxViewers"' . ($order_by == 'maxViewers'?' selected':'') . '>' . __('Maximum Viewers', 'livestreaming') . '</option>';

				echo '</select></div>';

			}
			echo '</div>';



			//query args
			$args=array(
				'post_type' => $options['custom_post'],
				'post_status' => 'publish',
				'posts_per_page' => $perPage,
				'offset'           => $offset,
				'order'            => 'DESC',
				'meta_query' => array(
					array( 'key' => 'hasSnapshot', 'value' => '1'),
				)
			);

			if ($order_by != 'post_date')
			{
				$args['orderby'] = 'meta_value_num';
				$args['meta_key'] = $order_by;
			}
			else
			{
				$args['orderby'] = 'post_date';
			}

			if ($category)  $args['category'] = $category;

			$postslist = get_posts( $args );


			//list cams
			if (count($postslist)>0)
			{
				$k = 0;
				foreach ( $postslist as $item )
				{
					if ($perRow) if ($k) if ($k % $perRow == 0) echo '<br>';

							$edate =  get_post_meta($item->ID, 'edate', true);
						$age = VWliveWebcams::format_age(time() -  $edate);
					$name = sanitize_file_name($item->post_title);

					if ($ban) $banLink = '<a class = "button" href="admin.php?page=live-webcams&ban=' . urlencode( $name ) . '">Ban This Webcam</a><br>';

					echo '<div class="videowhisperWebcam">';
					echo '<div class="videowhisperTitle">' . $name  . '</div>';
					echo '<div class="videowhisperTime">' . $banLink . $age . '</div>';

					$thumbFilename = "$dir/" . $name . ".jpg";
					$url = VWliveWebcams::roomURL($name);

					$noCache = '';
					if ($age=='LIVE') $noCache='?'.((time()/10)%100);

					if (file_exists($thumbFilename)) echo '<a href="' . $url . '"><IMG src="' . VWliveWebcams::path2url($thumbFilename) . $noCache .'" width="' . $options['thumbWidth'] . 'px" height="' . $options['thumbHeight'] . 'px"></a>';
					else echo '<a href="' . $url . '"><IMG SRC="' . plugin_dir_url(__FILE__). 'no-picture.jpg" width="' . $options['thumbWidth'] . 'px" height="' . $options['thumbHeight'] . 'px"></a>';
					echo "</div>";

				}
			}
			else echo "No webcams match current selection.";


			//pagination
			if ($selectPage)
			{
				echo "<BR>";
				if ($page>0) echo ' <a class="videowhisperButton g-btn type_secondary" href="JavaScript: void()" onclick="aurl' . $id . '=\'' . $ajaxurlCO.'&p='.($page-1). '\'; loadWebcams' . $id . '(\'Loading previous page...\');">' . __('Previous', 'videosharevod') . '</a> ';

				if (count($postslist) == $perPage) echo ' <a class="videowhisperButton g-btn type_secondary" href="JavaScript: void()" onclick="aurl' . $id . '=\'' . $ajaxurlCO.'&p='.($page+1). '\'; loadWebcams' . $id . '(\'Loading next page...\');">' . __('Next', 'videosharevod') . '</a> ';
			}

			die();
		}

		function any_in_array($array1, $array2)
		{
			foreach ($array1 as $value) if (in_array($value,$array2)) return true;
				return false;
		}


		function videowhisper_webcams_performer($atts)
		{
			if (!is_user_logged_in()) return __('Only registered users can broadcast webcams!','videosharevod');

			//shortocode attributes
			$atts = shortcode_atts(
				array(
					'include_css' => '1',
				), $atts, 'videowhisper_webcams');


			$options = get_option('VWliveWebcamsOptions');

			$uid = get_current_user_id();
			$user = get_userdata($uid);

			if ( ! VWliveWebcams::any_in_array( array( $options['rolePerformer'], 'administrator', 'super admin'), $user->roles))
				return __('User role does not allow publishing webcams!','videosharevod');

			//get or setup webcam post for this user
			$pid = VWliveWebcams::webcamPost();

			//process user's sessions to show updated balance
			VWliveWebcams::billSessions($uid);

			$htmlCode .=  'Your current balance: ' .  VWliveWebcams::balance($uid);

			$htmlCode .= '<br><a class="videowhisperButton" href="' . get_permalink($pid) . '">' . __('Go Live', 'videosharevod') . '</a>';

			if ($atts['include_css']) $htmlCode .= html_entity_decode(stripslashes($options['customCSS']));

			if (shortcode_exists('mycred_history')) $htmlCode .= '<br><br> <h5>Transactions</h5>[mycred_history user_id="current"]';

			return $htmlCode;
		}

		//! Performer Registration and Login

		function register_form()
		{

			$options = get_option('VWliveWebcamsOptions');

			if (!$options['registrationFormRole']) return;

			$roles = array($options['roleClient'], $options['rolePerformer']);

			echo '<label for="role"> ' . __('Role', 'videosharevod') . '<br><select id="role" name="role">';
			foreach ($roles as $role)
			{
				//create role if missing
				if (!$oRole = get_role($role))
				{
					add_role($role, ucwords($role), array('read' => true) );
					$oRole = get_role($role);
				}

				echo '<option value="' . $role . '">' . ucwords($oRole->name) . '</option>';

			}
			echo '</select></label>';
		}

		function user_register($user_id, $password="", $meta=array())
		{
			$options = get_option('VWliveWebcamsOptions');
			if (!$options['registrationFormRole']) return;

			$userdata = array();
			$userdata['ID'] = (int) $user_id;
			$userdata['role'] = sanitize_file_name($_POST['role']);

			//restrict registration roles
			$roles = array($options['roleClient'], $options['rolePerformer']);

			if (in_array( $userdata['role'], $roles ))
				wp_update_user($userdata);
		}

		function login_redirect( $redirect_to, $request, $user ) {

			//wp_users & wp_usermeta
			$user = get_userdata(get_current_user_id());

			if ( isset( $user->roles ) && is_array( $user->roles ) ) {
				//check for admins
				if ( in_array( 'administrator', $user->roles ) ) {
					// redirect them to the default place
					return $redirect_to;
				} else {

					$options = get_option('VWliveWebcamsOptions');

					//performer to dashboard
					if ( in_array(  $options['rolePerformer'], $user->roles ) )
					{
						$pid = $options['p_videowhisper_webcams_performer'];
						if ($pid) return get_permalink($pid);
						else return $redirect_to;
					}

					//client to webcams list
					if ( in_array(  $options['roleClient'], $user->roles ) )
					{
						$pid = $options['p_videowhisper_webcams'];
						if ($pid) return get_permalink($pid);
						else return $redirect_to;
					}


				}
			} else {
				return $redirect_to;
			}
		}


		function setupPages()
		{
			$options = get_option('VWliveWebcamsOptions');
			if ($options['disableSetupPages']) return;

			$pages = array(
				'videowhisper_webcams' => 'Webcams',
				'videowhisper_webcams_performer' => 'Performer Dashboard'
			);

			//create a menu and add pages
			$menu_name = 'VideoWhisper';
			$menu_exists = wp_get_nav_menu_object( $menu_name );
			if (!$menu_exists) $menu_id = wp_create_nav_menu($menu_name);

			//create pages if not created or existant
			foreach ($pages as $key => $value)
			{
				$pid = $options['p_'.$key];
				$page = get_post($pid);
				if (!$page) $pid = 0;

				if (!$pid)
				{
					global $user_ID;
					$page = array();
					$page['post_type']    = 'page';
					$page['post_content'] = '['.$key.']';
					$page['post_parent']  = 0;
					$page['post_author']  = $user_ID;
					$page['post_status']  = 'publish';
					$page['post_title']   = $value;
					$page['comment_status'] = 'closed';

					$pid = wp_insert_post ($page);
					$options['p_'.$key] = $pid;
					$link = get_permalink( $pid);

					if ($menu_id) wp_update_nav_menu_item($menu_id, 0, array(
								'menu-item-title' =>  $value,
								'menu-item-url' => $link,
								'menu-item-status' => 'publish'));

				}

			}

			update_option('VWliveWebcamsOptions', $options);
		}


		//! Billing Integration

		function balance($userID)
		{
			//get current user balance

			if (!$userID) return 0;

			if (function_exists( 'mycred_get_users_cred')) return mycred_get_users_cred($userID);

			return 0;
		}

		function transaction($ref = "ppv_live_webcams", $user_id = 1, $amount = 0, $entry = "PPV Live Webcams transaction.", $ref_id = null, $data = null)
		{
			//ref = explanation ex. ppv_client_payment
			//entry = explanation ex. PPV client payment in room.
			//utils: ref_id (int|string|array) , data (int|string|array|object)

			if ($amount == 0) return; //nothing

			if ($amount>0)
			{
				if (function_exists('mycred_add')) mycred_add($ref, $user_id, $amount, $entry, $ref_id, $data);
			}
			else
			{
				if (function_exists('mycred_subtract')) mycred_subtract( $ref, $user_id, $amount, $entry, $ref_id, $data );
			}
		}

		//! PPV Calculations
		function billSessions($uid=0)
		{
			$options = get_option('VWliveWebcamsOptions');

			if ($uid) $cnd = "AND (pid=$uid OR cid=$uid)";
			else $cnd = '';

			global $wpdb;
			$table_name4 = $wpdb->prefix . "vw_vmls_private";

			//force clean and close sessions terminated abruptly
			$closeTime = time() - $options['ppvCloseAfter'];

			//delete where only 1 entered (other could have accepted and quit)
			$sql="DELETE FROM `$table_name4` WHERE (status=0 OR status=1) AND ((cedate=0 AND pedate < $closeTime) OR (pedate=0 AND cedate < $closeTime))";
			$wpdb->query($sql);

			//update rest
			$sql="UPDATE `$table_name4` SET status='1' WHERE status='0' AND pedate < $closeTime AND cedate < $closeTime";
			$wpdb->query($sql);


			//bill sessions
			$billTime = time() - $options['ppvBillAfter'];
			$sql = "SELECT * FROM $table_name4 WHERE status='1' AND pedate < $billTime AND cedate < $billTime $cnd";

			$sessions = $wpdb->get_results($sql);
			if ($wpdb->num_rows>0) foreach ($sessions as $session) VWliveWebcams::billSession($session);
		}

		function billSession($session)
		{

			$options = get_option('VWliveWebcamsOptions');

			if (!$options['ppvPerformerPPM'] && !$options['ppvPPM']) return ;
			if (!$session) return ;
			if ($session->pedate == 0 || $session->cedate == 0 ) return 0; //did not enter both

			$end = min($session->pedate, $session->cedate);
			$start = max($session->psdate, $session->csdate);
			$totalDuration = $end - $start;
			$duration = $totalDuration - $options['ppvGraceTime'];
			if ($duration < 0) return ; //grace - nothing to bill

			$startDate = ' ' . date(DATE_RFC2822, $start);

			//client cost
			if ($options['ppvPPM']) VWliveWebcams::transaction('ppv_private', $session->cid, - number_format($duration * $options['ppvPPM'] / 60, 2, '.', ''), 'PPV private session with <a href="' . VWliveWebcams::roomURL($session->room) . '">' . $session->performer.'</a>' , $session->id);

			//performer earning
			if ($options['ppvPPM'] && $options['ppvRatio']) VWliveWebcams::transaction('ppv_private_earning', $session->pid, number_format($duration * $options['ppvPPM'] * $options['ppvRatio'] / 60, 2, '.', ''), 'Earning from PPV private session with ' . $session->client , $session->id);

			//performer cost
			if ($options['ppvPerformerPPM']) VWliveWebcams::transaction('ppv_private', $session->pid, - number_format($duration * $options['ppvPerformerPPM'] / 60, 2, '.', ''), 'Performer cost for PPV private session with ' . $session->performer , $session->id);


			//mark as billed
			global $wpdb;
			$table_name4 = $wpdb->prefix . "vw_vmls_private";

			$sql="UPDATE `$table_name4` set status='2' where id=" . $session->id;
			$wpdb->query($sql);
		}

		//calculate current cost (no processing)
		function clientCost($session)
		{
			$options = get_option('VWliveWebcamsOptions');

			if (!$options['ppvPPM']) return 0;
			if (!$session) return 0;
			if ($session->pedate == 0 || $session->cedate == 0 ) return 0; //did not enter both

			//duration when both online: max(psdate,csdate)->min(pedate,cedate)
			$duration = min($session->pedate, $session->cedate) - max($session->psdate, $session->csdate) - $options['ppvGraceTime'];
			if ($duration < 0) return 0; //grace

			return number_format($duration*$options['ppvPPM']/60, 2, '.', '');
		}

		function performerCost($session)
		{
			$options = get_option('VWliveWebcamsOptions');

			if (!$options['ppvPerformerPPM']) return 0;
			if (!$session) return 0;
			if ($session->pedate == 0 || $session->cedate == 0 ) return 0; //did not enter both

			//duration when both online: max(psdate,csdate)->min(pedate,cedate)
			$duration = min($session->pedate, $session->cedate) - max($session->psdate, $session->csdate) - $options['ppvGraceTime'];
			if ($duration < 0) return 0; //grace

			return number_format($duration*$options['ppvPerformerPPM']/60, 2, '.', '');
		}




		//! App Calls

		function rexit($output)
		{
			echo $output;
			exit;

		}

		function vmls_callback()
		{

			global $wpdb;
			$options = get_option('VWliveWebcamsOptions');

			ob_clean();

			switch ($_GET['task'])
			{
				//! login
			case 'm_login':

				$room_name = sanitize_file_name($_GET['room_name']);


				//does room exist?
				$postID = $wpdb->get_var( $sql = 'SELECT ID FROM ' . $wpdb->posts . ' WHERE post_name = \'' . $room_name . '\' and post_type=\''.$options['custom_post'] . '\' LIMIT 0,1' );
				if (!$postID)  VWliveWebcams::rexit('loggedin=0&msg=' . urlencode('Room does not exist: ' . $room_name)) ;

				$post = get_post( $postID );

				$rtmp_server = $options['rtmp_server'];
				$rtmp_amf = $options['rtmp_amf'];

				$tokenKey = $options['tokenKey'];
				$webKey = $options['webKey'];

				$serverRTMFP = $options['serverRTMFP'];
				$p2pGroup = $options['p2pGroup'];

				$supportRTMP = $options['supportRTMP'];
				$supportP2P = $options['supportP2P'];
				$alwaysRTMP = $options['alwaysRTMP'];
				$alwaysP2P = $options['alwaysP2P'];

				$disableBandwidthDetection = $options['disableBandwidthDetection'];

				$camRes = explode('x',$options['camResolution']);

				$canWatch = $options['canWatch'];
				$watchList = $options['watchList'];

				global $current_user;
				get_currentuserinfo();

				$loggedin=0;
				$msg="";
				$performer=0;
				$balance=0;
				$uid = 0;

				if (isset($current_user) )
				{
					//username
					$userName =  $options['userName']; if (!$userName) $userName='user_nicename';
					if ($current_user->$userName) $username = urlencode(sanitize_file_name($current_user->$userName));

					//owner (performer)
					if ($post->post_author == $current_user->ID) $performer = 1;

					$uid = $current_user->ID;

					if ($uid)
					{
						VWliveWebcams::billSessions($uid);
						$balance = VWliveWebcams::balance($uid);
					}

				}


				if (!$performer)
				{
					//client
					$parameters = $options['parametersClient'];
					$layoutCode = $options['layoutCodeClient'];
					$welcome = $options['welcomeClient'];

					$requestShow = 1;

					//access keys
					if ($current_user)
					{
						$userkeys = $current_user->roles;
						$userkeys[] = $current_user->user_login;
						$userkeys[] = $current_user->ID;
						$userkeys[] = $current_user->user_email;
						$userkeys[] = $current_user->display_name;
					}

					switch ($canWatch)
					{
					case "all":
						$loggedin=1;
						if (!$username)
						{
							$username="G_".base_convert((time()-1224350000).rand(0,10),10,36);
							$visitor=1; //ask for username

							$requestShow = 0;
							$welcome .= '<BR>' . 'You are NOT logged in. Only registered users can request private show!';

						}
						break;

					case "members":
						if ($username) $loggedin=1;
						else $msg=urlencode("<a href=\"/\">Please login first or register an account if you don't have one! Click here to return to website.</a>") . $msgp;
						break;

					case "list";
						if ($username)
							if (inList($userkeys, $watchList)) $loggedin=1;
							else $msg=urlencode("<a href=\"/\">$username, you are not in the allowed watchers list.</a>") . $msgp;
							else $msg=urlencode("<a href=\"/\">Please login first or register an account if you don't have one! Click here to return to website.</a>") . $msgp;
							break;

					}

					$videoDefault = get_post_meta($postID, 'performer', true);

					if ($uid)
					{
						$welcome .= '<BR>' . 'Your current balance:' . ' ' . $balance;
						if ($options['ppvPPM']) $welcome .= '<BR>' . 'Private show cost per minute is:' . ' ' . $options['ppvPPM'];
					}

					if ($uid) if ($options['ppvMinimum'] && $options['ppvPPM'])
							if ( $balance < $options['ppvMinimum'])
							{
								$requestShow = 0;
								$welcome .= '<BR>You do not have enough credits to request a private show.' . " ($options[ppvMinimum])";
							}
						else
						{
							if ($options['ppvGraceTime']) $welcome .= '<BR>' . 'Charging starts after a grace time:' . ' ' . $options['ppvGraceTime'] . 's';
						}

					$snap='client.png';

				}
				else
				{
					//performer
					$parameters = $options['parametersPerformer'];
					$layoutCode = $options['layoutCodePerformer'];
					$welcome = $options['welcomePerformer'];

					$loggedin=1;

					//performer is current user
					$videoDefault = $username;
					update_post_meta($postID, 'performer', $username);

					$requestShow = 0;

					$welcome .= '<BR>' . 'Your current balance:' . ' ' . $balance;


					if ($options['ppvPerformerPPM']>0) $welcome .= '<BR>' . 'Private show cost per minute for performer is:' . ' ' . $options['ppvPerformerPPM'];
					if ($options['ppvPPM']>0) $welcome .= '<BR>' . 'Private show cost per minute for client is:' . ' ' . $options['ppvPPM'];

					if ($options['ppvGraceTime']) $welcome .= '<BR>' . 'Charging starts after a grace time:' . ' ' . $options['ppvGraceTime'] . 's';

					if (($options['ppvPPM']>0) && ($options['ppvRatio']>0)) $welcome .= '<BR>' . 'Private show earning per minute for performer is:' . ' ' . number_format($options['ppvPPM']*$options['ppvRatio'], 2, '.','');


					$snap='performer.png';

				}

				$videoOffline = urlencode(plugins_url('videowhisper/offline.jpg', __FILE__ )); //when goes offline
				$videoBusy = urlencode(plugins_url('videowhisper/busy.jpeg', __FILE__ )); //when hides or until access

				?>firstParameter=fix&server=<?php echo $rtmp_server?>&serverAMF=<?php echo $rtmp_amf?>&tokenKey=<?php echo $tokenKey?>&serverRTMFP=<?php echo urlencode($serverRTMFP)?>&p2pGroup=<?php echo
				$p2pGroup?>&supportRTMP=<?php echo $supportRTMP?>&supportP2P=<?php echo $supportP2P?>&alwaysRTMP=<?php echo $alwaysRTMP?>&alwaysP2P=<?php echo $alwaysP2P?>&disableBandwidthDetection=<?php echo
				$disableBandwidthDetection?>&room=<?php echo $room_name?>&username=<?php echo $username?>&psnap=<?php echo urlencode($snap)?>&loggedin=<?php echo $loggedin?>&welcome=<?php echo $welcome?>&layoutCode=<?php echo urlencode($layoutCode)?>&filterRegex=<?php echo $filterRegex?>&filterReplace=<?php echo $filterReplace?>&friendsList=<?php echo $friendsList?>&requestShow=<?php echo $requestShow?>&videoDefault=<?php echo $videoDefault?>&videoOffline=<?php echo $videoOffline ?>&videoBusy=<?php echo $videoBusy ?>&layoutCode=<?php echo urlencode($layoutCode)?>&loadstatus=1<?php echo $parameters;
				break;

				//! status
			case 'm_status':
				$cam = (int) $_POST['cam'];
				$mic = (int) $_POST['mic'];

				$timeUsed = $currentTime = (int) $_POST['ct'];
				$lastTime = (int) $_POST['lt'];

				$s = sanitize_file_name($_POST['s']);
				$u = sanitize_file_name($_POST['u']);
				$room_name = $r = sanitize_file_name($_POST['r']);
				//$m=$_POST['m'];

				$ztime=time();


				//exit if no valid session name or room name
				if (!$s) VWliveWebcams::rexit('noSession=1');
				if (!$r) VWliveWebcams::rexit('noRoom=1');

				//does room exist?
				$postID = $wpdb->get_var( $sql = 'SELECT ID FROM ' . $wpdb->posts . ' WHERE post_name = \'' . $room_name . '\' and post_type=\''.$options['custom_post'] . '\' LIMIT 0,1' );
				if (!$postID)  VWliveWebcams::rexit('disconnect=' . urlencode('Room does not exist: ' . $room_name)) ;

				$post = get_post( $postID );


				$table_name = $wpdb->prefix . "vw_vmls_sessions";


				//performer ?
				global $current_user;
				get_currentuserinfo();
				$performer=0;
				if (isset($current_user) )
				{
					//owner (performer)
					if ($post->post_author == $current_user->ID) $performer = 1;
				}


				if ($performer)
				{
					//update cam stats
					update_post_meta($postID, 'edate', $ztime);

					$onlineTime = get_post_meta($postID, 'onlineTime', true);
					$dS = floor(($currentTime-$lastTime)/1000);
					update_post_meta($postID, 'onlineTime', $dS + $onlineTime);

					//update viewers
					$viewers =  $wpdb->get_results("SELECT count(id) as no FROM `$table_name` where status='1' and type='1' and room='" . $r . "'");
					update_post_meta($pid, 'viewers', $viewers);

				}
				else
				{

					//update viewers online
					$sql = "SELECT * FROM $table_name where session='$s' and status='1'";
					$session = $wpdb->get_row($sql);
					if (!$session)
					{
						$sql="INSERT INTO `$table_name` ( `session`, `username`, `room`, `message`, `sdate`, `edate`, `status`, `type`) VALUES ('$s', '$u', '$r', '$m', $ztime, $ztime, 1, 1)";
						$wpdb->query($sql);
						$session = $wpdb->get_row($sql);
					}
					else
					{
						$sql="UPDATE `$table_name` set edate=$ztime, room='$r', username='$u', message='$m' where session='$s' and status='1' and `type`='1'";
						$wpdb->query($sql);
					}

					$viewers =  $wpdb->get_results("SELECT count(id) as no FROM `$table_name` where status='1' and type='1' and room='" . $r . "'");

					update_post_meta($postID, 'viewers', $viewers);
					$maxViewers = get_post_meta($postID, 'maxViewers', true);
					if ($viewers >= $maxViewers)
					{
						update_post_meta($postID, 'maxViewers', $viewers);
						update_post_meta($postID, 'maxDate', $ztime);
					}

				}

				$maximumSessionTime = 0;


				?>timeTotal=<?php echo $maximumSessionTime?>&timeUsed=<?php echo $currentTime?>&lastTime=<?php echo $currentTime?>&disconnect=<?php echo $disconnect?>&loadstatus=1<?php

				break;

				//! private status
			case 'm_pend';
			case 'm_pstatus';

				$room_name = sanitize_file_name($_POST['r']);
				$caller = sanitize_file_name($_POST['s']);
				$username = sanitize_file_name($_POST['u']);
				$private = sanitize_file_name($_POST['p']);

				$currentTime = (int) $_POST['ct'];
				$lastTime = (int) $_POST['lt'];

				$end = (int) $_POST['e'];

				$sqlEnd = ''; $sqlEndC = '';

				if (!$end) $end = 0;
				else
				{
					$sqlEnd = ', status=1';
					$sqlEndC = "OR status='1'";
				}

				$ztime = time();
				$maximumSessionTime = 0;
				$disconnect = "";


				$postID = $wpdb->get_var( $sql = 'SELECT ID FROM ' . $wpdb->posts . ' WHERE post_name = \'' . $room_name . '\' and post_type=\''.$options['custom_post'] . '\' LIMIT 0,1' );

				if (!$postID) VWliveWebcams::rexit('disconnect=NoRoom' . urlencode($room_name));
				$post = get_post( $postID );

				global $current_user;
				get_currentuserinfo();
				$performer=0;

				if (isset($current_user))
					if ($post->post_author == $current_user->ID) $performer = 1;

					$table_name4 = $wpdb->prefix . "vw_vmls_private";
				//status: 0 = current, 1 = ended,  2 = charged

				//discard expired sessions:
				//$closeTime = time() - $options['ppvCloseAfter'];
				//AND (pedate > $closeTime OR cedate > $closeTime)

				if ($performer)
					{ //performer

					//retrieve or create session
					$sqlS = "SELECT * FROM $table_name4 where room='$room_name' AND performer='$caller' AND client='$private' AND (status='0' $sqlEndC) ORDER BY status ASC, id DESC";
					$session = $wpdb->get_row($sqlS);

					if (!$session)
					{
						if ($end) VWliveWebcams::rexit('disconnect=NoSessionToEnd');

						$dt = min($currentTime/1000, $options['ppvCloseAfter']);

						$sql="INSERT INTO `$table_name4` ( `performer`, `pid`, `client`, `room`, `psdate`, `pedate`, `status` ) VALUES ( '$caller', $current_user->ID, '$private', '$room_name', " . ($ztime - $dt) . ", $ztime, 0 )";
						$wpdb->query($sql);
						$wpdb->flush();
						$session = $wpdb->get_row($sqlS);
					}

					//update id and time
					$sdate = $session->psdate;
					if (!$sdate) $sdate = $ztime; //first time = start time

					//$debug = $sql;
					//$debug = '--' . $session->psdate . '--' . $session->id . '--' . $ztime . '--' . $sdate;

					$sql="UPDATE `$table_name4` SET pid = " . $current_user->ID . ", psdate=$sdate, pedate = $ztime $sqlEnd WHERE id = " . $session->id;
					$wpdb->query($sql);

					//info
					$timeUsed = $ztime - $sdate;

					$cost = VWliveWebcams::performerCost($session);
					$balance = VWliveWebcams::balance($current_user->ID);

					if ( $cost > $balance) $disconnect = "Not enough funds for performer to continue session.";
				}
				else
					{ //client

					//retrieve or create session
					$sqlS = "SELECT * FROM $table_name4 where room='$room_name' AND client='$caller' AND performer='$private' AND (status='0' $sqlEndC) ORDER BY status ASC, id DESC";
					$session = $wpdb->get_row($sqlS);

					if (!$session)
					{
						if ($end) VWliveWebcams::rexit('disconnect=NoSessionToEnd');

						$dt = min($currentTime/1000, $options['ppvCloseAfter']);

						$sql="INSERT INTO `$table_name4` ( `client`, `cid`, `performer`, `room`, `csdate`, `cedate`, `status` ) VALUES ( '$caller', $current_user->ID, $private, '$room_name', " . ($ztime - $dt) . ", $ztime, 0 )";
						$wpdb->query($sql);
						$session = $wpdb->get_row($sqlS);
					}

					//update id and time

					$sdate = $session->csdate;
					if (!$sdate) $sdate = $ztime; //first time = start time

					$sql="UPDATE `$table_name4` SET cid = " . $current_user->ID . ", csdate=$sdate, cedate = $ztime $sqlEnd WHERE id = " . $session->id;
					$wpdb->query($sql);

					//info
					$timeUsed = $ztime - $sdate;

					$cost = VWliveWebcams::clientCost($session);
					$balance = VWliveWebcams::balance($current_user->ID);

					if ( $cost > $balance) $disconnect = "Not enough funds for client to continue session.";

				}

				if ($session->status>0) $disconnect = "Session was already ended.";

				if ($cost) $credits_info .= '$' . $cost . '/';
				$credits_info .= '$' . $balance;

				//server session time to app ms
				$timeUsed=$timeUsed * 1000;


				?>timeTotal=<?php echo $maximumSessionTime?>&timeUsed=<?php echo $timeUsed?>&lastTime=<?php echo $currentTime?>&disconnect=<?php echo urlencode($disconnect)?>&statusInfo=<?php echo $credits_info?>&loadstatus=1&debug=<?php echo $debug?><?php
				break;


				//! snapshots
			case 'vw_snapshots':


				if (!isset($GLOBALS["HTTP_RAW_POST_DATA"])) VWliveWebcams::rexit('missingArguments=1');

				$stream = sanitize_file_name($_GET['name']);
				$room_name = sanitize_file_name($_GET['room']);

				if (strstr($stream,'.php')) VWliveWebcams::rexit('missingArguments=1');
				if (!$stream) VWliveWebcams::rexit('missingArguments=1');
				if (!$room_name) VWliveWebcams::rexit('missingArguments=1');

				$postID = $wpdb->get_var( $sql = 'SELECT ID FROM ' . $wpdb->posts . ' WHERE post_name = \'' . $room_name . '\' and post_type=\''.$options['custom_post'] . '\' LIMIT 0,1' );
				$post = get_post( $postID );

				global $current_user;
				get_currentuserinfo();
				$performer=0;
				if (isset($current_user) )
				{
					//owner (performer)
					if ($post->post_author == $current_user->ID) $performer = 1;
				}

				if (!$performer) VWliveWebcams::rexit('noPerformer=1'); //only performer updates snapshot

				//
				$dir=$options['uploadsPath'];
				if (!file_exists($dir)) mkdir($dir);
				$dir .= "/_snapshots";
				if (!file_exists($dir)) mkdir($dir);

				//get bytearray
				$jpg = $GLOBALS["HTTP_RAW_POST_DATA"];

				//save file
				$filename = "$dir/$room_name.jpg";
				$fp=fopen($filename ,"w");
				if ($fp)
				{
					fwrite($fp,$jpg);
					fclose($fp);
				}

				//generate thumb
				$thumbWidth = $options['thumbWidth'];
				$thumbHeight = $options['thumbHeight'];

				$src = imagecreatefromjpeg($filename);
				list($width, $height) = getimagesize($filename);
				$tmp = imagecreatetruecolor($thumbWidth, $thumbHeight);

				$dir = $options['uploadsPath']. "/_thumbs";
				if (!file_exists($dir)) mkdir($dir);

				$thumbFilename = "$dir/$room_name.jpg";
				imagecopyresampled($tmp, $src, 0, 0, 0, 0, $thumbWidth, $thumbHeight, $width, $height);
				imagejpeg($tmp, $thumbFilename, 95);

				//detect tiny images without info
				if (filesize($thumbFilename)>5000) $picType = 1;
				else $picType = 2;

				//update post meta
				if ($postID) update_post_meta($postID, 'hasSnapshot', $picType);

				//$debug = urlencode($thumbWidth.'x'.$thumbHeight.'--'.$filename .'--'. $thumbFilename);
				echo 'loadstatus=1&debug=' . $debug;
				break;


			case 'm_logout':
				wp_redirect( home_url());
				break;


			case 'translation':
				?><translations>
			   <?php
				$options = get_option('VWliveWebcamsOptions');
				echo html_entity_decode(stripslashes($options['translationCode']));
?>
				</translations><?php
				break;


			default:
				echo 'task=' . $_GET['task'];
			}

			die();
		}




		//! Utility Functions

		function roomURL($room)
		{

			$options = get_option('VWliveWebcamsOptions');

			global $wpdb;

			$postID = $wpdb->get_var( "SELECT ID FROM $wpdb->posts WHERE post_name = '" . sanitize_file_name($room) . "' and post_type='" . $options['custom_post'] . "' LIMIT 0,1" );

			if ($postID) return get_post_permalink($postID);
		}

		function path2url($file, $Protocol='http://')
		{
			return $Protocol.$_SERVER['HTTP_HOST'].str_replace($_SERVER['DOCUMENT_ROOT'], '', $file);
		}


		function format_time($t,$f=':') // t = seconds, f = separator
			{
			return sprintf("%02d%s%02d%s%02d", floor($t/3600), $f, ($t/60)%60, $f, $t%60);
		}

		function format_age($t)
		{
			if ($t<30) return "LIVE";
			return sprintf("%d%s%d%s%d%s", floor($t/86400), 'd ', ($t/3600)%24,'h ', ($t/60)%60,'m');
		}

		//! Admin Side

		function adminMenu() {

			add_menu_page('Live Webcams', 'Live Webcams', 'manage_options', 'live-webcams', array('VWliveWebcams', 'adminOptions'), 'dashicons-video-alt2',83);

			add_submenu_page("live-webcams", "Live Webcams", "Settings", 'manage_options', "live-webcams", array('VWliveWebcams', 'adminOptions'));
			add_submenu_page("live-webcams", "Live Webcams", "Documentation", 'manage_options', "live-webcams-doc", array('VWliveWebcams', 'adminDocs'));

		}

		function settings_link($links) {
			$settings_link = '<a href="admin.php?page=live-webcams">'.__("Settings").'</a>';
			array_unshift($links, $settings_link);
			return $links;
		}

		function adminDocs()
		{
?>
<div class="wrap">
<div id="icon-options-general" class="icon32"><br></div>
<h2>VideoWhisper PPV Live Webcams</h2>
</div>

<h3>Quick Setup Tutorial</h3>
<ol>
<li>Install and activate the PPV Live Webcams plugin by VideoWhisper</li>
<li>From <a href="admin.php?page=live-webcams">Live Webcams > Settings</a> in WP backend and configure settings (it's compulsory to fill a valid RTMP hosting address)</li>
<li>From <a href="nav-menus.php">Appearance > Menus</a> add Webcams and optionally the Performer Dashboard pages to main site menu</li>
<li>From <a href="options-permalink.php">Settings > Permalinks</a> enable a SEO friendly structure (ex. Post name)</li>
<li>Install and enable a <a href="admin.php?page=live-webcams&tab=billing">billing plugin</a></li>
</ol>

<h3>Shortcodes</h3>

<h4>[videowhisper_webcams perPage="6" perrow="0" order_by= "edate" category_id=" select_category="1" select_order="1" select_page="1" include_css="1" url_vars="1" url_vars_fixed="1"]</h4>
Lists and updates webcams using AJAX. Allows filtering and toggling filter controls.

<h4>[videowhisper_messenger room="Room Name"]</h4>
Shows videochat application. Automatically detects room if shown on webcam post.

<h4>[videowhisper_webcams_performer include_css="1"]</h4>
Shows performer dashboard with balance, link to access own webcam post (creates it if missing) and transaction stats.

<?php
		}

		//! Options

		function getAdminOptions() {

			$upload_dir = wp_upload_dir();
			$root_url = plugins_url();
			$root_ajax = admin_url( 'admin-ajax.php?action=vmls&task=');

			$adminOptions = array(
				'custom_post' => 'webcam',
				'postTemplate' => 'page.php',

				'disableSetupPages' => '0',
				'registrationFormRole' => '1',

				'roleClient' => 'client',
				'rolePerformer' => 'performer',

				'userName' => 'display_name',
				'webcamName' => 'user_login',

				'thumbWidth' => '240',
				'thumbHeight' => '180',
				'perPage' =>'6',

				'ppvGraceTime' => '30',
				'ppvPPM' => '0.50',
				'ppvRatio' => '0.80',
				'ppvPerformerPPM' => '0.00',
				'ppvMinimum' => '1.50',

				'ppvCloseAfter' => '120',
				'ppvBillAfter' => '10',

				'rtmp_server' => 'rtmp://your-server/videowhisper',
				'rtmp_amf' => 'AMF3',

				'canWatch' => 'all',
				'watchList' => 'Super Admin, Administrator, Editor, Author, Contributor, Subscriber, Client, Student, Member',

				'camBandwidth' => '40960',
				'camMaxBandwidth' => '81920',

				'videoCodec'=>'H264',
				'codecProfile' => 'baseline',
				'codecLevel' => '3.1',

				'soundCodec'=> 'Speex',
				'soundQuality' => '9',
				'micRate' => '22',


				'overLogo' => $root_url .'PPV-Live-Webcams/videowhisper/logo.png',
				'overLink' => 'http://www.videowhisper.com',

				'tokenKey' => 'VideoWhisper',
				'webKey' => 'VideoWhisper',

				'serverRTMFP' => 'rtmfp://stratus.adobe.com/f1533cc06e4de4b56399b10d-1a624022ff71/',
				'p2pGroup' => 'VideoWhisper',
				'supportRTMP' => '1',
				'supportP2P' => '0',
				'alwaysRTMP' => '0',
				'alwaysP2P' => '0',

				'disableBandwidthDetection' => '1',

				'videowhisper' => 0,

				'uploadsPath' => $upload_dir['basedir'] . '/vw-webcams',
				'adServer' => $root_ajax .'m_ads',

				'welcomePerformer' => 'Welcome to your performer room!',
				'welcomeClient' => 'Welcome!',

				'parametersPerformer' => '&usersEnabled=1&chatEnabled=1&videoEnabled=1&webcamSupported=1&webcamEnabled=1&toolbarEnabled=1&removeChatOnPrivate=0&requestPrivate=0&assignedPrivate=0&directPrivate=0&canHide=1&canDenyAll=1&hideOnPrivate=1&soundNotifications=1&camWidth=480&camHeight=360&camFPS=30&micRate=22&camBandwidth=40000&bufferLive=0.1&bufferFull=0.1&bufferLivePlayback=0.1&bufferFullPlayback=0.1&showCamSettings=1&advancedCamSettings=1&camMaxBandwidth=81920&disableBandwidthDetection=0&disableUploadDetection=0&limitByBandwidth=1&configureSource=0&fillWindow=0&disableVideo=0&disableSound=0&floodProtection=2&writeText=1&statusInterval=30000&statusPrivateInterval=10000&externalInterval=0',

				'parametersClient' => '&usersEnabled=1&chatEnabled=1&videoEnabled=1&webcamSupported=1&webcamEnabled=0&toolbarEnabled=1&removeChatOnPrivate=1&removeVideoOnPrivate=1&removeUsersOnPrivate=1&maximizePrivate=1&assignedPrivate=1&requestPrivate=0&directPrivate=0&canHide=0&canDenyAll=0&hideOnPrivate=0&soundNotifications=0&camWidth=320&camHeight=240&camFPS=15&micRate=11&camBandwidth=32768&bufferLive=0.1&bufferFull=0.1&bufferLivePlayback=0.1&bufferFullPlayback=0.1&showCamSettings=1&advancedCamSettings=1&camMaxBandwidth=81920&disableBandwidthDetection=0&disableUploadDetection=0&limitByBandwidth=1&configureSource=0&fillWindow=0&disableVideo=0&disableSound=0&floodProtection=3&writeText=1&statusInterval=60000&statusPrivateInterval=10000&externalInterval=0',

				'layoutCodePerformer' => 'id=0&label=Users&x=747&y=2&width=205&height=218&resize=true&move=true; id=1&label=Chat&x=747&y=225&width=451&height=432&resize=true&move=true; id=2&label=Video&x=2&y=2&width=741&height=457&resize=true&move=true; id=3&label=Webcam&x=956&y=2&width=240&height=219&resize=true&move=true',

				'layoutCodeClient' => 'id=0&label=Users&x=820&y=5&width=375&height=200&resize=true&move=true; id=1&label=Chat&x=820&y=210&width=375&height=440&resize=true&move=true; id=2&label=Video&x=5&y=5&width=810&height=646&resize=true&move=true; id=3&label=Webcam&x=840&y=20&width=240&height=219&resize=true&move=true',

				'translationCode' => '',

				'customCSS' => <<<HTMLCODE
<style type="text/css">

.videowhisperWebcam
{
position: relative;
display:inline-block;

	border:1px solid #aaa;
	background-color:#777;
	padding: 0px;
	margin: 2px;

	width: 240px;
    height: 180px;
}

.videowhisperWebcam:hover {
	border:1px solid #fff;
}

.videowhisperWebcam IMG
{
padding: 0px;
margin: 0px;
border: 0px;
}

.videowhisperTitle
{
position: absolute;
top:5px;
left:5px;
font-size: 20px;
color: #FFF;
text-shadow:1px 1px 1px #333;
}

.videowhisperTime
{
position: absolute;
bottom:8px;
left:5px;
font-size: 15px;
color: #FFF;
text-shadow:1px 1px 1px #333;
}


.videowhisperButton {
	-moz-box-shadow:inset 0px 1px 0px 0px #ffffff;
	-webkit-box-shadow:inset 0px 1px 0px 0px #ffffff;
	box-shadow:inset 0px 1px 0px 0px #ffffff;
	-webkit-border-top-left-radius:6px;
	-moz-border-radius-topleft:6px;
	border-top-left-radius:6px;
	-webkit-border-top-right-radius:6px;
	-moz-border-radius-topright:6px;
	border-top-right-radius:6px;
	-webkit-border-bottom-right-radius:6px;
	-moz-border-radius-bottomright:6px;
	border-bottom-right-radius:6px;
	-webkit-border-bottom-left-radius:6px;
	-moz-border-radius-bottomleft:6px;
	border-bottom-left-radius:6px;
	text-indent:0;
	border:1px solid #dcdcdc;
	display:inline-block;
	color:#666666;
	font-family:Verdana;
	font-size:15px;
	font-weight:bold;
	font-style:normal;
	height:50px;
	line-height:50px;
	width:200px;
	text-decoration:none;
	text-align:center;
	text-shadow:1px 1px 0px #ffffff;
	background-color:#e9e9e9;

}

.videowhisperButton:hover {
	background-color:#f9f9f9;
}

.videowhisperButton:active {
	position:relative;
	top:1px;
}

td {
    padding: 4px;
}

table, .videowhisperTable {
    border-spacing: 4px;
    border-collapse: separate;
}

.videowhisperDropdown {
    display:inline-block;
    border: 1px solid #111;
    overflow: hidden;
    border-radius:3px;
    color: #eee;
    background: #556570;
    width: 240px;
}

.videowhisperSelect {
    width: 100%;
    border: none;
    box-shadow: none;
    background: transparent;
    background-image: none;
    -webkit-appearance: none;
}

.videowhisperSelect:focus {
    outline: none;
}

</style>

HTMLCODE

			);

			$options = get_option('VWliveWebcamsOptions');
			if (!empty($options)) {
				foreach ($options as $key => $option)
					$adminOptions[$key] = $option;
			}
			update_option('VWliveWebcamsOptions', $adminOptions);
			return $adminOptions;
		}



		function adminOptions()
		{
			$options = VWliveWebcams::getAdminOptions();

			if (isset($_POST))
			{
				foreach ($options as $key => $value)
					if (isset($_POST[$key])) $options[$key] = $_POST[$key];
					update_option('VWliveWebcamsOptions', $options);
			}

			VWliveWebcams::setupPages();

?>
<div class="wrap">
<div id="icon-options-general" class="icon32"><br></div>
<h2>VideoWhisper PPV Live Webcams</h2>
</div>

<h2 class="nav-tab-wrapper">
	<a href="admin.php?page=live-webcams&tab=server" class="nav-tab <?php echo $active_tab=='server'?'nav-tab-active':'';?>">Server</a>
	<a href="admin.php?page=live-webcams&tab=integration" class="nav-tab <?php echo $active_tab=='integration'?'nav-tab-active':'';?>">Integration</a>
	<a href="admin.php?page=live-webcams&tab=performer" class="nav-tab <?php echo $active_tab=='performer'?'nav-tab-active':'';?>">Performer</a>
	<a href="admin.php?page=live-webcams&tab=client" class="nav-tab <?php echo $active_tab=='client'?'nav-tab-active':'';?>">Client</a>
	<a href="admin.php?page=live-webcams&tab=ppv" class="nav-tab <?php echo $active_tab=='ppv'?'nav-tab-active':'';?>">PPV</a>
	<a href="admin.php?page=live-webcams&tab=billing" class="nav-tab <?php echo $active_tab=='billing'?'nav-tab-active':'';?>">Billing</a>
</h2>

<form method="post" action="<?php echo $_SERVER["REQUEST_URI"]; ?>">
<?php
			$active_tab = isset( $_GET[ 'tab' ] ) ? $_GET[ 'tab' ] : 'server';

			switch ($active_tab)
			{
			case 'server':
?>
<h3>Server Settings</h3>
Configure hosting options.
<h4>RTMP Address</h4>
<p>To run this, make sure your hosting environment meets all <a href="http://www.videowhisper.com/?p=Requirements" target="_blank">requirements</a>.<BR>If you don't have a videowhisper rtmp address
yet (from a managed rtmp host), go to <a href="http://www.videowhisper.com/?p=RTMP+Applications" target="_blank">RTMP Application   Setup</a> for  installation details.</p>
<input name="rtmp_server" type="text" id="rtmp_server" size="100" maxlength="256" value="<?php echo $options['rtmp_server']?>"/>*
<BR> A public accessible rtmp hosting server is required with custom videowhisper rtmp side. Ex: rtmp://your-server/videowhisper

<h4>Disable Bandwidth Detection</h4>
<p>Required on some rtmp servers that don't support bandwidth detection and return a Connection.Call.Fail error.</p>
<select name="disableBandwidthDetection" id="disableBandwidthDetection">
  <option value="0" <?php echo $options['disableBandwidthDetection']?"":"selected"?>>No</option>
  <option value="1" <?php echo $options['disableBandwidthDetection']?"selected":""?>>Yes</option>
</select>

<h4>Token Key</h4>
<input name="tokenKey" type="text" id="tokenKey" size="32" maxlength="64" value="<?php echo $options['tokenKey']?>"/>
<BR>A <a href="http://www.videowhisper.com/?p=RTMP+Applications#settings">secure token</a> can be used with Wowza Media Server.

<h4>Web Key</h4>
<input name="webKey" type="text" id="webKey" size="32" maxlength="64" value="<?php echo $options['webKey']?>"/>
<BR>A web key can be used for <a href="http://www.videochat-scripts.com/videowhisper-rtmp-web-authetication-check/">VideoWhisper RTMP Web Session Check</a>.

<h4>RTMFP Address</h4>
<p> Get your own independent RTMFP address by registering for a free <a href="https://www.adobe.com/cfusion/entitlement/index.cfm?e=cirrus" target="_blank">Adobe Cirrus developer key</a>. This is
required for P2P support.</p>
<input name="serverRTMFP" type="text" id="serverRTMFP" size="80" maxlength="256" value="<?php echo $options['serverRTMFP']?>"/>
<h4>P2P Group</h4>
<input name="p2pGroup" type="text" id="p2pGroup" size="32" maxlength="64" value="<?php echo $options['p2pGroup']?>"/>
<h4>Support RTMP Streaming</h4>
<select name="supportRTMP" id="supportRTMP">
  <option value="0" <?php echo $options['supportRTMP']?"":"selected"?>>No</option>
  <option value="1" <?php echo $options['supportRTMP']?"selected":""?>>Yes</option>
</select>
<h4>Always do RTMP Streaming</h4>
<p>Enable this if you want all streams to be published to server, no matter if there are registered subscribers or not (in example if you're using server side video archiving and need all streams published for recording).</p>
<select name="alwaysRTMP" id="alwaysRTMP">
  <option value="0" <?php echo $options['alwaysRTMP']?"":"selected"?>>No</option>
  <option value="1" <?php echo $options['alwaysRTMP']?"selected":""?>>Yes</option>
</select>
<br>Recommended.

<h4>Support P2P Streaming</h4>
<select name="supportP2P" id="supportP2P">
  <option value="0" <?php echo $options['supportP2P']?"":"selected"?>>No</option>
  <option value="1" <?php echo $options['supportP2P']?"selected":""?>>Yes</option>
</select>
<br>Not recommended as P2P is highly dependant on client network and ISP restrictions. Often results in video streaming failure or huge latency.

<h4>Always do P2P Streaming</h4>
<select name="alwaysP2P" id="alwaysP2P">
  <option value="0" <?php echo $options['alwaysP2P']?"":"selected"?>>No</option>
  <option value="1" <?php echo $options['alwaysP2P']?"selected":""?>>Yes</option>
</select>

<h4>Uploads Path</h4>
<p>Path where logs and snapshots will be uploaded. Make sure you use a location outside plugin folder to avoid losing logs on updates and plugin uninstallation.</p>
<input name="uploadsPath" type="text" id="uploadsPath" size="80" maxlength="256" value="<?php echo $options['uploadsPath']?>"/>

			<?php
				break;


			case 'integration':

				//preview
				global $current_user;
				get_currentuserinfo();
?>

<h3>General Integration Settings</h3>
Configure WordPress integration options.

<h4>Webcam Name</h4>
<select name="webcamName" id="webcamName">
  <option value="display_name" <?php echo $options['webcamName']=='display_name'?"selected":""?>>Display Name (<?php echo $current_user->display_name;?>)</option>
  <option value="user_login" <?php echo $options['webcamName']=='user_login'?"selected":""?>>Login (<?php echo $current_user->user_login;?>)</option>
  <option value="user_nicename" <?php echo $options['webcamName']=='user_nicename'?"selected":""?>>Nicename (<?php echo $current_user->user_nicename;?>)</option>
  <option value="user_email" <?php echo $options['webcamName']=='user_email'?"selected":""?>>Email (<?php echo $current_user->user_email;?>)</option>
  <option value="ID" <?php echo $options['webcamName']=='ID'?"selected":""?>>ID (<?php echo $current_user->ID;?>)</option>
</select>
<br>Webcam room name (in listings). Will be used in webcam vanity url.


<h4>Username</h4>
<select name="userName" id="userName">
  <option value="display_name" <?php echo $options['userName']=='display_name'?"selected":""?>>Display Name (<?php echo $current_user->display_name;?>)</option>
  <option value="user_login" <?php echo $options['userName']=='user_login'?"selected":""?>>Login (<?php echo $current_user->user_login;?>)</option>
  <option value="user_nicename" <?php echo $options['userName']=='user_nicename'?"selected":""?>>Nicename (<?php echo $current_user->user_nicename;?>)</option>
  <option value="user_email" <?php echo $options['userName']=='user_email'?"selected":""?>>Email (<?php echo $current_user->user_email;?>)</option>
  <option value="ID" <?php echo $options['userName']=='ID'?"selected":""?>>ID (<?php echo $current_user->ID;?>)</option>
</select>
<br>Shows as username in chat.


<h4>Webcam Post Name</h4>
<input name="custom_post" type="text" id="custom_post" size="12" maxlength="32" value="<?php echo $options['custom_post']?>"/>
<br>Custom post name for webcams. Will be used for webcams url. Ex: webcam

<h4>Post Template Filename</h4>
<input name="postTemplate" type="text" id="postTemplate" size="20" maxlength="64" value="<?php echo $options['postTemplate']?>"/>
<br>Template file located in current theme folder, that should be used to render webcam post page. Ex: page.php, single.php


<h4>Setup Pages</h4>
<p>Create pages for main functionality. Also creates a menu with these pages (VideoWhisper) that can be added to themes.</p>
<select name="disableSetupPages" id="disableSetupPages">
  <option value="0" <?php echo $options['disableSetupPages']?"":"selected"?>>Yes</option>
  <option value="1" <?php echo $options['disableSetupPages']?"selected":""?>>No</option>
</select>

<h4>Registration Form Roles</h4>
<p>Add roles to registration form so users can register as client or performer.</p>
<select name="registrationFormRole" id="registrationFormRole">
  <option value="1" <?php echo $options['registrationFormRole']?"selected":""?>>Yes</option>
  <option value="0" <?php echo $options['registrationFormRole']?"":"selected"?>>No</option>
</select>

<h4>Translation Code for Chat Application</h4>
<?php
				$options['translationCode'] = htmlentities(stripslashes($options['translationCode']));
?>
<textarea name="translationCode" id="translationCode" cols="80" rows="5"><?php echo $options['translationCode']?></textarea>
<br>Generate by writing and sending "/videowhisper translation" in chat (contains xml tags with text and translation attributes). Texts are added to list only after being shown once in interface. If any texts don't show up in generated list you can manually add new entries for these. Same translation file is used for interfaces so setting should cumulate all translations.

<h4>Webcam Thumb Width</h4>
<input name="thumbWidth" type="text" id="thumbWidth" size="4" maxlength="4" value="<?php echo $options['thumbWidth']?>"/>

<h4>Webcam Thumb Height</h4>
<input name="thumbHeight" type="text" id="thumbHeight" size="4" maxlength="4" value="<?php echo $options['thumbHeight']?>"/>

<h4>Default Webcams Per Page</h4>
<input name="perPage" type="text" id="perPage" size="3" maxlength="3" value="<?php echo $options['perPage']?>"/>


	 <?php
				break;


			case 'performer':

				$options['layoutCodePerformer'] = htmlentities(stripslashes($options['layoutCodePerformer']));
				$options['parametersPerformer'] = htmlentities(stripslashes($options['parametersPerformer']));
				$options['welcomePerformer'] = htmlentities(stripslashes($options['welcomePerformer']));


?>
<h3>Performer Settings</h3>

<h4>Performer Role Name</h4>
<p>This is used as registration role option and access to performer dashboard page (redirection on login). Administrators can also manually access dashboard page and setup/access webcam page for testing.</p>
<input name="rolePerformer" type="text" id="rolePerformer" size="20" maxlength="64" value="<?php echo $options['rolePerformer']?>"/>
<br>Ex: performer, teacher, trainer, tutor, provider, model, author, expert

<h4>Welcome Message for Performer</h4>
<textarea name="welcomePerformer" id="parametersPerformer" cols="80" rows="2"><?php echo $options['welcomePerformer']?></textarea>

<h4>Parameters for Performer Interface</h4>
<textarea name="parametersPerformer" id="parametersPerformer" cols="80" rows="10"><?php echo $options['parametersPerformer']?></textarea>
<br>For more details see <a href="http://www.videowhisper.com/?p=php+video+messenger#integrate">PHP Video Messenger documentation</a>.

<h4>Custom Layout Code for Performer</h4>
<textarea name="layoutCodePerformer" id="layoutCodePerformer" cols="80" rows="5"><?php echo $options['layoutCodePerformer']?></textarea>
<br>Generate by writing and sending "/videowhisper layout" in chat (contains panel positions, sizes, move and resize toggles). Copy and paste code here.
	 <?php
				break;


			case 'client':

				$options['layoutCodeClient'] = htmlentities(stripslashes($options['layoutCodeClient']));
				$options['parametersClient'] = htmlentities(stripslashes($options['parametersClient']));
				$options['welcomeClient'] = htmlentities(stripslashes($options['welcomeClient']));

?>
<h3>Client Settings</h3>

<h4>Client Role Name</h4>
<p>This is used as registration role option.</p>
<input name="roleClient" type="text" id="roleClient" size="20" maxlength="64" value="<?php echo $options['roleClient']?>"/>
<br>Ex: client, student, member, subscriber

<h4>Who can access public (free) chat</h4>
<select name="canWatch" id="canWatch">
  <option value="all" <?php echo $options['canWatch']=='all'?"selected":""?>>Anybody</option>
  <option value="members" <?php echo $options['canWatch']=='members'?"selected":""?>>All Members</option>
  <option value="list" <?php echo $options['canWatch']=='list'?"selected":""?>>Members in List</option>
</select>
<br>Performers can access their own rooms even if they don't have permissions to access free chat.

<h4>Members allowed to watch video (comma separated usernames, roles, IDs)</h4>
<textarea name="watchList" cols="80" rows="2" id="watchList"><?php echo $options['watchList']?>
</textarea>

<h4>Welcome Message for Client</h4>
<textarea name="welcomeClient" id="parametersPerformer" cols="80" rows="2"><?php echo $options['welcomeClient']?></textarea>

<h4>Parameters for Client Interface</h4>
<textarea name="parametersClient" id="parametersClient" cols="80" rows="10"><?php echo $options['parametersClient']?></textarea>
<br>For more details see <a href="http://www.videowhisper.com/?p=php+video+messenger#integrate">PHP Video Messenger documentation</a>.

<h4>Custom Layout Code for Client</h4>
<textarea name="layoutCodeClient" id="layoutCodeClient" cols="80" rows="5"><?php echo $options['layoutCodeClient']?></textarea>
<br>Generate by writing and sending "/videowhisper layout" in chat (contains panel positions, sizes, move and resize toggles). Copy and paste code here.
<?php
				break;

			case 'ppv':
?>
<h3>Pay Per View Settings</h3>

<h4>Grace Time</h4>
<p>Private video chat is charged per minute after this time.</p>
<input name="ppvGraceTime" type="text" id="ppvGraceTime" size="10" maxlength="16" value="<?php echo $options['ppvGraceTime']?>"/>s
<br>Ex: 30; Set 0 to disable.

<h4>Pay Per Minute Cost for Client</h4>
<p>Paid by client in private video chat.</p>
<input name="ppvPPM" type="text" id="ppvPPM" size="10" maxlength="16" value="<?php echo $options['ppvPPM']?>"/>
<br>Ex: 0.5; Set 0 to disable.

<h4>Performer Earning Ratio</h4>
<p>Performer receives this ratio from client charge.</p>
<input name="ppvRatio" type="text" id="ppvRatio" size="10" maxlength="16" value="<?php echo $options['ppvRatio']?>"/>s
<br>Ex: 0.8; Set 0 to disable.

<h4>Minimum Balance</h4>
<p>Only clients that have a minimum balance can request private shows.</p>
<input name="ppvMinimum" type="text" id="ppvMinimum" size="10" maxlength="16" value="<?php echo $options['ppvMinimum']?>"/>
<br>Ex: 1.5; Set 0 to disable.

<h4>Pay Per Minute Cost for Performer</h4>
<p>Performers can also be charged for the private video chat time.</p>
<input name="ppvPerformerPPM" type="text" id="ppvPerformerPPM" size="10" maxlength="16" value="<?php echo $options['ppvPerformerPPM']?>"/>s
<br>Ex: 0.10; Set 0 to disable.

<h4>Bill After</h4>
<p>Closed sessions are billed after a minimum time, required for both client computers to update usage time. There's one transaction for entire private session, not for each minute or second.</p>
<input name="ppvBillAfter" type="text" id="ppvBillAfter" size="10" maxlength="16" value="<?php echo $options['ppvBillAfter']?>"/>s
<br>Ex. 10s

<h4>Close Sessions</h4>
<p>After some time, close sessions terminated abruptly and delete sessions where users did not enter both, due to client error. After closing billing can occur for valid sessions.</p>
<input name="ppvCloseAfter" type="text" id="ppvCloseAfter" size="10" maxlength="16" value="<?php echo $options['ppvCloseAfter']?>"/>s
<br>Ex. 120s
	<?php
				break;

			case 'billing':
?>
<h3>Billing Settings</h3>

<h4>1) myCRED</h4>
<?php
				if (is_plugin_active('mycred/mycred.php')) echo 'Detected'; else echo 'Not detected. Please install and activate <a target="_mycred" href="https://wordpress.org/plugins/mycred/">myCRED</a>!';

				if (function_exists( 'mycred_get_users_cred')) echo '<br>Testing balance: You have ' . mycred_get_users_cred() . ' points.';
?>

<p><a target="_mycred" href="https://wordpress.org/plugins/mycred/">myCRED</a> is an adaptive points management system that lets you award / charge your users for interacting with your WordPress powered website. The Buy Content add-on allows you to sell any publicly available post types, including video presentation posts created by this plugin. You can select to either charge users to view the content or pay the post's author either the whole sum or a percentage.<p>
<h4>2) myCRED buyCRED Module</h4>
 <?php
				if (class_exists( 'myCRED_buyCRED_Module' ) ) echo 'Detected'; else echo 'Not detected. Please install and activate myCRED with <a href="admin.php?page=myCRED_page_addons">buyCRED addon</a>!';
?>
<p>
myCRED <a href="admin.php?page=myCRED_page_addons">buyCRED addon</a> should be enabled and at least 1 <a href="admin.php?page=myCRED_page_gateways"> payment gateway</a> configured for users to be able to buy credits. Setup a page for users to buy credits with shortcode [mycred_buy_form]. </p>
<h4>3) myCRED Sell Content Module</h4>
 <?php
				if (class_exists( 'myCRED_Sell_Content_Module' ) ) echo 'Detected'; else echo 'Not detected. Please install and activate myCRED with <a href="admin.php?page=myCRED_page_addons">Sell Content addon</a>!';
?>
<p>
myCRED <a href="admin.php?page=myCRED_page_addons">Sell Content addon</a> should be enabled as it's required to enable certain stat shortcodes. Optionally add "<?=$options['custom_post']?>"  to Post Types in <a href="admin.php?page=myCRED_page_settings">Sell Content settings tab</a> so access to webcams can be sold from backend. You can also configure payout to content author from there, if necessary.
<?php
				break;


			}
			submit_button();
			echo '</form>';

		}

	}
}

//instantiate
if (class_exists("VWliveWebcams"))
{
	$liveWebcams = new VWliveWebcams();
}

//Actions and Filters
if (isset($liveWebcams))
{
	add_action('init', array(&$liveWebcams, 'init'));

	add_action("plugins_loaded", array(&$liveWebcams, 'plugins_loaded'));
	add_action('admin_menu', array(&$liveWebcams, 'adminMenu'));

	add_action('register_form', array(&$liveWebcams,'register_form'));
	add_action('user_register', array(&$liveWebcams,'user_register'));

	//add_filter( 'sidebars_widgets', array(&$liveWebcams,'sidebars_widgets') );
	//add_action( 'get_sidebar', array(&$liveWebcams,'get_sidebar') );

	add_filter( "single_template", array(&$liveWebcams,'single_template') );

}



?>
