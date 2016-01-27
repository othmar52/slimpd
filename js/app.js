$(document).ready(function() {
    "use strict";
    
    var $ = window.jQuery;
    window.sliMpd = $.extend(true, window.sliMpd, {
        modules : {},
        
        /**
		 * adds get-paramter to url, respecting existing and not-existing params
		 * TODO: currently not compatible with urlstring that contains a #hash
		 * @param {string} urlstring
		 * @param {string} paramName
		 * @param {string} paramValue
		 * 
		 */
		setGetParameter : function(urlstring, paramName, paramValue) {
		    if (urlstring.indexOf(paramName + "=") >= 0)
		    {
		        var prefix = urlstring.substring(0, urlstring.indexOf(paramName));
		        var suffix = urlstring.substring(urlstring.indexOf(paramName));
		        suffix = suffix.substring(suffix.indexOf("=") + 1);
		        suffix = (suffix.indexOf("&") >= 0) ? suffix.substring(suffix.indexOf("&")) : "";
		        urlstring = prefix + paramName + "=" + paramValue + suffix;
		    }
		    else
		    {
		    if (urlstring.indexOf("?") < 0)
		        urlstring += "?" + paramName + "=" + paramValue;
		    else
		        urlstring += "&" + paramName + "=" + paramValue;
		    }
		    return urlstring;
		}
    });
    
    window.sliMpd.navbar = new window.sliMpd.modules.NavbarView({
    	el : 'nav.main-nav'
    });
    window.sliMpd.navbar.render();
    
   
    window.sliMpd.localPlayer = new window.sliMpd.modules.LocalPlayer({
    	el : '.permaplayer.player-local'
    });
    window.sliMpd.mpdPlayer = new window.sliMpd.modules.MpdPlayer({
    	el : '.permaplayer.player-mpd'
    });
    window.sliMpd.currentPlayer = window.sliMpd.localPlayer;
    
    
    window.sliMpd.router = new window.sliMpd.modules.Router();
    
    window.Backbone.history.start({
    	pushState : true
    });
    
	
	/* toggle between mpd-control and local player (jPlayer) */
	$('.playerModeToggle a').on('click', function(e) {
		e.preventDefault();
		if($(this).hasClass('active-mpd') === true) {
			$(this).addClass('active-local').removeClass('active-mpd').html($(this).attr('data-label-local'));
			window.sliMpd.currentPlayer = window.sliMpd.localPlayer;
		} else {
			$(this).addClass('active-mpd').removeClass('active-local').html($(this).attr('data-label-mpd'));
			window.sliMpd.currentPlayer = window.sliMpd.mpdPlayer;
			// pause local player when switching to mpd
			window.sliMpd.currentPlayer.setPlayPauseState('pause');
		}
		$.cookie("playerMode", window.sliMpd.currentPlayer.mode, { expires : 365, path: '/' });
		$('.player-local,.player-mpd').toggle();
		window.sliMpd.currentPlayer.drawFavicon();
	});
  
	//$("#jquery_jplayer_1").jPlayer("play");
    
    
});

$(document).ready(function(){
	return;
	
	$('body').on('click', '.ajax-btn, .ajax-link', function(){
		var ajaxTarget = $(this).attr('data-ajaxtarget');
		if(ajaxTarget) {
			// TODO: create proper currently loading visualizing
			$('<div class="modal-backdrop fade in" id="loading-backdrop"></div>').appendTo(document.body);
			$.ajax({
				url: setGetParameter($(this).attr('href'), 'nosurrounding', '1')
			}).done(function(response){
				$(ajaxTarget).html(response);
				$("#loading-backdrop").remove();
				return;
			});
		}
		var localObj = $(this).attr('data-localplayer');
		if(typeof localObj == 'undefined' || playerMode !== 'local') {
			$.ajax({
				url: $(this).attr('data-href')
			}).done(function(response){
				// TODO: notify or replace content
				refreshInterval();
			});
		} else {
			try{
		        var a = JSON.parse(localObj);
		        localPlayer(a);
		    }catch(e){
		    	console.log(e + ' in data-localplayer attribute');
		    }
		}
		return false;
	});
	
	/* toggle between mpd-control and local player (jPlayer) */
	  $('.playerModeToggle a').on('click', function(e) {
	  	e.preventDefault();
	  	if($(this).hasClass('active-mpd') === true) {
	  		$(this).addClass('active-local').removeClass('active-mpd').html($(this).attr('data-label-local'));
	  		playerMode = "local";
	  	} else {
	  		$(this).addClass('active-mpd').removeClass('active-local').html($(this).attr('data-label-mpd'));
	  		playerMode = "mpd";
	  		// pause local player when switching to mpd
	  		setPlayPauseState('pause');
	  	}
	  	$.cookie("playerMode", playerMode, { expires : 365, path: '/' });
	  	$('.player-local,.player-mpd').toggle();
	  	drawFavicon(false, false);
	  });
	  
	  $('#global-modal').on('click', '.playerModeToggleTrigger', function(e) {
	  	e.preventDefault();
	  	$('.playerModeToggle a').trigger('click');
	  });
	  
});
