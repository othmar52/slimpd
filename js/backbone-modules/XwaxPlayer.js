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

		initialize(options) {
			this.deckIndex = options.deckIndex;
			this.timeGridSelectorCanvas = "timegrid-xwax-deck-"+ this.deckIndex;
			this.$content = $(".player-"+ this.mode, this.$el);
			this.showWaveform = options.showWaveform;
			this.trackAnimation = { currentPosPerc: 0 };

			if(this.showWaveform === true) {
				this.timeLineLight = new window.TimelineLite();
			}

			window.sliMpd.modules.AbstractPlayer.prototype.initialize.call(this, options);

			this._template = _.template("<div class='show-no-connection'>xwax connection failed</div>");
			this.rendered = false;
			this.render(true);
		},

		render(options) {
			//console.log("calling XwaxPlayer::render() " + this.deckIndex);
			window.sliMpd.modules.AbstractPlayer.prototype.render.call(this, options);
		},

		onRedrawComplete(item) {
			if(this.showWaveform !== true) {
				this.updateTimecode(this.timecode);
				return;
			}
			// animate from 0 to 100, onUpdate -> change Text
			this.timeLineLight = new window.TimelineLite();
			this.trackAnimation.currentPosPerc = 0;
			this.timeLineLight.to(this.trackAnimation, this.nowPlayingDuration, {
				currentPosPerc: 100,
				ease: window.Linear.easeNone,
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

		updateStateIcons() {
			$(".xwax-deck-"+this.deckIndex+"-status-elapsed").text(this.formatTime(this.nowPlayingElapsed));
			$(".xwax-deck-"+this.deckIndex+"-status-total").text(this.formatTime(this.nowPlayingDuration));
			window.sliMpd.modules.AbstractPlayer.prototype.updateStateIcons.call(this);
		},

		zeroPad(number, n) {
			var zeroPad = "" + number;
			while(zeroPad.length < n) {
				zeroPad = "0" + zeroPad;
			}
			return zeroPad;
		},

		timelineSetValue(value) {
			this.timeLineLight.progress(value/100);
			window.sliMpd.modules.AbstractPlayer.prototype.timelineSetValue.call(this, value);
		},

		updateSlider(item) {
			if(this.showWaveform !== true) {
				return;
			}
			// TODO: how to respect parents padding on absolute positioned div with width 100% ?
			$(".xwax-deck-"+ this.deckIndex+"-status-progressbar").css("width", "calc("+ this.timeLineLight.progress() *100 +"% - 15px)");
			window.sliMpd.modules.AbstractPlayer.prototype.updateSlider.call(this, item);
		},
		updateTimecode(timecode) {
			this.timecode = timecode;
			$(".xwax-deck-"+ this.deckIndex+ " .timecoder").text(timecode);
		}
	});

}());
