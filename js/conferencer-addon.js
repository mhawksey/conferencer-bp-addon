// JavaScript Document
jQuery(document).ready(function($) {
	// $("html, body").animate({ scrollTop: $('#item-nav').offset().top }, 1000);
	if ($('#item-meta .excerpt').length>0 && !$('.youtube').length){
		$('.content').hide();
	}
	if ($('.youtube').length){
		$('.excerpt, .read-less').hide();
	}
	$('a.read').click(function () {
		$(this).parent('.excerpt').hide();
		$(this).closest('#item-meta').find('.content').slideDown('fast');
		return false;
	});
	$('a.read-less').click(function () {
		$(this).parent('.content').slideUp('fast');
		$(this).closest('#item-meta').find('.excerpt').show();
		return false;
	});
	$('#group-settings-form #group-name, #group-settings-form #group-desc').attr('disabled', 'disabled');
	$('#group-settings-form #delete-group-understand').parent().hide();
	$('.delete-group #message p').text('Deleting sessions has been disabled. Use the Sessions menu in WP-Admin instead');

});


// JavaScript Document
jQuery(document).ready(function($) {
	var instructions = "If you would like us to include your blog posts in the 'Reader' enter your blog address and <a id='searchForRSS' href='javascript:void(0)'>click here</a> and select the correct 'Blog RSS' from below. Any blog posts that you publish with #altc in the post title or body will automatically be included in the Reader.";
	var blog_field = $('.field_blog input[type=text]');
	var newText = $('<legend id="bloginstruc" style="width:75%">'+instructions+'</legend>').insertAfter(blog_field);	
	$('#searchForRSS').on("click", function(event){
		$('.field_blog-rss input[type=text]').after("<img src='/wp-admin/images/loading.gif' id='loadingFeeds'/>");
		$.post(
			ajaxurl, 
			{
				'action': 'xprofile_detect_blog_rss',
				'blog': blog_field.val(),
				'id': $('.field_blog-rss input[type=text]').attr('id')
			}, 
			function(response){
				$("#other_feed").remove();
				
				$('.field_blog-rss input[type=text]').replaceWith(response);
				$("#loadingFeeds").hide();
				// http://stackoverflow.com/a/5426112/1027723
				if ($('.field_blog-rss').find('select').children('option').length > 1){
					$("#other_feed").hide();
				}
				$('.field_blog-rss').find('select').change(function() {
				  if($(this).find('option:selected').val() == "Other"){
					$("#other_feed").show();
				  }else{
					$("#other_feed").hide();
				  }
				});
				$("#other_feed").keyup(function(ev){
					var othersOption = $('.field_blog-rss').find('option:selected');
					if(othersOption.val() == "Other")
					{
						ev.preventDefault();
						//change the selected drop down text
						$(othersOption).html($("#other_feed").val()); 
					} 
				});
				$('#profile-edit-form').submit(function() {
					var othersOption = $('.field_blog-rss').find('option:selected');
					if(othersOption.val() == "Other")
					{
						// replace select value with text field value
						var field = $('.field_blog-rss').find('select');
						var field_id = field.attr('id');
						field.remove();
						$("#other_feed").attr("id", field_id).attr("name", field_id);
					}
				});
			}
		);
	});
});

/* Rewrite of 
Plugin Name: RS Buddypress Activity Refresh
PLugin URI: http://buddypress.org/community/groups/rs-buddypress-activity-refresh/
Description: This plugin automatically refresh the Buddypress activity stream
Author: Florian Koenig-Heidinger
Requires at least: 2.9.1 / 1.2.4
Tested up to: 3.8 / 1.8.1
Tags: buddypress
Version: 1.8
Author URI: http://buddypress.org/community/members/Spitzohr/
*/
var	new_activities = 0;
var original_page_title = '';
var rsBpActivityRefreshTimeout;

