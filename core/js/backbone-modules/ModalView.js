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
 * FITNESS FOR A PARTICULAR PURPOSE.  See the GNU Affero General Public License
 * for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */
/*
 * dependencies: jquery, backbonejs, underscorejs, window.sliMpd.router, window.sliMpd.modules.AbstractView
 */
(function() {
	"use strict";
	var $ = window.jQuery,
		_ = window._;
	window.sliMpd = $.extend(true, window.sliMpd, {
		modules : {}
	});
	window.sliMpd.modules.ModalView = window.sliMpd.modules.AbstractView.extend({

		rendered : false,

		$modal : null,

		initialize : function(options) {
			//console.log(options);
			this.$modal = $("#global-modal");
			window.sliMpd.modules.AbstractView.prototype.initialize.call(this, options);
		},

		render : function() {
			window.sliMpd.modules.AbstractView.prototype.render.call(this);
			this.rendered = true;
		},

		renderModalContent : function(markup) {
			this.$modal.find(".modal-content").html(markup);
			this.rendered = false;
			this.render();
			this.$modal.modal("show");
		},

		addMarkupToModal : function(markup) {

		}
	});
}());
