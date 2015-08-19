<?php
// https://gist.github.com/hugowetterberg/81747
function ical_split($preamble, $value) {
  $value = trim($value);
  //$value = strip_tags($value);
  $value = preg_replace('/\n+/', ' ', $value);
  $value = preg_replace('/\s{2,}/', ' ', $value);
 
  $preamble_len = strlen($preamble);
 
  $lines = array();
  while (strlen($value)>(75-$preamble_len)) {
    $space = (75-$preamble_len);
    $mbcc = $space;
    while ($mbcc) {
      $line = mb_substr($value, 0, $mbcc);
      $oct = strlen($line);
      if ($oct > $space) {
        $mbcc -= $oct-$space;
      }
      else {
        $lines[] = $line;
        $preamble_len = 1; // Still take the tab into account
        $value = mb_substr($value, $mbcc);
        break;
      }
    }
  }
  if (!empty($value)) {
    $lines[] = $value;
  }
 
  return join($lines, "\n\t");
}

// Modified from http://wordpress.org/plugins/events-manager/
$session_id = $_GET['sessionid'];
$ical = $_GET['ical'];
$user_id = $_GET['user_id'];
$uid = $_GET['uid'];
if (isset($ical) || isset($session_id )):
$output = "BEGIN:VCALENDAR
VERSION:2.0
CALSCALE:GREGORIAN
PRODID:-//".get_bloginfo()."//EN\n";
echo $output;
//echo preg_replace("/([^\r])\n/", "$1\r\n", $output);
	if (isset($session_id) && $ical == 1){
		echo(render_cal_event($session_id));
	} elseif ( $ical== 'download' || $ical == 'feed'){
		if ($uid){
			$user = reset(
				 get_users(
				  array(
				   'meta_key' => 'con_user_ical_uid',
				   'meta_value' => $uid,
				   'number' => 1,
				   'count_total' => false,
				   'fields' => 'ids'
				  )
				 )
				);
			if (!empty($user)){
				$user_id = $user;
			} else {
				return;	
			}
		} else {
			return;
		}
		
		$group_ids = BP_Groups_Member::get_group_ids($user_id);

		
		foreach ( $group_ids['groups'] as $group_id ) {
			$args = array ('post_type' => 'session',
						   'post_status'    => 'publish',
						   'meta_query' => array(
										array(
											'key' => 'con_group',
											'value' => $group_id,
											)
					),
				'fields' => 'ids');
				
			$ids = get_posts($args);

			if (!empty($ids)){
				echo (render_cal_event($ids[0]));
			}
		}	
	}
echo "END:VCALENDAR";
endif; // querystring

function render_cal_event($id){ 
	$post = get_post($id);
	Conferencer::add_meta($post);
	//calendar header
	
	
	$dateStart	= ':'.get_gmt_from_date(date('Y-m-d H:i:s', get_post_meta($post->time_slot, '_conferencer_starts', true)), 'Ymd\THis\Z');
	$dateEnd = ':'.get_gmt_from_date(date('Y-m-d H:i:s', get_post_meta($post->time_slot, '_conferencer_ends', true)), 'Ymd\THis\Z');
	
	
	//formats
	$summary = ical_escape_text($post->post_title);
	$permalink = get_permalink($id);
	$altdescription = do_shortcode("[session_meta
								post_id='$post->ID'
								show='room,track'
								room_prefix='Room: '
								room_suffix='\r\n'
								track_prefix='Track: ']")."\r\n".$post->post_content."\r\n".$permalink;
	//$description =  mysql_real_escape_string(strip_tags($altdescription));
	//$altdescription = html_entity_decode($altdescription, ENT_QUOTES, "utf-8");
	//$altdescription = str_replace("\r\n","\\n",str_replace(";","\;",str_replace(",",'\,',$altdescription)));
	$description = ical_escape_text($altdescription);
	$description = str_replace(array("\r\n", "\n", "\r"), "\\n", $description);
	$location = ical_escape_text(get_the_title($post->room));
	$dateModified = get_gmt_from_date($post->post_modified, 'Ymd\THis\Z');
	
	
	//create a UID
	$UID = sprintf( '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
		// 32 bits for "time_low"
		mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ),
		// 16 bits for "time_mid"
		mt_rand( 0, 0xffff ),
		// 16 bits for "time_hi_and_version",
		// four most significant bits holds version number 4
		mt_rand( 0, 0x0fff ) | 0x4000,
		// 16 bits, 8 bits for "clk_seq_hi_res",
		// 8 bits for "clk_seq_low",
		// two most significant bits holds zero and one for variant DCE1.1
		mt_rand( 0, 0x3fff ) | 0x8000,
		// 48 bits for "node"
		mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff )
	);
			
	//output ical item		
	$output = "BEGIN:VEVENT
	UID:{$UID}
	DTSTART{$dateStart}
	DTEND{$dateEnd}
	DTSTAMP:{$dateModified}
	";
	$output .= "SUMMARY;LANGUAGE=en-gb:" . $summary . "\n";
	if( $description ){
		$output .= "DESCRIPTION:" . $description . "\n";
	}
	$output .= "LOCATION:".$location."\n";
	$output .= "URL:".$permalink."\n";
	$output .= "END:VEVENT\n";
	
	//clean up new lines, rinse and repeat
	$output = preg_replace('/\t+/', '', $output);
	return $output;
	//echo preg_replace("/([^\r])\n/", "$1\r\n", $output);
}
function ical_escape_text($text) {
  $text = html_entity_decode($text, ENT_QUOTES, "utf-8");
  $text = strip_tags($text);
  $text = str_replace("\r\n","\\n",str_replace(";","\;",str_replace(",",'\,',$text)));
  return $text;
}