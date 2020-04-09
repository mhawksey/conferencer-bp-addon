<?php
new Conferencer_Shortcode_Agenda_Custom_XML();
class Conferencer_Shortcode_Agenda_Custom_XML extends Conferencer_Shortcode {
	var $shortcode = 'agendacustomxml';
	var $defaults = array(
		'row_type' => 'track',
		'session_tooltips' => true,
		'session_meta_show' => 'title,type,speakers,room,chair',
		'speakers_prefix' => 'Authors: ', 
		'room_prefix' => 'Room: ', 
		'type_prefix' => 'Type: ', 
		'chair_prefix' => 'Chair: ', 
		'show_empty_rows' => true,
		'show_empty_columns' => true,
		'show_empty_cells' => null,
		'show_unassigned_column' => false,
		'tabs' => 'days',
		'tab_day_format' => 'M. j, Y',
		'row_day_format' => 'l, F j, Y',
		'row_time_format' => 'g:ia',
		'show_row_ends' => false,
		'keynote_spans_tracks' => true,
		'link_sessions' => true,
		'link_speakers' => true,
		'link_rooms' => true,
		'link_time_slots' => true,
		'link_columns' => true,
		'unassigned_column_header_text' => 'N/A',
		'unscheduled_row_text' => 'Unscheduled',
	);
	
	var $buttons = array('agenda');
	
	function prep_options() {
		parent::prep_options();
		
		if (!in_array($this->options['row_type'], array('track', 'room', 'type'))) {
			$this->options['row_type'] = false;
		}
		
		if ($this->options['show_empty_cells'] != null) {
			$this->options['show_empty_rows'] = $this->options['show_empty_cells'];
			$this->options['show_empty_columns'] = $this->options['show_empty_cells'];
		}
	}

