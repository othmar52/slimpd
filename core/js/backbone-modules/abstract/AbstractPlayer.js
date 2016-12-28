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
    window.sliMpd.modules.AbstractPlayer = window.sliMpd.modules.AbstractView.extend({

        name : null,
        rendered : false,
        mode : "",

        nowPlayingPercent : 0,
        nowPlayingState : "pause",
        nowPlayingDuration : 0,
        nowPlayingElapsed : 0,
        nowPlayingItem : "",
        previousPlayingItem : "",

        state : {
            repeat : 0,
            random : 0,
            consume : 0
        },

        faviconDoghnutColor : "#000000",
        faviconBackgroundColor : "transparent",

        timeGridSelectorCanvas : "",
        timeGridSelectorSeekbar : "",
        timeGridStrokeColor : "",
        timeGridStrokeColor2 : "",

        showWaveform : true,

        intervalActive : 2000, // [ms]
        intervalInactive : 5000, // [ms]

        waveformSettings : { },

        initialize : function(options) {
            this.render();
            window.sliMpd.modules.AbstractView.prototype.initialize.call(this, options);
        },

        render : function(renderMarkup) {
            if (this.rendered) {
                return;
            }

            if (renderMarkup) {
                this.$el.html($(this._template((this.model || {}).attributes)));
            }

            window.sliMpd.modules.AbstractView.prototype.render.call(this);

            this.rendered = true;
        },

        // fetch markup with trackinfos
        redraw : function(item) {
            item = item || { item : 0};
            var url =  window.sliMpd.conf.absRefPrefix + "markup/"+ this.mode+"player";
            url = window.sliMpd.router.setGetParameter(url, "item", item.item);
            if(this.mode === "xwax") {
                url = window.sliMpd.router.setGetParameter(url, "deck", this.deckIndex);
                if(this.showWaveform === false) {
                    url = window.sliMpd.router.setGetParameter(url, "type", "djscreen");
                } 
            }

            $.ajax({
                url : url
            }).done(function(response){
                // place markup in DOM
                this._template = _.template(response);
                this.rendered = false;
                this.render(true);
                this.onRedrawComplete(item);
                this.reloadCss(item.hash);
            }.bind(this));
        },
        onRedrawComplete : function(item) { return; },

        // highlight state icons when state is active
        updateStateIcons : function() {
            var that = this;
            ["repeat", "random", "consume"].forEach(function(prop) {
                $(".status-"+prop, that.$el)[
                    (that.state[prop] === 1) ? "addClass" : "removeClass"
                ]("active");
            });
        },

        process : function(item) {
            if(typeof this[item.action] === "function") {
                this[item.action](item);
                return;
            }
            //console.log("ERROR: invalid action \""+ item.action +"\" in "+ this.mode +"Player-item. exiting...");
            return;
        },

        // define those methods in inherited implementation of AbstractPlayer
        play : function(item) { return; },
        pause : function(item) { return; },
        togglePause : function(item) { return; },
        toggleRepeat : function(item) {
            this.state.repeat = (this.state.repeat === 1) ? 0 : 1;
            this.updateStateIcons();
        },
        toggleRandom : function(item) {
            this.state.random = (this.state.random === 1) ? 0 : 1;
            this.updateStateIcons();
        },
        toggleConsume : function(item) {
            this.state.consume = (this.state.consume === 1) ? 0 : 1;
            this.updateStateIcons();
        },
        setPlayPauseIcon : function() { return; },
        next : function(item) { return; },
        prev : function(item) { return; },
        seek : function(item) { return; },
        seekzero : function(item) { return; },
        remove : function() {
            window.sliMpd.modules.AbstractView.prototype.remove.call(this);
        },

        softclearPlaylist : function(item) { return; },

        appendTrack : function(item) { return; },
        appendTrackAndPlay : function(item) { return; },
        injectTrack : function(item) { return; },
        injectTrackAndPlay : function(item) { return; },
        replaceTrack : function(item) { return; },
        softreplaceTrack : function(item) { return; },

        appendDir : function(item) { return; },
        appendDirAndPlay : function(item) { return; },
        injectDir : function(item) { return; },
        injectDirAndPlay : function(item) { return; },
        replaceDir : function(item) { return; },
        softreplaceDir : function(item) { return; },

        appendPlaylist : function(item) { return; },
        appendPlaylistAndPlay : function(item) { return; },
        injectPlaylist : function(item) { return; },
        injectPlaylistAndPlay : function(item) { return; },
        replacePlaylist : function(item) { return; },
        softreplacePlaylist : function(item) { return; },

        soundEnded : function(item) { return; },
        removeDupes : function(item) { return; },
        removeTrack : function(item) { return; },

        reloadCss : function(hash) {
            /**
             * visual now-playing indicator is solved by requesting a dynamically created css
             * @see <head><style id="css-[playertype]">
             */
            var suffix, selector;
            suffix = "";
            selector = "#css-"+this.mode+"player";
            if(this.mode === "xwax") {
                suffix = "?deck=" + this.deckIndex;
                selector = "#css-xwaxdeck-"+ this.deckIndex;
            }
            $(selector).attr("href", window.sliMpd.conf.absRefPrefix + "css/"+ this.mode +"player/"+ ((hash) ? hash : "0") + suffix);

        },

        drawFavicon : function() {
            window.FavIconX.config({
                updateTitle: false,
                shape: "doughnut",
                doughnutRadius: 7,
                overlay: this.nowPlayingState,
                overlayColor : this.faviconDoghnutColor,
                borderColor: this.faviconDoghnutColor,
                fillColor: this.faviconDoghnutColor,
                borderWidth : 1.2,
                backgroundColor : this.faviconBackgroundColor,
                titleRenderer : function(v, t){
                    var nowPlaying = $(".player-" + window.sliMpd.currentPlayer.mode + " .now-playing-string").text();
                    return nowPlaying === "" ? t : t + " - " + nowPlaying;
                }
            }).setValue(this.nowPlayingPercent);
        },

        drawTimeGrid : function() {
            if(this.nowPlayingDuration <= 0) {
                return;
            }

            var cnv = document.getElementById(this.timeGridSelectorCanvas);
            if(cnv === null) {
                return;
            }
            var width = $(this.timeGridSelectorSeekbar).width();
            var height = 10;

            $("."+this.timeGridSelectorCanvas).css("width", width + "px");
            cnv.width = width;
            cnv.height = height;
            var ctx = cnv.getContext("2d");

            var strokePerHour = 60;
            var changeColorAfter = 5;
            //$.jPlayer.timeFormat.showHour = false;

            // draw stroke at zero-position
            ctx.fillStyle = this.timeGridStrokeColor2;
            ctx.fillRect(0,0,1,height);

            // longer than 30 minutes
            if(this.nowPlayingDuration > 1800) {
                strokePerHour = 12;
                changeColorAfter = 6;
            }

            // longer than 1 hour
            if(this.nowPlayingDuration > 3600) {
                strokePerHour = 6;
                changeColorAfter = 6;
                //$.jPlayer.timeFormat.showHour = true;
            }
            var pixelGap = width / this.nowPlayingDuration * (3600/ strokePerHour); 

            for (var i=0; i < this.nowPlayingDuration/(3600/strokePerHour); i++) {
                ctx.fillStyle = ((i+1)%changeColorAfter === 0) ? this.timeGridStrokeColor2 : this.timeGridStrokeColor;
                ctx.fillRect(pixelGap*(i+1),0,1,height);
            }

            ctx.globalCompositeOperation = "destination-out";
            ctx.fill();
        },

        drawWaveform : function() {
            // thanks to https://github.com/Idnan/SoundCloud-Waveform-Generator
            var $waveFormWrapper = $("#"+ this.mode +"-waveform-wrapper");
            $waveFormWrapper.html(
                $("<p />").html("generating waveform...")
            );

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
        },

        bufferMeasure : function(position, length, data) {
            var sum = 0.0;
            for (var i = position; i <= (position + length) - 1; i++) {
                sum += Math.pow(data[i], 2);
            }
            return Math.sqrt(sum / data.length);
        },

        drawBar : function(i, h) {
            this.waveformSettings.context.fillStyle = this.waveformSettings.waveColor;
            var w = this.waveformSettings.barWidth;
            if (this.waveformSettings.barGap !== 0) {
                w *= Math.abs(1 - this.waveformSettings.barGap);
            }
            var x = i + (w / 2);
            var y = this.waveformSettings.canvas.height - h;

            if(this.waveformSettings.mirrored === 1) {
                y /=2;
            }

            this.waveformSettings.context.fillRect(x, y, w, h);
        },

        getMaxVal : function(inputArray) {
            var max = 0;
            for(var i=0; i<inputArray.length; i++) {
                max = (inputArray[i] > max) ? inputArray[i] : max;
            }
            return max;
        },

        formatTime : function(seconds) {
            if(typeof seconds === "undefined") {
                return "-- : --";
            }
            seconds     = Math.round(seconds);
            var hour     = Math.floor(seconds / 3600);
            var minutes = Math.floor(seconds / 60) % 60;
            seconds     = seconds % 60;

            if (hour > 0) {
                return hour + ":" + this.zeroPad(minutes, 2) + ":" + this.zeroPad(seconds, 2);
            }
            return minutes + ":" + this.zeroPad(seconds, 2);
        },

        zeroPad : function(number, n) {
            var zeroPad = "" + number;
            while(zeroPad.length < n) {
                zeroPad = "0" + zeroPad;
            }
            return zeroPad;
        },

        /* only for polled mpd player implementation - begin */
        refreshInterval : function() {
            this.pollWorker.postMessage({
                cmd: "refreshInterval"
            });
        },
        pollWorker : null,
        processPollData : function(data) { return; },
        /* only for polled mpd player implementation - end */


        /* only for mpd player progressbar implementation/interpolation - begin */
        trackAnimation : null,
        timeLineLight : null,
        timelineSetValue : function(value) { return; },
        updateSlider : function() { return; }
        /* only for polled mpd player implementation/interpolation  - end */
    });
}());
