// JavaScript Document
jQuery(document).ready(function(jq) {
	jq(".abstract-text a.expander").on('click touchstart', function(e){
		var tip = jq(this).parent().find(".session-ex-text");
		var chev = jq(this).find(".fa");
		if (jq( this ).hasClass( "fa-chevron-up" )){
			jq(".expander .fa").removeClass('fa-chevron-up');
			jq(".expander .fa").addClass('fa-chevron-down');
			//jq(".session-ex-text").slideUp();
			jq(this).find(".fa-chevron-down").removeClass('fa-chevron-down').addClass('fa-chevron-up');
			//tip.slideDown(); 
		} else {
			jq(this).find(".fa-chevron-up").removeClass('fa-chevron-up').addClass('fa-chevron-down');
			//tip.slideUp(); 
		}	
	});
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
});