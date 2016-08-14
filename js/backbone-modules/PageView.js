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
