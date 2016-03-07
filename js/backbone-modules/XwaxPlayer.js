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
    window.sliMpd.modules.XwaxPlayer = window.sliMpd.modules.AbstractPlayer.extend({

        mode : 'xwax',
        faviconDoghnutColor : 'rgb(255,156,1)',
        faviconBackgroundColor : '#444',
        
        timeGridSelectorCanvas : 'timegrid-mpd',
		timeGridSelectorSeekbar : '.xwax-ctrl-seekbar',
		timeGridStrokeColor : '#7B6137',
		timeGridStrokeColor2 : '#FCC772',
		
		deckIndex : false,
		
		
		timeLineLight : null,

        initialize : function(options) {
        	this.deckIndex = options.deckIndex;
        	//this.redraw();
        	this.$content = $('.player-'+ this.mode, this.$el);
        	
        	this.trackAnimation = { currentPosPerc: 0 };
        	this.timeLineLight = new TimelineLite();
        	
        	this.poll();
            window.sliMpd.modules.AbstractPlayer.prototype.initialize.call(this, options);
        },

        render : function(options) {
            window.sliMpd.modules.AbstractPlayer.prototype.render.call(this, options);
        },
		
        onRedrawComplete : function(item) {
        	
        	// animate from 0 to 100, onUpdate -> change Text
			this.timeLineLight = new TimelineLite();
			this.trackAnimation.currentPosPerc = 0;
			this.timeLineLight.to(this.trackAnimation, this.nowPlayingDuration, {
			  	currentPosPerc: 100, 
			  	ease: Linear.easeNone,  
			  	onUpdate: this.updateSlider,
			  	onUpdateScope: this
			});
	    	
	    	if(this.nowPlayingState == 'play') {
	    		this.timelineSetValue(this.nowPlayingPercent);
			} else {
	    		this.timeLineLight.pause();
	    	}
	    	this.drawTimeGrid();
	
        	
        	
        	window.sliMpd.modules.AbstractPlayer.prototype.onRedrawComplete.call(this, item);
        },
        
        updateStateIcons : function() {
        	$('.xwax-deck-'+this.deckIndex+'-status-elapsed').text(this.formatTime(this.nowPlayingElapsed));
			$('.xwax-deck-'+this.deckIndex+'-status-total').text(this.formatTime(this.nowPlayingDuration));
        	window.sliMpd.modules.AbstractPlayer.prototype.updateStateIcons.call(this);
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
			$('.xwax-deck-'+ this.deckIndex+'-status-progressbar').css('width', 'calc('+ this.timeLineLight.progress() *100 +'% - 15px)');
			window.sliMpd.modules.AbstractPlayer.prototype.updateSlider.call(this, item);
		}
    });
    
})();
