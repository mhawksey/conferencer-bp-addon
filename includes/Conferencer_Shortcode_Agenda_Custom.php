<?php
new Conferencer_Shortcode_Agenda_Custom();
class Conferencer_Shortcode_Agenda_Custom extends Conferencer_Shortcode {
	var $shortcode = 'agendacustom';
	var $defaults = array(
		'column_type' => 'track',
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
		'default_day' => 1
	);
	
	var $buttons = array('agenda');
	
	function prep_options() {
		parent::prep_options();
		
		if (!in_array($this->options['column_type'], array('track', 'room', 'type'))) {
			$this->options['column_type'] = false;
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
	
		if ($column_type) {
			$column_post_counts = array(
				-1 => 0, // keynotes
				0 => 0, // unscheduled
			);
			$column_posts = Conferencer::get_posts($column_type);
		
			foreach ($agenda as $time_slot_id => $time_slot) {
				foreach ($column_posts as $column_post_id => $column_post) {
					$column_post_counts[$column_post_id] = 0;
					$agenda[$time_slot_id][$column_post_id] = array();
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

			if ($column_type) {
				$column_id = $session->$column_type ? $session->$column_type : 0;
				if ($keynote_spans_tracks && $session->keynote) $column_id = -1;
				$agenda[$time_slot_id][$column_id][$session->ID] = $session;
				$column_post_counts[$column_id]++;
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
	
		if ($column_type) {
			$column_headers = array();
		
			// post column headers
			foreach ($column_posts as $column_post) {
				if (!$show_empty_columns && in_array($column_post->ID, $empty_column_post_ids)) continue;
			
				$column_headers[] = array(
					'title' => $column_post->post_title,
					'class' => 'column_'.$column_post->post_name,
					'link' => $link_columns ? get_permalink($column_post->ID) : false,
				);
			}
		
			if ($show_unassigned_column && count($column_post_counts[0])) {
				// extra column header for sessions not assigned to a column
				$column_headers[] = array(
					'title' => $unassigned_column_header_text,
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

<div class="agenda" id="buddypress">
  <?php echo '<script>var defaultDay='.$default_day.';</script>' ?>
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
			echo ('<h3>'.ucwords($filter).'</h3>');
			echo ('<div class="filters">');
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
				foreach ($filter_out as $filter_name => $filter_values) {
					echo '<div id="'.$filter.'-'.$filter_name.'"><h4>'.ucwords($filter_name).'</h4>'.$filter_values.'</div>';
				}
			}	
			echo "<style type='text/css'>\n".$style."</style>";
			echo ('</div>'); // filters
		}
	}
?>
    <h3>Your Followed Sessions</h3>
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
      <?php $tab_idx = 0;
		$tab_lkup = array();?>
      <?php foreach ($tab_headers as $tab_header) { 
				$tab_idx++; 
				$tab_lkup[get_day($tab_header)] = "day".$tab_idx;
		?>
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
        <?php $row_starts = $last_row_starts = $second_table = false; ?>
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
      </tbody>
    </table>
    <!-- #conferencer_agenda_tab_xxx --> </div>
  <?php } else $second_table = true; ?>
  <!-- <?php print_r($row_starts);?> -->
  <div id="<?php echo $tab_lkup[get_day($row_starts)]; ?>">
  <div id="scroller-anchor"></div>
    <table class="grid">
      <?php if ($column_type) $this->display_headers($column_headers); ?>
      <tbody>
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
        <tr<?php output_classes($classes); ?>>
          <?php // Time slot column -------------------------- ?>
          <td class="time_slot" ><div class="ts"></div><div class="tm"><?php
									if ($time_slot_id) {
										$time_slot_link = get_post_meta($time_slot_id, '_conferencer_link', true)
											OR $time_slot_link = get_permalink($time_slot_id);
										$html = date($row_time_format, $row_starts);
										if ($show_row_ends) $html .= " &ndash; ".date($row_time_format, $row_ends);
										if ($link_time_slots) $html = "<a href='$time_slot_link'>$html</a>";
										echo $html;
									}
								?></div></td>
          <?php // Display session cells --------------------- ?>
          <?php $colspan = $column_type ? count($column_headers) : 1; ?>
          <?php if ($non_session) { // display a non-sessioned time slot ?>
          <td class="sessions" colspan="<?php echo $colspan; ?>"><p>
              <?php
											$html = get_the_title($time_slot_id);
											if ($link_time_slots) $html = "<a href='$time_slot_link'>$html</a>";
											echo $html;
										?>
            </p></td>
          <?php } else if (isset($cells[-1])) { ?>
            <td class="sessions keynote-sessions" colspan="<?php echo $colspan; ?>"><?php
										foreach ($cells[-1] as $session) {
											$this->display_session($session);
										}
									?></td>
            <?php } else if ($column_type) { // if split into columns, multiple cells  ?>
            <?php foreach ($cells as $cell_sessions) { ?>
            <td class="sessions <?php if (empty($cell_sessions)) echo 'no-sessions'; ?>"><?php
											foreach ($cell_sessions as $session) {
												$this->display_session($session);
											}
										?></td>
            <?php } ?>
          <?php } else { // all sessions in one cell ?>
          <td class="sessions <?php if (empty($cells)) echo 'no-sessions'; ?>"><?php
										foreach ($cells as $session) {
											$this->display_session($session);
										}
									?></td>
          <?php } ?>
        </tr>
        <?php } ?>
      </tbody>
    </table>
    <?php if ($tabs) { ?>
    <!-- #conferencer_agenda_tab_xxx --> </div>
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
	
	function display_session($session) {
		//print_r($session);
		if (function_exists('conferencer_agenda_display_session')) {
			conferencer_agenda_display_session($session, $this->options);
			return;
		}

		extract($this->options);
		$group_id = get_post_meta($session->ID, 'con_group', true);
		?>
<a name="sessionid<?php echo $group_id ;?>"></a>
<div class="session <?php if ($session->track) echo " track-".Conferencer_BP_Addon::get_the_slug($session->track); 
						  if ($session->type) echo " type-".Conferencer_BP_Addon::get_the_slug($session->type);?>" group-id="<?php echo $group_id ;?>">
  <div class="generic-button group-button prog public" id="groupbutton-<?php echo $group_id ;?>" style="display:none"></div>
    <?php 
		/*$webinar_link = get_post_meta($session->ID, 'con_webinar_link', true);
		if ($webinar_link) {
				echo'<div class="generic-button webinar-button"><a href="'.$webinar_link.'">Join Webinar</a></div>';
		}*/
	?>
  <?php if (get_post_meta($session->ID, 'con_live', true)) echo '<div class="islive">Live Streamed</div>'; ?>
  <?php echo do_shortcode("
				[session_meta
					post_id='$session->ID' 
					show='".$session_meta_show."' 
					speakers_prefix='".$speakers_prefix."'  
					room_prefix='".$room_prefix."' 
					type_prefix='".$type_prefix."'  
					chair_prefix='".$chair_prefix."'  
					link_title=".($link_sessions ? 'true' : 'false')." 
					link_speakers=".($link_speakers ? 'true' : 'false')." 
					link_room=".($link_rooms ? 'true' : 'false')." 
				]");	?>
  <?php if ($session_tooltips) { ?>
  <div class="session-ex">
    <div class="session-ex-text" style="display:none"> <?php echo do_shortcode("[session_meta post_id='$session->ID' show='time' link_all=false]"); ?>
      <p class="excerpt"><?php echo $this->generate_agenda_excerpt($session); ?></p>
    </div>
    <a class="expander"><i class="fa fa-chevron-down"></i></a> </div>
  <?php } ?>
</div>
<?php }	
}