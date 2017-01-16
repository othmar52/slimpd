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
    window.sliMpd.modules.AuthView = window.sliMpd.modules.PageView.extend({

        name : null,
        rendered : false,

        initialize : function(options) {
            window.sliMpd.modules.PageView.prototype.initialize.call(this, options);
        },

        render : function(renderMarkup) {
            if (this.rendered) {
                return;
            }
            window.sliMpd.modules.PageView.prototype.render.call(this, renderMarkup);
            $(".toggle-password-lock", this.$el).off("click", this.togglePasswordLockClickListener).on("click", this.togglePasswordLockClickListener);
            $(".toggle-lock", this.$el).off("click", this.toggleLockClickListener).on("click", this.toggleLockClickListener);
            $(".remember_password_toggle", this.$el).hide();
            this.rendered = true;
        },

        remove : function() {
            $(".toggle-password-lock", this.$el).off("click", this.togglePasswordLockClickListener);
             $(".toggle-lock", this.$el).off("click", this.toggleLockClickListener);
            window.sliMpd.modules.PageView.prototype.remove.call(this);
        },

        /**
         * shows or hides lock/unlock icon next to password field
         *
         * @param clickEvent e
         * @return void
         */
        togglePasswordLockClickListener : function(e) {
            var $el = $(e.currentTarget);
            if($el.find(".toggle-lock").first().hasClass("fa-unlock-alt")) {
                $(".remember_password_toggle", this.$el).hide();
                return;
            }
            $(".remember_password_toggle", this.$el).show();
        },

        /**
         * swaps lock icons locked/unlocked and sets value of a connected hidden input field to "" or "1"
         *
         * @param clickEvent e
         * @return void
         */
        toggleLockClickListener : function(e) {
            var $el = $(e.currentTarget);
            $el.toggleClass("fa-unlock-alt fa-lock");
            $el.parent().toggleClass("active disabled");
            var $hidden = $( $el.attr("data-target"), this.$el );
            if($el.hasClass("fa-lock")) {
                $hidden.val("1");
                return;
            }
            $hidden.val("");
        }
    });
}());
