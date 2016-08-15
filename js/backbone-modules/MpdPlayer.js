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
	window.sliMpd.modules.MpdPlayer = window.sliMpd.modules.AbstractPlayer.extend({
		mode : "mpd",
		faviconDoghnutColor : window.sliMpd.conf.color.mpd.favicon,
		faviconBackgroundColor : "#444",

		timeGridSelectorCanvas : "timegrid-mpd",
		timeGridSelectorSeekbar : ".mpd-ctrl-seekbar",
		timeGridStrokeColor : window.sliMpd.conf.color.mpd.secondary,
		timeGridStrokeColor2 : window.sliMpd.conf.color.mpd.primary,

		state : {
			repeat : 0,
			random : 0,
			consume : 0
		},

		intervalActive : 2000,
		intervalInactive : 5000,
		timeLineLight : null,

		pollWorker : null,

		playlistState : false,
		initialNotificationBlocker : true,

		initialize(options) {
			this.$content = $(".player-"+ this.mode, this.$el);

			this.trackAnimation = { currentPosPerc: 0 };
			this.timeLineLight = new window.TimelineLite();

			this.pollWorker = new Worker(window.sliMpd.conf.absFilePrefix + "js/poll-worker.js");
			var that = this;
			this.pollWorker.addEventListener("message", function(e) {
				that.processPollData(e.data);
			}, false);

			this.pollWorker.postMessage({
				cmd: "setPollUrl",
				value: window.sliMpd.conf.absRefPrefix + "mpdstatus"
			});

			this.pollWorker.postMessage({
				cmd: "setMiliseconds",
				value: (($.cookie("playerMode") === "mpd") ? this.intervalActive : this.intervalInactive)
			});

			this.pollWorker.postMessage({
				cmd: "start"
			});

			window.sliMpd.modules.AbstractPlayer.prototype.initialize.call(this, options);
		},

		render(options) {
			window.sliMpd.modules.AbstractPlayer.prototype.render.call(this, options);
		},

		play(item) {
			window.sliMpd.fireRequestAndNotify(item.mpdurl);
			this.redraw();
			//this.reloadCss(item.hash);
			window.sliMpd.modules.AbstractPlayer.prototype.play.call(this, item);
		},

		togglePause(item) {
			if(this.nowPlayingState === "play") {
				window.sliMpd.fireRequestAndNotify(window.sliMpd.conf.absRefPrefix + "mpdctrl/pause");
				this.nowPlayingState = "pause";
			} else {
				window.sliMpd.fireRequestAndNotify(window.sliMpd.conf.absRefPrefix + "mpdctrl/play");
				this.nowPlayingState = "play";
			}
			window.sliMpd.modules.AbstractPlayer.prototype.togglePause.call(this, item);
			this.setPlayPauseIcon(item);
			window.sliMpd.drawFavicon();
		},

		seek(item) {
			window.sliMpd.fireRequestAndNotify(item.mpdurl);
			window.sliMpd.modules.AbstractPlayer.prototype.seek.call(this, item);
		},

		seekzero(item) {
			window.sliMpd.fireRequestAndNotify(window.sliMpd.conf.absRefPrefix + "mpdctrl/seekPercent/0");
			this.timelineSetValue(0);
			window.sliMpd.modules.AbstractPlayer.prototype.seekzero.call(this, item);
		},

		prev(item) {
			window.sliMpd.fireRequestAndNotify(item.mpdurl);
			this.refreshInterval();
			window.sliMpd.modules.AbstractPlayer.prototype.prev.call(this, item);
		},

		next(item) {
			window.sliMpd.fireRequestAndNotify(item.mpdurl);
			this.refreshInterval();
			window.sliMpd.modules.AbstractPlayer.prototype.next.call(this, item);
		},

		remove(item) {
			window.sliMpd.fireRequestAndNotify(item.mpdurl);
			this.refreshInterval();
			window.sliMpd.modules.AbstractPlayer.prototype.remove.call(this, item);
		},

		toggleRepeat(item) {
			window.sliMpd.fireRequestAndNotify(item.mpdurl);
			window.sliMpd.modules.AbstractPlayer.prototype.toggleRepeat.call(this, item);
		},

		toggleRandom(item) {
			window.sliMpd.fireRequestAndNotify(item.mpdurl);
			window.sliMpd.modules.AbstractPlayer.prototype.toggleRandom.call(this, item);
		},

		toggleConsume(item) {
			window.sliMpd.fireRequestAndNotify(item.mpdurl);
			window.sliMpd.modules.AbstractPlayer.prototype.toggleConsume.call(this, item);
		},

		softclearPlaylist(item) {
			window.sliMpd.fireRequestAndNotify(item.mpdurl);
			this.refreshInterval();
			window.sliMpd.modules.AbstractPlayer.prototype.softclearPlaylist.call(this, item);
		},

		appendTrack(item) {
			window.sliMpd.fireRequestAndNotify(item.mpdurl);
			this.refreshInterval();
			window.sliMpd.modules.AbstractPlayer.prototype.appendTrack.call(this, item);
		},
		appendTrackAndPlay(item) {
			window.sliMpd.fireRequestAndNotify(item.mpdurl);
			this.refreshInterval();
			window.sliMpd.modules.AbstractPlayer.prototype.appendTrackAndPlay.call(this, item);
		},

		injectTrack(item) {
			window.sliMpd.fireRequestAndNotify(item.mpdurl);
			this.refreshInterval();
			window.sliMpd.modules.AbstractPlayer.prototype.injectTrack.call(this, item);
		},

		injectTrackAndPlay(item) {
			window.sliMpd.fireRequestAndNotify(item.mpdurl);
			this.refreshInterval();
			window.sliMpd.modules.AbstractPlayer.prototype.injectTrackAndPlay.call(this, item);
		},

		replaceTrack(item) {
			window.sliMpd.fireRequestAndNotify(item.mpdurl);
			this.refreshInterval();
			window.sliMpd.modules.AbstractPlayer.prototype.replaceTrack.call(this, item);
		},

		softreplaceTrack(item) {
			window.sliMpd.fireRequestAndNotify(item.mpdurl);
			this.refreshInterval();
			window.sliMpd.modules.AbstractPlayer.prototype.softreplaceTrack.call(this, item);
		},


		appendDir(item) {
			window.sliMpd.fireRequestAndNotify(item.mpdurl);
			this.refreshInterval();
			window.sliMpd.modules.AbstractPlayer.prototype.appendDir.call(this, item);
		},

		appendDirAndPlay(item) {
			window.sliMpd.fireRequestAndNotify(item.mpdurl);
			this.refreshInterval();
			window.sliMpd.modules.AbstractPlayer.prototype.appendDirAndPlay.call(this, item);
		},

		injectDir(item) {
			window.sliMpd.fireRequestAndNotify(item.mpdurl);
			this.refreshInterval();
			window.sliMpd.modules.AbstractPlayer.prototype.injectDir.call(this, item);
		},

		injectDirAndPlay(item) {
			window.sliMpd.fireRequestAndNotify(item.mpdurl);
			this.refreshInterval();
			window.sliMpd.modules.AbstractPlayer.prototype.injectDirAndPlay.call(this, item);
		},

		replaceDir(item) {
			window.sliMpd.fireRequestAndNotify(item.mpdurl);
			this.refreshInterval();
			window.sliMpd.modules.AbstractPlayer.prototype.replaceDir.call(this, item);
		},

		softreplaceDir(item) {
			window.sliMpd.fireRequestAndNotify(item.mpdurl);
			window.sliMpd.modules.AbstractPlayer.prototype.softreplaceDir.call(this, item);
		},


		appendPlaylist(item) {
			window.sliMpd.fireRequestAndNotify(item.mpdurl);
			this.refreshInterval();
			window.sliMpd.modules.AbstractPlayer.prototype.appendPlaylist.call(this, item);
		},

		appendPlaylistAndPlay(item) {
			window.sliMpd.fireRequestAndNotify(item.mpdurl);
			window.sliMpd.modules.AbstractPlayer.prototype.appendPlaylistAndPlay.call(this, item);
		},

		injectPlaylist(item) {
			window.sliMpd.fireRequestAndNotify(item.mpdurl);
			this.refreshInterval();
			window.sliMpd.modules.AbstractPlayer.prototype.injectPlaylist.call(this, item);
		},

		injectPlaylistAndPlay(item) {
			window.sliMpd.fireRequestAndNotify(item.mpdurl);
			this.refreshInterval();
			window.sliMpd.modules.AbstractPlayer.prototype.injectPlaylistAndPlay.call(this, item);
		},

		replacePlaylist(item) {
			window.sliMpd.fireRequestAndNotify(item.mpdurl);
			this.refreshInterval();
			window.sliMpd.modules.AbstractPlayer.prototype.replacePlaylist.call(this, item);
		},

		softreplacePlaylist(item) {
			window.sliMpd.fireRequestAndNotify(item.mpdurl);
			this.refreshInterval();
			window.sliMpd.modules.AbstractPlayer.prototype.softreplacePlaylist.call(this, item);
		},

		removeDupes(item) {
			window.sliMpd.fireRequestAndNotify(item.mpdurl);
			this.refreshInterval();
			window.sliMpd.modules.AbstractPlayer.prototype.removeDupes.call(this, item);
		},

		process(item) {
			window.sliMpd.modules.AbstractPlayer.prototype.process.call(this, item);
		},

		processPollData(data){

			this.nowPlayingPercent = data.percent;
			this.nowPlayingState = data.state;
			this.nowPlayingDuration = data.duration;
			this.nowPlayingElapsed = data.elapsed;
			this.nowPlayingItem = data.songid;

			this.state.repeat = data.repeat;
			this.state.random = data.random;
			this.state.consume = data.consume;

			// helper var to avoid double page reload (trackchange + playlistchange)
			var forcePageReload = false;

			// no need to update this stuff in case local player is active...
			if(window.sliMpd.currentPlayer.mode === "mpd") {
				this.updateStateIcons();
				this.setPlayPauseIcon();

				// TODO: interpolate nowPlayingElapsed independent of poll interval
				$(".mpd-status-elapsed").text(this.formatTime(this.nowPlayingElapsed));
				$(".mpd-status-total").text(this.formatTime(this.nowPlayingDuration));

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

				// update view in case current route shows playlist and playlist has changed
				if(this.playlistState !== data.playlist) {
					// make sure we do not reload after initial rendering of sliMpd
					if(this.playlistState !== false) {
						forcePageReload = true;
					}
					this.playlistState = data.playlist;
				}
			}

			// update trackinfo only onTrackChange()
			if(this.previousPlayingItem !== this.nowPlayingItem) {
				// make sure we do not reload after initial rendering of sliMpd
				if(this.previousPlayingItem !== "") {
					// update view in case current route shows playlist and track has changed
					forcePageReload = true;
				}
				this.previousPlayingItem = this.nowPlayingItem;
				this.redraw("");
				this.refreshInterval();
			}

			if(forcePageReload === true) {
				window.sliMpd.router.refreshIfName("playlist");
			}
		},

		refreshInterval() {
			window.sliMpd.modules.AbstractPlayer.prototype.refreshInterval.call(this);
		},

		onRedrawComplete(item) {
			var that = this;
			$(".mpd-ctrl-seekbar").on("click", function(e){
				var percent = Math.round((e.pageX - $(this).offset().left) / ($(this).width()/100));
				$(".mpd-status-progressbar", that.$el).css("width", percent+"%");
				that.process({"action": "seek", "mpdurl" : window.sliMpd.conf.absRefPrefix + "mpdctrl/seekPercent/" + percent});
				that.timelineSetValue(percent);
			});
			$(".mpd-ctrl-seekzero", this.$el).on("click", function(e){
				that.seekzero();
			});

			// do not show notification on initial load
			if(that.initialNotificationBlocker === false) {
				var mpdNotify = $("<div/>")
					.append(
						$("<div/>")
						.attr("class", "row")
						.append(
							$("<div/>")
							.attr("class", "col-md-2")
							.append(
								$("<img/>")
									.attr("src", $(".player-mpd img").attr("src"))
									.attr("width", "70")
							)
						)
						.append(
							$("<div/>")
							.attr("class", "col-md-10")
							.append(
								"<span class='uc dark small'>MPD trackchange</span><br>" + $(".player-mpd .now-playing-string").text()
							)
						)
					);
				window.sliMpd.notify({
					"notify":1,
					"type": "mpd",
					"message": $(mpdNotify).html()
				});
			}
			that.initialNotificationBlocker = false;
			window.sliMpd.modules.AbstractPlayer.prototype.onRedrawComplete.call(this, item);
		},

		updateStateIcons() {
			window.sliMpd.modules.AbstractPlayer.prototype.updateStateIcons.call(this);
		},

		// TODO: make markup more generic and move this to AbstractPlayer for usage in both players (local+mpd)
		setPlayPauseIcon(item) {
			if(this.nowPlayingState === "play") {
				$(".mpd-status-playpause", this.$el).addClass("fa-pause");
				$(".mpd-status-playpause", this.$el).removeClass("fa-play");
			} else {
				$(".mpd-status-playpause", this.$el).removeClass("fa-pause");
				$(".mpd-status-playpause", this.$el).addClass("fa-play");
			}
			window.sliMpd.modules.AbstractPlayer.prototype.setPlayPauseIcon.call(this, item);
		},

		formatTime(seconds) {
			if(typeof seconds === "undefined") {
				return "-- : --";
			}
			seconds 	= Math.round(seconds);
			var hour 		= Math.floor(seconds / 3600);
			var minutes 	= Math.floor(seconds / 60) % 60;
			seconds 		= seconds % 60;

			if (hour > 0) {
				return hour + ":" + this.zeroPad(minutes, 2) + ":" + this.zeroPad(seconds, 2);
			}
			return minutes + ":" + this.zeroPad(seconds, 2);
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
			$(".mpd-status-progressbar").css("width", this.timeLineLight.progress() *100 +"%");
			window.sliMpd.modules.AbstractPlayer.prototype.updateSlider.call(this, item);
		}
	});
}());
