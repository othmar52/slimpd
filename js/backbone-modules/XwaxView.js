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

		lastTimecodes : [],

		toggler : false,

		showWaveform : true,

		pollWorker : null,
		intervalActive : 3000,
		intervalInactive : 6000,

		notrunningTolerance : 2,
		notrunningCounter : 0,

		initialize(options) {
			this.showWaveform = options.showWaveform;
			window.sliMpd.modules.AbstractView.prototype.initialize.call(this, options);
		},

		render() {
			// only render page once (to prevent multiple click listeners)
			if (this.rendered) {
				return;
			}
			//console.log("calling XwaxGui::render()");
			this.toggler = $(".xwax-gui-toggler");
			window.sliMpd.modules.AbstractView.prototype.render.call(this);
			this.rendered = true;
		},

		toggleXwaxGui() {
			if(this.visible === false) {
				this.showXwaxGui();
				return;
			}
			this.hideXwaxGui();
		},

		showXwaxGui() {
			for(var i=0; i< this.totalDecks; i++) {
				var deckView = new window.sliMpd.modules.XwaxPlayer({
					el : ".xwax-deck-"+i,
					deckIndex : i,
					showWaveform : this.showWaveform
				});
				this.deckViews.push(deckView);
				if(this.xwaxRunning === true) {
					deckView.redraw();
				}
				this.visible = true;
			}
			$("body").addClass("slimplayer xwax-enabled");
			$(".xwax-error").removeClass("hidden");
			this.toggler.removeClass("btn-default").addClass("btn-success");

			this.pollWorker = new Worker(window.sliMpd.conf.absFilePrefix+"js/poll-worker.js");
			var that = this;
			this.pollWorker.addEventListener("message", function(e) {
				that.processPollData(e.data);
			}, false);

			this.pollWorker.postMessage({
				cmd: "setPollUrl",
				value: window.sliMpd.conf.absRefPrefix + "xwaxstatus"
			});

			this.pollWorker.postMessage({
				cmd: "setMiliseconds",
				value: this.intervalActive
			});

			this.pollWorker.postMessage({
				cmd: "start"
			});
		},

		hideXwaxGui() {
			this.lastDeckTracks = [];
			this.lastTimecodes = [];
			this.pollWorker.postMessage({
				cmd: "stop"
			});
			this.pollWorker = null;

			//console.log("hideXwaxGui()");
			this.deckViews.forEach(function (view){
				//console.log("destroying view " + view.deckIndex);
				view.close();
			});
			$("body").removeClass("slimplayer xwax-enabled");
			this.toggler.removeClass("btn-success").removeClass("btn-danger").addClass("btn-default");
			this.xwaxRunning = false;
			this.deckViews = [];
			this.visible = false;
		},
		
		processXwaxNotRunning() {
			//console.log("processXwaxNotRunning()");

			// sometimes we have connection errors with xwax"s socket
			// instead of disabling xwax stuff immediatly wait for x more poll request
			this.notrunningCounter++;
			if(this.notrunningCounter < this.notrunningTolerance) {
				this.pollWorker.postMessage({
					cmd: "setMiliseconds",
					value: this.intervalActive
				});
				return;
			}

			this.toggler.removeClass("btn-success").addClass("btn-danger");
			this.xwaxRunning = false;
			$(".player-xwax").addClass("no-connection");
			this.pollWorker.postMessage({
				cmd: "setMiliseconds",
				value: this.intervalInactive
			});
		},
		
		processPollData(data){
			if(this.visible === false) {
				return;
			}

			try {
				if (typeof data.notify !== "undefined") {
					this.processXwaxNotRunning();
					return;
				}
			} catch(e) {
				this.processXwaxNotRunning();
				return;
			}
			this.notrunningCounter = 0;
			if(this.xwaxRunning === false) {
				this.toggler.removeClass("btn-danger").addClass("btn-success");
				$(".player-xwax").removeClass("no-connection");
			}

			this.xwaxRunning = true;
			/*
			console.log("pitch " + data[0].pitch);
			console.log("player_diff " + data[0].player_diff);
			console.log("player_sync_pitch " + data[0].player_sync_pitch);
			console.log("player_target_position " + data[0].player_target_position);
			console.log("timecode_control " + data[0].timecode_control);
			console.log("timecode_speed " + data[0].timecode_speed);
			console.log("-----------------------------");
			*/
			for(var i=0; i< this.totalDecks; i++) {
				this.deckViews[i].nowPlayingPercent = data[i].percent;
				this.deckViews[i].nowPlayingState = data[i].state;

				try {
					this.deckViews[i].nowPlayingDuration = data[i].item.miliseconds/1000;
				} catch(e) {
					this.deckViews[i].nowPlayingDuration = data[i].length;
				}
				if(data[i].length > this.deckViews[i].nowPlayingDuration) {
					this.deckViews[i].nowPlayingDuration = data[i].length;
					this.deckViews[i].onRedrawComplete();
				}
				this.deckViews[i].nowPlayingDuration = data[i].length;
				this.deckViews[i].nowPlayingElapsed = data[i].position;

				if(this.showWaveform === true) {
					this.deckViews[i].timelineSetValue(data[i].percent);
				}
				this.deckViews[i].updateStateIcons();
				if(this.lastDeckTracks[i] !== data[i].path) {
					this.lastDeckTracks[i] = data[i].path;
					this.deckViews[i].nowPlayingItem = data[i].path;
					var hash = (data[i].item === null) ? "0000000" : data[i].item.relativePathHash;
					this.deckViews[i].redraw({hash: hash});
					//console.log("redraw deck " + i);
				}
				if(this.lastTimecodes[i] !== data[i].timecode) {
					this.lastTimecodes[i] = data[i].timecode;
					this.deckViews[i].updateTimecode(data[i].timecode);
				}
				this.deckViews[i].nowPlayingItem = data[i].path;
			}
		}
	});
}());
