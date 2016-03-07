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
    window.sliMpd.modules.AbstractPlayer = window.sliMpd.modules.AbstractView.extend({

        name : null,
        rendered : false,
        mode : '',
        
        nowPlayingPercent : 0,
        nowPlayingState : 'pause',
        nowPlayingDuration : 0,
        nowPlayingElapsed : 0,
        nowPlayingItem : '',
        previousPlayingItem : '',
        
        state : {
        	repeat : 0,
        	random : 0,
        	consume : 0
        },
        
        faviconDoghnutColor : '#000000',
        faviconBackgroundColor : 'transparent',
        
        timeGridSelectorCanvas : '',
		timeGridSelectorSeekbar : '',
		timeGridStrokeColor : '',
		timeGridStrokeColor2 : '',
		
		intervalActive : 2000, // [ms]
		intervalInactive : 5000, // [ms]

        initialize : function(options) {
            window.sliMpd.modules.AbstractView.prototype.initialize.call(this, options);
        },

        render : function(renderMarkup) {
            if (this.rendered) {
                return;
            }
            
            if (renderMarkup) {
            	this.$el.html($(this._template((this.model || {}).attributes)));
            }
            
            window.sliMpd.modules.AbstractView.prototype.render.call(this);
            
            this.rendered = true;
        },
        
        // fetch markup with trackinfos
        redraw : function(item) {
        	$.ajax({
    			url: '/markup/'+ this.mode+'player?'+ ((this.mode === 'xwax') ? 'deck='+this.deckIndex : 'item='+ item.item)
    		}).done(function(response){
    			// place markup in DOM
    			this._template = _.template(response);
    			this.rendered = false;
    			this.render(true);
    			this.onRedrawComplete(item);
    			this.reloadCss(item.hash);
    		}.bind(this));
        },
        onRedrawComplete : function(item) { return; },
        
        // highlight state icons when state is active
        updateStateIcons : function() {
        	var that = this;
        	['repeat', 'random', 'consume'].forEach(function(prop) {
			    if(that.state[prop] == '1') {
	    			$('.status-'+prop, that.$el).addClass('active');
		    	} else {
		    		$('.status-'+prop, that.$el).removeClass('active');
		    	}
			});
        },
        
        onRedrawComplete : function(item) { return; },
        
        process : function(item) {
        	//console.log('AbstractPlayer::process()'); console.log(item);
	        switch(item.action) {
	        	case 'play':
	        		this.play(item);
	        		break;
	        	case 'pause':
	        		this.pause(item);
	        		break;
	        	case 'togglePause':
	        		this.togglePause(item);
	        		break;
	        	case 'toggleRepeat':
	        		this.toggleRepeat(item);
	        		break;
	        	case 'toggleRandom':
	        		this.toggleRandom(item);
	        		break;
	        	case 'toggleConsume':
	        		this.toggleConsume(item);
	        		break;
	        	case 'next':
	        		this.next(item);
	        		break;
	        	case 'prev':
	        		this.prev(item);
	        		break;
	        	case 'seek':
	        		this.seek(item);
	        		break;
	        	case 'remove':
	        		this.remove(item);
	        		break;
	        	case 'clearPlaylistNotCurrent':
	        		this.clearPlaylistNotCurrent(item);
	        		break;
	        	case 'addDirToPlaylist':
	        		this.addDirToPlaylist(item);
	        		break;
	        	case 'addPlaylistToPlaylist':
	        		this.addPlaylistToPlaylist(item);
	        		break;
	        	case 'replaceCurrentPlaylist':
	        		this.replaceCurrentPlaylist(item);
	        		break;
	        	case 'replaceCurrentPlaylistKeepTrack':
	        		this.replaceCurrentPlaylistKeepTrack(item);
	        		break;
	        	case 'soundEnded':
	        		this.soundEnded(item);
	        		break;
	        	case 'removeDupes':
	        		this.removeDupes(item);
	        		break;
	        	default:
	        		console.log('ERROR: invalid action "'+ item.action +'" in '+ this.mode +'Player-item. exiting...');
    				return;
	        }

        },
        
        // define those methods in inherited implementation of AbstractPlayer
        play : function(item) { return; },
        pause : function(item) { return; },
        togglePause : function(item) { return; },
        toggleRepeat : function(item) { return; },
        toggleRandom : function(item) { return; },
        toggleConsume : function(item) { return; },
        setPlayPauseIcon : function() { return; },
        next : function(item) { return; },
        prev : function(item) { return; },
        seek : function(item) { return; },
        remove : function(item) { return; },
        clearPlaylistNotCurrent : function(item) { return; },
        addDirToPlaylist : function(item) { return; },
        addPlaylistToPlaylist : function(item) { return; },
        replaceCurrentPlaylist : function(item) { return; },
        replaceCurrentPlaylistKeepTrack : function(item) { return; },
        soundEnded : function(item) { return; },
        removeDupes : function(item) { return; },
                
		reloadCss : function(hash) {
			// TODO: comment whats happening here
			// FIXME: mpd-version is broken since backbonify
			$('#css-'+ this.mode +'player').attr('href', '/css/'+ this.mode +'player/'+ ((hash) ? hash : '0'));
		},
		
		drawFavicon : function() {
			FavIconX.config({
				updateTitle: false,
				shape: 'doughnut',
				doughnutRadius: 7,
				overlay: this.nowPlayingState,
				overlayColor : this.faviconDoghnutColor,
				borderColor: this.faviconDoghnutColor,
				fillColor: this.faviconDoghnutColor,
				borderWidth : 1.2,
				backgroundColor : this.faviconBackgroundColor,
				titleRenderer: function(v, t){
					return $('.player-'+ this.mode +' .now-playing-string').text();
				}
			}).setValue(this.nowPlayingPercent);
		},
		
		drawTimeGrid : function() {
			if(this.nowPlayingDuration <= 0) {
				return;
			}

			var cnv = document.getElementById(this.timeGridSelectorCanvas);
			var width = $(this.timeGridSelectorSeekbar).width();
			var height = 10;
			
			$('.'+this.timeGridSelectorCanvas).css('width', width + 'px');
			cnv.width = width;
			cnv.height = height;
			var ctx = cnv.getContext('2d');
			
			var strokePerHour = 60;
			var changeColorAfter = 5;
			//$.jPlayer.timeFormat.showHour = false;
			
			// longer than 30 minutes
			if(this.nowPlayingDuration > 1800) {
				strokePerHour = 12;
				changeColorAfter = 6;
			}
			
			// longer than 1 hour
			if(this.nowPlayingDuration > 3600) {
				strokePerHour = 6;
				changeColorAfter = 6;
				//$.jPlayer.timeFormat.showHour = true;
			}
			var pixelGap = width / this.nowPlayingDuration * (3600/ strokePerHour); 
		
			for (var i=0; i < this.nowPlayingDuration/(3600/strokePerHour); i++) {
		    	ctx.fillStyle = ((i+1)%changeColorAfter == 0) ? this.timeGridStrokeColor2 : this.timeGridStrokeColor;
		    	ctx.fillRect(pixelGap*(i+1),0,1,height);
		    }
		    
		    ctx.globalCompositeOperation = 'destination-out';
		    ctx.fill();
		},
		
		/* only for polled mpd player implementation - begin */
		poller : null,
		intervalActive : 0,
		intervalInactive : 0,
		poll : function() { return; },
		refreshInterval : function () {
			clearInterval(this.poller);
			this.poll();
		},
		/* only for polled mpd player implementation - end */
		
		
		/* only for mpd player progressbar implementation/interpolation - begin */
		trackAnimation : null,
		timeLineLight : null,
		timelineSetValue : function(value) { return; },
		updateSlider : function() { return; }
		/* only for polled mpd player implementation/interpolation  - end */
		
        
    });
    
})();