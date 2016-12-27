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
    window.sliMpd.modules.XwaxPlayer = window.sliMpd.modules.TimelinedPlayer.extend({

        mode : "xwax",
        faviconDoghnutColor : "rgb(255,156,1)",
        faviconBackgroundColor : "#444",

        timeGridSelectorCanvas : "",
        timeGridSelectorSeekbar : ".xwax-ctrl-seekbar",
        timeGridStrokeColor : "#7B6137",
        timeGridStrokeColor2 : "#FCC772",

        deckIndex : false,
        timecode : "",

        initialize : function(options) {
            this.deckIndex = options.deckIndex;
            this.timeGridSelectorCanvas = "timegrid-xwax-deck-"+ this.deckIndex;
            this.$content = $(".player-"+ this.mode, this.$el);
            this.showWaveform = options.showWaveform;

            window.sliMpd.modules.TimelinedPlayer.prototype.initialize.call(this, options);

            this._template = _.template("<div class='show-no-connection'>xwax connection failed</div>");
            this.rendered = false;
            this.render(true);
        },

        render : function(options) {
            //console.log("calling XwaxPlayer::render() " + this.deckIndex);
            window.sliMpd.modules.AbstractPlayer.prototype.render.call(this, options);
        },

        onRedrawComplete : function(item) {
            if(this.showWaveform !== true) {
                this.updateTimecode(this.timecode);
                return;
            }
            this.setPlayHead();
            this.drawTimeGrid();
            window.sliMpd.modules.AbstractPlayer.prototype.onRedrawComplete.call(this, item);
        },

        updateStateIcons : function() {
            $(".xwax-deck-"+this.deckIndex+"-status-elapsed").text(this.formatTime(this.nowPlayingElapsed));
            $(".xwax-deck-"+this.deckIndex+"-status-total").text(this.formatTime(this.nowPlayingDuration));
            window.sliMpd.modules.AbstractPlayer.prototype.updateStateIcons.call(this);
        },

        timelineSetValue : function(value) {
            this.timeLineLight.progress(value/100);
            window.sliMpd.modules.AbstractPlayer.prototype.timelineSetValue.call(this, value);
        },

        updateSlider : function(item) {
            if(this.showWaveform !== true) {
                return;
            }
            // TODO: how to respect parents padding on absolute positioned div with width 100% ?
            $(".xwax-deck-"+ this.deckIndex+"-status-progressbar").css("width", "calc("+ this.timeLineLight.progress() *100 +"% - 15px)");
            window.sliMpd.modules.AbstractPlayer.prototype.TimelinedPlayer.call(this, item);
        },
        updateTimecode : function(timecode) {
            this.timecode = timecode;
            $(".xwax-deck-"+ this.deckIndex+ " .timecoder").text(timecode);
        }
    });

}());
