var jq = jQuery, hashparts;

            
jq(document).on('click', 'div.group-button a', function() {
			var gid = jq(this).parent().attr('id');
			gid = gid.split('-');
			gid = gid[1];
			
			var nonce = jq(this).attr('href');
			nonce = nonce.split('?_wpnonce=');
			nonce = nonce[1].split('&');
			nonce = nonce[0];
			
			var thelink = jq(this);

			jq.post( ajaxurl, {
				action: 'joinleave_group',
				'cookie': bp_get_cookies(),
				'gid': gid,
				'_wpnonce': nonce
			},
			function(response)
			{
				//console.log(response);
				response = response.substr(0, response.length-1);
				var parentdiv = thelink.parent();

				jq(parentdiv).fadeOut(200, 
					function() {
						parentdiv.html(response);
						
						var but = parentdiv.children("a:first");
						if (but.text() == "Join Session"){
							but.text('Follow Session');
						} else if (but.text() == "Leave Session"){
							but.text('Unfollow Session');
						}
						parentdiv.fadeIn(200);
					}
				);
			});
			return false;
		}
	);
	
jq( document ).ready(function() {
	jq('.myicalfeed a').click(function(e) {
		e.preventDefault();
		jq( "#myicalurlbox" ).slideToggle( "slow" );
	});
	var separator = '-';
	jq('.conferencer_tabs').each(function() {
		var tabs = $('.tabs li', this);
		var allContent = $([]);
		
		tabs.each(function() {
			var tab = $(this);
			var content = $($('a', this).attr('href'));
			allContent = $(allContent).add(content);
			
			$('a', tab).click(function(e) {
				$.address.value(e.target.hash.replace(/^#/, '').replace(separator, '/'));
				tabs.removeClass('current');
				tab.addClass('current');
				
				allContent.hide();
				content.show();
				
				return false;
			});
		});
	});
	
	if ($.address.value() != '/') {
		hashparts = $.address.value().split('/');
		var curTab = '#'+hashparts[1];
		$('a[href="'+curTab+'"]').click();
    } else {
		$('.tabs > li > a:first').click();	
	}
	$(window).bind('hashchange', function( e ) {
		if ($.address.value() != '/') {
			hashparts = $.address.value().split('/');
			var curTab = '#'+hashparts[1];
			$('a[href="'+curTab+'"]').click();
		} else {
			$('.tabs > li > a:first').click();	
		}
	});
	jq("#allsession_box, #mysession_box").on('click', function () {
		if (jq("#allsession_box").is(':checked')) {
            jq('.join-group').parent().parent().slideUp();
			window.location.hash = '/'+hashparts[1]+"/my";
        }
        else {
            jq('.join-group').parent().parent().slideDown();
			jq('input:checkbox').change();
			window.location.hash = '/'+hashparts[1]+"/all";
        }
	});
	//jq('.session a').address();  
	/*jq('.mysessions').on('click', function() {
		var checkbox = jq(this).find(':checkbox');
		checkbox.each(function(){
		   jq(this).prop('checked', !checkbox[0].checked);
		});
	*/
	
		/*
		if (jq(this).find('#allsession_box').is(':checked')){
			jq('#allsession_box').prop('checked',false);
			jq('#mysession_box').prop('checked',true);
			jq('.join-group').parent().parent().slideUp();
			window.location.hash = '/'+hashparts[1]+"/my";
		} else if (jq(this).find('#mysession_box').is(':checked')){
			jq('#allsession_box').prop('checked',true);
			jq('#mysession_box').prop('checked',false);
			jq('.join-group').parent().parent().slideDown();
			jq('input:checkbox').change();
			window.location.hash = '/'+hashparts[1]+"/all";
		}*/
	//});

	jq(".track input:checkbox, .type input:checkbox").on('change', function() {
		var session_class = '.session.' +  this.id;   
		var sessionState = jq('.mysessions').text();
		if (sessionState == 'My Sessions' || sessionState == 'Not logged in'){
			jq(this).is(':checked') ? jq(session_class).slideDown() : jq(session_class).slideUp();
			jq(this).is(':checked') ? createCookie(this.id, true) : createCookie(this.id, false);
		} else {
			if(jq(this).is(':checked') ){
				if (jq(session_class).find('.leave-group').length){
					jq(session_class).find('.leave-group').parent().parent().slideDown();
				}
			} else {
				jq(session_class).slideUp();
			}
		}
		return false;
	});
	jq(".track input:checkbox, .type input:checkbox").each(function(){
		var chk = readCookie(this.id) === 'false' ? false : true;
		if (!chk) {
			jq(this).click()	
		}
		//jq(this).prop('checked', chk);
	});
	jq(".session a.expander").on('click touchstart', function(e){
		var tip = jq(this).parent().find(".session-ex-text");
		var chev = jq(this).find(".fa");
		if (!tip.is(":visible")){
			jq(".expander .fa").removeClass('fa-chevron-up');
			jq(".expander .fa").addClass('fa-chevron-down');
			jq(".session-ex-text").slideUp();
			jq(this).find(".fa-chevron-down").removeClass('fa-chevron-down').addClass('fa-chevron-up');
			tip.slideDown(); 
		} else {
			jq(this).find(".fa-chevron-up").removeClass('fa-chevron-up').addClass('fa-chevron-down');
			tip.slideUp(); 
		}	
	});
	/*jq('.check').toggle(function(){
        jq('.'+jq(this).attr("id")+' input:checkbox').removeAttr('checked');
		jq('input:checkbox').change();
        jq(this).text('Check All'); 
    },function(){
		jq('.'+jq(this).attr("id")+' input:checkbox').attr('checked','checked');
		jq('input:checkbox').change();
        jq(this).text('Uncheck All');       
    });*/

	
	// http://stackoverflow.com/a/5334231/1027723
	var gids = [];
	jq(".group-button.prog").each(function(){
	   gids.push(this.id.replace('groupbutton-','')); 
	});
	
	jq.post( ajaxurl, {
		action: 'get_joinleave_buttons_array',
		'gids': gids.toString(),
		cache: false,
		},function(response){
			if (response== 0 ){
				jq(".generic-button.prog").hide();
				jq(".mysessions").text("Not logged in");
				return;	
			};
			var data = JSON.parse(response);
			var ids = data.ids;
			jq.each(ids, function(key, val) {
				var sess = jq(".generic-button[id='groupbutton-"+key+"']");
				sess.html(val);
			});
			jq(".mysessions").html('<label for="allsession"><input id="allsession_box" name="sess" type="radio" checked/>All Sessions <label for="mysession"><input id="mysession_box" type="radio" name="sess" />My Sessions</label>');
			if (data.uid){
				jq("#myicalurl").val(data.uid);
				jq(".myicalfeed a").attr('href', data.uid);
				jq(".myicalfeed").show();
			}
			
			jq(".generic-button.prog, .myical").show();	
			var hash = (hashparts[2] !== 'undefined' ) ? hashparts[2] : false;
			if (hash == "my"){
				jq('.mysessions').click();
			}
		})
		
});

/*function sessionToggle(){
	jq('.mysessions').toggle(function(){
		jq('.group-button.join-group').parent().parent().slideUp();
        //jq(this).find('a').text('All Sessions'); 
		window.location.hash = "myb"
		//return false;
    },function(){
		jq('.group-button.join-group').parent().parent().slideDown();
		jq('input:checkbox').change();
        //jq(this).find('a').text('My Sessions'); 
		window.location.hash = "all"
		//return false;      
    });	
}*/
/* Returns a querystring of BP cookies (cookies beginning with 'bp-') */
function bp_get_cookies() {
	// get all cookies and split into an array
	var allCookies   = document.cookie.split(";");

	var bpCookies    = {};
	var cookiePrefix = 'bp-';

	// loop through cookies
	for (var i = 0; i < allCookies.length; i++) {
		var cookie    = allCookies[i];
		var delimiter = cookie.indexOf("=");
		var name      = jq.trim( unescape( cookie.slice(0, delimiter) ) );
		var value     = unescape( cookie.slice(delimiter + 1) );

		// if BP cookie, store it
		if ( name.indexOf(cookiePrefix) == 0 ) {
			bpCookies[name] = value;
		}
	}

	// returns BP cookies as querystring
	return encodeURIComponent( jq.param(bpCookies) );
}
// http://stackoverflow.com/a/1460174/1027723
function createCookie(name, value, days) {
    var expires;

    if (days) {
        var date = new Date();
        date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
        expires = "; expires=" + date.toGMTString();
    } else {
        expires = "";
    }
    document.cookie = escape(name) + "=" + escape(value) + expires + "; path=/";
}

function readCookie(name) {
    var nameEQ = escape(name) + "=";
    var ca = document.cookie.split(';');
    for (var i = 0; i < ca.length; i++) {
        var c = ca[i];
        while (c.charAt(0) === ' ') c = c.substring(1, c.length);
        if (c.indexOf(nameEQ) === 0) return unescape(c.substring(nameEQ.length, c.length));
    }
    return null;
}

function eraseCookie(name) {
    createCookie(name, "", -1);
}