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
		
		intervalActive : 2000,
		intervalInactive : 5000,

        initialize : function(options) {
            window.sliMpd.modules.AbstractPlayer.prototype.initialize.call(this, options);
            this.poll();
        },

        render : function(options) {
            window.sliMpd.modules.AbstractPlayer.prototype.render.call(this, options);
        },
        
        play : function(item) {
        	console.log(this.mode + 'Player:play()');
        	console.log(item);
			$.get(item.mpdurl);
			this.redraw();
    		//this.reloadCss(item.hash);
        },
        
        // IMPORTANT TODO: how to avoid growing memory consumption on those frequent poll-requests?
		poll : function (){
			var that = this;
		    $.get('/mpdstatus', function(data) {
		    	var data = JSON.parse(data);
		    	
		    	that.nowPlayingPercent = data.percent;
        		that.nowPlayingState = data.state;
        		that.nowPlayingDuration = data.duration;
        		that.nowPlayingElapsed = data.elapsed;
		    	that.nowPlayingItem = data.songid;
		    	
		    	that.stateRepeat = data.repeat;
		    	that.stateRandom = data.random;
		    	that.stateConsume = data.consume;
		    	

		    	['repeat', 'random', 'consume'].forEach(function(prop) {
				    if(data[prop] == '1') {
		    		$('.mpd-status-'+prop).addClass('active');
			    	} else {
			    		$('.mpd-status-'+prop).removeClass('active');
			    	}
				});
				
				
				// TODO: find out why this snippet does not work
				//if(data.state !== 'play' && $('.mpd-status-playpause').hasClass('fa-pause')) {
				//	$('.mpd-status-playpause').toggleClass('fa-pause fa-play');
				//}
				
				if(this.nowPlayingState == 'play') {
					$('.mpd-status-playpause').addClass('fa-pause');
					$('.mpd-status-playpause').removeClass('fa-play');
				} else {
					$('.mpd-status-playpause').removeClass('fa-pause');
					$('.mpd-status-playpause').addClass('fa-play');
				}
				
		    	$('.mpd-status-elapsed').text(that.formatTime(that.nowPlayingElapsed));
		    	$('.mpd-status-total').text(that.formatTime(that.nowPlayingDuration));
		    	
		    	// TODO: simulate seamless progressbar-growth and seamless secondscounter
		    	// TODO: how to respect parents padding on absolute positioned div with width 100% ?
		    	$('.mpd-status-progressbar').css('width', 'calc('+ that.nowPlayingPercent+'% - 15px)');
		    	
		    	
		    	that.drawFavicon();
		
		
		    	
		    	// update trackinfo only onTrackChange()
		    	if(that.previousPlayingItem != that.nowPlayingItem) {
		    		that.previousPlayingItem = that.nowPlayingItem
		    		that.redraw(''); 
		    		that.refreshInterval();
		    		return;
		    	}
		        that.poller = setTimeout(that.poll, ((window.sliMpd.currentPlayer.mode === 'mpd') ? that.intervalActive:that.intervalInactive));
		    });
		},
		
        onRedrawComplete : function(item) {
        	//this.refreshInterval(); // TODO: why is this doubling our interval?
        	window.sliMpd.modules.AbstractPlayer.prototype.onRedrawComplete.call(this, item);
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