	function content() {
		extract($this->options);
		$conferencer_options = get_option('conferencer_options');

		// Define main agenda variable

		$agenda = array();
	
		// Fill agenda with empty time slot rows
	
		foreach (Conferencer::get_posts('time_slot', false, 'start_time_sort') as $time_slot_id => $time_slot) {
			$agenda[$time_slot_id] = array();
		}
		$agenda[0] = array(); // for unscheduled time slots
	
		// If the agenda is split into columns, fill rows with empty "cell" arrays
	
		if ($row_type) {
			$row_post_counts = array(
				-1 => 0, // keynotes
				0 => 0, // unscheduled
			);
			$row_posts = Conferencer::get_posts($row_type);
		
			foreach ($agenda as $time_slot_id => $time_slot) {
				foreach ($row_posts as $row_post_id => $row_post) {
					$row_post_counts[$row_post_id] = 0;
					$agenda[$time_slot_id][$row_post_id] = array();
				}
				$agenda[$time_slot_id][0] = array();
			}
		}
	
		// Get all session information
	
		$sessions = Conferencer::get_posts('session', false, 'order_sort');
		foreach (array_keys($sessions) as $id) {
			Conferencer::add_meta($sessions[$id]);
		}
	
		// Put sessions into agenda variable
	
		foreach ($sessions as $session) {
			$time_slot_id = $session->time_slot ? $session->time_slot : 0;

			if ($row_type) {
				$row_id = $session->$row_type ? $session->$row_type : 0;
				if ($keynote_spans_tracks && $session->keynote) $row_id = -1;
				$agenda[$time_slot_id][$row_id][$session->ID] = $session;
				$row_post_counts[$row_id]++;
			} else {
				$agenda[$time_slot_id][$session->ID] = $session;
			}
		}
		
		// Remove empty unscheduled rows
		
		if (deep_empty($agenda[0])) unset($agenda[0]);
	
		// Conditionally remove empty rows and columns
	
		if (!$show_empty_rows) {
			foreach ($agenda as $time_slot_id => $cells) {
				$non_session = get_post_meta($time_slot_id, '_conferencer_non_session', true);
				if (!$non_session && deep_empty($cells)) unset($agenda[$time_slot_id]);
			}
		}
	
		if (!$show_empty_columns) {
			$empty_column_post_ids = array();
			foreach ($column_posts as $column_post_id => $column_post) {
				if (!$column_post_counts[$column_post_id]) $empty_column_post_ids[] = $column_post_id;
			}
		
			foreach ($agenda as $time_slot_id => $cells) {
				foreach ($empty_column_post_ids as $empty_column_post_id) {
					unset($agenda[$time_slot_id][$empty_column_post_id]);
				}
			}
		}

		// Set up tabs
	
		if ($tabs) {
			$tab_headers = array();
		
			foreach ($agenda as $time_slot_id => $cells) {
				if ($tabs == 'days') {
					if ($starts = get_post_meta($time_slot_id, '_conferencer_starts', true)) {
						$tab_headers[] = get_day($starts);
					} else $tab_headers[] = 0;
				}
			}
		
			$tab_headers = array_unique($tab_headers);
			
			if (count($tab_headers) < 2) $tabs = false;
		}
		
		// Set up column headers
	
		if ($row_type) {
			$row_headers = array();
		
			// post column headers
			foreach ($row_posts as $row_post) {
				if (!$show_empty_columns && in_array($row_post->ID, $empty_column_post_ids)) continue;
			
				$row_headers[] = array(
					'ID' => $row_post->ID,
					'title' => $row_post->post_title,
					'class' => $row_type.'_col '.$row_post->post_name,
					'link' => $link_columns ? get_permalink($row_post->ID) : false,
				);
			}
		
			if ($show_unassigned_column && count($row_post_counts[0])) {
				// extra column header for sessions not assigned to a column
				$row_headers[] = array(
					'title' => $unassigned_row_header_text,
					'class' => 'column_not_applicable',
					'link' => false,
				);
			} else {
				// remove cells if no un-assigned sessions
				foreach ($agenda as $time_slot_id => $cells) {
					unset($agenda[$time_slot_id][0]);
				}
			}
		}
	
		// Remove unscheduled time slot, if without sessions
		if (deep_empty($agenda[0])) unset($agenda[0]);

		// Start buffering output
		
		

		ob_start();
	
		?>
<style type="text/css">
.agenda .session, .agenda .room_col, .time_slot div  {
font-size:80%;	
}
.agenda .room_col div {
  margin-left: -15px;
  position: absolute;
  width: 60px;
  transform: rotate(-90deg);
  -webkit-transform: rotate(-90deg); /* Safari/Chrome */
  -moz-transform: rotate(-90deg);    /* Firefox */
  -o-transform: rotate(-90deg);      /* Opera */
  -ms-transform: rotate(-90deg);     /* IE 9 */	
}
td.room_col {
  max-width: 50px;
  height: 60px;
  line-height: 14px;
  padding-bottom: 15px;
  text-align: center;
}
tr.row{
min-height:60px;	
border-top: none;
border-right: none;
}
tr.row:last-child{
border-bottom: none;
}
.type_table{
margin-bottom:0px;	
}
.agenda td {
/* vertical-align:top;*/	
}
.non_session{
text-align:center;	
}
.agenda .leave-group, .agenda .join-group {
	font-size:100% !important;
}
</style>
<div class="agenda" id="buddypress">
  <?php  echo '<script src="'.plugins_url( 'js/confprog.js?v=53' , dirname(__FILE__) ).'"></script>'; ?>
  <div class="agenda-filter">
    <h2>Filters</h2>
  <?php
	$filters = array('track', 'type');
	foreach($filters as $filter){
		$filter_posts = Conferencer::get_posts($filter);
		$filter_array = array();
		$filter_out = array();
		$style ="";
		if(!empty($filter_posts)){

			foreach ($filter_posts as $filter_post) {
				$filter_array[$filter_post->ID] = $filter_post->post_title;
				$filter_type = get_post_meta($filter_post->ID, $filter.'_filter', true);
				$filter_color = get_post_meta($filter_post->ID, $filter.'_color', true);
				
				if ($filter_color !=""){
					$filter_out[$filter_type] .= '<div class="'.$filter.' '.$filter.'-'.$filter_post->post_name.'" id="'.$filter.'-'.$filter_post->post_name.'">
													<label><input id="'.$filter.'-'.$filter_post->post_name.'" name="'.$filter.'-'.$filter_post->ID.'" type="checkbox" checked/>' .
													$filter_post->post_title.'</label>
												 </div>';
				
				
					$style .= '.agenda .'.$filter.'-'.$filter_post->post_name.' { border-left-color: '.$filter_color.';} ';
				}
			}
			if (!empty($filter_out)){
				echo ('<h3>'.ucwords($filter).'</h3>');
				echo ('<div class="filters">');
				foreach ($filter_out as $filter_name => $filter_values) {
					echo '<div id="'.$filter.'-'.$filter_name.'"><h4>'.ucwords($filter_name).'</h4>'.$filter_values.'</div>';
				}
				echo ('</div>'); // filters
			}	
			echo "<style type='text/css'>\n".$style."</style>";
			
		}
	}
?>
    <h3>Followed Sessions</h3>
    <div class="myical generic-button public" style="display:none"><a href="?ical=download">Download all my sessions (.ics)</a></div>
    <div class="myicalfeed generic-button public" style="display:none"><a href="?ical=feed">Subscribe to my sessions (.ics)</a>
      <div id="myicalurlbox" style="display:none"><input onClick="this.setSelectionRange(0, 9999)" type="text" id="myicalurl" /> Copy and paste the url in the box into your calendar software </div>
    </div>
    <div class="filters">
    	<div class="mysessions"><img src="<?php echo plugins_url( 'images/icons/loading.gif' , dirname(__FILE__) )?>" /></div>
    </div>
  </div> <!-- end agenda-filter -->
  <?php if ($tabs) { ?>
  <div class="conferencer_tabs">
    <ul class="tabs">
      <!-- <?php $tab_idx = 0;
						   $tab_lkup = array();?> -->
      <?php foreach ($tab_headers as $tab_header) { ?>
      <?php $tab_idx ++; 
								$tab_lkup[get_day($tab_header)] = "day".$tab_idx;?>
      <li>
        <?php if ($tabs == 'days') { ?>
        <a href="#day<?php echo $tab_idx; ?>"> <?php echo $tab_header ? date($tab_day_format, $tab_header) : $unscheduled_row_text; ?> </a>
        <?php } ?>
      </li>
      <?php } ?>
    </ul>
    <?php } else { ?>
    <table class="grid">
      <?php if ($column_type) $this->display_headers($column_headers); ?>
      <tbody>
        <?php } ?>
        <?php $row_starts = $last_row_starts = $second_table = false; 
		$rowidx=0;
		$xml = Array();?>
        <?php foreach ($agenda as $time_slot_id => $cells) { ?>
        <?php
							// Set up row information
					        
							$last_row_starts = $row_starts;
							$row_starts = get_post_meta($time_slot_id, '_conferencer_starts', true);
							$row_ends = get_post_meta($time_slot_id, '_conferencer_ends', true);
							$non_session = get_post_meta($time_slot_id, '_conferencer_non_session', true);
							$no_sessions = deep_empty($cells);
						
							// Show day seperators
							$show_next_day = $row_day_format !== false && date('w', $row_starts) != date('w', $last_row_starts);
						
							if ($show_next_day) { ?>
        <?php if ($tabs) { ?>
        <?php if ($second_table) { ?>
    
    <!-- #conferencer_agenda_tab_xxx --> <textarea><?php $rowidx=0; $this->render_xml($xml); $xml = Array(); ?></textarea></div>
  <?php } else $second_table = true; ?>
  <div id="<?php echo $tab_lkup[get_day($row_starts)]; ?>">
  
  <div id="scroller-anchor"></div>
  
  
  
      <?php if ($column_type) $this->display_headers($column_headers); ?>
      
        <?php } else { ?>
        <tr class="day">
          <td colspan="<?php echo $column_type ? count($column_headers) + 1 : 2; ?>"><?php echo $row_starts ? date($row_day_format, $row_starts) : $unscheduled_row_text; ?></td>
        </tr>
        <?php } ?>
        <?php }
							// Set row classes

							$classes = array();
							if ($non_session) $classes[] = 'non-session';
							else if ($no_sessions) $classes[] = 'no-sessions';
						?>
        
          <?php // Time slot column -------------------------- ?>
          <?php $rowspan = $row_type ? count($row_headers) : 1; 
		  // $xml[$rowidx]  ?>           
          <?php // Display session cells --------------------- ?>
          <?php $colspan = $column_type ? count($column_headers) : 1; ?>
          <?php
                        if ($time_slot_id) {
                            $time_slot_link = get_post_meta($time_slot_id, '_conferencer_link', true)
                                OR $time_slot_link = get_permalink($time_slot_id);
                            $html = date($row_time_format, $row_starts);
                            if ($show_row_ends) $html .= " &ndash; ".date($row_time_format, $row_ends);
                            if ($link_time_slots) $html = "<a href='$time_slot_link'>$html</a>";
                            //echo $html;
							$xml[$rowidx]['time'] = $html;
							$xml[$rowidx]['rows'] = 0;
							
							
                        }
                        
                    ?>
          <?php if ($non_session) { // display a non-sessioned time slot  ?>
        
<?php
											$html = htmlspecialchars(get_the_title($time_slot_id), ENT_XML1);
											if ($link_time_slots) $html = "$html";
											//echo $html;
										?>
          <?php	$xml[$rowidx]['rooms']['no_name'] = Array('<Cell aid:table="cell" aid:crows="1" aid:ccols="1" aid:ccolwidth="227.97244094547244"><Session>'.$html.'</Session></Cell>'); ?>
          <?php $xml[$rowidx]['rows']++ ?>
          <?php } else if (isset($cells[-1])) { ?>
         
            <?php
										foreach ($cells[-1] as $session) {
											//$this->display_session($session, true);
										}
									?>
               
          <?php	$xml[$rowidx]['rooms']['no_name'] = Array('<Cell aid:table="cell" aid:crows="1" aid:ccols="1" aid:ccolwidth="227.97244094547244"><Session>'.htmlspecialchars(get_the_title($session->ID), ENT_XML1).'</Session></Cell>'); ?>
            <?php $xml[$rowidx]['rows']++ ?>                
            <?php } else if ($row_type) { // if split into rows, multiple cells  ?>
         	
            <?php foreach ($row_headers as $row_header) {?>
            	
            	<?php 
				$do_rows = Array();
				foreach ($cells as $cell_sessions) { 
					foreach ($cell_sessions as $session) {
						if ($row_header['ID'] == $session->$row_type){
							
							$do_rows[] = $session;
						}
					}
				}
				?>
                
            <?php if (!empty($do_rows)) {?>

                            
                <?php $xml[$rowidx]['rooms'][$row_header['title']] = Array(); ?>
                <?php foreach ($do_rows as $do_row) { ?>
                <?php 	//$this->display_session($do_row); ?>
                <?php $xml[$rowidx]['rooms'][$row_header['title']][] = $this->get_session($do_row); ?>
                <?php $xml[$rowidx]['rows']++ ?>
                <?php } ?> 
           		 <?php } // end $do_row?>
            <?php } ?>
            
          <?php } else { // all sessions in one cell ?>
         
          <?php } ?>
        <?php $rowidx++; ?>
        <?php } ?>

   
    <?php if ($tabs) { ?>
    <textarea><?php $rowidx=0; $this->render_xml($xml); ?><!-- #conferencer_agenda_tab_xxx --></textarea> </div>
   
</div>
<!-- .conferencer_agenda_tabs -->
<?php } ?>

</div>
<!-- .agenda -->

<?php
	
		// Retrieve and return buffer
	
		return ob_get_clean();
	}
	
