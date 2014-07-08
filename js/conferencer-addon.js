// JavaScript Document
jQuery(document).ready(function($) {
	// $("html, body").animate({ scrollTop: $('#item-nav').offset().top }, 1000);
	if ($('#item-meta .excerpt').length>0){
		$('.content').hide();
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
	 $('p:contains("WARNING")').text('Deleting sessions has been disabled. Use the Sessions menu in WP-Admin instead');
});