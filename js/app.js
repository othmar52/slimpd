$.fn.random = function() {
  return this.eq(Math.floor(Math.random() * this.length));
}

Array.prototype.max = function() {
  return Math.max.apply(null, this);
};

$(document).ready(function() {
    "use strict";
    
    var $ = window.jQuery;
    window.sliMpd = $.extend(true, window.sliMpd, {
        modules : {},
        
        drawFaviconTimeout : 0,
        
        xwax : false,
        
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
		
		// TODO: respect playersize + visible xwax gui for positioning
		notify : function(notifyConf) {
    		$.notify({
				// options
				message: notifyConf.message
			},{
				// settings
				type: (notifyConf.type || 'info'),
				z_index: 10000,
				offset: {
					x: '10',
					y: '110'
				},
				placement: {
					from: "bottom",
					align: "right"
				},
			});
		},
		
		notifyError : function(errorUrl) {
			// TODO: get message from language file
    		this.notify({
				message : "<h4>OOOPS!</h4> something went wrong...<br /><a class=\"alert-link\" target=\"_blank\" href=\""+ errorUrl+"\">" + errorUrl + "</a>",
				type : "danger"
			});
		},

		/* toggle between mpd-control and local player (jPlayer) */
		togglePlayer : function() {
			var perspective = -1100;
			var origin = '50% 50%';
			var ease = Power2.easeInOut;
			var speed = 0.5;
			var classToRemove = window.sliMpd.conf.color.mpd.bodyclass;
			var classToAdd = window.sliMpd.conf.color.local.bodyclass;

			var transformPreviousPlayerFrom = {
				transformOrigin: origin,
				transformPerspective: perspective,
				display: 'block',
				rotationX: 0,
				y: 0,
				ease: ease
			}
			var transformPreviousPlayerTo = {
				rotationX: 90,
				y: 50,
				opacity: 0,
				ease: ease
			}
			var transformNewPlayerFrom = {
				transformOrigin: origin,
				transformPerspective: perspective,
				display: 'block',
				rotationX: -90,
				y: -50
			}
			var transformNewPlayerTo = {
				rotationX: 0,
				y:0,
				ease: ease,
				opacity: 1
			}

			$('.player-local,.player-mpd').removeClass('hidden');

			if(window.sliMpd.currentPlayer.mode === 'mpd') {
				// activate local player
				window.sliMpd.currentPlayer = window.sliMpd.localPlayer;

				// reduce poll amount of inactive mpd player
				window.sliMpd.mpdPlayer.pollWorker.postMessage({
					cmd: 'setMiliseconds',
					value: window.sliMpd.mpdPlayer.intervalInactive
				});

				// flip animation for both players
				TweenLite.fromTo($('.player-mpd'), speed, transformPreviousPlayerFrom, transformPreviousPlayerTo);
				TweenLite.fromTo($('.player-local'), speed, transformNewPlayerFrom, transformNewPlayerTo);
			} else {
				// pause local player when switching to mpd
				window.sliMpd.currentPlayer.process({'action':'pause'});

				// activate mpd player
				window.sliMpd.currentPlayer = window.sliMpd.mpdPlayer;

				// increase poll amount as mpd player is now active
				window.sliMpd.mpdPlayer.pollWorker.postMessage({
					cmd: 'setMiliseconds',
					value: window.sliMpd.mpdPlayer.intervalActive
				});
				window.sliMpd.currentPlayer.refreshInterval();

				// flip animation for both players
				TweenLite.fromTo($('.player-local'), speed, transformPreviousPlayerFrom, transformPreviousPlayerTo);
				TweenLite.fromTo($('.player-mpd'), speed, transformNewPlayerFrom, transformNewPlayerTo);

				classToRemove = window.sliMpd.conf.color.local.bodyclass;
				classToAdd = window.sliMpd.conf.color.mpd.bodyclass;
			}

			// change body-class for colorizing all links
			$('body').addClass(classToAdd).removeClass(classToRemove);

			$.cookie("playerMode", window.sliMpd.currentPlayer.mode, { expires : 365, path: '/' });
			window.sliMpd.drawFavicon();
			window.sliMpd.currentPlayer.drawWaveform();
		} 
    });
    
    window.sliMpd.navbar = new window.sliMpd.modules.NavbarView({
    	el : 'nav.main-nav'
    });
    window.sliMpd.navbar.render();
    
    window.sliMpd.xwax = new window.sliMpd.modules.XwaxView({
		el : '.player-xwax',
		showWaveform : true
    });
    window.sliMpd.xwax.render();
    
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
	
	/* toggle playersize */
	$('.playerSizeToggle a').on('click', function(e) {
		e.preventDefault();
		$('body').toggleClass('slimplayer');
		$(this).find('i').toggleClass('fa-chevron-down').toggleClass('fa-chevron-up');
		window.sliMpd.currentPlayer.drawWaveform();
	});

	/* toggle between display tags and display filepath */
	$('.fileModeToggle a').on('click', function(e) {
		e.preventDefault();
		$('body').toggleClass('ffn');
		$(this).find('i').toggleClass('fa-toggle-off').toggleClass('fa-toggle-on');
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
	
	
	$('.navbar-upper').affix({
  		offset: {bottom: 200}
	});
	
	NProgress.configure({
		showSpinner: false,
		parent: '#nprog-container',
		speed: 100,
		trickleRate: 0.02,
		trickleSpeed: 800
	});
	
	
	// TODO: is it correct to place this here (excluded from all bootstrap-views)?
	$(function(){
	    $('#top-link-block').removeClass('hidden').affix({
	        // how far to scroll down before link "slides" into view
	        offset: {top:100}
	    });
	});
	$('#top-link-block a').on('click', function(e) {
		e.preventDefault();
		$('html,body').animate({scrollTop:0},'fast');
		return false;
	});

	/*
	 * force confirmation when user leaves sliMpd in case local audio is playing
	 * as the browser is not displaying the text there is no nedd to fetch string from language file
	 */
	window.onbeforeunload=function(){
		if(window.sliMpd.currentPlayer.mode === 'local' && window.sliMpd.currentPlayer.nowPlayingState === 'play') {
			return 'local audio is currently playing';
		}
	}

	/*
	 * add lazy resize listener
	 */
	$(window).bind('resizeEnd', function() {
		window.sliMpd.currentPlayer.drawWaveform();
		window.sliMpd.currentPlayer.drawTimeGrid();
	});
	$(window).resize(function() {
		if(this.resizeTO) clearTimeout(this.resizeTO);
		this.resizeTO = setTimeout(function() {
			$(this).trigger('resizeEnd');
		}, 500);
	});
});


