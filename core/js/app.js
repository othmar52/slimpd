/* Copyright (C) 2015-2016 othmar52 <othmar52@users.noreply.github.com>
 *                         stt <stt@mmc-agentur.at>
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
$(document).ready(function() {
    "use strict";
    var $ = window.jQuery;
    var NProgress = window.NProgress;

    window.sliMpd = $.extend(true, window.sliMpd, {
        modules : {},

        drawFaviconTimeout : 0,

        xwax : false,

        username : null,

        drawFavicon : function() {
            clearTimeout(window.sliMpd.drawFaviconTimeout);
            window.sliMpd.currentPlayer.drawFavicon();
            window.sliMpd.drawFaviconTimeout = setTimeout(window.sliMpd.drawFavicon, 2000);
        },

        fireRequestAndNotify : function(url) {
            $.get(url).done(function(response) {
                window.sliMpd.checkNotify(response);
            });
        },

        checkNotify : function(notifyConf) {
            try {
                if (typeof notifyConf.notify !== "undefined") {
                    this.notify(notifyConf);
                }
            } catch(e) {
                //console.log(e + " no json response in SliMpd::checkNotify()");
            }
        },

        // TODO: respect playersize + visible xwax gui for positioning
        notify : function(notifyConf) {
            $.notify({
                // options
                message: notifyConf.message
            },{
                // settings
                type: (notifyConf.type || "info"),
                z_index: 10000,
                offset: {
                    x: "10",
                    y: "110"
                },
                placement: {
                    from: "bottom",
                    align: "right"
                },
            });
        },

        notifyError : function(errorUrl) {
            // TODO: get message from language file
            this.notify({
                message : "<h4>OOOPS!</h4> something went wrong...<br /><a class=\"alert-link\" target=\"_blank\" href=\""+ errorUrl+"\">" + errorUrl + "</a>",
                type : "danger"
            });
        },

        /* toggle between mpd-control and local player (jPlayer) */
        togglePlayer : function() {
            var TweenMax = window.TweenMax;
            var Back = window.Back;
            var Power2 = window.Power2;

            var perspective = -1000;
            var originPrev = "50% 50%";
            var originNew = "50% 50%";
            var ease = Back.easeInOut.config(1);
            var easeIn = Power2.easeIn;
            var easeOut = Power2.easeOut;
            var speed = 0.5;
            var classToRemove = window.sliMpd.conf.color.mpd.bodyclass;
            var classToAdd = window.sliMpd.conf.color.local.bodyclass;

            var tweenIn;
            var tweenOut;
            var timeScale = 0.7;

            $(".player-local,.player-mpd").removeClass("hidden");

            var transformPreviousPlayerFrom = {
                display: "block",
                transformOrigin: originPrev,
                transformPerspective: perspective,
                zIndex: 1030,
                rotationX: 0,
                y: 0,
                z:0
            };
            var transformPreviousPlayerTo = {
                display: "none",
                rotationX: 90,
                y: $(".player-mpd").height()/2,
                z: -5,
                ease
            };
            var transformNewPlayerFrom = {
                transformOrigin: originNew,
                transformPerspective: perspective,
                display: "block",
                zIndex: 1029,
                rotationX: -90,
                y: -$(".player-mpd").height()/2,
                z: -5
            };
            var transformNewPlayerTo = {
                display: "block",
                rotationX: 0,
                y:0,
                z:0,
                delay:0.02,
                ease
            };


            if(window.sliMpd.currentPlayer.mode === "mpd") {
                // activate local player
                window.sliMpd.currentPlayer = window.sliMpd.localPlayer;

                // reduce poll amount of inactive mpd player
                window.sliMpd.mpdPlayer.pollWorker.postMessage({
                    cmd: "setMiliseconds",
                    value: window.sliMpd.mpdPlayer.intervalInactive
                });

                // flip animation for both players
                tweenIn = TweenMax.fromTo($(".player-mpd"), speed, transformPreviousPlayerFrom, transformPreviousPlayerTo);
                tweenOut = TweenMax.fromTo($(".player-local"), speed, transformNewPlayerFrom, transformNewPlayerTo);

                tweenIn.timeScale(timeScale);
                tweenOut.timeScale(timeScale);

                //TweenMax.fromTo($(".permaplayer-wrapper"), speed, {rotationX: 0 }, {rotationX: 90});
            } else {
                // pause local player when switching to mpd
                window.sliMpd.currentPlayer.process({"action":"pause"});

                // activate mpd player
                window.sliMpd.currentPlayer = window.sliMpd.mpdPlayer;

                // increase poll amount as mpd player is now active
                window.sliMpd.mpdPlayer.pollWorker.postMessage({
                    cmd: "setMiliseconds",
                    value: window.sliMpd.mpdPlayer.intervalActive
                });
                window.sliMpd.currentPlayer.refreshInterval();

                // flip animation for both players
                tweenIn = TweenMax.fromTo($(".player-local"), speed, transformPreviousPlayerFrom, transformPreviousPlayerTo);
                tweenOut = TweenMax.fromTo($(".player-mpd"), speed, transformNewPlayerFrom, transformNewPlayerTo);

                tweenIn.timeScale(timeScale);
                tweenOut.timeScale(timeScale);

                //TweenMax.fromTo($(".permaplayer-wrapper"), speed, {rotationX: 90 }, {rotationX: 0});

                classToRemove = window.sliMpd.conf.color.local.bodyclass;
                classToAdd = window.sliMpd.conf.color.mpd.bodyclass;
            }

            // change body-class for colorizing all links in half of animation time
            $("body").addClass(classToAdd).removeClass(classToRemove);

            $.cookie("playerMode", window.sliMpd.currentPlayer.mode, { expires : 365, path: "/" });
            window.sliMpd.drawFavicon();
            window.sliMpd.currentPlayer.drawWaveform();
        },

        checkUserChange: function(username) {
            if(this.username === username) {
                return;
            }
            this.username = username;
            this.handleUserChange();
        },

        handleUserChange: function() {
            $.ajax({
                url: window.sliMpd.conf.absRefPrefix,
                type: "get",
                success : function( siteMarkup ) {
                    // refresh navigation
                    window.sliMpd.navbar.redraw($(siteMarkup).find("nav.main-nav").html());
                }
            });
        },

        handleUnauthorized: function(responseMarkup) {
            $("<div class='overlay-backdrop'>"+ responseMarkup +"</div>")
            .css({opacity: 1, display: "block"})
            .on("click", function(e){
                $(e.currentTarget).remove();
            })
            .appendTo(document.body);
        }
    });

    window.sliMpd.navbar = new window.sliMpd.modules.NavbarView({
        el : "nav.main-nav"
    });
    window.sliMpd.username = window.sliMpd.conf.currentUser;
    window.sliMpd.navbar.render();

    window.sliMpd.xwax = new window.sliMpd.modules.XwaxView({
        el : ".player-xwax",
        showWaveform : true
    });
    window.sliMpd.xwax.render();

    window.sliMpd.modal = new window.sliMpd.modules.ModalView({
        el : "#global-modal .modal-content"
    });

    window.sliMpd.localPlayer = new window.sliMpd.modules.LocalPlayer({
        el : ".permaplayer.player-local"
    });
    window.sliMpd.mpdPlayer = new window.sliMpd.modules.MpdPlayer({
        el : ".permaplayer.player-mpd"
    });

    window.sliMpd.currentPlayer = ($.cookie("playerMode") === "mpd")
        ? window.sliMpd.mpdPlayer
        : window.sliMpd.localPlayer;

    window.sliMpd.router = new window.sliMpd.modules.Router();

    window.Backbone.history.start({
        pushState : true
    });

    window.sliMpd.drawFavicon();

    /* toggle between display tags and display filepath */
    $(".fileModeToggle a").on("click", function(e) {
        e.preventDefault();
        $("body").toggleClass("ffn");
        $(this).find("i").toggleClass("fa-toggle-off").toggleClass("fa-toggle-on");
    });

    // delegate calls to data-toggle="lightbox"
    $(document).delegate("*[data-toggle='lightbox']", "click", function(event) {
        event.preventDefault();
        return $(this).ekkoLightbox({
            always_show_close: true,
            gallery_parent_selector: "body"
        });
    });

    $(document).on("keydown", null, "ctrl+space", function(){
        // FIXME: this does not work with open autocomplete-widget. obviously ac overrides key bindings
        $("#mainsearch").focus().select();
        return false;
    });

    NProgress.configure({
        showSpinner: false,
        parent: "#nprog-container",
        speed: 100,
        trickleRate: 0.02,
        trickleSpeed: 800
    });

    // TODO: is it correct to place this here (excluded from all bootstrap-views)?
    $(function(){
        $("#top-link-block").removeClass("hidden").affix({
            // how far to scroll down before link "slides" into view
            offset: {top:100}
        });
    });
    $("#top-link-block a").on("click", function(e) {
        e.preventDefault();
        $("html,body").animate({scrollTop:0},"fast");
        return false;
    });

    /*
     * force confirmation when user leaves sliMpd in case local audio is playing
     * as the browser is not displaying the text there is no nedd to fetch string from language file
     * TODO: make this optinal via config
     */
    window.onbeforeunload = function(){
        if(window.sliMpd.currentPlayer.mode === "local" && window.sliMpd.currentPlayer.nowPlayingState === "play") {
            return "local audio is currently playing";
        }
    };

    /*
     * add lazy resize listener
     */
    $(window).bind("resizeEnd", function() {
        window.sliMpd.currentPlayer.drawWaveform();
        window.sliMpd.currentPlayer.drawTimeGrid();
    });
    $(window).resize(function() {
        if(this.resizeTO) {
            clearTimeout(this.resizeTO);
        }
        this.resizeTO = setTimeout(function() {
            $(this).trigger("resizeEnd");
        }, 500);
    });
});
