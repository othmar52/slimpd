/* Copyright (C) 2016 othmar52 <othmar52@users.noreply.github.com>
 *                    stt <stt@mmc-agentur.at>
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
 * dependencies: jquery, backbonejs, underscorejs, window.sliMpd.router
 */
(function() {
    "use strict";

    var $ = window.jQuery,
        _ = window._;
    window.sliMpd = $.extend(true, window.sliMpd, {
        modules : {}
    });
    window.sliMpd.modules.AbstractView = window.Backbone.View.extend({

        rendered : false,

        initialize : function(options) {
            window.Backbone.View.prototype.initialize.call(this, options);
            _.bindAll.apply(_, [this].concat(_.functions(this)));
        },

        // TODO: check why event binding this does not work for this.ajaxPostSubmitListener
        /*
        events : {
            "click a.ajax-link": "genericClickListener",
            "click .ajax-rqst": "ajaxRequestClickListener",
            "click .player-ctrl": "playerCtrlClickListener",
            "click .ajax-partial": "ajaxPartialClickListener",
            "click .trigger-modal": "triggerModalClickListener",
            "mouseenter .glue-hover-trigger": "itemGlueMouseenterListener",
            "mouseleave .glue-hover-trigger": "itemGlueMouseleaveListener",
            "click .toggle-content": "itemToggleClickListener",
            "keyup input.clearable": "clearableInputListener",
            "click .clearinput": "clearinputClickListener",
            "click .force-xwax-poll": "forceXwaxPoll",
            //"click *[data-toggle="lightbox"]": "triggerLightboxClickListener",
            "click .toggle-player": "playerModeToggleTriggerListener",
            "click .toggle-player-size": "playerSizeToggleTriggerListener",
            "click .xwax-gui-toggler": "xwaxGuiToggleTriggerListener",
            // TODO: do we really have to add BookBlock EventListeners manually???
            "click .bb-nav-next": "bookblockNextClickListener",
            "click .bb-nav-prev": "bookblockPrevClickListener",
            "submit .ajax-post": "ajaxPostSubmitListener",
            "change .toggle-checkbox toggleCheckboxChangeListener",
            "paste .toggle-checkbox toggleCheckboxChangeListener",
            "keyup .toggle-checkbox toggleCheckboxChangeListener",
        },
        */
        events : {
            //"submit .ajax-post": "ajaxPostSubmitListener"
        },

        render : function() {
            // only render page once (to prevent multiple click listeners)
            if (this.rendered) {
                return;
            }
            $("body", this.$el).animate({ scrollTop: 0 }, 300);

            // TODO: move this stuff to events property
            $("a.ajax-link", this.$el).off("click", this.genericClickListener).on("click", this.genericClickListener);
            $(".ajax-rqst", this.$el).off("click", this.ajaxRequestClickListener).on("click", this.ajaxRequestClickListener);
            $(".player-ctrl", this.$el).off("click", this.playerCtrlClickListener).on("click", this.playerCtrlClickListener);
            $(".ajax-partial", this.$el).off("click", this.ajaxPartialClickListener).on("click", this.ajaxPartialClickListener);
            $(".ajax-post", this.$el).off("submit", this.ajaxPostSubmitListener).on("submit", this.ajaxPostSubmitListener);
            $(".trigger-modal", this.$el).off("click", this.triggerModalClickListener).on("click", this.triggerModalClickListener);
            $(".glue-hover-trigger", this.$el).off("mouseenter", this.itemGlueMouseenterListener).on("mouseenter", this.itemGlueMouseenterListener);
            $(".glue-hover-trigger", this.$el).off("mouseleave", this.itemGlueMouseleaveListener).on("mouseleave", this.itemGlueMouseleaveListener);
            $(".toggle-content", this.$el).off("click", this.itemToggleClickListener).on("click", this.itemToggleClickListener);
            $("input.clearable", this.$el).off("keyup", this.clearableInputListener).on("keyup", this.clearableInputListener);
            $(".clearinput", this.$el).off("click", this.clearinputClickListener).on("click", this.clearinputClickListener);
            $(".force-xwax-poll", this.$el).off("click", this.forceXwaxPoll).on("click", this.forceXwaxPoll);
            //$("*[data-toggle="lightbox"]", this.$el).off("click", this.triggerLightboxClickListener).on("click", this.triggerLightboxClickListener);
            $(".toggle-player", this.$el).off("click", this.playerModeToggleTriggerListener).on("click", this.playerModeToggleTriggerListener);
            $(".toggle-player-size", this.$el).off("click", this.playerSizeToggleTriggerListener).on("click", this.playerSizeToggleTriggerListener);
            $(".xwax-gui-toggler", this.$el).off("click", this.xwaxGuiToggleTriggerListener).on("click", this.xwaxGuiToggleTriggerListener);
            // TODO: do we really have to add BookBlock EventListeners manually???
            $(".bb-nav-next", this.$el).off("click", this.bookblockNextClickListener).on("click", this.bookblockNextClickListener);
            $(".bb-nav-prev", this.$el).off("click", this.bookblockPrevClickListener).on("click", this.bookblockPrevClickListener);
            $(".toggle-checkbox", this.$el).off("change paste keyup", this.toggleCheckboxChangeListener).on("change paste keyup", this.toggleCheckboxChangeListener);
            $(".toggle-stream", this.$el).off("click", this.toggleStream).on("click", this.toggleStream);

            /* display selected value in dropdown instead of dropdown-label */
            // TODO: choose more generic attribute-names. maybe we add "label-dropdowns" which has nothing to do with sorting...
            var that = this;
            $(".dropdown-label", that.$el).each(function(index, item){
                $(item).find(".btn:first-child").html(
                    $("*[data-sortkey=\""+ $(item, that.$el).attr("data-activesorting")+"\"]", that.$el).html()
                );
            });

            $(".ajax-form", this.$el).on("submit", function(e) {
                e.preventDefault();
                var url = $(this).attr("action") + "?" + $(this).serialize();
                window.sliMpd.router.navigate(url, {
                    trigger : true
                });

                //that.searchfield.autocomplete("close");
            });

            $(".dropdown-toggle", this.$el).dropdown();
            $(".toggle-tooltip", this.$el).tooltip();
            $("[data-toggle=\"popover\"]", this.$el).popover({
                html: "true",
                container: "#main",
                content : function() {
                    return ($(this).data("imageUrl"))
                        ? "<img class=\"popover-image\" src=\""+$(this).data("imageUrl")+"\">"
                        : $(this).attr("title");
                }
            });

            $(".inline-tab-nav a", this.$el).click(function (e) {
                e.preventDefault();
                $(this).tab("show");
            });

            // TODO : remove this as soon as all svg-waveforms have been replaced by canvas-waveforms (widget-trackcontrol, xwaxPlayer)
            $("object.svg-ajax-object", that.$el).each(function(index, item){
                var obj = item;
                $.ajax({
                    url: $(obj).attr("data-svgurl")
                }).retry({
                    times: 10,
                    timeout: 3000
                }).then(function(response){
                    $(obj).attr("data", $(obj).attr("data-svgurl"));
                });
            });

            // TODO : add xwaxPlayer- and trackwidget-waveform
            $(".waveform-wrapper", that.$el).each(function(index, item){
                var obj = item;
                $.ajax({
                    url: $(obj).attr("data-jsonurl")
                }).retry({
                    times: 10,
                    timeout: 3000
                }).then(function(response){
                    switch($(obj).attr("data-player")) {
                        case "mpd": window.sliMpd.mpdPlayer.drawWaveform(); break;
                        case "local": window.sliMpd.localPlayer.drawWaveform(); break;
                    }
                });
            });

            requestAnimationFrame(function(){
                $("[data-spy=affix]", that.$el).affix({offset: {top: 100 }});
                //$("[data-spy=affix]", that.$el).affix("checkPosition");
            });

            $( "#bb-bookblock", that.$el ).bookblock( {
                speed : 800,
                shadowSides : 0.8,
                shadowFlip : 0.7,
                circular : false,
                nextEl : ".bb-nav-next",
                prevEl : ".bb-nav-prev"
            });

            window.Backbone.View.prototype.render.call(this);
            this.rendered = true;
        },

        remove : function() {
            $("a.ajax-link", this.$el).off("click", this.genericClickListener);
            $(".ajax-rqst", this.$el).off("click", this.ajaxRequestClickListener);
            $(".player-ctrl", this.$el).off("click", this.playerCtrlClickListener);
            $(".ajax-partial", this.$el).off("click", this.ajaxPartialClickListener);
            $(".ajax-post", this.$el).off("submit", this.ajaxPostSubmitListener);
            $(".trigger-modal", this.$el).off("click", this.triggerModalClickListener);
            $(".toggle-player", this.$el).off("click", this.playerModeToggleTriggerListener);
            $(".glue-hover-trigger", this.$el).off("mouseenter", this.itemGlueMouseenterListener);
            $(".glue-hover-trigger", this.$el).off("mouseleave", this.itemGlueMouseleaveListener);
            $(".toggle-content", this.$el).off("click", this.itemToggleClickListener);
            $("input.clearable", this.$el).off("input", this.clearableInputListener);
            $(".clearinput", this.$el).off("click", this.clearinputClickListener);
            $(".force-xwax-poll", this.$el).off("click", this.forceXwaxPoll);
            $(".toggle-player-size", this.$el).off("click", this.playerSizeToggleTriggerListener);
            $(".xwax-gui-toggler", this.$el).off("click", this.xwaxGuiToggleTriggerListener);
            $(".bb-nav-next", this.$el).off("click", this.bookblockNextClickListener);
            $(".bb-nav-prev", this.$el).off("click", this.bookblockPrevClickListener);
            $(".toggle-checkbox", this.$el).off("change paste keyup", this.toggleCheckboxChangeListener);
            $(".toggle-stream", this.$el).off("click", this.toggleStream);

            $( "#bb-bookblock", this.$el ).bookblock("destroy");

            window.Backbone.View.prototype.remove.call(this);
        },

        close : function() {
            //console.log("AbstractView::destroyView");
            this.remove();
            this.unbind();
        },

        genericClickListener : function(e) {
            e.preventDefault();
            var $el = $(e.currentTarget);
            window.sliMpd.router.navigate($el.attr("href"), {
                trigger : true
            });
            if($el.hasClass("trigger-hide-modal")) {
                window.sliMpd.modal.$modal.modal("hide");
            }
            if($el.hasClass("refresh-status")) {
                window.sliMpd.mpdPlayer.refreshInterval();
            }
        },

        playerCtrlClickListener : function(e) {
            e.preventDefault();
            var $el = $(e.currentTarget);
            if(typeof $el.attr("data-player") == "undefined") {
                //console.log("ERROR: missing player-item. exiting...");
                return;
            }
            try {
                var item = JSON.parse($el.attr("data-player"));
                window.sliMpd.currentPlayer.process(item);
                if($el.hasClass("trigger-hide-modal")) {
                    window.sliMpd.modal.$modal.modal("hide");
                }
            } catch(e) {
                //console.log(e + " in data-player attribute");
            }
        },

        ajaxPartialClickListener : function(e) {
            e.preventDefault();
            var $el = $(e.currentTarget);
            if(typeof $el.attr("data-ajaxtarget") == "undefined") {
                $el.attr("data-ajaxtarget", "#main");
            }
            var that = this;
            window.NProgress.start();
            $.ajax({
                url: window.sliMpd.router.setGetParameter($el.attr("href"), "nosurrounding", "1")
            }).done(function(response) {
                window.sliMpd.checkNotify(response);
                // TODO: add smooth animation
                $($el.attr("data-ajaxtarget")).hide().html(response).show(200);
                that.rendered = false;
                that.render();
                window.NProgress.done();
            }).fail(function() {
                window.sliMpd.notifyError($el.attr("href"));
                window.NProgress.done();
                return;
            });
        },

        ajaxRequestClickListener : function(e) {
            e.preventDefault();
            var $el = $(e.currentTarget);
            $.ajax({
                url: window.sliMpd.router.setGetParameter($el.attr("data-href"), "nosurrounding", "1")
            }).done(function(response) {
                window.sliMpd.checkNotify(response);
                if($el.hasClass("trigger-hide-modal")) {
                    window.sliMpd.modal.$modal.modal("hide");
                }
            }).fail(function() {
                window.sliMpd.notifyError($el.attr("data-href"));
                return;
            });
        },

        ajaxPostSubmitListener : function(e) {
            e.preventDefault();
            var $el = $(e.currentTarget);
            var that = this;
            window.NProgress.start();
            $.ajax({
                url: window.sliMpd.router.setGetParameter($el.attr("action"), "nosurrounding", "1"),
                method : "post",
                data: $el.serialize()
            }).done(function(response) {
                that.$el.html(response);
                that.rendered = false;
                that.render();
                window.NProgress.done();
                window.sliMpd.mpdPlayer.refreshInterval();
            }).fail(function() {
                window.sliMpd.notifyError($el.attr("data-href"));
                window.NProgress.done();
                return;
            });
        },

        triggerModalClickListener : function(e) {
            e.preventDefault();
            var $el = $(e.currentTarget);
            // open immediatly - dont wait for ajax response
            window.sliMpd.modal.openModal();
            $.ajax({
                url: $el.attr("data-href")
            }).done(function(response){
                try {
                    if (typeof response.notify !== "undefined") {
                        window.sliMpd.notify(response);
                        window.sliMpd.modal.$modal.modal("hide");
                        return;
                    }
                } catch(e) {
                    //console.log(e + " no json response in SliMpd::checkNotify()");
                }
                window.sliMpd.modal.renderModalContent(response);
            }).fail(function() {
                window.sliMpd.notifyError($el.attr("data-href"));
                return;
            });
        },

        /*
        // FIXME: why does this not work?
        // @see: app.js:document.ready()
        triggerLightboxClickListener(e) {
            event.preventDefault();
            return $(this).ekkoLightbox({
                always_show_close: true,
                type: "image"
            });
        },
        */
        playerModeToggleTriggerListener : function(e) {
            e.preventDefault();
            window.sliMpd.togglePlayer();
        },

        playerSizeToggleTriggerListener : function(e) {
            e.preventDefault();
            $("body").toggleClass("slimplayer");
            $(this).find("i").toggleClass("fa-chevron-down").toggleClass("fa-chevron-up");
            this.drawWaveform();
        },

        itemGlueMouseenterListener : function(e) {
            e.preventDefault();
            $(e.currentTarget).closest(".glue-hover-wrap").addClass("underline");
        },

        itemGlueMouseleaveListener : function(e) {
            e.preventDefault();
            $(e.currentTarget).closest(".glue-hover-wrap").removeClass("underline");
        },

        itemToggleClickListener : function(e) {
            e.preventDefault();
            var $el = $(e.currentTarget);
            var $target = $($el.attr("data-toggle"));
            if($el.hasClass("close-all")) {
                $(".toggle-content").each(function(index, item){
                    var $item = $(item);
                    if($item.attr("data-toggle") !== $el.attr("data-toggle")) {
                        $($item.attr("data-toggle")).hide();
                        $item.text($item.attr("data-text1"));
                    }
                });
            }
            $target.slideToggle(300, "swing");
            $el.text((($target.is(":hidden")) ? $el.attr("data-text1") : $el.attr("data-text2") ) );
        },

        process : function(e) { },

        clearableInputListener : function(e) {
            var $el = $(e.currentTarget);
            $el.siblings(".clearinput").toggle(Boolean($el.val()));
        },

        clearinputClickListener : function(e) {
            //console.log("clearinputClickListener()");
            var $el = $(e.currentTarget);
            $( $el.attr("data-selector") ).val("").focus();
            $el.hide();
        },

        forceXwaxPoll : function(e) {
            try {
                window.sliMpd.xwax.pollWorker.postMessage({ cmd: "refreshIntervalDelayed"});
            } catch (error) {
                //console.log("ERROR window.sliMpd.xwax.pollWorker.postMessage::refreshIntervalDelayed()");
            }
        },

        xwaxGuiToggleTriggerListener : function(e) {
            e.preventDefault();
            window.sliMpd.xwax.toggleXwaxGui();
        },

        bookblockNextClickListener : function(e) {
            e.preventDefault();
            $("#bb-bookblock").bookblock("next");
        },

        bookblockPrevClickListener : function(e) {
            e.preventDefault();
            $("#bb-bookblock").bookblock("prev");
        },

        toggleCheckboxChangeListener : function(e) {
            var $el = $(e.currentTarget);
            var $checkbox = $($el.attr("data-checkbox"));
            if($el.val() === "") {
                $checkbox.prop("checked", false);
                return;
            }
            $checkbox.prop("checked", true);
        }
    });
}());
