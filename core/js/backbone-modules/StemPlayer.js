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
 * FITNESS FOR A PARTICULAR PURPOSE. See the GNU Affero General Public License
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
    window.sliMpd.modules.StemPlayer = window.sliMpd.modules.AbstractPlayer.extend({
        mode : "stem",
        playerSelector : "",

        faviconDoghnutColor : window.sliMpd.conf.color.local.favicon,
        faviconBackgroundColor : "#444",

        playerIndex : false,
        isSoloed : false,
        isMuted : false,
        isIsolated : false,

        starttime : 0,

        timeGridSelectorCanvas : "timegrid-stem",
        timeGridSelectorSeekbar : ".jp-seek-bar",
        timeGridStrokeColor : window.sliMpd.conf.color.local.secondary,
        timeGridStrokeColor2 : window.sliMpd.conf.color.local.primary,
        
        // @see core/templates/partials/jsincludes/conf.htm
        colorMapping : {
                0: "red",
                1: "pink",
                2: "cyan",
                3: "orange",
                4: "green",
                5: "blue",
                6: "yellow"
        },

        state : {
            repeat : 1,
            random : 1,
            consume : 0
        },

        // TODO: remove property as soon as local player has full functionality
        tempNotSupportedYetNotify : {"message": "not supported in <strong>local player</strong> yet - use <strong>mpd</strong>", "type": "danger"},

        initialize : function(options) {
            this.playerIndex = options.playerIndex;
            this.playerSelector = "#jquery_stemplayer_"+options.playerIndex;
            
            
            if(typeof this.colorMapping[this.playerIndex] !== "undefined") {
                var col = window.sliMpd.conf.color[ this.colorMapping[this.playerIndex] ];
                this.timeGridStrokeColor2 = col.primary;
                $(".stemtrack-title", this.el).css("color", col.primary);
                $(".jp-volume-bar-value", this.el).css("background-color", "#353535" /*col.secondary*/);
            }
            var that = this;
            /*
            $.jPlayer.prototype.seekBar = function(e) { // Handles clicks on the seekBar
                console.log("seekBar copy");
                if(this.css.jq.seekBar.length) {
                    // Using $(e.currentTarget) to enable multiple seek bars
                    var $bar = $(e.currentTarget),
                        offset = $bar.offset(),
                        x = e.pageX - offset.left,
                        w = $bar.width(),
                        p = 100 * x / w;
                    this.playHead(p);
                    console.log(p);
                }
            };
            */
            
             
            /* init local player */
            $(this.playerSelector).jPlayer({
                //cssSelectorAncestor: "#jp_container_1",
                //cssSelectorAncestor: "#container_stemplayer_0",
                cssSelectorAncestor: "#stem-container",
                //cssSelectorAncestor: this.playerSelector,
                swfPath: window.sliMpd.conf.absFilePrefix + "vendor-dist/happyworm/jplayer/dist/jplayer",
                supplied: "mp3",
                useStateClassSkin: false,
                autoBlur: false,
                smoothPlayBar: true,
                keyEnabled: false,
                remainingDuration: false,
                preload: 'auto',
                toggleDuration: true,
                ended : function() {
                    that.soundEnded({});
                },
                progress : function(e,data){
                    //console.log($(this).data("jPlayer").status);
                    var jStatus = $(this).data("jPlayer").status;
                    that.nowPlayingPercent = jStatus.currentPercentAbsolute;
                    that.nowPlayingState = (jStatus.paused === false && jStatus.currentTime > 0) ? "play" : "pause";
                    that.nowPlayingDuration = jStatus.duration;
                    that.nowPlayingElapsed = jStatus.currentTime;
                    //that.nowPlayingItem = jStatus.src;

                    // TODO: check why jPlayer event "loadedmetadata" sometimes has no duration (timegrid fails to render)
                    // draw the timegrid only once as soon as we know the total duration and remove the progress eventListener
                    // @see: http://jplayer.org/latest/developer-guide/#jPlayer-events
                    if(this.playerIndex > 0) {
                        return;
                    }
                    that.drawTimeGrid();
                },
                seeked : function() {
                    window.sliMpd.drawFavicon();
                },
                cssSelector: {
                    seekBar: ".jp-seek-bar",
                    playBar: (( that.playerIndex == 0) ? ".jp-play-bar" : ""),
                    volumeBar: "#container_stemplayer_" + that.playerIndex + " .jp-volume-bar",
                    volumeBarValue: "#container_stemplayer_" + that.playerIndex + " .jp-volume-bar-value",
                    currentTime: (( that.playerIndex == 0) ? ".jp-current-time" : ""),
                    duration: (( that.playerIndex == 0) ? ".jp-duration" : "")
                },
                volumeBar: function(e) { // Handles clicks on the volumeBar
                    if(this.css.jq.volumeBar.length) {
                        // Using $(e.currentTarget) to enable multiple volume bars
                        var $bar = $(e.currentTarget),
                            offset = $bar.offset(),
                            x = e.pageX - offset.left,
                            w = $bar.width(),
                            y = $bar.height() - e.pageY + offset.top,
                            h = $bar.height();
                        if(this.options.verticalVolume) {
                            this.volume(y/h);
                        } else {
                            this.volume(x/w);
                        }
                    }
                    if(this.options.muted) {
                        this._muted(false);
                    }
                }
            }).bind($.jPlayer.event.timeupdate, that.updateTimer);


            this.updateStateIcons();
            window.sliMpd.modules.AbstractPlayer.prototype.initialize.call(this, options);
        },

        updateTimer : function(event) {
            var status = event.jPlayer.status;
            //console.log(status.duration);
            //console.log(status.currentTime);
            //console.log("this.", $(this).data("starttime"));
            //console.log("this.starttime", this.starttime);
            $('.jp-current-time', this.$el).text($.jPlayer.convertTime($(this).data("starttime") + status.currentTime));
            //$('.jp-duration', this.$el).text($.jPlayer.convertTime(delta+status.duration - status.currentTime));
        },

        render : function(options) {
            window.sliMpd.modules.AbstractPlayer.prototype.render.call(this, options);
        },

        loadTrack : function(item) {
            //console.log(item);
            // TODO: check why item.ext is sometimes "vorbis" instead of "ogg"             
            item.ext = (item.ext === "vorbis") ? "ogg" : item.ext;

            // WARNING: jPlayer"s essential Audio formats are: mp3 or m4a.
            // wav, flac, ogg, m4a plays fine in chromium under linux but we have to add an unused mp3-property...
            // TODO: really provide alternative urls instead of adding an invalid url for mp3
            var mp3Url = "//" + sliMpd.conf.subdomainPattern.replace("%d", this.playerIndex)+"/deliver/" + item.item + "?stream=1";
            var jPlayerConfObject = {
                "mp3" : mp3Url,
                "supplied": item.ext + ",mp3"
            };
            if($(this.playerSelector).data("volume")) {
                $(this.playerSelector).jPlayer(
                    "volume",
                    $(this.playerSelector).data("volume")
                );
            }
            if($(this.playerSelector).data("starttime")) {
                this.starttime = $(this.playerSelector).data("starttime");
            }

            jPlayerConfObject[item.ext] = mp3Url;
            jPlayerConfObject.wmode = "window";
            $(this.playerSelector).jPlayer(
                "setMedia",
                jPlayerConfObject
            );
            this.nowPlayingItem = item.hash;

            this.drawWaveform();

            //this.redraw(item);
            //this.reloadCss(item.hash);
        },

        play : function(item) {
            $(this.playerSelector).jPlayer("play");
        },

        pause : function(item) {
            $(this.playerSelector).jPlayer("pause");
        },

        isPlaying : function() {
            return !$(this.playerSelector).data().jPlayer.status.paused;
        },

        destroy : function() {
            $(this.playerSelector).jPlayer("destroy");
        },

        isolate : function(item) {
            //this.isIsolated = true;
            $(".isolate-stem", this.$el).addClass("btn-violet");
            return this;
        },

        unisolate : function(item) {
            //this.isIsolated = false;
            $(".isolate-stem", this.$el).removeClass("btn-violet");
            return this;
        },

        solo : function(item) {
            //this.isSoloed = true;
            $(".solo-stem", this.$el).addClass("btn-yellow");
            return this;
        },

        unsolo : function(item) {
            //this.isSoloed = false;
            $(".solo-stem", this.$el).removeClass("btn-yellow");
            return this;
        },

        mute : function(item) {
            //this.isMuted = true;
            $(".mute-stem", this.$el).addClass("btn-red");
            return this;
        },

        unmute : function(item) {
            //this.isMuted = false;
            $(".mute-stem", this.$el).removeClass("btn-red");
            return this;
        },

        muteInternal : function(item) {
            $(this.playerSelector).jPlayer("mute");
            return this;
        },

        unmuteInternal : function(item) {
            $(this.playerSelector).jPlayer("unmute");
            return this;
        },

        prev : function(item) {
            window.sliMpd.notify(this.tempNotSupportedYetNotify);
            window.sliMpd.modules.AbstractPlayer.prototype.prev.call(this, item);
        },

        next : function(item) {
            this.soundEnded(item);
            window.sliMpd.modules.AbstractPlayer.prototype.next.call(this, item);
        },

        seekzero : function(item) {
            $(this.playerSelector).jPlayer("playHead", 0);
            window.sliMpd.modules.AbstractPlayer.prototype.seekzero.call(this, item);
        },

        redraw : function(item) {
            window.sliMpd.modules.AbstractPlayer.prototype.redraw.call(this, item);
        },

        onRedrawComplete : function(item) {
            // re-bind controls(seeek-bar) on ajax loaded control-markup
            $(this.playerSelector).jPlayer({cssSelectorAncestor: "#jp_container_1"});
            var that = this;
            $(".stem-ctrl-seekzero", this.$el).on("click", function(e){
                that.seekzero();
            });
            this.updateStateIcons();
            window.sliMpd.modules.AbstractPlayer.prototype.onRedrawComplete.call(this, item);
        },

        updateStateIcons : function() {
            window.sliMpd.modules.AbstractPlayer.prototype.updateStateIcons.call(this);
        },

        // TODO: make markup more generic and move this to AbstractPlayer
        setPlayPauseIcon : function(item) {
            var control = $(".stemplayer-play-pause");
            if (this.nowPlayingState === "play") {
                $(control).addClass("stemplayer-pause").removeClass("stemplayer-play").html("<i class='fa fa-pause sign-ctrl fa-lg'></i>");
            } else {
                $(control).addClass("stemplayer-play").removeClass("stemplayer-pause").html("<i class='fa fa-play sign-ctrl fa-lg'></i>");
            }
            window.sliMpd.drawFavicon();
            window.sliMpd.modules.AbstractPlayer.prototype.setPlayPauseIcon.call(this, item);
        },

        pause : function(item) {
            $(this.playerSelector).jPlayer("pause");
            this.nowPlayingState = "pause";
            window.sliMpd.modules.AbstractPlayer.prototype.pause.call(this, item);
            this.setPlayPauseIcon(item);
        },

        togglePause : function(item) {
            if (this.nowPlayingState === "play") {
                $(this.playerSelector).jPlayer("pause");
                this.nowPlayingState = "pause";
            } else {
                $(this.playerSelector).jPlayer("play");
                this.nowPlayingState = "play";
            }
            window.sliMpd.modules.AbstractPlayer.prototype.togglePause.call(this, item);
            this.setPlayPauseIcon(item);
        },

        soundEnded : function(item) {
            // TODO: add functionality "current playlist" (like mpd) for local player 
            // for now use all rendered tracks as playlist

            //console.log("local soundEnded()");
            if(this.state.random === 1) {
                //console.log("local random is active");
                var playableItems = $("#main .track-row:not(.track-"+ this.nowPlayingItem+")");
                if(playableItems.length < 1) {
                    return;
                }
                var randomIndex = Math.floor(Math.random() * playableItems.length);
                playableItems.eq(randomIndex).find(".is-playbtn").click();
                return;
            }
            //console.log("local random is NOT active");
            // check if current track is rendered
            var current = $(".track-" + this.nowPlayingItem);
            if(!current.length) {
                //console.log("current track is not rendered. fallback to first rendered track...");
                $("#main .is-playbtn")[0].click();
                return;
            }
            //console.log("current track is rendered");
            var next = current.nextAll(".track-row").find(".is-playbtn");
            if(next.length) {
                //console.log("found next track");
                next[0].click();
                return;
            }
            //console.log("we have no next track. fallback to first rendered track...");
            $("#main .is-playbtn")[0].click();
        },

        /**
         * TODO: use abstract player drawWaveform()
         */
        drawWaveform : function() {
            // thanks to https://github.com/Idnan/SoundCloud-Waveform-Generator
            var $waveFormWrapper = $(".waveform-wrapper", this.$el);

            $waveFormWrapper.html(
                $("<p />").html("generating waveform...")
            );
            this.waveformSettings = {};
            this.waveformSettings.waveColor = this.timeGridStrokeColor2,
            this.waveformSettings.canvas = document.createElement("canvas"),
            this.waveformSettings.context = this.waveformSettings.canvas.getContext("2d");
            this.waveformSettings.canvas.width = $waveFormWrapper.width();
            this.waveformSettings.canvas.height = $waveFormWrapper.height();
            this.waveformSettings.barWidth = window.sliMpd.conf.waveform.barwidth;
            this.waveformSettings.barGap = window.sliMpd.conf.waveform.gapwidth;
            this.waveformSettings.mirrored = ($("body").hasClass("slimplayer"))
                ? 0
                : window.sliMpd.conf.waveform.mirrored;
            var that = this;

            $.ajax({
                url: $waveFormWrapper.attr("data-jsonurl"),
                dataType: "json",
                success : function(vals) {
                    
                    var len = Math.floor(vals.length / that.waveformSettings.canvas.width);
                    var maxVal = that.getMaxVal(vals);
                    if(maxVal === 0) {
                        // draw at least a one pixel line for 100% silence files
                        maxVal = 1;
                    }
                    for (var j = 0; j < that.waveformSettings.canvas.width; j += that.waveformSettings.barWidth) {
                        that.drawBar(
                            j,
                            (that.bufferMeasure(Math.floor(j * (vals.length / that.waveformSettings.canvas.width)), len, vals) * maxVal/10)
                            *
                            (that.waveformSettings.canvas.height / maxVal)
                            +
                            1
                        );
                    }
                    $waveFormWrapper.html("");
                    $(that.waveformSettings.canvas).appendTo($waveFormWrapper);
                },
                error : function(response) {
                    $waveFormWrapper.html(
                        $("<p />").html("error generating waveform...")
                    );
                }
            });
        }
    });
}());