	function display_headers($column_headers) { ?>
<thead>
  <tr>
    <th class="column_time_slot"></th>
    <?php foreach ($column_headers as $column_header) { ?>
    <th class="<?php echo $column_header['class']; ?>"> <?php
							$html = $column_header['title'];
							if ($column_header['link']) $html = "<a href='".$column_header['link']."'>$html</a>";
							echo $html;
						?>
    </th>
    <?php } ?>
  </tr>
</thead>
<?php }
	
	function generate_agenda_excerpt($post_id = false) {
		if ($post_id) $post = is_numeric($post_id) ? get_post($post_id) : $post_id;
		else $post = $GLOBALS['post'];

		if (!$post) return '';
		if (isset($post->post_excerpt) && !empty($post->post_excerpt)) return $post->post_excerpt;
		if (!isset($post->post_content)) return '';
	
		$content = $raw_content = $post->post_content;
	
		if (!empty($content)) {
			$content = strip_shortcodes($content);
			$content = apply_filters('the_content', $content);
			$content = str_replace(']]>', ']]&gt;', $content);
			$content = strip_tags($content);

			$excerpt_length = apply_filters('excerpt_length', 100);
			$words = preg_split("/[\n\r\t ]+/", $content, $excerpt_length + 1, PREG_SPLIT_NO_EMPTY);
			if (count($words) > $excerpt_length) {
				array_pop($words);
				$content = implode(' ', $words);
				$content .= "...";
			} else $content = implode(' ', $words);
		}
		$content .= '<a class="more-link" href="' . get_permalink($post->ID) . '">more</a>';
		return apply_filters('wp_trim_excerpt', $content, $raw_content);
	}
	