function rsBpAtivityRefresh_getLastId()
{
	// get highest ID for the request
	last_id = 0;
	jQuery('ul.activity-list').find('li').each(function()
	{
		objectId = jQuery(this).attr('id');
		if (objectId)
		{
			objectName = objectId.substring(0, objectId.indexOf('-'));
			objectNumber = parseInt(objectId.substring(objectId.indexOf('-') + 1));
			if ('acomment' == objectName || 'activity' == objectName)
			{
				if (last_id  < objectNumber)
				{
					last_id = objectNumber;
				}
			}
		}
	});
	return last_id;	
}

function rsBpAtivityRefresh_loadNewActivities()
{
	scope = jQuery.cookie( 'bp-activity-scope');
	filter = jQuery.cookie( 'bp-activity-filter');
	last_id = rsBpAtivityRefresh_getLastId();

	jQuery.post( ajaxurl,
	{
		action: 'rs_bp_activity_refresh',
		'last_id': last_id,
		'scope': scope,
		'filter': filter
	},
	function(response)
	{

		// Check for errors and append if found.
		if ( response[0] + response[1] != '-1' && response.length > 0)
		{
			// add response to hidden field
			jQuery('#rs-hidden-response').html(response);

			// reset last_insert_id
			last_insert_li = false;

			// check each list item
			jQuery('#rs-hidden-response').children('li').each(function()
			{
				objectId = jQuery(this).attr('id');
				objectId = objectId.substring(objectId.indexOf('-') + 1);
				if ((jQuery('ul.activity-list #activity-' + objectId).attr('id') == undefined)
					&& (jQuery('ul.activity-list #acomment-' + objectId).attr('id') == undefined)
				)
				{ // add new item
					if (last_insert_li)
					{
						jQuery(last_insert_li).after(this);
					}
					else
					{
						jQuery('ul.activity-list').prepend(this);
					}
					jQuery(this).addClass('new-update').hide().slideDown( 300 );
					last_insert_li = this;
					new_activities++;
					document.title = original_page_title + ' (' + new_activities + ')';
				}
			});

			// clear hidden field
			jQuery('#rs-hidden-response').html('');
		}
	});
}

function rsBpAtivityRefresh_automaticRefresh()
{
	rsBpAtivityRefresh_loadNewActivities();
	if (rsBpActivityRefreshTimeago)
	{
		jQuery('span.timeago').timeago();
	}
	// reset time and start function again
	rsBpActivityRefreshTimeout = setTimeout( function(){rsBpAtivityRefresh_automaticRefresh(); } , rsBpActivityRefreshRate * 1000 );
	//setTimeout( 'rsBpAtivityRefresh_automaticRefresh();' , rsBpActivityRefreshRate * 1000 );
}

jQuery(document).ready(function()
{
	original_page_title = document.title;
	if (jQuery('ul.activity-list').length > 0 || jQuery('body').hasClass('groups') && !jQuery('body').hasClass('activity-permalink'))
	{
		// create hidden field
		jQuery('body').append('<div id="rs-hidden-response" style="display: none;"></div>');
		
		
		jQuery('#activity-filter-select').prev().after('<li id="activity-refresh" style="float:right"><div class="activity-refresh-item"><a href="" id="activity-refresh-button">Auto-Refresh: Off</a></div></li>');
		
		$('#activity-refresh-button').toggle(function() {
			$(this).attr('class', 'active').text('Auto-Refresh: On');
			rsBpActivityRefreshTimeout = setTimeout( function(){rsBpAtivityRefresh_automaticRefresh(); } , rsBpActivityRefreshRate * 1000 );
			return false;
		}, function() {
			$(this).attr('class', 'inactive').text('Auto-Refresh: Off');
			clearTimeout(rsBpActivityRefreshTimeout);
			return false;
		});

		if (typeof rsBpActivityRefreshRate == "undefined")
		{
			rsBpActivityRefreshRate = 10;
		}
		if (rsBpActivityRefreshTimeago)
		{
			jQuery('span.timeago').timeago();
		}
		// start refreshing
		//var rsBpActivityRefreshTimeout = setTimeout( function(){rsBpAtivityRefresh_automaticRefresh(); } , rsBpActivityRefreshRate * 1000 );
	}

	jQuery('div.activity-type-tabs').click( function(event) {
		document.title = original_page_title;
	});
});