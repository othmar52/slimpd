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
        faviconDoghnutColor : window.sliMpd.conf.color.mpd.favicon,
        faviconBackgroundColor : '#444',
        
        timeGridSelectorCanvas : 'timegrid-mpd',
		timeGridSelectorSeekbar : '.mpd-ctrl-seekbar',
		timeGridStrokeColor : window.sliMpd.conf.color.mpd.secondary,
		timeGridStrokeColor2 : window.sliMpd.conf.color.mpd.primary,
		
		state : {
        	repeat : 0,
        	random : 0,
        	consume : 0
        },
		
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
			window.sliMpd.fireRequestAndNotify(item.mpdurl);
			this.redraw();
    		//this.reloadCss(item.hash);
    		window.sliMpd.modules.AbstractPlayer.prototype.play.call(this, item);
        },
        
        togglePause : function(item) {
			if(this.nowPlayingState == 'play') {
				window.sliMpd.fireRequestAndNotify('/mpdctrl/pause');
				this.nowPlayingState = 'pause';
			} else {
				window.sliMpd.fireRequestAndNotify('/mpdctrl/play');
				this.nowPlayingState = 'play';
			}
			window.sliMpd.modules.AbstractPlayer.prototype.togglePause.call(this, item);
			this.setPlayPauseIcon(item);
			window.sliMpd.drawFavicon();
		},
		
        seek : function(item) {
			window.sliMpd.fireRequestAndNotify(item.mpdurl);
			window.sliMpd.modules.AbstractPlayer.prototype.seek.call(this, item);
        },
        
        seekzero : function(item) {
			window.sliMpd.fireRequestAndNotify('/mpdctrl/seekPercent/0');
			this.timelineSetValue(0);
			window.sliMpd.modules.AbstractPlayer.prototype.seekzero.call(this, item);
        },

        prev : function(item) {
        	window.sliMpd.fireRequestAndNotify(item.mpdurl);
        	this.refreshInterval();
        	window.sliMpd.modules.AbstractPlayer.prototype.prev.call(this, item);
        },
        
        next : function(item) {
        	window.sliMpd.fireRequestAndNotify(item.mpdurl);
        	this.refreshInterval();
        	// TODO: do we really need to append '?null' to routename comparison?
        	window.sliMpd.router.refreshIfName('playlist?null');
        	window.sliMpd.modules.AbstractPlayer.prototype.next.call(this, item);
        },
        
        remove : function(item) {
        	window.sliMpd.fireRequestAndNotify(item.mpdurl);
        	// TODO: check current route and refresh in case we are on current mpd-playlist
        	window.sliMpd.modules.AbstractPlayer.prototype.remove.call(this, item);
        },
        
        toggleRepeat : function(item) {
        	window.sliMpd.fireRequestAndNotify(item.mpdurl);
        	window.sliMpd.modules.AbstractPlayer.prototype.toggleRepeat.call(this, item);
        },
        
        toggleRandom : function(item) {
        	window.sliMpd.fireRequestAndNotify(item.mpdurl);
        	window.sliMpd.modules.AbstractPlayer.prototype.toggleRandom.call(this, item);
        },
        
        toggleConsume : function(item) {
        	window.sliMpd.fireRequestAndNotify(item.mpdurl);
        	window.sliMpd.modules.AbstractPlayer.prototype.toggleConsume.call(this, item);
        },
        
        softclearPlaylist : function(item) {
        	window.sliMpd.fireRequestAndNotify(item.mpdurl);
        	// TODO: check current route and refresh in case we are on current mpd-playlist
        	window.sliMpd.modules.AbstractPlayer.prototype.softclearPlaylist.call(this, item);
        },

        
        
		// TODO: check current route and refresh in case we are on current mpd-playlist
		
		appendTrack : function(item) {
			window.sliMpd.fireRequestAndNotify(item.mpdurl);
			window.sliMpd.modules.AbstractPlayer.prototype.appendTrack.call(this, item);
		},
		appendTrackAndPlay : function(item) {
			window.sliMpd.fireRequestAndNotify(item.mpdurl);
			window.sliMpd.modules.AbstractPlayer.prototype.appendTrackAndPlay.call(this, item);
		},
		
		injectTrack : function(item) {
			window.sliMpd.fireRequestAndNotify(item.mpdurl);
			window.sliMpd.modules.AbstractPlayer.prototype.injectTrack.call(this, item);
		},
		
		injectTrackAndPlay : function(item) {
			window.sliMpd.fireRequestAndNotify(item.mpdurl);
			window.sliMpd.modules.AbstractPlayer.prototype.injectTrackAndPlay.call(this, item);
		},
		
		replaceTrack : function(item) {
			window.sliMpd.fireRequestAndNotify(item.mpdurl);
			window.sliMpd.modules.AbstractPlayer.prototype.replaceTrack.call(this, item);
		},
		
		softreplaceTrack : function(item) {
			window.sliMpd.fireRequestAndNotify(item.mpdurl);
			window.sliMpd.modules.AbstractPlayer.prototype.softreplaceTrack.call(this, item);
		},
		
				
		appendDir : function(item) {
			window.sliMpd.fireRequestAndNotify(item.mpdurl);
			window.sliMpd.modules.AbstractPlayer.prototype.appendDir.call(this, item);
		},
		
		appendDirAndPlay : function(item) {
			window.sliMpd.fireRequestAndNotify(item.mpdurl);
			window.sliMpd.modules.AbstractPlayer.prototype.appendDirAndPlay.call(this, item);
		},
		
		injectDir : function(item) {
			window.sliMpd.fireRequestAndNotify(item.mpdurl);
			window.sliMpd.modules.AbstractPlayer.prototype.injectDir.call(this, item);
		},
		
		injectDirAndPlay : function(item) {
			window.sliMpd.fireRequestAndNotify(item.mpdurl);
			window.sliMpd.modules.AbstractPlayer.prototype.injectDirAndPlay.call(this, item);
		},
		
		replaceDir : function(item) {
			window.sliMpd.fireRequestAndNotify(item.mpdurl);
			window.sliMpd.modules.AbstractPlayer.prototype.replaceDir.call(this, item);
		},
		
		softreplaceDir : function(item) {
			window.sliMpd.fireRequestAndNotify(item.mpdurl);
			window.sliMpd.modules.AbstractPlayer.prototype.softreplaceDir.call(this, item);
		},
		
				
		appendPlaylist : function(item) {
			window.sliMpd.fireRequestAndNotify(item.mpdurl);
			window.sliMpd.modules.AbstractPlayer.prototype.appendPlaylist.call(this, item);
		},
		
		appendPlaylistAndPlay : function(item) {
			window.sliMpd.fireRequestAndNotify(item.mpdurl);
			window.sliMpd.modules.AbstractPlayer.prototype.appendPlaylistAndPlay.call(this, item);
		},
		
		injectPlaylist : function(item) {
			window.sliMpd.fireRequestAndNotify(item.mpdurl);
			window.sliMpd.modules.AbstractPlayer.prototype.injectPlaylist.call(this, item);
		},
		
		injectPlaylistAndPlay : function(item) {
			window.sliMpd.fireRequestAndNotify(item.mpdurl);
			window.sliMpd.modules.AbstractPlayer.prototype.injectPlaylistAndPlay.call(this, item);
		},
		
		replacePlaylist : function(item) {
			window.sliMpd.fireRequestAndNotify(item.mpdurl);
			window.sliMpd.modules.AbstractPlayer.prototype.replacePlaylist.call(this, item);
		},
		
		softreplacePlaylist : function(item) {
			window.sliMpd.fireRequestAndNotify(item.mpdurl);
			window.sliMpd.modules.AbstractPlayer.prototype.softreplacePlaylist.call(this, item);
		},
	




        
        
        
        
        removeDupes : function(item) {
        	window.sliMpd.fireRequestAndNotify(item.mpdurl);
        	// TODO: check current route and refresh in case we are on current mpd-playlist
        	window.sliMpd.modules.AbstractPlayer.prototype.removeDupes.call(this, item);
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
		    	
		    	that.state.repeat = data.repeat;
		    	that.state.random = data.random;
		    	that.state.consume = data.consume;
		    	
		    	// no need to update this stuff in case local player is active...
		    	if(window.sliMpd.currentPlayer.mode === 'mpd') {
	
			    	that.updateStateIcons();
					
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
			    	that.drawTimeGrid();
		    	}
		    	
		    	// update trackinfo only onTrackChange()
		    	if(that.previousPlayingItem != that.nowPlayingItem) {
		    		that.previousPlayingItem = that.nowPlayingItem
		    		that.redraw(''); 
		    		that.refreshInterval();
		    		return;
		    	}
		        that.poller = setTimeout(
					that.poll,
					((window.sliMpd.currentPlayer.mode === 'mpd')
						? that.intervalActive
						: that.intervalInactive
					)
				);
			});
		},
		
        onRedrawComplete : function(item) {
        	var that = this;
        	$('.mpd-ctrl-seekbar').on('click', function(e){
				var percent = Math.round((e.pageX - $(this).offset().left) / ($(this).width()/100));
				$('.mpd-status-progressbar', that.$el).css('width', percent+'%');
				that.process({'action': 'seek', 'mpdurl' : '/mpdctrl/seekPercent/' + percent});
				that.timelineSetValue(percent);
			});
			$('.mpd-ctrl-seekzero', this.$el).on('click', function(e){
				that.seekzero();
			});

			var mpdNotify = $('<div/>')
				.append(
					$('<div/>')
					.attr('class', 'row')
					.append(
						$('<div/>')
						.attr('class', 'col-md-2')
						.append(
							$('<img/>')
								.attr('src', $('.player-mpd img').attr('src'))
								.attr('width', '70')
						)
					)
					.append(
						$('<div/>')
						.attr('class', 'col-md-10')
						.append(
							'<span class="uc dark small">MPD trackchange</span><br>' + $('.player-mpd .now-playing-string').text()
						)
					)
				);
			window.sliMpd.notify({
				'notify':1,
				'type': 'mpd',
				'message': $(mpdNotify).html()
			});
        	window.sliMpd.modules.AbstractPlayer.prototype.onRedrawComplete.call(this, item);
        },
        
        updateStateIcons : function() {
        	window.sliMpd.modules.AbstractPlayer.prototype.updateStateIcons.call(this);
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
			$('.mpd-status-progressbar').css('width', this.timeLineLight.progress() *100 +'%');
			window.sliMpd.modules.AbstractPlayer.prototype.updateSlider.call(this, item);
		}
    });
    
})();
