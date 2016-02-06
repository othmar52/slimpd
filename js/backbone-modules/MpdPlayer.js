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
        faviconDoghnutColor : 'rgb(255,156,1)',
        faviconBackgroundColor : '#444',
        
        timeGridSelectorCanvas : 'timegrid-mpd',
		timeGridSelectorSeekbar : '.mpd-ctrl-seekbar',
		timeGridStrokeColor : '#7B6137',
		timeGridStrokeColor2 : '#FCC772',
		
		intervalActive : 2000,
		intervalInactive : 5000,
		timeLineLight : null,

        initialize : function(options) {
        	this.$content = $('.player-'+ this.mode, this.$el);
        	
        	this.trackAnimation = { currentPosPerc: 0 };
        	this.timeLineLight = new TimelineLite();
        	
        	this.poll();
            window.sliMpd.modules.AbstractPlayer.prototype.initialize.call(this, options);
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
			window.sliMpd.drawFavicon();
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
        	// TODO: do we really need to append '?null' to routename comparison?
        	window.sliMpd.router.refreshIfName('playlist?null');
        	window.sliMpd.modules.AbstractPlayer.prototype.next.call(this, item);
        },
        
        remove : function(item) {
        	$.get(item.mpdurl);
        	// TODO: check current route and refresh in case we are on current mpd-playlist
        	window.sliMpd.modules.AbstractPlayer.prototype.remove.call(this, item);
        },
        
        toggleRepeat : function(item) {
        	$.get(item.mpdurl);
        	//this.refreshInterval();
        	window.sliMpd.modules.AbstractPlayer.prototype.toggleRepeat.call(this, item);
        },
        
        toggleRandom : function(item) {
        	$.get(item.mpdurl);
        	//this.refreshInterval();
        	window.sliMpd.modules.AbstractPlayer.prototype.toggleRandom.call(this, item);
        },
        
        toggleConsume : function(item) {
        	$.get(item.mpdurl);
        	//this.refreshInterval();
        	window.sliMpd.modules.AbstractPlayer.prototype.toggleConsume.call(this, item);
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
					
					// TODO: interpolate nowPlayingElapsed independent frpm poll interval
			    	$('.mpd-status-elapsed').text(that.formatTime(that.nowPlayingElapsed));
			    	$('.mpd-status-total').text(that.formatTime(that.nowPlayingDuration));
			    	
			    	// animate from 0 to 100, onUpdate -> change Text
					that.timeLineLight = new TimelineLite();
					that.trackAnimation.currentPosPerc = 0;
					that.timeLineLight.to(that.trackAnimation, that.nowPlayingDuration, {
					  	currentPosPerc: 100, 
					  	ease: Linear.easeNone,  
					  	onUpdate: that.updateSlider,
					  	onUpdateScope: that
					});
			    	
			    	if(that.nowPlayingState == 'play') {
			    		that.timelineSetValue(that.nowPlayingPercent);
					} else {
			    		that.timeLineLight.pause();
			    	}
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
				// TODO: how to respect parents padding (15px) on absolute positioned div with width 100% ?
				var percent = Math.round((e.pageX - $(this).offset().left) / (($(this).width()+15)/100));
				$('.mpd-status-progressbar', that.$el).css('width', 'calc('+ percent+'% - 15px)');
				that.process({'action': 'seek', 'mpdurl' : '/mpdctrl/seekPercent/' + percent});
				that.timelineSetValue(percent);
			});
			$.notify({
				// options
				message: 'MPD playing: ' + $('.player-mpd .now-playing-string').text()
			},{
				// settings
				type: 'info'
			});
			$.notify();
        	window.sliMpd.modules.AbstractPlayer.prototype.onRedrawComplete.call(this, item);
        },
        
        // TODO: make markup more generic and move this to AbstractPlayer for usage in both players (local+mpd)
        setPlayPauseIcon : function(item) {
			if(this.nowPlayingState == 'play') {
				$('.mpd-status-playpause', this.$el).addClass('fa-pause');
				$('.mpd-status-playpause', this.$el).removeClass('fa-play');
			} else {
				$('.mpd-status-playpause', this.$el).removeClass('fa-pause');
				$('.mpd-status-playpause', this.$el).addClass('fa-play');
			}
			window.sliMpd.modules.AbstractPlayer.prototype.setPlayPauseIcon.call(this, item);
		},
		
		formatTime : function(seconds) {
			if(typeof seconds == 'undefined') {
				return '-- : --';
			}
			var seconds 	= Math.round(seconds);
			var hour 		= Math.floor(seconds / 3600);
			var minutes 	= Math.floor(seconds / 60) % 60;
			seconds 		= seconds % 60;
				
			if (hour > 0)	return hour + ':' + this.zeroPad(minutes, 2) + ':' + this.zeroPad(seconds, 2);
			else			return minutes + ':' + this.zeroPad(seconds, 2);
		},
		
		zeroPad : function(number, n) {
			var zeroPad = '' + number;
			while(zeroPad.length < n) {
				zeroPad = '0' + zeroPad;
			}
			return zeroPad;
		},
		
		timelineSetValue : function(value) {
			this.timeLineLight.progress(value/100);
			window.sliMpd.modules.AbstractPlayer.prototype.timelineSetValue.call(this, value);
		},
		
		updateSlider : function(item) {
			// TODO: how to respect parents padding on absolute positioned div with width 100% ?
			$('.mpd-status-progressbar').css('width', 'calc('+ this.timeLineLight.progress() *100 +'% - 15px)');
			window.sliMpd.modules.AbstractPlayer.prototype.updateSlider.call(this, item);
		}
    });
    
})();