	function get_session($session , $is_keynote = false) {
		//print_r($session);
		if (function_exists('conferencer_agenda_display_session')) {
			conferencer_agenda_display_session($session, $this->options);
			return;
		}

		extract($this->options);
		$group_id = get_post_meta($session->ID, 'con_group', true);
		if (!$is_keynote) {
			
			$html ='<SessionType'.(($session->track) ? str_replace(array('&#038;', '/', '//', ' '), '', get_the_title($session->track)) : 'Non').' aid:table="cell" aid:crows="1" aid:ccols="1" aid:ccolwidth="227.97244094547244"><Session><SessionTitle>'.htmlspecialchars(get_the_title($session->ID), ENT_XML1).'</SessionTitle> '. htmlspecialchars(trim(strip_tags (do_shortcode("
						[session_meta
							post_id='$session->ID' 
							show='speakers' 
							speakers_prefix='".$speakers_prefix."'  
							room_prefix='".$room_prefix."' 
							type_prefix='".$type_prefix."'  
							chair_prefix='".$chair_prefix."'  
							link_title=".($link_sessions ? 'true' : 'false')." 
							link_speakers=".($link_speakers ? 'true' : 'false')." 
							link_room=".($link_rooms ? 'true' : 'false')." 
						]"))), ENT_XML1).'</Session></SessionType'.(($session->track) ? str_replace(array('&#038;', '/', '//', ' '), '', get_the_title($session->track)) : 'Non').'>';
						
			return $html;?>
		<?php } else { //is keynote ?>
        	<Session><?php echo htmlspecialchars(get_the_title($session->ID), ENT_XML1);?></Session>
        <?php } ?>

<?php }	
	
	function render_xml($xml){
		$out = "";
		//print_r($xml);
		foreach ($xml as $slot){
			$out .= '<Cell aid:table="cell" aid:crows="'.$slot['rows'].'" aid:ccols="1" aid:ccolwidth="22.17716535433067"><Time>'.$slot['time'].'</Time></Cell>';
			//$out .= '<Cell aid:table="cell" aid:crows="1" aid:ccols="1" aid:ccolwidth="22.17716535433067"><Time>'.$slot['time'].'</Time></Cell>';
			$total_rows = $total_rows + $slot['rows'];
			foreach ($slot['rooms'] as $room_name => $room){
				if ($room_name == 'no_name'){
					$out .= '<Cell aid:table="cell" aid:crows="1" aid:ccols="1" aid:ccolwidth="15.307086612992066"> </Cell>';
					$out .= $room[0];
				} else {
					$out .= '<Cell aid:table="cell" aid:crows="'.count($room).'" aid:ccols="1" aid:ccolwidth="15.307086612992066"><Room>'.$room_name.'</Room></Cell>';
					//$out .= '<Cell aid:table="cell" aid:crows="1" aid:ccols="1" aid:ccolwidth="15.307086612992066"><Room>'.$room_name.'</Room></Cell>';
					$out .= implode('', $room);
				}
				
			}
		}
		
		$table = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Root><Story>';
		$table .= '<Table xmlns:aid="http://ns.adobe.com/AdobeInDesign/4.0/" aid:table="table" aid:trows="'.$total_rows.'" aid:tcols="3">';
		$table .= $out;
		$table .= '</Table></Story></Root>';
		
		echo $table;
	}
	
	
	function display_session($session , $is_keynote = false) {
		//print_r($session);
		if (function_exists('conferencer_agenda_display_session')) {
			conferencer_agenda_display_session($session, $this->options);
			return;
		}

		extract($this->options);
		$group_id = get_post_meta($session->ID, 'con_group', true);
		if (!$is_keynote) {
			?><SessionType_<?php if ($session->track) echo(get_the_title($session->track));?>  aid:table="cell" aid:crows="1" aid:ccols="1" aid:ccolwidth="227.97244094547244"><Session><SessionTitle><?php echo htmlspecialchars(get_the_title($session->ID), ENT_XML1);?></SessionTitle><?php echo htmlspecialchars(trim(strip_tags (do_shortcode("
						[session_meta
							post_id='$session->ID' 
							show='speakers' 
							speakers_prefix='".$speakers_prefix."'  
							room_prefix='".$room_prefix."' 
							type_prefix='".$type_prefix."'  
							chair_prefix='".$chair_prefix."'  
							link_title=".($link_sessions ? 'true' : 'false')." 
							link_speakers=".($link_speakers ? 'true' : 'false')." 
							link_room=".($link_rooms ? 'true' : 'false')." 
						]"))), ENT_XML1);	?></Session></SessionType_><?php if ($session->track) echo(get_the_title($session->track));?>>
		<?php } else { //is keynote ?>
        	<Session><?php echo htmlspecialchars(get_the_title($session->ID), ENT_XML1);?></Session>
        <?php } ?>

<?php }	
}