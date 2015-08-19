// JavaScript Document
jQuery(document).ready(function($) {
	// $("html, body").animate({ scrollTop: $('#item-nav').offset().top }, 1000);
	/*if ($('#item-meta .excerpt').length>0 && !$('.youtube').length){
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
	});*/
	$('#group-settings-form #group-name, #group-settings-form #group-desc').attr('disabled', 'disabled');
	$('#group-settings-form #delete-group-understand').parent().hide();
	$('.delete-group #message p').text('Deleting sessions has been disabled. Use the Sessions menu in WP-Admin instead');
	
	$('#search-which > option:nth-child(2)').text( "Sessions");

});

// JavaScript Document
jQuery(document).ready(function(jq) {
	jq(".session-abstract a.expander").on('click touchstart', function(e){
		var tip = jq(this).parent().find(".session-ex-text");
		var chev = jq(this).find(".fa");
		if (jq( this ).find('.fa').hasClass( "fa-chevron-up" )){
			jq(".expander .fa").removeClass('fa-chevron-up');
			jq(".expander .fa").addClass('fa-chevron-down');
			jq(".abstract-text").addClass('excerpt').removeClass('excerpt-off');
			jq("#item-header-content")[0].scrollIntoView();
			//tip.slideDown(); 
		} else {
			jq(".expander .fa").addClass('fa-chevron-up');
			jq(".expander .fa").removeClass('fa-chevron-down');
			jq('.abstract-text').removeClass('excerpt').addClass('excerpt-off');
			//tip.slideUp(); 
		}	
	});
});

// JavaScript Document
jQuery(document).ready(function($) {
	$('.widget_conferencer_sponsors_widget .sponsors').fadeshow();
	
	var instructions = "If you would like us to include your blog posts in the 'Reader' enter your blog address and <a id='searchForRSS' href='javascript:void(0)'>click here</a> and select the correct 'Blog RSS' from below.";
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
(function($) {
	$.fn.fadeshow = function(opts) {
		var options = {
			interval: 5000,
			fadespeed: 1000
		};
		
		if (opts) $.extend(options, opts);
		
		return this.each(function() {
			var container = $(this);
			var slides = $(container).children();
			//console.log(container.parent().height());

			/*var height = 0;
			$.each(slides, function() {
				height = Math.max(height, $(this).height());
			});
			*/
			var height = container.parent().height()-20;
			$.each(slides, function() {
				/*$(this).css({
					'margin-top': Math.floor((height - $(this).find('img').height())/2) + 'px'
				});*/
				//console.log($(this).find('img:first').height());
			})
			
			//container.height(height);
			slides.first().css({ opacity: 1 }).addClass('active');
			if (slides.length <= 1) return;
			
			setInterval(function() {
				active = $('.active', container);
				next = active.next().length ? active.next() : slides.first();
				
				$(active).animate({ opacity: 0 }, options.fadespeed, function() {
					$(this).removeClass('active');
				});
				
				$(next).animate({ opacity: 1 }, options.fadespeed, function() {
					$(this).addClass('active');
				});
			}, options.interval);
		});
	};
})(jQuery);
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
/*var	new_activities = 0;
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
		var rsBpActivityRefreshRate = 10;
		var rsBpActivityRefreshTimeago = true;
		jQuery.timeago.settings.refreshMillis = 0;
		
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
		
		// create hidden field
		jQuery('body').append('<div id="rs-hidden-response" style="display: none;"></div>');
		
		
		jQuery('#activity-filter-select').prev().after('<li id="activity-refresh" style="float:right"><div class="activity-refresh-item"><a href="" id="activity-refresh-button">Auto-Refresh: Off</a></div></li>');
		
		jQuery('#activity-refresh-button').toggle(function() {
			jQuery(this).attr('class', 'active').text('Auto-Refresh: On');
			rsBpActivityRefreshTimeout = setTimeout( function(){rsBpAtivityRefresh_automaticRefresh(); } , rsBpActivityRefreshRate * 1000 );
			return false;
		}, function() {
			jQuery(this).attr('class', 'inactive').text('Auto-Refresh: Off');
			clearTimeout(rsBpActivityRefreshTimeout);
			return false;
		});

		
	}

	jQuery('div.activity-type-tabs').click( function(event) {
		document.title = original_page_title;
	});
});*/