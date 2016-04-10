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
        
        totalDecks : 3, // TODO: get available decks from server
        
        xwaxRunning : false,
        
        visible : false,
        
        deckViews : [],
        
        lastDeckTracks : [],
        
        toggler : false,
        
        intervalActive : 3000,
		intervalInactive : 6000,
        
        initialize : function(options) {
            window.sliMpd.modules.AbstractView.prototype.initialize.call(this, options);
        },

        render : function() {
        	// only render page once (to prevent multiple click listeners)
            if (this.rendered) {
                return;
            }
            console.log('calling XwaxGui::render()');
            this.toggler = $('.xwax-gui-toggler', this.$el);
            this.toggler.off('click', this.toggleXwaxGui).on('click', this.toggleXwaxGui);
            window.sliMpd.modules.AbstractView.prototype.render.call(this);
            this.rendered = true;
		},
		
		toggleXwaxGui : function() {
			if(this.visible === false) {
				this.showXwaxGui();
			} else {
				this.hideXwaxGui();
			}
		},
		
		showXwaxGui : function() {
			for(var i=0; i< this.totalDecks; i++) {
				var deckView = new window.sliMpd.modules.XwaxPlayer({
			    	el : '.xwax-deck-'+i,
			    	deckIndex : i
			    });
        		this.deckViews.push(deckView); 
			    if(this.xwaxRunning === true) {
			    	deckView.redraw();
			    }
			    this.visible = true;
			}
			$('body').addClass('slimplayer');
			$('.xwax-error').removeClass('hidden');
			this.toggler.removeClass('btn-default').addClass('btn-success');
			this.poll();
		},
		
		hideXwaxGui : function() {
		    this.lastDeckTracks = [];
		    clearTimeout(this.poller);
		    //console.log('hideXwaxGui()');
		    this.deckViews.forEach(function (view){
		    	//console.log('destroying view ' + view.deckIndex);
	    		view.close();
	    		
		    });
		    $('body').removeClass('slimplayer');
		    this.toggler.removeClass('btn-success').removeClass('btn-danger').addClass('btn-default');
		    this.xwaxRunning = false;
		    this.deckViews = [];
		    this.visible = false;
		    $('.xwax-error', this.$el).addClass('hidden');
		    $('.xwax-gui', this.$el).removeClass('hidden');
		},
		
		processXwaxNotRunning : function() {
			//console.log('processXwaxNotRunning()');
			this.toggler.removeClass('btn-success').addClass('btn-danger');
			$('.xwax-error', this.$el).removeClass('hidden');
		    $('.xwax-gui', this.$el).addClass('hidden');
		    
			this.xwaxRunning = false;
		    this.lastDeckTracks = [];
		    
		    this.deckViews.forEach(function (deckView){
	    		
	    		deckView.rendered = false;
	    		deckView.$el.html('xwax not running');
	    		deckView.render();
		    });
		    clearTimeout(this.poller);
		    this.poller = setTimeout(this.poll, this.intervalInactive);
		},
		
		// IMPORTANT TODO: how to avoid growing memory consumption on those frequent poll-requests?
		poll : function (){
			if(this.visible === false) {
				return;
			}
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
				if(that.xwaxRunning === false) {
					that.toggler.removeClass('btn-danger').addClass('btn-success');
					$('.xwax-error', this.$el).addClass('hidden');
		    		$('.xwax-gui', this.$el).removeClass('hidden');
				}
				
				that.xwaxRunning = true;
				/*
		    	console.log('pitch ' + xwaxStatus[0].pitch);
		    	console.log('player_diff ' + xwaxStatus[0].player_diff);
		    	console.log('player_sync_pitch ' + xwaxStatus[0].player_sync_pitch);
		    	console.log('player_target_position ' + xwaxStatus[0].player_target_position);
		    	console.log('timecode_control ' + xwaxStatus[0].timecode_control);
		    	console.log('timecode_speed ' + xwaxStatus[0].timecode_speed);
		    	console.log('-----------------------------');
		    	*/
		    	
		    	for(var i=0; i< that.totalDecks; i++) {
		    		
		    		
		    		that.deckViews[i].nowPlayingPercent = xwaxStatus[i].percent;
		    		that.deckViews[i].nowPlayingState = xwaxStatus[i].state;
		    		
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
			    	
			    	that.deckViews[i].timelineSetValue(xwaxStatus[i].percent);
			    	that.deckViews[i].updateStateIcons();
			    	if(that.lastDeckTracks[i] !== xwaxStatus[i].path) {
		    			that.lastDeckTracks[i] = xwaxStatus[i].path;
		    			that.deckViews[i].nowPlayingItem = xwaxStatus[i].path;
		    			var hash = (xwaxStatus[i].item === null) ? '0000000' : xwaxStatus[i].item.relativePathHash;
		    			that.deckViews[i].redraw({hash: hash});
		    			//console.log('redraw deck ' + i);
		    		}
		    		that.deckViews[i].nowPlayingItem = xwaxStatus[i].path;
			    	
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
