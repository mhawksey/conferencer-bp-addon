
var jq = jQuery.noConflict();
var hashparts;

jq(document).on('click', 'div.group-button a', function() {
    var gid = jq(this).parent().attr('id');
    gid = gid.split('-');
    gid = gid[1];

    var nonce = jq(this).attr('href');
    nonce = nonce.split('?_wpnonce=');
    nonce = nonce[1].split('&');
    nonce = nonce[0];

    var thelink = jq(this);

    jq.post(ajaxurl, {
            action: 'joinleave_group',
            'cookie': bp_get_cookies(),
            'gid': gid,
            '_wpnonce': nonce
        },
        function(response) {
            //console.log(response);
            response = response.substr(0, response.length - 1);
            var parentdiv = thelink.parent();

            jq(parentdiv).fadeOut(200,
                function() {
                    parentdiv.html(response);

                    var but = parentdiv.children("a:first");
                    but.text('');
                    /*if (but.text() == "Join Session"){
                    	but.text('');
                    } else if (but.text() == "Leave Session"){
                    	but.text('');
                    }*/
                    parentdiv.fadeIn(200);
                }
            );
        });
    return false;
});

jq(function( $ ) {
    moveScroller();

    jq('.myicalfeed a').click(function(e) {
        e.preventDefault();
        jq("#myicalurlbox").slideToggle("slow");
    });
    var separator = '-';
    jq('.conferencer_tabs').each(function() {
        var tabs = jq('.tabs li', this);
        var allContent = jq([]);

        tabs.each(function() {
            var tab = jq(this);
            var content = jq(jq('a', this).attr('href'));
            allContent = jq(allContent).add(content);

            jq('a', tab).click(function(e) {
                var hashparts = jq.address.value().split('/');
                var targetHash = e.target.hash.replace(/^#/, '');
                hashparts[1] = e.target.hash.replace(/^#/, '')
                jq.address.value(hashparts.join('/'));
                tabs.removeClass('current');
                tab.addClass('current');
                checkRowVis();
                allContent.hide();
                content.show();

                return false;
            });
        });
    });

    if (jq.address.value() != '/') {
        hashparts = jq.address.value().split('/');
        var curTab = '#' + hashparts[1];
        jq('a[href="' + curTab + '"]').click();
    } else {
        //jq('.tabs > li > a:first').click();	
        jq('.tabs > li:nth-child(' + defaultDay + ') > a').click();
    }
    jq(window).bind('hashchange', function(e) {
        if (jq.address.value() != '/') {
            hashparts = jq.address.value().split('/');
            var curTab = '#' + hashparts[1];
            jq('a[href="' + curTab + '"]').click();
        } else {
            //jq('.tabs > li > a:first').click();
            jq('.tabs > li:nth-child(' + defaultDay + ') > a').click();
        }
    });

    function checkRowVis() {
        jq(".room_col").each(function() {
            if (jq(this).next().find(">div:visible").length == 0) {
                jq(this).hide();
            } else {
                jq(this).show();
            }
        });
    }

    jq(".track input:checkbox, .type input:checkbox").on('change', function() {
        var session_class = '.session.' + this.id;
        if (jq('.mysessions').find("img").length === 0) {
            jq(this).is(':checked') ? jq(session_class).slideDown(0, checkRowVis) : jq(session_class).slideUp(400, checkRowVis);
            jq(this).is(':checked') ? createCookie(this.id, true) : createCookie(this.id, false);
        } else {
            if (jq(this).is(':checked')) {
                if (jq(session_class).find('.leave-group').length) {
                    jq(session_class).find('.leave-group').parent().parent().slideDown(0, checkRowVis);
                }
            } else {
                jq(session_class).slideUp(400, checkRowVis);
            }
        }

        return false;
    });
    jq(".track input:checkbox, .type input:checkbox").each(function() {
        var chk = readCookie(this.id) === 'false' ? false : true;
        if (!chk) {
            jq(this).click()
        }
        //jq(this).prop('checked', chk);
    });
    jq(".session a.expander").on('click', function(e) {
        var tip = jq(this).parent().find(".session-ex-text");
        var chev = jq(this).find(".fa");
        if (!tip.is(":visible")) {
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


    // http://stackoverflow.com/a/5334231/1027723
    if (jq('body').hasClass('logged-in')) {
        var gids = [];
        jq(".group-button.prog").each(function() {
            gids.push(this.id.replace('groupbutton-', ''));
        });

        jq.post(ajaxurl, {
            action: 'get_joinleave_buttons_array',
            'gids': gids.toString(),
            cache: false,
        }, function(response) {
            if (response == 0) {
                jq(".generic-button.prog").hide();
                jq(".mysessions").text("Not logged in");
                return;
            };
            var data = JSON.parse(response);
            var ids = data.ids;
            jq.each(ids, function(key, val) {
                var sess = jq(".generic-button[id='groupbutton-" + key + "']");
                sess.html(val);
            });
            jq(".mysessions").html('<label for="allsession"><input id="allsession_box" name="sess" type="radio" checked/>All Sessions</label> <label for="mysession"><input id="mysession_box" type="radio" name="sess" />My Sessions</label>');
            jq(".mysessions input").on('click', function() {
                if (!jq("#allsession_box").is(':checked')) {
                    jq('.join-group').parent().parent().slideUp(400, checkRowVis);
                    window.location.hash = '/' + hashparts[1] + "/my";
                } else {
                    jq('.join-group').parent().parent().slideDown(0, checkRowVis);
                    jq('input:checkbox').change();
                    window.location.hash = '/' + hashparts[1] + "/all";
                }
            });

            if (data.uid) {
                jq("#myicalurl").val(data.site + '/?ical=feed&uid=' + data.uid);
                jq(".myicalfeed a").attr('href', data.site + '/?ical=feed&uid=' + data.uid);
                jq(".myicalfeed").show();

                jq(".myical a").attr('href', data.site + '/?ical=download&uid=' + data.uid);
                jq(".myical").show();
            }

            jq(".generic-button.prog").show();

            var hash = (hashparts[2] !== 'undefined') ? hashparts[2] : false;
            if (hash == "my") {
                jq('#mysession_box').click();
                jq('.join-group').parent().parent().slideUp(400, checkRowVis);
                window.location.hash = '/' + hashparts[1] + "/my";
            }
        })
    } else {
        jq(".generic-button.prog").hide();
        jq(".mysessions").text("Not logged in");
    }

});

// http://stackoverflow.com/a/2153775/1027723
function moveScroller() {
    var move = function() {
        var st = jq(window).scrollTop();
        var ts = jq(".ts");

        ts.each(function() {
            var tp = 0;
            var wpb = (jq('#wpadminbar:visible').length) ? jq('#wpadminbar').height() : 0;
            var s = jq(this).next();
            var ot = jq(this).offset().top;
            var oth = s.height();
            var cb = ot + jq(this).parent().height() + oth;

            if (jq(this).closest("tr").is(":last-child")) {
                cb = ot + jq(this).parent().height()
            }

            if (st > ot && st < cb) {
                var tp = 0;
                if (jq(this).closest("tr").is(":last-child") && st > cb - oth) {
                    tp = -(st - cb + oth);
                }
                s.css({
                    position: "fixed",
                    top: wpb + tp + "px"
                });
            } else {
                if (st <= ot || st > cb) {
                    s.css({
                        position: "relative",
                        top: ""
                    });
                }
            }
        });
    };
    jq(window).scroll(move);
    move();
}
/* Returns a querystring of BP cookies (cookies beginning with 'bp-') */
function bp_get_cookies() {
    // get all cookies and split into an array
    var allCookies = document.cookie.split(";");

    var bpCookies = {};
    var cookiePrefix = 'bp-';

    // loop through cookies
    for (var i = 0; i < allCookies.length; i++) {
        var cookie = allCookies[i];
        var delimiter = cookie.indexOf("=");
        var name = jq.trim(unescape(cookie.slice(0, delimiter)));
        var value = unescape(cookie.slice(delimiter + 1));

        // if BP cookie, store it
        if (name.indexOf(cookiePrefix) == 0) {
            bpCookies[name] = value;
        }
    }

    // returns BP cookies as querystring
    return encodeURIComponent(jq.param(bpCookies));
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