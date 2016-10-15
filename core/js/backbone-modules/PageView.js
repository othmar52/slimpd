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
 * dependencies: jquery, backbonejs, underscorejs, window.sliMpd.router, window.sliMpd.modules.AbstractView
 */
(function() {
	"use strict";
	
	var $ = window.jQuery,
		_ = window._;
	window.sliMpd = $.extend(true, window.sliMpd, {
		modules : {}
	});
	window.sliMpd.modules.PageView = window.sliMpd.modules.AbstractView.extend({

		name : null,
		rendered : false,

		initialize(options) {
			this.name = options.name;
			this._template = _.template(options.templateString);

			window.sliMpd.modules.AbstractView.prototype.initialize.call(this, options);
		},

		/**
		 * 
 		 * @param bool renderMarkup - if true renders markup using the templateString (set to false on initial page-laod)
		 */
		render(renderMarkup) {
			// only render page once (to prevent multiple click listeners)
			if (this.rendered) {
				return;
			}

			if (renderMarkup) {
				this.el = (this.$el = $(this._template((this.model || {}).attributes)))[0];
			}

			window.sliMpd.modules.AbstractView.prototype.render.call(this);
			this.rendered = true;
		},
		
		remove() {
			window.sliMpd.modules.AbstractView.prototype.remove.call(this);
		}
	});
}());
