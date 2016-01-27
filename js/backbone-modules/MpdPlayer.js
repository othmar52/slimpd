/*
 * dependencies: jquery, backbonejs, underscorejs
 */
(function() {
    "use strict";
    
    var $ = window.jQuery,
        _ = window._;
    $.extend(true, window.sliMpd, {
        modules : {}
    });
    window.sliMpd.modules.MpdPlayer = window.sliMpd.modules.AbstractPlayer.extend({

        mode : 'mpd',
        doghnutColor : '#278DBA', // used in drawFavicon()
        
        selectorCanvas : 'timegrid-mpd',		// used in drawTimeGrid()
		selectorSeekbar : 'mpd-ctrl-seekbar', 	// used in drawTimeGrid()
		strokeColor : '#7B6137', 				// used in drawTimeGrid()
		strokeColor2 : '#FCC772',				// used in drawTimeGrid()

        initialize : function(options) {
            window.Backbone.View.prototype.initialize.call(this, options);
        },

        render : function(options) {
            window.Backbone.View.prototype.initialize.call(this, options);
        },
        
        play : function(item) {
        	console.log(this.mode + 'Player:play()');
        	console.log(item);
			
    		this.reloadCss(item.hash);
       },
       
       setPlayPauseState : function(what) {
			var player = $("#jquery_jplayer_1");
			var control = $('.localplayer-play-pause');
			if(what == 'play') {
				$(player).jPlayer( "play");
				$(control).addClass('localplayer-pause').removeClass('localplayer-play').html('<i class="fa fa-pause sign-ctrl fa-lg"></i>');
			} else {
				$(player).jPlayer( "pause");
				$(control).addClass('localplayer-play').removeClass('localplayer-pause').html('<i class="fa fa-play sign-ctrl fa-lg"></i>');
			}
			//drawFavicon(false, false);
			return false;
		},
		
		formatTime : function(seconds) {
			var seconds 	= Math.round(seconds);
			var hour 		= Math.floor(seconds / 3600);
			var minutes 	= Math.floor(seconds / 60) % 60;
			seconds 		= seconds % 60;
				
			if (hour > 0)	return hour + ':' + this.zeroPad(minutes, 2) + ':' + this.zeroPad(seconds, 2);
			else			return minutes + ':' + this.zeroPad(seconds, 2);
		},
		
		zeroPad : function(number, n) {
			var zeroPad = '' + number;
			while(zeroPad.length < n)
				zeroPad = '0' + zeroPad; 
			
			return zeroPad;
		}
        
    });
    
})();
