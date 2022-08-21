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
    window.sliMpd.modules.TimelinedPlayer = window.sliMpd.modules.AbstractPlayer.extend({

        timeLineLight : null,
        showWaveform : true,

        initialize : function(options) {
            this.trackAnimation = { currentPosPerc: 0 };
            if(this.showWaveform === true) {
                this.timeLineLight = window.gsap.timeline();
            }
            window.sliMpd.modules.AbstractPlayer.prototype.initialize.call(this, options);
        },

        setPlayHead : function() {
            // animate from 0 to 100, onUpdate -> change Text
            this.timeLineLight = window.gsap.timeline();
            this.trackAnimation.currentPosPerc = 0;
            this.timeLineLight.to(this.trackAnimation, {
                duration: this.nowPlayingDuration,
                currentPosPerc: 100,
                ease: window.Linear.easeNone,
                onUpdate: this.updateSlider,
                onUpdateScope: this
            });

            if(this.nowPlayingState === "play") {
                this.timelineSetValue(this.nowPlayingPercent);
                return;
            }
            this.timeLineLight.pause();
        },

        timelineSetValue : function(value) {
            this.timeLineLight.progress(value/100);
            window.sliMpd.modules.AbstractPlayer.prototype.timelineSetValue.call(this, value);
        }
    });
}());
