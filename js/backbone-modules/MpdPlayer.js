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
        	this.$content = $('.player-'+ this.mode, this.$el);
            window.sliMpd.modules.AbstractPlayer.prototype.initialize.call(this, options);
            this.poll();
        },

        render : function(options) {
            window.sliMpd.modules.AbstractPlayer.prototype.render.call(this, options);
        },
        
        play : function(item) {
			$.get(item.mpdurl);
			this.redraw();
    		//this.reloadCss(item.hash);
    		window.sliMpd.modules.AbstractPlayer.prototype.play.call(this, item);
        },
        togglePause : function(item) {
			if(this.nowPlayingState == 'play') {
				$.get('/mpdctrl/pause');
				this.nowPlayingState = 'pause';
			} else {
				$.get('/mpdctrl/play');
				this.nowPlayingState = 'play';
			}
			window.sliMpd.modules.AbstractPlayer.prototype.togglePause.call(this, item);
			this.setPlayPauseIcon(item);
		},
        seek : function(item) {
			$.get(item.mpdurl);
			window.sliMpd.modules.AbstractPlayer.prototype.seek.call(this, item);
        },
        prev : function(item) {
        	$.get(item.mpdurl);
        	this.refreshInterval();
        	window.sliMpd.modules.AbstractPlayer.prototype.prev.call(this, item);
        },
        next : function(item) {
        	$.get(item.mpdurl);
        	this.refreshInterval();
        	window.sliMpd.modules.AbstractPlayer.prototype.next.call(this, item);
        },
        remove : function(item) {
        	$.get(item.mpdurl);
        	// TODO: check current route and refresh in case we are on current mpd-playlist
        	window.sliMpd.modules.AbstractPlayer.prototype.remove.call(this, item);
        },
        clearPlaylistNotCurrent : function(item) {
        	$.get(item.mpdurl);
        	// TODO: check current route and refresh in case we are on current mpd-playlist
        	window.sliMpd.modules.AbstractPlayer.prototype.clearPlaylistNotCurrent.call(this, item);
        },
        addDirToPlaylist : function(item) {
        	$.get(item.mpdurl);
        	// TODO: check current route and refresh in case we are on current mpd-playlist
        	window.sliMpd.modules.AbstractPlayer.prototype.clearPlaylistNotCurrent.call(this, item);
        },
        process : function(item) {
        	window.sliMpd.modules.AbstractPlayer.prototype.process.call(this, item);
        },
        
        // IMPORTANT TODO: how to avoid growing memory consumption on those frequent poll-requests?
		poll : function (){
			var that = this;
		    $.get('/mpdstatus', function(data) {
		    	data = JSON.parse(data);
		    	
		    	that.nowPlayingPercent = data.percent;
        		that.nowPlayingState = data.state;
        		that.nowPlayingDuration = data.duration;
        		that.nowPlayingElapsed = data.elapsed;
		    	that.nowPlayingItem = data.songid;
		    	
		    	that.stateRepeat = data.repeat;
		    	that.stateRandom = data.random;
		    	that.stateConsume = data.consume;
		    	
		    	
		    	// no need to update this stuff in case local player is active...
		    	if(window.sliMpd.currentPlayer.mode === 'mpd') {
	
			    	['repeat', 'random', 'consume'].forEach(function(prop) {
					    if(data[prop] == '1') {
			    		$('.mpd-status-'+prop, that.$el).addClass('active');
				    	} else {
				    		$('.mpd-status-'+prop, that.$el).removeClass('active');
				    	}
					});
					
					that.setPlayPauseIcon();
					
			    	$('.mpd-status-elapsed').text(that.formatTime(that.nowPlayingElapsed));
			    	$('.mpd-status-total').text(that.formatTime(that.nowPlayingDuration));
			    	
			    	// TODO: simulate/interpolate seamless progressbar-growth and seamless secondscounter
			    	// TODO: how to respect parents padding on absolute positioned div with width 100% ?
			    	$('.mpd-status-progressbar').css('width', 'calc('+ that.nowPlayingPercent+'% - 15px)');
			    	
		    	}
		    	
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
        	var that = this;
        	$('.mpd-ctrl-seekbar').on('click', function(e){
        		console.log('clickedi click');
				// TODO: how to respect parents padding (15px) on absolute positioned div with width 100% ?
				var percent = Math.round((e.pageX - $(this).offset().left) / (($(this).width()+15)/100));
				$('.mpd-status-progressbar', that.$el).css('width', 'calc('+ percent+'% - 15px)');
				that.process({'action': 'seek', 'mpdurl' : '/mpdctrl/seekPercent/' + percent});
			});
			$('a.ajax-link', this.$el).on('click', this.genericClickListener);
            $('.player-ctrl', this.$el).on('click', this.playerCtrlClickListener);
        	window.sliMpd.modules.AbstractPlayer.prototype.onRedrawComplete.call(this, item);
        },
        
        // TODO: make markup more generic and move this to AbstractPlayer
        setPlayPauseIcon : function(item) {
			if(this.nowPlayingState == 'play') {
				$('.mpd-status-playpause', this.$el).addClass('fa-pause');
				$('.mpd-status-playpause', this.$el).removeClass('fa-play');
			} else {
				$('.mpd-status-playpause', this.$el).removeClass('fa-pause');
				$('.mpd-status-playpause', this.$el).addClass('fa-play');
			}
			this.drawFavicon();
			window.sliMpd.modules.AbstractPlayer.prototype.setPlayPauseIcon.call(this, item);
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
