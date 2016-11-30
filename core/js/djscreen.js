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
$(document).ready(function() {
    "use strict";

    var $ = window.jQuery;
    window.sliMpd = $.extend(true, window.sliMpd, {
        modules : {},
        xwax : false,

        fireRequestAndNotify : function(url) {
            $.get(url).done(function(response) {
                window.sliMpd.checkNotify(response);
            });
        },

        checkNotify : function(endcodedResponse) {
            try {
                var notifyConf = JSON.parse(endcodedResponse);
                if (typeof notifyConf.notify !== "undefined") {
                    this.notify(notifyConf);
                }
            } catch(e) {
                //console.log(e + " no json response in SliMpd::checkNotify()");
            }
        },

        notify : function(notifyConf) {
            ////////////////////////////////////////////////
            // FIXME:
            // bootstrap-notify.min.js:1 Uncaught TypeError: Cannot read property "title" of undefined
            // check notify"s-template variable for title
            //////////////////////////////////////////////// 
            $.notify({
                // options
                message: notifyConf.message
            },{
                // settings
                type: (notifyConf.type || "info"),
                z_index: 10000
            });
            $.notify();
        },

        notifyError : function(errorUrl) {
            this.notify({
                message : "<h4>OOOPS!</h4> something went wrong...<br /><a class=\"alert-link\" target=\"_blank\" href=\""+ errorUrl+"\">" + errorUrl + "</a>",
                type : "danger"
            });
        }
    });

    window.sliMpd.router = new window.sliMpd.modules.Router();

    window.sliMpd.xwax = new window.sliMpd.modules.XwaxView({
        el : ".player-xwax",
        showWaveform : false
    });
    window.sliMpd.xwax.render();
    window.sliMpd.xwax.showXwaxGui();

});
