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
        
        doghnutColor : 'rgb(45,146,56)',	// used in drawFavicon()
        
        selectorCanvas : 'timegrid-local', 	// used in drawTimeGrid()
		selectorSeekbar : 'jp-seek-bar', 	// used in drawTimeGrid()
		strokeColor : '#157720', 			// used in drawTimeGrid()
		strokeColor2 : '#2DFF45', 			// used in drawTimeGrid()

        initialize : function(options) {
        	var that = this;
        	this.$content = $('.player-'+ this.mode, this.$el);
        	
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
		            //localPlayer({'action': 'soundEnded'});
		        },
		        progress: function(e,data){
		        	
		        	that.nowPlayingPercent = $(this).data('jPlayer').status.currentPercentAbsolute;
		        	that.duration = $(this).data('jPlayer').status.duration;
		        	
		        	// TODO: make sure we have an interval ~ 3sec for drawFavicon()
				  	//window.sliMpd.localPlayer.drawFavicon();
				  	
		        	// TODO: check why jPlayer event 'loadedmetadata' sometimes has no duration (timegrid fails to render)
		        	// draw the timegrid only once as soon as we know the total duration and remove the progress eventListener
		        	// @see: http://jplayer.org/latest/developer-guide/#jPlayer-events
				  	that.drawTimeGrid();
				  	//console.log($(this).data('jPlayer').status.currentPercentAbsolute);
				  	
				}
			});
            window.sliMpd.modules.AbstractPlayer.prototype.initialize.call(this, options);
        },

        render : function(options) {
            window.sliMpd.modules.AbstractPlayer.prototype.render.call(this, options);
        },
        
        play : function(item) {
        	console.log(this.mode + 'Player:play()');
        	console.log(item);
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
			this.nowPlayingItem = item.item;
			this.redraw(item);
    		this.reloadCss(item.hash);
		},
		redraw : function(item) {
			window.sliMpd.modules.AbstractPlayer.prototype.redraw.call(this, item);
		},
		
		onRedrawComplete : function(item) {
			// re-bind controls on ajax loaded control-markup
    		$(this.playerSelector).jPlayer({cssSelectorAncestor: "#jp_container_1"});
			window.sliMpd.modules.AbstractPlayer.prototype.onRedrawComplete.call(this, item);
		},
       
		setPlayPauseState : function() {
			var player = $(this.playerSelector);
			var control = $('.localplayer-play-pause');
			if(this.nowPlayingState == 'play') {
				$(player).jPlayer( "play");
				$(control).addClass('localplayer-pause').removeClass('localplayer-play').html('<i class="fa fa-pause sign-ctrl fa-lg"></i>');
			} else {
				$(player).jPlayer( "pause");
				$(control).addClass('localplayer-play').removeClass('localplayer-pause').html('<i class="fa fa-play sign-ctrl fa-lg"></i>');
			}
			this.drawFavicon();
		},
		
		togglePause : function() {
			var localPlayerStatus = $('#jquery_jplayer_1').data('jPlayer').status;
			if(localPlayerStatus.paused === false) {
				setPlayPauseState('pause');
			} else {
				setPlayPauseState('play');
			}
		},
		
		soundEnded : function() {
			// for now take any rendered track and play it
			// TODO: add functionality "current playlist" (like mpd) for local player 
			var playable = $( ".is-playbtn[data-localplayer]").length;
			if(playable) {
				$(".is-playbtn[data-localplayer]").eq(Math.floor(Math.random()*playable)).click();
			}
		}
        
    });
    
})();

