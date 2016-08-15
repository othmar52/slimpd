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
	window.sliMpd.modules.AlbumView = window.sliMpd.modules.PageView.extend({

		name : null,
		rendered : false,

		initialize(options) {
			window.Backbone.View.prototype.initialize.call(this, options);
		},

		render() {
			if (this.rendered) {
				return;
			}
			window.sliMpd.modules.PageView.call(this);
			this.rendered = true;
		}
		
	});	
}());
