$.fn.random = function() {
  return this.eq(Math.floor(Math.random() * this.length));
}

$(document).ready(function() {
    "use strict";
    
    var $ = window.jQuery;
    window.sliMpd = $.extend(true, window.sliMpd, {
        modules : {},
        
        drawFaviconTimeout : 0,
        
        /**
		 * adds get-paramter to url, respecting existing and not-existing params
		 * TODO: currently not compatible with urlstring that contains a #hash
		 * @param {string} urlstring
		 * @param {string} paramName
		 * @param {string} paramValue
		 */
		setGetParameter : function(urlstring, paramName, paramValue) {
		    if (urlstring.indexOf(paramName + "=") >= 0) {
		        var prefix = urlstring.substring(0, urlstring.indexOf(paramName));
		        var suffix = urlstring.substring(urlstring.indexOf(paramName));
		        suffix = suffix.substring(suffix.indexOf("=") + 1);
		        suffix = (suffix.indexOf("&") >= 0) ? suffix.substring(suffix.indexOf("&")) : "";
		        urlstring = prefix + paramName + "=" + paramValue + suffix;
		    } else {
			    urlstring += (urlstring.indexOf("?") < 0)
			        ? "?" + paramName + "=" + paramValue
			    	: "&" + paramName + "=" + paramValue;
		    }
		    return urlstring;
		},
		
		drawFavicon : function() {
			clearTimeout(window.sliMpd.drawFaviconTimeout);
			window.sliMpd.currentPlayer.drawFavicon();
			window.sliMpd.drawFaviconTimeout = setTimeout(window.sliMpd.drawFavicon, 2000);
		},
		
		fireRequestAndNotify : function(url) {
			$.get(url).done(function(response) {
				window.sliMpd.checkNotify(response);
			});
		},
		
		checkNotify : function(endcodedResponse) {
			try {
	        	var notifyConf = JSON.parse(endcodedResponse);
	        	if (typeof notifyConf.notify !== 'undefined') {
	        		this.notify(notifyConf);
	        	}
		    } catch(e) {
		    	//console.log(e + ' no json response in SliMpd::checkNotify()');
			}
		},
		
		notify : function(notifyConf) {
			////////////////////////////////////////////////
			// FIXME:
			// bootstrap-notify.min.js:1 Uncaught TypeError: Cannot read property 'title' of undefined
			// check notify's-template variable for title
			//////////////////////////////////////////////// 
    		$.notify({
				// options
				message: notifyConf.message
			},{
				// settings
				type: (notifyConf.type || 'info')
			});
			$.notify();
		},
		
		notifyError : function(errorUrl) {
    		this.notify({
				message : "<h4>OOOPS!</h4> something went wrong...<br /><a class=\"alert-link\" target=\"_blank\" href=\""+ errorUrl+"\">" + errorUrl + "</a>",
				type : "danger"
			});
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
    
    window.sliMpd.currentPlayer = ($.cookie("playerMode") === 'mpd')
		? window.sliMpd.mpdPlayer
    	: window.sliMpd.localPlayer;
    
    
    window.sliMpd.router = new window.sliMpd.modules.Router();
    
    window.Backbone.history.start({
    	pushState : true
    });
    
	window.sliMpd.drawFavicon();
	
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
		// TODO: reload all rendered images in frontend to get the correct color of fallback images
		window.sliMpd.drawFavicon();
	}); 
	
	// delegate calls to data-toggle="lightbox"
	$(document).delegate('*[data-toggle="lightbox"]', 'click', function(event) {
		event.preventDefault();
		return $(this).ekkoLightbox({
			always_show_close: true,
			gallery_parent_selector: 'body'
		});
	});
	
	$(document).on('keydown', null, 'ctrl+space', function(){
		// FIXME: this does not work with open autocomplete-widget. obviously ac overrides key bindings
		$('#mainsearch').focus().select();
		return false;
	});
});