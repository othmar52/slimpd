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
        
        nowPlayingPercent : 0,	    // used in drawFavicon()
        nowPlayingState : 'pause',  // used in drawFavicon()
        nowPlayingDuration : 0,		// used in drawTimeGrid()
        nowPlayingElapsed : 0,
        nowPlayingItem : '',
        previousPlayingItem : '',
        
        stateRepeat : 0,
        stateRandom : 0,
        stateConsume : 0,
        
        doghnutColor : '#000000', // used in drawFavicon()
        
        selectorCanvas : '',	// used in drawTimeGrid()
		selectorSeekbar : '',	// used in drawTimeGrid()
		strokeColor : '',		// used in drawTimeGrid()
		strokeColor2 : '',		// used in drawTimeGrid()
		
		
		intervalActive : 2000, // ms
		intervalInactive : 5000, // ms

        initialize : function(options) {
            window.sliMpd.modules.AbstractView.prototype.initialize.call(this, options);
        },

        render : function() {
        	console.log('AbstractPlayer::render()');
            if (this.rendered) {
            	console.log('AbstractPlayer::render() return');
                return;
            }
            
            window.sliMpd.modules.AbstractView.prototype.render.call(this);
            
            this.rendered = true;
        },
        // fetch markup with trackinfos
        redraw : function(item) {
        	$.ajax({
    			url: '/markup/'+ this.mode+'player?item='+ item.item
    		}).done(function(response){
    			// place markup in DOM
    			$('.player-'+ this.mode).html(response);
    			this.onRedrawComplete(item);
    		}.bind(this));
        },
        onRedrawComplete : function(item) { return; },
        
        process : function(item) {
        	// TODO: move to abstractview
        	console.log(this.mode + 'Player:process()');

	        switch(item.action) {
	        	case 'play':
	        		this.play(item);
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
	        	default:
	        		console.log('ERROR: invalid action "'+ item.action +'" in '+ this.mode +'Player-item. exiting...');
    				return;
	        }

        },
        
        // define those methods in inherited implementation of AbstractPlaer
        play : function(item) { return; },
        togglePause : function() { return; },
        toggleRepeat : function() { return; },
        toggleRandom : function() { return; },
        toggleConsume : function() { return; },
        setPlayPauseState : function() { return; },
        next : function(item) { return; },
        prev : function(item) { return; },
        seek : function(item) { return; },
                
		reloadCss : function(hash) {
			// TODO: comment whats happening here
			$('#css-'+ this.mode +'player').attr('href', '/css/'+ this.mode +'player/'+ ((hash) ? hash : '0'));
		},
		drawFavicon : function() {
			
			// TODO: set percent in each playermode
			//var localPlayerStatus = $('#jquery_jplayer_1').data('jPlayer').status;
			//percent = localPlayerStatus.currentPercentAbsolute;
			
			FavIconX.config({
				updateTitle: false,
				shape: 'doughnut',
				doughnutRadius: 7.5,
				overlay: this.nowPlayingState,
				overlayColor: '#777',
				borderColor: this.doghnutColor,
				fillColor: this.doghnutColor,
				titleRenderer: function(v, t){
					return $('.player-'+ this.mode +' .now-playing-string').text();
				}
			}).setValue(this.nowPlayingPercent);
		},
		
		drawTimeGrid : function() {
	
			if(this.nowPlayingDuration <= 0) {
				return;
			}

			var cnv = document.getElementById(this.selectorCanvas);
			var width = $('.' + this.selectorSeekbar).width();
			var height = 10;
			
			$('.'+this.selectorCanvas).css('width', width + 'px');
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
		    	ctx.fillStyle = ((i+1)%changeColorAfter == 0) ? this.strokeColor2 : this.strokeColor;
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
		}
		/* only for polled mpd player implementation - end */
		
        
    });
    
})();