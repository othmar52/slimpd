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

		mode : "xwax",
		faviconDoghnutColor : "rgb(255,156,1)",
		faviconBackgroundColor : "#444",

		timeGridSelectorCanvas : "",
		timeGridSelectorSeekbar : ".xwax-ctrl-seekbar",
		timeGridStrokeColor : "#7B6137",
		timeGridStrokeColor2 : "#FCC772",

		deckIndex : false,
		showWaveform : true,
		timecode : "",

		timeLineLight : null,

		initialize : function(options) {
			this.deckIndex = options.deckIndex;
			this.timeGridSelectorCanvas = "timegrid-xwax-deck-"+ this.deckIndex;
			this.$content = $(".player-"+ this.mode, this.$el);
			this.showWaveform = options.showWaveform;
			this.trackAnimation = { currentPosPerc: 0 };

			if(this.showWaveform === true) {
				this.timeLineLight = new TimelineLite();
			}

			//console.log("XwaxPlayer::init() " + this.deckIndex);
			//this.listenTo(this.parent, "hideXwaxGui", this.close);

			window.sliMpd.modules.AbstractPlayer.prototype.initialize.call(this, options);
		},

		close : function(){
			//console.log("XwaxPlayer::close()");
			this.remove();
			// IMPORTANT TODO: shouldnt remove() removing the DOM element???
			this.$el.html("<div class='show-no-connection'>xwax connection failed</div>");
			this.unbind();
			//window.sliMpd.modules.AbstractPlayer.prototype.close.call(this, options);
		},

		render : function(options) {
			//console.log("calling XwaxPlayer::render() " + this.deckIndex);
			window.sliMpd.modules.AbstractPlayer.prototype.render.call(this, options);
		},

		onRedrawComplete : function(item) {
			if(this.showWaveform != true) {
				this.updateTimecode(this.timecode);
				return;
			}
			// animate from 0 to 100, onUpdate -> change Text
			this.timeLineLight = new TimelineLite();
			this.trackAnimation.currentPosPerc = 0;
			this.timeLineLight.to(this.trackAnimation, this.nowPlayingDuration, {
				currentPosPerc: 100,
				ease: Linear.easeNone,
				onUpdate: this.updateSlider,
				onUpdateScope: this
			});

			if(this.nowPlayingState === "play") {
				this.timelineSetValue(this.nowPlayingPercent);
			} else {
				this.timeLineLight.pause();
			}
			this.drawTimeGrid();
			window.sliMpd.modules.AbstractPlayer.prototype.onRedrawComplete.call(this, item);
		},

		updateStateIcons : function() {
			$(".xwax-deck-"+this.deckIndex+"-status-elapsed").text(this.formatTime(this.nowPlayingElapsed));
			$(".xwax-deck-"+this.deckIndex+"-status-total").text(this.formatTime(this.nowPlayingDuration));
			window.sliMpd.modules.AbstractPlayer.prototype.updateStateIcons.call(this);
		},

		formatTime : function(seconds) {
			if(typeof seconds === "undefined") {
				return "-- : --";
			}
			var seconds 	= Math.round(seconds);
			var hour 		= Math.floor(seconds / 3600);
			var minutes 	= Math.floor(seconds / 60) % 60;
			seconds 		= seconds % 60;

			if (hour > 0)	return hour + ":" + this.zeroPad(minutes, 2) + ":" + this.zeroPad(seconds, 2);
			else			return minutes + ":" + this.zeroPad(seconds, 2);
		},

		zeroPad : function(number, n) {
			var zeroPad = "" + number;
			while(zeroPad.length < n) {
				zeroPad = "0" + zeroPad;
			}
			return zeroPad;
		},

		timelineSetValue : function(value) {
			this.timeLineLight.progress(value/100);
			window.sliMpd.modules.AbstractPlayer.prototype.timelineSetValue.call(this, value);
		},

		updateSlider : function(item) {
			if(this.showWaveform != true) {
				return;
			}
			// TODO: how to respect parents padding on absolute positioned div with width 100% ?
			$(".xwax-deck-"+ this.deckIndex+"-status-progressbar").css("width", "calc("+ this.timeLineLight.progress() *100 +"% - 15px)");
			window.sliMpd.modules.AbstractPlayer.prototype.updateSlider.call(this, item);
		},
		updateTimecode : function(timecode) {
			this.timecode = timecode;
			$(".xwax-deck-"+ this.deckIndex+ " .timecoder").text(timecode);
		}
	});

})();
