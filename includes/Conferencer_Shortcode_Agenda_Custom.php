<?php
new Conferencer_Shortcode_Agenda_Custom();
class Conferencer_Shortcode_Agenda_Custom extends Conferencer_Shortcode {
	var $shortcode = 'agendacustom';
	var $defaults = array(
		'column_type' => 'track',
		'session_tooltips' => true,
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
		
		if (!in_array($this->options['column_type'], array('track', 'room'))) {
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
	
		<div class="conferencer_agenda">
			<?php if ($conferencer_options['details_toggle']) { ?>
				<a href="#" class="conferencer_session_detail_toggle">
					<span class="show">display session details</span>
					<span class="hide">hide session details</span>
				</a>
			<?php } ?>
<?php 
$column_posts = Conferencer::get_posts('track');
$track_array = array();
$out_types = array();
//$out = "<!-- ";
?>
<?php  echo '<script src="'.plugins_url( 'js/confprog.js?v=35' , dirname(__FILE__) ).'"></script>'; ?>
<div class="agenda-filter">
<h2>Filters</h2>
<div id="theme-filters">
<?php
foreach ($column_posts as $column_post) {
	$track_array[$column_post->ID] = $column_post->post_title;
	$track_type = get_post_meta($column_post->ID, 'track_filter', true);
	if (isset($track_type)){
		$out_types[$track_type] .= '<div class="track track-'.$column_post->post_name.'" id="track-'.$column_post->post_name.'">
							<label><input id="track-'.$column_post->post_name.'" name="track-'.$column_post->ID.'" type="checkbox" checked/>' .
							$column_post->post_title.'</label>
						</div>';
	}
}
foreach ($out_types as $track_name => $track_values) {
	echo '<div id="track-'.$track_name.'"><h3>'.ucwords($track_name).'</h3>'.$track_values.'</div>';
}

//echo $out." -->";
?>
<div>(<a href="#" class="check">Uncheck All</a>)</div>  
</div>
<h3>Your Sessions Filter</h3>
<div class="mysessions"><img src="<?php echo plugins_url( 'images/icons/loading.gif' , dirname(__FILE__) )?>/" /></div>
<?php if (is_user_logged_in()) {
	echo '<div class="myical generic-button group-button public" style="display:none"><a href="?ical=all">Download all my sessions (.ics)</a></div>';
}?>
</div>
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
                            	
								<a href="#day<?php echo $tab_idx; ?>">
									<?php echo $tab_header ? date($tab_day_format, $tab_header) : $unscheduled_row_text; ?>
								</a>
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
									<table class="grid">
										<?php if ($column_type) $this->display_headers($column_headers); ?>
										<tbody>
								<?php } else { ?>
									<tr class="day">
										<td colspan="<?php echo $column_type ? count($column_headers) + 1 : 2; ?>">
											<?php echo $row_starts ? date($row_day_format, $row_starts) : $unscheduled_row_text; ?>
										</td>
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
					
							<td class="time_slot">
								<?php
									if ($time_slot_id) {
										$time_slot_link = get_post_meta($time_slot_id, '_conferencer_link', true)
											OR $time_slot_link = get_permalink($time_slot_id);
										$html = date($row_time_format, $row_starts);
										if ($show_row_ends) $html .= " &ndash; ".date($row_time_format, $row_ends);
										if ($link_time_slots) $html = "<a href='$time_slot_link'>$html</a>";
										echo $html;
									}
								?>
							</td>
						
							<?php // Display session cells --------------------- ?>
							
							<?php $colspan = $column_type ? count($column_headers) : 1; ?>

							<?php if ($non_session) { // display a non-sessioned time slot ?>

								<td class="sessions" colspan="<?php echo $colspan; ?>">
									<p>
										<?php
											$html = get_the_title($time_slot_id);
											if ($link_time_slots) $html = "<a href='$time_slot_link'>$html</a>";
											echo $html;
										?>
									</p>
								</td>
								
							<?php } else if (isset($cells[-1])) { ?>
								
								<td class="sessions keynote-sessions" colspan="<?php echo $colspan; ?>">
									<?php
										foreach ($cells[-1] as $session) {
											$this->display_session($session);
										}
									?>
								</td>

							<?php } else if ($column_type) { // if split into columns, multiple cells  ?>

								<?php foreach ($cells as $cell_sessions) { ?>
									<td class="sessions <?php if (empty($cell_sessions)) echo 'no-sessions'; ?>">
										<?php
											foreach ($cell_sessions as $session) {
												$this->display_session($session);
											}
										?>
									</td>
								<?php } ?>

							<?php } else { // all sessions in one cell ?>
							
								<td class="sessions <?php if (empty($cells)) echo 'no-sessions'; ?>">
									<?php
										foreach ($cells as $session) {
											$this->display_session($session);
										}
									?>
								</td>
							
							<?php } ?>
						</tr>
					<?php } ?>
				</tbody>
			</table>
			
			<?php if ($tabs) { ?>
				 <!-- #conferencer_agenda_tab_xxx --> </div>
				</div> <!-- .conferencer_agenda_tabs -->
			<?php } ?>
	
		</div> <!-- .conferencer_agenda -->
	
		<?php
	
		// Retrieve and return buffer
	
		return ob_get_clean();
	}
	
	function display_headers($column_headers) { ?>
		<thead>
			<tr>
				<th class="column_time_slot"></th>
				<?php foreach ($column_headers as $column_header) { ?>
					<th class="<?php echo $column_header['class']; ?>">
						<?php
							$html = $column_header['title'];
							if ($column_header['link']) $html = "<a href='".$column_header['link']."'>$html</a>";
							echo $html;
						?>
					</th>
				<?php } ?>
			</tr>
		</thead>
	<?php }
	
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
		<div class="session <?php if ($session->track) echo "track-".Conferencer_BP_Addon::get_the_slug($session->track); ?>" group-id="<?php echo $group_id ;?>">
        <div class="generic-button group-button prog public" id="groupbutton-<?php echo $group_id ;?>" style="display:none">
        <?php //echo '<a id="group-' . esc_attr( $group_id ) . '" class="join-group" rel="join" title="' . __( 'Join Group', 'buddypress' ) . '" href="' . wp_nonce_url( trailingslashit( bp_get_root_domain() . '/' . bp_get_groups_root_slug() . '/' . groups_get_slug($group_id) . '/' ) . 'join', 'groups_join_group' ) . '">' . __( 'Join Group', 'buddypress' ) . '</a>';
		?></div>
            <?php $islive = get_post_meta($session->ID, 'conc_wp_live', true);
				if ($islive) echo '<div class="islive">LIVE STREAMED</div>'; ?>
			<?php echo do_shortcode("
				[session_meta
					post_id='$session->ID'
					show='title,speakers,room'
					speakers_prefix='with '
					room_prefix='in ',
					track_prefix='In theme ',
					link_title=".($link_sessions ? 'true' : 'false')."
					link_speakers=".($link_speakers ? 'true' : 'false')."
					link_room=".($link_rooms ? 'true' : 'false')."
				]
			");	?>
			<?php if ($session_tooltips) { ?>
				<div class="session-tooltip">
					<?php echo do_shortcode("
						[session_meta
							post_id='$session->ID'
							show='title,speakers,room,track',
							track_prefix='In theme ',
							link_all=false
						]
					"); ?>
					
					<p class="excerpt"><?php echo generate_excerpt($session); ?></p>
					<div class="arrow"></div><div class="inner-arrow"></div>
				</div>
			<?php } ?>
		
		</div>

	<?php }
	
}
