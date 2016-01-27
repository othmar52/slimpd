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
    window.sliMpd.modules.AbstractPlayer = window.sliMpd.modules.PageView.extend({

        name : null,
        rendered : false,
        mode : '',
        percent : 0,	// used in drawFavicon()
        state : 'pause',// used in drawFavicon()
        doghnutColor : '#000000', // used in drawFavicon()
        
        duration : 0,			// used in drawTimeGrid()
        selectorCanvas : '',	// used in drawTimeGrid()
		selectorSeekbar : '',	// used in drawTimeGrid()
		strokeColor : '',		// used in drawTimeGrid()
		strokeColor2 : '',		// used in drawTimeGrid()

        initialize : function(options) {
            window.Backbone.View.prototype.initialize.call(this, options);
        },

        render : function() {
            if (this.rendered) {
                return;
            }
            
            window.sliMpd.modules.PageView.call(this);
            
            this.rendered = true;
        },
        
        process : function(itemstring) {
        	console.log(this.mode + 'Player:process()');
        	if(typeof itemstring == 'undefined') {
        		console.log('ERROR: missing player-item. exiting...');
        		return;
        	}

			try {
		        var item = JSON.parse(itemstring);
		        switch(item.action) {
		        	case 'play':
		        		this.play(item);
		        		break;
		        	case 'togglePause':
		        		this.togglePause();
		        		break;
		        	default:
		        		console.log('ERROR: invalid action "'+ item.action +'" in '+ this.mode +'Player-item. exiting...');
        				return;
		        }
		    } catch(e) {
		    	console.log(e + ' in data-player attribute');
		    }
        },
        
		reloadCss : function(hash) {
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
				overlay: this.state,
				overlayColor: '#777',
				borderColor: this.doghnutColor,
				fillColor: this.doghnutColor,
				titleRenderer: function(v, t){
					return $('.player-'+ this.mode +' .now-playing-string').text();
				}
			}).setValue(this.percent);
		},
		
		drawTimeGrid : function() {
	
			if(this.duration <= 0) {
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
			if(this.duration > 1800) {
				strokePerHour = 12;
				changeColorAfter = 6;
			}
			
			// longer than 1 hour
			if(this.duration > 3600) {
				strokePerHour = 6;
				changeColorAfter = 6;
				//$.jPlayer.timeFormat.showHour = true;
			}
			var pixelGap = width / this.duration * (3600/ strokePerHour); 
		
			for (var i=0; i < this.duration/(3600/strokePerHour); i++) {
		    	ctx.fillStyle = ((i+1)%changeColorAfter == 0) ? this.strokeColor2 : this.strokeColor;
		    	ctx.fillRect(pixelGap*(i+1),0,1,height);
		    }
		    
		    ctx.globalCompositeOperation = 'destination-out';
		    ctx.fill();
		}	
        
    });
    
})();