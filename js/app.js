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
    
	window.sliMpd.modal = new window.sliMpd.modules.ModalView({
    	el : '#global-modal .modal-content'
    });
    
    window.sliMpd.localPlayer = new window.sliMpd.modules.LocalPlayer({
    	el : '.permaplayer.player-local'
    });
    window.sliMpd.mpdPlayer = new window.sliMpd.modules.MpdPlayer({
    	el : '.permaplayer.player-mpd'
    });
    
    if($.cookie("playerMode") === 'mpd') {
    	window.sliMpd.currentPlayer = window.sliMpd.mpdPlayer;
    } else {
    	window.sliMpd.currentPlayer = window.sliMpd.localPlayer;
    }
    
    
    window.sliMpd.router = new window.sliMpd.modules.Router();
    
    window.Backbone.history.start({
    	pushState : true
    });
    
	
	/* toggle between mpd-control and local player (jPlayer) */
	$('.playerModeToggle a').on('click', function(e) {
		e.preventDefault();
		if(window.sliMpd.currentPlayer.mode === 'mpd') {
			$(this).addClass('active-local').removeClass('active-mpd').html($(this).attr('data-label-local'));
			window.sliMpd.currentPlayer = window.sliMpd.localPlayer;
		} else {
			$(this).addClass('active-mpd').removeClass('active-local').html($(this).attr('data-label-mpd'));
			// pause local player when switching to mpd
			window.sliMpd.currentPlayer.process({'action':'pause'});
			window.sliMpd.currentPlayer = window.sliMpd.mpdPlayer;
			window.sliMpd.currentPlayer.refreshInterval();
		}
		$.cookie("playerMode", window.sliMpd.currentPlayer.mode, { expires : 365, path: '/' });
		$('.player-local,.player-mpd').toggle();
		//window.sliMpd.currentPlayer.drawFavicon();
	});    
});