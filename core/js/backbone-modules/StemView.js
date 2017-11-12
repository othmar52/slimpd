/* Copyright (C) 2017 othmar52 <othmar52@users.noreply.github.com>
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
    window.sliMpd.modules.StemView = window.sliMpd.modules.PageView.extend({

        name : null,
        rendered : false,
        playerViews : [],
        
        totalPlayers : 0,

        initialize : function(options) {
            window.sliMpd.modules.PageView.prototype.initialize.call(this, options);
            //console.log("StemView::init()");
        },

        render : function(renderMarkup) {
            if (this.rendered) {
                return;
            }
            window.sliMpd.modules.PageView.prototype.render.call(this, renderMarkup);
            //console.log("StemView::render()");
            this.showPlayerGui();
            
            $(".toggleplay-stem", this.el).off("click", this.togglePlayStem).on("click", this.togglePlayStem);
            $(".isolate-stem", this.el).off("click", this.toggleIsolateStem).on("click", this.toggleIsolateStem);
            $(".solo-stem", this.el).off("click", this.toggleSoloStem).on("click", this.toggleSoloStem);
            $(".mute-stem", this.el).off("click", this.toggleMuteStem).on("click", this.toggleMuteStem);
            $(".unsolo-stems", this.el).off("click", this.unsoloAllStems).on("click", this.unsoloAllStems);
            $(".unmute-stems", this.el).off("click", this.unmuteAllStems).on("click", this.unmuteAllStems);
            this.rendered = true;
        },

        showPlayerGui : function() {
            //console.log("StemView::showPlayerGui()", this.$el);
            // create a player instance for each track
            var elements = $(".jp-jplayer", this.$el);

            for(var i=0; i< elements.length; i++) {
                //console.log("rendering player " + this.totalPlayers);
                this.totalPlayers ++;
                var playerView = new window.sliMpd.modules.StemPlayer({
                    el : "#container_stemplayer_"+i,
                    playerIndex : i,
                    showWaveform : this.showWaveform
                });
                
                // TODO: find out why player views only work on initial load
                //playerView._template = _.template($("#container_stemplayer_"+i, this.$el).prop('outerHTML'));
                //playerView.rendered = false;
                //playerView.render(true);
                
                playerView.loadTrack($(elements[i]).data());
                this.playerViews.push(playerView);
            }
        },

        remove : function() {
            $(".toggleplay-stem", this.$el).off("click", this.togglePlayStem);
            $(".isolate-stem", this.el).off("click", this.toggleIsolateStem)
            $(".solo-stem", this.$el).off("click", this.toggleSoloStem);
            $(".mute-stem", this.$el).off("click", this.toggleMuteStem);
            $(".unsolo-stems", this.el).off("click", this.unsoloAllStems);
            $(".unmute-stems", this.el).off("click", this.unmuteAllStems);
            for(var i=0; i< this.totalPlayers; i++) {
                //console.log("destroying jPlayer ", this.playerViews[i]);
                this.playerViews[i].destroy();
            }
            window.sliMpd.modules.PageView.prototype.remove.call(this);
        },

        togglePlayStem : function(e) {
            var cmd = (this.playerViews[0].isPlaying() === true) ? "pause" : "play";
            for(var i=0; i< this.totalPlayers; i++) {
                this.playerViews[i][cmd]();
            }
            $(".toggleplay-stem", this.$el).text(
                ((cmd === "pause") ? "PLAY" : "PAUSE") + " STEM"
            );
        },


        toggleIsolateStem : function(e) {
            $(e.currentTarget).blur();
            var currentIndex = $(e.currentTarget).data("stemtrack");
            if(this.playerViews[currentIndex].isIsolated === true) {
                this.unisolateStem(currentIndex);
                return;
            }
            this.isolateStem(currentIndex);
        },

        isolateStem : function(stemIndex) {
            for(var i=0; i< this.totalPlayers; i++) {
                this.playerViews[i].isIsolated = false;
            }
            this.playerViews[stemIndex].isIsolated = true;
            this.setVolumesAndButtonStates();
        },

        unisolateStem : function(stemIndex) {
            this.playerViews[stemIndex].isIsolated = false;
            this.setVolumesAndButtonStates();
        },

        toggleSoloStem : function(e) {
            $(e.currentTarget).blur();
            var currentIndex = $(e.currentTarget).data("stemtrack");
            if(this.playerViews[currentIndex].isSoloed === true) {
                this.unsoloStem(currentIndex);
                return;
            }
            this.soloStem(currentIndex);
        },

        soloStem : function(stemIndex) {
            this.playerViews[stemIndex].isSoloed = true;
            this.setVolumesAndButtonStates();
        },

        unsoloStem : function(stemIndex) {
            console.log("calling unsoloStem() for index" + stemIndex);
            this.playerViews[stemIndex].isSoloed = false;
            this.setVolumesAndButtonStates();
        },

        toggleMuteStem : function(e) {
            $(e.currentTarget).blur();
            var currentIndex = $(e.currentTarget).data("stemtrack");
            if(this.playerViews[currentIndex].isMuted === true) {
                this.unmuteStem(currentIndex);
                return;
            }
            this.muteStem(currentIndex);
        },

        muteStem : function(stemIndex) {
            this.playerViews[stemIndex].isMuted = true;
            this.setVolumesAndButtonStates();
        },

        unmuteStem : function(stemIndex) {
            this.playerViews[stemIndex].isMuted = false;
            this.setVolumesAndButtonStates();
        },

        unsoloAllStems : function(e) {
            for(var i=0; i< this.totalPlayers; i++) {
                this.playerViews[i].isSoloed = false;
            }
            this.setVolumesAndButtonStates();
            $(e.currentTarget).blur();
        },

        setVolumesAndButtonStates : function () {
            
            var anyTrackIsolated = false;
            var anyTrackSoloed = false;
            for(var i=0; i< this.totalPlayers; i++) {
                if(this.playerViews[i].isIsolated === true) {
                    anyTrackIsolated = true;
                }
                if(this.playerViews[i].isSoloed === true) {
                    anyTrackSoloed = true;
                }
            }
            // highest priority isolate
            if(anyTrackIsolated === true) {
                for(var i=0; i< this.totalPlayers; i++) {
                    this.playerViews[i].unmute().unsolo();
                    if(this.playerViews[i].isIsolated === true) {
                        this.playerViews[i].isolate().unmuteInternal();
                        continue;
                    }
                    this.playerViews[i].unisolate().muteInternal();
                }
                return;
            }
            // 2nd priority isolate
            if(anyTrackSoloed === true) {
                for(var i=0; i< this.totalPlayers; i++) {
                    this.playerViews[i].unmute().unisolate();
                    if(this.playerViews[i].isSoloed === true) {
                        this.playerViews[i].solo().unmuteInternal();
                        continue;
                    }
                    this.playerViews[i].unsolo().muteInternal();
                }
                return;
            }
            // 3rd priority mute
            for(var i=0; i< this.totalPlayers; i++) {
                this.playerViews[i].unsolo().unisolate();
                if(this.playerViews[i].isMuted === true) {
                    this.playerViews[i].mute().muteInternal();
                    continue;
                }
                this.playerViews[i].unmute().unmuteInternal();
            }
        },

        unmuteAllStems : function(e) {
            for(var i=0; i< this.totalPlayers; i++) {
                this.playerViews[i].isMuted = false;
            }
            this.setVolumesAndButtonStates();
            $(e.currentTarget).blur();
        }
    });
}());
