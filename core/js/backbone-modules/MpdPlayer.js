/* Copyright (C) 2016 othmar52 <othmar52@users.noreply.github.com>
 *
 * This file is part of sliMpd - a php based mpd web client
 *
 * This program is free software: you can redistribute it and/or modify it
 * under the terms of the GNU Affero General Public License as published by the
 * Free Software Foundation, either version 3 of the License, or (at your
 * option) any later version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or
 * FITNESS FOR A PARTICULAR PURPOSE.  See the GNU Affero General Public License
 * for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */
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

		initialize : function(options) {
			this.$content = $(".player-"+ this.mode, this.$el);

			this.trackAnimation = { currentPosPerc: 0 };
			this.timeLineLight = new window.TimelineLite();

			this.pollWorker = new Worker(window.sliMpd.conf.absFilePrefix + "core/js/poll-worker.js");
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

		render : function(options) {
			window.sliMpd.modules.AbstractPlayer.prototype.render.call(this, options);
		},

		play : function(item) {
			window.sliMpd.fireRequestAndNotify(item.mpdurl);
			this.redraw();
			//this.reloadCss(item.hash);
			window.sliMpd.modules.AbstractPlayer.prototype.play.call(this, item);
		},

		togglePause : function(item) {
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

		seek : function(item) {
			window.sliMpd.fireRequestAndNotify(item.mpdurl);
			window.sliMpd.modules.AbstractPlayer.prototype.seek.call(this, item);
		},

		seekzero : function(item) {
			window.sliMpd.fireRequestAndNotify(window.sliMpd.conf.absRefPrefix + "mpdctrl/seekPercent/0");
			this.timelineSetValue(0);
			window.sliMpd.modules.AbstractPlayer.prototype.seekzero.call(this, item);
		},

		prev : function(item) {
			window.sliMpd.fireRequestAndNotify(item.mpdurl);
			this.refreshInterval();
			window.sliMpd.modules.AbstractPlayer.prototype.prev.call(this, item);
		},

		next : function(item) {
			window.sliMpd.fireRequestAndNotify(item.mpdurl);
			this.refreshInterval();
			window.sliMpd.modules.AbstractPlayer.prototype.next.call(this, item);
		},

		removeTrack : function(item) {
			window.sliMpd.fireRequestAndNotify(item.mpdurl);
			this.refreshInterval();
			window.sliMpd.modules.AbstractPlayer.prototype.removeTrack.call(this, item);
		},

		toggleRepeat : function(item) {
			window.sliMpd.fireRequestAndNotify(item.mpdurl);
			window.sliMpd.modules.AbstractPlayer.prototype.toggleRepeat.call(this, item);
		},

		toggleRandom : function(item) {
			window.sliMpd.fireRequestAndNotify(item.mpdurl);
			window.sliMpd.modules.AbstractPlayer.prototype.toggleRandom.call(this, item);
		},

		toggleConsume : function(item) {
			window.sliMpd.fireRequestAndNotify(item.mpdurl);
			window.sliMpd.modules.AbstractPlayer.prototype.toggleConsume.call(this, item);
		},

		softclearPlaylist : function(item) {
			window.sliMpd.fireRequestAndNotify(item.mpdurl);
			this.refreshInterval();
			window.sliMpd.modules.AbstractPlayer.prototype.softclearPlaylist.call(this, item);
		},

		appendTrack : function(item) {
			window.sliMpd.fireRequestAndNotify(item.mpdurl);
			this.refreshInterval();
			window.sliMpd.modules.AbstractPlayer.prototype.appendTrack.call(this, item);
		},
		appendTrackAndPlay : function(item) {
			window.sliMpd.fireRequestAndNotify(item.mpdurl);
			this.refreshInterval();
			window.sliMpd.modules.AbstractPlayer.prototype.appendTrackAndPlay.call(this, item);
		},

		injectTrack : function(item) {
			window.sliMpd.fireRequestAndNotify(item.mpdurl);
			this.refreshInterval();
			window.sliMpd.modules.AbstractPlayer.prototype.injectTrack.call(this, item);
		},

		injectTrackAndPlay : function(item) {
			window.sliMpd.fireRequestAndNotify(item.mpdurl);
			this.refreshInterval();
			window.sliMpd.modules.AbstractPlayer.prototype.injectTrackAndPlay.call(this, item);
		},

		replaceTrack : function(item) {
			window.sliMpd.fireRequestAndNotify(item.mpdurl);
			this.refreshInterval();
			window.sliMpd.modules.AbstractPlayer.prototype.replaceTrack.call(this, item);
		},

		softreplaceTrack : function(item) {
			window.sliMpd.fireRequestAndNotify(item.mpdurl);
			this.refreshInterval();
			window.sliMpd.modules.AbstractPlayer.prototype.softreplaceTrack.call(this, item);
		},


		appendDir : function(item) {
			window.sliMpd.fireRequestAndNotify(item.mpdurl);
			this.refreshInterval();
			window.sliMpd.modules.AbstractPlayer.prototype.appendDir.call(this, item);
		},

		appendDirAndPlay : function(item) {
			window.sliMpd.fireRequestAndNotify(item.mpdurl);
			this.refreshInterval();
			window.sliMpd.modules.AbstractPlayer.prototype.appendDirAndPlay.call(this, item);
		},

		injectDir : function(item) {
			window.sliMpd.fireRequestAndNotify(item.mpdurl);
			this.refreshInterval();
			window.sliMpd.modules.AbstractPlayer.prototype.injectDir.call(this, item);
		},

		injectDirAndPlay : function(item) {
			window.sliMpd.fireRequestAndNotify(item.mpdurl);
			this.refreshInterval();
			window.sliMpd.modules.AbstractPlayer.prototype.injectDirAndPlay.call(this, item);
		},

		replaceDir : function(item) {
			window.sliMpd.fireRequestAndNotify(item.mpdurl);
			this.refreshInterval();
			window.sliMpd.modules.AbstractPlayer.prototype.replaceDir.call(this, item);
		},

		softreplaceDir : function(item) {
			window.sliMpd.fireRequestAndNotify(item.mpdurl);
			window.sliMpd.modules.AbstractPlayer.prototype.softreplaceDir.call(this, item);
		},


		appendPlaylist : function(item) {
			window.sliMpd.fireRequestAndNotify(item.mpdurl);
			this.refreshInterval();
			window.sliMpd.modules.AbstractPlayer.prototype.appendPlaylist.call(this, item);
		},

		appendPlaylistAndPlay : function(item) {
			window.sliMpd.fireRequestAndNotify(item.mpdurl);
			window.sliMpd.modules.AbstractPlayer.prototype.appendPlaylistAndPlay.call(this, item);
		},

		injectPlaylist : function(item) {
			window.sliMpd.fireRequestAndNotify(item.mpdurl);
			this.refreshInterval();
			window.sliMpd.modules.AbstractPlayer.prototype.injectPlaylist.call(this, item);
		},

		injectPlaylistAndPlay : function(item) {
			window.sliMpd.fireRequestAndNotify(item.mpdurl);
			this.refreshInterval();
			window.sliMpd.modules.AbstractPlayer.prototype.injectPlaylistAndPlay.call(this, item);
		},

		replacePlaylist : function(item) {
			window.sliMpd.fireRequestAndNotify(item.mpdurl);
			this.refreshInterval();
			window.sliMpd.modules.AbstractPlayer.prototype.replacePlaylist.call(this, item);
		},

		softreplacePlaylist : function(item) {
			window.sliMpd.fireRequestAndNotify(item.mpdurl);
			this.refreshInterval();
			window.sliMpd.modules.AbstractPlayer.prototype.softreplacePlaylist.call(this, item);
		},

		removeDupes : function(item) {
			window.sliMpd.fireRequestAndNotify(item.mpdurl);
			this.refreshInterval();
			window.sliMpd.modules.AbstractPlayer.prototype.removeDupes.call(this, item);
		},

		process : function(item) {
			window.sliMpd.modules.AbstractPlayer.prototype.process.call(this, item);
		},

		processPollData : function(data){

			this.nowPlayingPercent = data.percent;
			this.nowPlayingState = data.state;
			this.nowPlayingDuration = data.duration;
			this.nowPlayingElapsed = data.elapsed;
			this.nowPlayingItem = data.songid;

			this.state.repeat = parseInt(data.repeat);
			this.state.random = parseInt(data.random);
			this.state.consume = parseInt(data.consume);

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

		refreshInterval : function() {
			window.sliMpd.modules.AbstractPlayer.prototype.refreshInterval.call(this);
		},

		onRedrawComplete : function(item) {
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

		updateStateIcons : function() {
			window.sliMpd.modules.AbstractPlayer.prototype.updateStateIcons.call(this);
		},

		// TODO: make markup more generic and move this to AbstractPlayer for usage in both players (local+mpd)
		setPlayPauseIcon : function(item) {
			if(this.nowPlayingState === "play") {
				$(".mpd-status-playpause", this.$el).addClass("fa-pause");
				$(".mpd-status-playpause", this.$el).removeClass("fa-play");
			} else {
				$(".mpd-status-playpause", this.$el).removeClass("fa-pause");
				$(".mpd-status-playpause", this.$el).addClass("fa-play");
			}
			window.sliMpd.modules.AbstractPlayer.prototype.setPlayPauseIcon.call(this, item);
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
			$(".mpd-status-progressbar").css("width", this.timeLineLight.progress() *100 +"%");
			window.sliMpd.modules.AbstractPlayer.prototype.updateSlider.call(this, item);
		}
	});
}());
