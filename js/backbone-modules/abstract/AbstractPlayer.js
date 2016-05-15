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
		
		showWaveform : true,
		
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
        	var url = '/markup/'+ this.mode+'player';
        	if(this.mode === 'xwax') {
        		url = window.sliMpd.setGetParameter(url, 'deck', this.deckIndex);
        		if(this.showWaveform === false) {
        			url = window.sliMpd.setGetParameter(url, 'type', 'djscreen');
        		} 
        	} else {
        		url = window.sliMpd.setGetParameter(url, 'item', item.item);
        	}
        	
        	$.ajax({
    			url: url
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
        
        process : function(item) {
        	//console.log('AbstractPlayer::process()'); console.log(item);
	        switch(item.action) {
	        	case 'play': this.play(item); break;
	        	case 'pause':this.pause(item); break;
	        	case 'togglePause': this.togglePause(item); break;
	        	case 'toggleRepeat': this.toggleRepeat(item); break;
	        	case 'toggleRandom': this.toggleRandom(item); break;
	        	case 'toggleConsume': this.toggleConsume(item); break;
	        	case 'next': this.next(item); break;
	        	case 'prev': this.prev(item); break;
	        	case 'seek': this.seek(item); break;
	        	case 'remove': this.remove(item); break;
	        	
	        	/* TODO: check removal begin */
	        	case 'clearPlaylistNotCurrent': this.clearPlaylistNotCurrent(item); break;
	        	case 'appendTrackToPlaylist': this.appendTrackToPlaylist(item); break;
	        	case 'appendTrackToPlaylistAndPlay': this.appendTrackToPlaylistAndPlay(item); break;
	        	case 'addDirToPlaylist': this.addDirToPlaylist(item); break;
	        	case 'addPlaylistToPlaylist': this.addPlaylistToPlaylist(item); break;
	        	case 'replaceCurrentPlaylist': this.replaceCurrentPlaylist(item); break;
	        	case 'replaceCurrentPlaylistKeepTrack': this.replaceCurrentPlaylistKeepTrack(item); break;
	        	/* TODO: check removal end */
	        	
	        	case 'appendTrack':            this.appendTrack(item);                break;
	        	case 'appendTrackAndPlay':     this.appendTrackAndPlay(item);         break;
	        	case 'injectTrack':            this.injectTrack(item);                break;
	        	case 'injectTrackAndPlay':     this.injectTrackAndPlay(item);         break;
	        	case 'replaceTrack':           this.replaceTrack(item);               break;
	        	case 'softreplaceTrack':       this.softreplaceTrack(item);           break;
	        	
	        	case 'appendDir':              this.appendDir(item);                  break;
	        	case 'appendDirAndPlay':       this.appendDirAndPlay(item);           break;
	        	case 'injectDir':              this.injectDir(item);                  break;
	        	case 'injectDirAndPlay':       this.injectDirAndPlay(item);           break;
	        	case 'replaceDir':             this.replaceDir(item);                 break;
	        	case 'softreplaceDir':         this.softreplaceDir(item);             break;
	        	
	        	case 'appendPlaylist':         this.appendPlaylist(item);             break;
	        	case 'appendPlaylistAndPlay':  this.appendPlaylistAndPlay(item);      break;
	        	case 'injectPlaylist':         this.injectPlaylist(item);             break;
	        	case 'injectPlaylistAndPlay':  this.injectPlaylistAndPlay(item);      break;
	        	case 'replacePlaylist':        this.replacePlaylist(item);            break;
	        	case 'softreplacePlaylist':    this.softreplacePlaylist(item);        break;
	        	
	        	case 'soundEnded': this.soundEnded(item); break;
	        	case 'removeDupes': this.removeDupes(item); break;
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
        
        /* TODO: check removal begin */
        clearPlaylistNotCurrent : function(item) { return; },
        appendTrackToPlaylist : function(item) { return; },
        appendTrackToPlaylistAndPlay : function(item) { return; },
        addDirToPlaylist : function(item) { return; },
        addPlaylistToPlaylist : function(item) { return; },
        replaceCurrentPlaylist : function(item) { return; },
        replaceCurrentPlaylistKeepTrack : function(item) { return; },
        /* TODO: check removal end */
       
       
		appendTrack : function(item) { return; },
		appendTrackAndPlay : function(item) { return; },
		injectTrack : function(item) { return; },
		injectTrackAndPlay : function(item) { return; },
		replaceTrack : function(item) { return; },
		softreplaceTrack : function(item) { return; },
			        	
		appendDir : function(item) { return; },
		appendDirAndPlay : function(item) { return; },
		injectDir : function(item) { return; },
		injectDirAndPlay : function(item) { return; },
		replaceDir : function(item) { return; },
		softreplaceDir : function(item) { return; },
			        	
		appendPlaylist : function(item) { return; },
		appendPlaylistAndPlay : function(item) { return; },
		injectPlaylist : function(item) { return; },
		injectPlaylistAndPlay : function(item) { return; },
		replacePlaylist : function(item) { return; },
		softreplacePlaylist : function(item) { return; },
       
       
        soundEnded : function(item) { return; },
        removeDupes : function(item) { return; },
                
		reloadCss : function(hash) {
			// TODO: comment whats happening here
			// FIXME: mpd-version is broken since backbonify
			var suffix, selector;
			if(this.mode === 'xwax') {
				suffix = '?deck=' + this.deckIndex;
				selector = '#css-xwaxdeck-'+ this.deckIndex;
			} else {
				suffix = '';
				selector = '#css-'+this.mode+'player';
			}
			$(selector).attr('href', '/css/'+ this.mode +'player/'+ ((hash) ? hash : '0') + suffix);
			
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
			if(cnv === null) {
				return;
			}
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