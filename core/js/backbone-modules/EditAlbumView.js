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
	window.sliMpd.modules.EditAlbumView = window.sliMpd.modules.PageView.extend({

		name : null,
		rendered : false,
		initialFormValues : "",

		initialize : function(options) {
			window.sliMpd.modules.PageView.prototype.initialize.call(this, options);
		},

		render : function(renderMarkup) {
			if (this.rendered) {
				return;
			}
			window.sliMpd.modules.PageView.prototype.render.call(this, renderMarkup);

			// record all form values before user-input modifies values
			this.formSnapshot("#edit-album");

			var that = this;

			$(".marry", this.$el).off("click", this.marryTrackClickListener).on("click", this.marryTrackClickListener);
			$(".marry-all", this.$el).off("click", this.marryAllTracksClickListener).on("click", this.marryAllTracksClickListener);

			$("#edit-album", this.$el).on("submit", function(e) {
				e.preventDefault();
				window.NProgress.start();
				var currentItems = that.convertSerializedArrayToHash($(this).serializeArray());
				var itemsToSubmit = that.hashDiff(that.initialFormValues, currentItems);
				$.ajax({
					type: $(this).attr("method"),
					url: window.sliMpd.router.setGetParameter($(this).attr("action"), "nosurrounding", "1"),
					data: itemsToSubmit,
					success: function(response) {
						window.NProgress.done();
						window.sliMpd.checkNotify(response);
					}
				});
			});

			$(".grid", this.$el).sortable({
				tolerance: "pointer",
				revert: "invalid",
				placeholder: "span2 well placeholder tile",
				forceHelperSize: true
			});

			this.rendered = true;
		},

		remove : function() {
			$(".marry", this.$el).off("click", this.marryTrackClickListener);
			$(".marry-all", this.$el).off("click", this.marryAllTracksClickListener);
			window.sliMpd.modules.PageView.prototype.remove.call(this);
		},

		marryTrack : function(index) {
			// add class to external item
			$("#ext-item-" + index).addClass("is-married");

			// search local item of same position
			$(".local-items div.well:eq("+ index +")").addClass("is-married");
		},

		unmarryTrack : function(index) {
			// add class to external item
			$("#ext-item-" + index).removeClass("is-married");

			// search local item of same position
			$(".local-items div.well:eq("+ index +")").removeClass("is-married");
		},

		marryAllTracks : function(maxIndex) {
			for(var index=0; index<maxIndex; index++) {
				this.marryTrack(index);
			}
		},

		unmarryAllTracks : function(maxIndex) {
			for(var index=0; index<maxIndex; index++) {
				this.unmarryTrack(index);
			}
		},

		marryTrackClickListener : function(e) {
			e.preventDefault();
			var index = $(e.currentTarget).attr("data-index");
			if($("#ext-item-" + index).hasClass("is-married")) {
				this.unmarryTrack(index);
				return;
			}
			this.marryTrack(index);
		},

		marryAllTracksClickListener : function(e) {
			e.preventDefault();
			// count married items
			var $countMarried = $(".local-items .is-married").length;

			// count total of shorter list for comparison
			var $compareWith = ($(".local-items .well").length > $(".external-items .well").length)
				? $(".external-items .well").length
				: $(".local-items .well").length;
			
			// in case we have more married than unmarried -> unmarry all and vice versa
			//var $action = "unmarryAllTracks";
			if($compareWith - $countMarried > $countMarried) {
				this.marryAllTracks($compareWith);
				return;
			}
			this.unmarryAllTracks($compareWith);
		},

		formSnapshot : function(selectorx) {
			var $form = $(selectorx, this.$el);
			if(!$form.length) {
				return;
			}
			// store all initial form values in variable
			this.initialFormValues = this.convertSerializedArrayToHash($form.serializeArray());
		},

		hashDiff : function (startItems, currentItems) {
			var finalItems = {};
			for (var itemKey in currentItems) {
				if (startItems[itemKey] === currentItems[itemKey]) {
					continue;
				}
				finalItems[itemKey] = currentItems[itemKey];
			}
			return finalItems;
		},

		convertSerializedArrayToHash : function (a) {
			var r = {};
			for (var i = 0;i<a.length;i++) {
				r[a[i].name] = a[i].value;
			}
			return r;
		}
	});
}());
