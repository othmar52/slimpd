$.fn.random = function() {
	return this.eq(Math.floor(Math.random() * this.length));
};

$(document).ready(function() {
	"use strict";
	var $ = window.jQuery;
	window.sliMpd = $.extend(true, window.sliMpd, {
		modules : {},
	});
	window.sliMpd.router = new window.sliMpd.modules.Router();
	window.Backbone.history.start({
		pushState : true
	});
});
