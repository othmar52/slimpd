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

		initialize(options) {
			//console.log(options);
			this.$modal = $("#global-modal");
			window.sliMpd.modules.AbstractView.prototype.initialize.call(this, options);
		},

		render() {
			window.sliMpd.modules.AbstractView.prototype.render.call(this);
			this.rendered = true;
		},

		renderModalContent(markup) {
			this.$modal.find(".modal-content").html(markup);
			this.rendered = false;
			this.render();
			this.$modal.modal("show");
		},

		addMarkupToModal(markup) {

		}
	});

}());
