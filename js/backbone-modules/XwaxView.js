/*
 * dependencies: jquery, backbonejs, underscorejs, window.sliMpd.router, window.sliMpd.modules.AbstractView
 */
(function() {
    "use strict";
    
    var $ = window.jQuery,
        _ = window._;
    window.sliMpd = $.extend(true, window.sliMpd, {
        modules : {}
    });
    window.sliMpd.modules.XwaxView = window.sliMpd.modules.AbstractView.extend({

        rendered : false,
        tabAutocomplete : false,
        
        totalDecks : 3, // TODO: get from config
        
        xwaxRunning : false,
        
        deckViews : [],
        
        lastDeckTracks : [],
        
        intervalActive : 3000,
		intervalInactive : 6000,
        
        initialize : function(options) {
        	for(var i=0; i< this.totalDecks; i++) {
        		this.deckViews[i] = new window.sliMpd.modules.XwaxPlayer({
			    	el : '.xwax-deck-'+i,
			    	deckIndex : i
			    });
			    if(this.xwaxRunning === true) {
			    	this.deckViews[i].redraw();
			    }
			}
            window.sliMpd.modules.AbstractView.prototype.initialize.call(this, options);
        },

        render : function() {
        	// only render page once (to prevent multiple click listeners)
            if (this.rendered) {
                return;
            }
			this.poll();
            window.sliMpd.modules.AbstractView.prototype.render.call(this);
            this.rendered = true;
		},
		
		processXwaxNotRunning : function() {
			console.log('processXwaxNotRunning()');
			this.xwaxRunning = false;
		    this.lastDeckTracks = [];
		    
		    for(var i=0; i< this.totalDecks; i++) {
	    		
	    		this.deckViews[i].rendered = false;
	    		this.deckViews[i].$el.html('xwax not running');
	    		this.deckViews[i].render();
		    }
		    		
		    this.poller = setTimeout(this.poll, this.intervalInactive);
		},
		
		// IMPORTANT TODO: how to avoid growing memory consumption on those frequent poll-requests?
		poll : function (){
			var that = this;
		    $.get('/xwaxstatus', function(data) {
		    	
		    	try {
		        	var xwaxStatus = JSON.parse(data);
		        	if (typeof xwaxStatus.notify !== 'undefined') {
		        		that.processXwaxNotRunning();
		        		return;
		        	}
			    } catch(e) {
			    	that.processXwaxNotRunning();
		        	return;
				}
				that.xwaxRunning = true;
		    	//console.log(xwaxStatus[1]);
		    	for(var i=0; i< that.totalDecks; i++) {
		    		if(that.lastDeckTracks[i] !== xwaxStatus[i].path) {
		    			that.lastDeckTracks[i] = xwaxStatus[i].path;
		    			var hash = (xwaxStatus[i].item === null) ? '0000000' : xwaxStatus[i].item.relativePathHash;
		    			that.deckViews[i].redraw({hash: hash});
		    			//console.log('redraw deck ' + i);
		    		}
		    		
		    		that.deckViews[i].nowPlayingPercent = xwaxStatus[i].percent;
		    		that.deckViews[i].nowPlayingState = 'play';
		    		
		    		try {
		    			that.deckViews[i].nowPlayingDuration = xwaxStatus[i].item.miliseconds/1000;
		    		} catch(e) {
		    			that.deckViews[i].nowPlayingDuration = xwaxStatus[i].length;
		    		}
		    		if(xwaxStatus[i].length > that.deckViews[i].nowPlayingDuration) {
		    			that.deckViews[i].nowPlayingDuration = xwaxStatus[i].length;
		    			that.deckViews[i].onRedrawComplete();
		    		}
		    		that.deckViews[i].nowPlayingDuration = xwaxStatus[i].length;
		    		
		    		that.deckViews[i].nowPlayingElapsed = xwaxStatus[i].position;
			    	that.deckViews[i].nowPlayingItem = xwaxStatus[i].path;
			    	that.deckViews[i].timelineSetValue(xwaxStatus[i].percent);
			    	that.deckViews[i].updateStateIcons();
			    	
			    	//console.log(xwaxStatus);
			    	
		    	
		    		//that.deckViews[i].onRedrawComplete();
		    	}
		    	
		    	
		    	//$('.xwax-deck-'+ +'-status-elapsed').text(that.formatTime(that.nowPlayingElapsed));
			    //$('.mpd-status-total').text(that.formatTime(that.nowPlayingDuration));
		    	
		    	
		    	
		    	/*
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
		    	*/
		        that.poller = setTimeout(that.poll, that.intervalActive);
		    });
		}

    });
    
})();
