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
    window.sliMpd.modules.LocalPlayer = window.sliMpd.modules.AbstractPlayer.extend({
        mode : 'local',
        playerSelector : '#jquery_jplayer_1',
        
        faviconDoghnutColor : window.sliMpd.conf.color.local.favicon,
        faviconBackgroundColor : '#444',
        
        timeGridSelectorCanvas : 'timegrid-local',
		timeGridSelectorSeekbar : '.jp-seek-bar',
		timeGridStrokeColor : window.sliMpd.conf.color.local.secondary,
		timeGridStrokeColor2 : window.sliMpd.conf.color.local.primary,
		
		state : {
        	repeat : 1,
        	random : 1,
        	consume : 0
        },
		
		// TODO: remove property as soon as local player has full functionality
		tempNotSupportedYetNotify : {"message": "not supported in <strong>local player</strong> yet - use <strong>mpd</strong>", "type": "danger"},

        initialize : function(options) {
        	var that = this;
        	
		    /* init local player */
			$(this.playerSelector).jPlayer({
		        cssSelectorAncestor: "#jp_container_1",
		        swfPath: "/vendor/happyworm/jplayer/dist/jplayer", // TODO: get & prefix with conf.absRefPrefix
		        supplied: "mp3",
		        useStateClassSkin: false,
		        autoBlur: false,
		        smoothPlayBar: true,
		        keyEnabled: true,
		        remainingDuration: false,
		        toggleDuration: true,
		        ended: function() {
		            that.soundEnded({});
		        },
		        progress: function(e,data){
		        	//console.log($(this).data('jPlayer').status);
		        	var jStatus = $(this).data('jPlayer').status;
		        	that.nowPlayingPercent = jStatus.currentPercentAbsolute;
        			that.nowPlayingState = (jStatus.paused===false && jStatus.currentTime > 0) ? 'play' : 'pause';
       				that.nowPlayingDuration = jStatus.duration;
					that.nowPlayingElapsed = jStatus.currentTime;
					//that.nowPlayingItem = jStatus.src;
				  	
		        	// TODO: check why jPlayer event 'loadedmetadata' sometimes has no duration (timegrid fails to render)
		        	// draw the timegrid only once as soon as we know the total duration and remove the progress eventListener
		        	// @see: http://jplayer.org/latest/developer-guide/#jPlayer-events
				  	that.drawTimeGrid();
				},
				seeked: function() {
					window.sliMpd.drawFavicon();
				}
			});
			// TODO: why does toggleRandom(), toggleRepeat(), toggleConsume() not work on initial load???
			this.updateStateIcons();
            window.sliMpd.modules.AbstractPlayer.prototype.initialize.call(this, options);
        },

        render : function(options) {
            window.sliMpd.modules.AbstractPlayer.prototype.render.call(this, options);
        },
        
        play : function(item) {
			// TODO: check why item.ext is sometimes 'vorbis' instead of 'ogg' 			
			item.ext = (item.ext == 'vorbis') ? 'ogg' : item.ext;
			
			// WARNING: jPlayer's essential Audio formats are: mp3 or m4a.
			// wav, flac, ogg, m4a plays fine in chromium under linux but we have to add an unused mp3-property...
			// TODO: really provide alternative urls instead of adding an invalid url for mp3
			$(this.playerSelector).jPlayer(
				'setMedia',
				{
					[item.ext] : '/deliver/' + item.item,
					'mp3' : '/deliver/' + item.item,
					'supplied': item.ext + ',mp3'
				}
			).jPlayer( "play");
			this.nowPlayingItem = item.hash;
			this.redraw(item);
    		//this.reloadCss(item.hash);
		},
		
		prev : function(item) {
        	window.sliMpd.notify(this.tempNotSupportedYetNotify);
        	window.sliMpd.modules.AbstractPlayer.prototype.prev.call(this, item);
        },
        
        next : function(item) {
			this.soundEnded(item);
        	window.sliMpd.modules.AbstractPlayer.prototype.next.call(this, item);
        },
		
		redraw : function(item) {
			window.sliMpd.modules.AbstractPlayer.prototype.redraw.call(this, item);
		},
		
		onRedrawComplete : function(item) {
			// re-bind controls(seeek-bar) on ajax loaded control-markup
    		$(this.playerSelector).jPlayer({cssSelectorAncestor: "#jp_container_1"});
    		this.updateStateIcons();
			window.sliMpd.modules.AbstractPlayer.prototype.onRedrawComplete.call(this, item);
		},
		
        updateStateIcons : function() {
        	window.sliMpd.modules.AbstractPlayer.prototype.updateStateIcons.call(this);
        },
		
		// TODO: make markup more generic and move this to AbstractPlayer
		setPlayPauseIcon : function(item) {
			var control = $('.localplayer-play-pause');
			if (this.nowPlayingState == 'play') {
				$(control).addClass('localplayer-pause').removeClass('localplayer-play').html('<i class="fa fa-pause sign-ctrl fa-lg"></i>');
			} else {
				$(control).addClass('localplayer-play').removeClass('localplayer-pause').html('<i class="fa fa-play sign-ctrl fa-lg"></i>');
			}
			window.sliMpd.drawFavicon();
			window.sliMpd.modules.AbstractPlayer.prototype.setPlayPauseIcon.call(this, item);
		},
		
		pause : function(item) {
			$(this.playerSelector).jPlayer('pause');
			this.nowPlayingState = 'pause';
			window.sliMpd.modules.AbstractPlayer.prototype.pause.call(this, item);
			this.setPlayPauseIcon(item);
		},
		
		togglePause : function(item) {
			if (this.nowPlayingState == 'play') {
				$(this.playerSelector).jPlayer('pause');
				this.nowPlayingState = 'pause';
			} else {
				$(this.playerSelector).jPlayer('play');
				this.nowPlayingState = 'play';
			}
			window.sliMpd.modules.AbstractPlayer.prototype.togglePause.call(this, item);
			this.setPlayPauseIcon(item);
		},
		
		toggleRepeat : function(item) {
			this.state.repeat = (this.state.repeat === 1) ? 0 : 1;
			this.updateStateIcons(); 
			window.sliMpd.notify(this.tempNotSupportedYetNotify);
        	window.sliMpd.modules.AbstractPlayer.prototype.toggleRepeat.call(this, item);
        },
        
        toggleRandom : function(item) {
        	this.state.random = (this.state.random === 1) ? 0 : 1;
			this.updateStateIcons(); 
        	window.sliMpd.modules.AbstractPlayer.prototype.toggleRandom.call(this, item);
        },
        
        toggleConsume : function(item) {
        	this.state.consume = (this.state.consume === 1) ? 0 : 1;
			this.updateStateIcons();
			window.sliMpd.notify(this.tempNotSupportedYetNotify);
        	window.sliMpd.modules.AbstractPlayer.prototype.toggleConsume.call(this, item);
        },
		
		soundEnded : function(item) {
			// TODO: add functionality "current playlist" (like mpd) for local player 
			// for now use all rendered tracks as playlist
			
			//console.log('local soundEnded()');
			if(this.state.random == 1) {
				//console.log('local random is active');
				$("#main .track-row:not(.track-"+ this.nowPlayingItem+")").random().find('.is-playbtn').click();
			} else {
				//console.log('local random is NOT active');
				// check if current track is rendered
				var current = $('.track-' + this.nowPlayingItem);
				if(current.length) {
					//console.log('current track is rendered');
					var next = current.nextAll('.track-row').find('.is-playbtn');
					if(next.length) {
						//console.log('found next track');
						next[0].click();
					} else {
						//console.log('we have no next track. fallback to first rendered track...');
						$('#main .is-playbtn')[0].click();
					}
					
				} else {
					//console.log('current track is not rendered. fallback to first rendered track...');
					$('#main .is-playbtn')[0].click();
				}
			}
		},
		
		clearPlaylistNotCurrent : function(item) {
        	window.sliMpd.notify(this.tempNotSupportedYetNotify);
        	window.sliMpd.modules.AbstractPlayer.prototype.clearPlaylistNotCurrent.call(this, item);
        },
        
        addDirToPlaylist : function(item) {
        	window.sliMpd.notify(this.tempNotSupportedYetNotify);
        	window.sliMpd.modules.AbstractPlayer.prototype.addDirToPlaylist.call(this, item);
        },
        
        addPlaylistToPlaylist : function(item) {
        	window.sliMpd.notify(this.tempNotSupportedYetNotify);
        	window.sliMpd.modules.AbstractPlayer.prototype.addPlaylistToPlaylist.call(this, item);
        },
        
        replaceCurrentPlaylist : function(item) {
        	window.sliMpd.notify(this.tempNotSupportedYetNotify);
        	window.sliMpd.modules.AbstractPlayer.prototype.replaceCurrentPlaylist.call(this, item);
        },
        
        replaceCurrentPlaylistKeepTrack : function(item) {
        	window.sliMpd.notify(this.tempNotSupportedYetNotify);
        	window.sliMpd.modules.AbstractPlayer.prototype.replaceCurrentPlaylistKeepTrack.call(this, item);
        },
        
        removeDupes : function(item) {
        	window.sliMpd.notify(this.tempNotSupportedYetNotify);
        	window.sliMpd.modules.AbstractPlayer.prototype.removeDupes.call(this, item);
        }
        
    });
    
})();

