$(document).ready(function() {
	"use strict";

	var $ = window.jQuery;
	window.sliMpd = $.extend(true, window.sliMpd, {
		modules : {},
		xwax : false,

		fireRequestAndNotify(url) {
			$.get(url).done(function(response) {
				window.sliMpd.checkNotify(response);
			});
		},

		checkNotify(endcodedResponse) {
			try {
				var notifyConf = JSON.parse(endcodedResponse);
				if (typeof notifyConf.notify !== "undefined") {
					this.notify(notifyConf);
				}
			} catch(e) {
				//console.log(e + " no json response in SliMpd::checkNotify()");
			}
		},

		notify(notifyConf) {
			////////////////////////////////////////////////
			// FIXME:
			// bootstrap-notify.min.js:1 Uncaught TypeError: Cannot read property "title" of undefined
			// check notify"s-template variable for title
			//////////////////////////////////////////////// 
			$.notify({
				// options
				message: notifyConf.message
			},{
				// settings
				type: (notifyConf.type || "info"),
				z_index: 10000
			});
			$.notify();
		},

		notifyError(errorUrl) {
			this.notify({
				message : "<h4>OOOPS!</h4> something went wrong...<br /><a class=\"alert-link\" target=\"_blank\" href=\""+ errorUrl+"\">" + errorUrl + "</a>",
				type : "danger"
			});
		}
	});

	window.sliMpd.xwax = new window.sliMpd.modules.XwaxView({
		el : ".player-xwax",
		showWaveform : false
	});
	window.sliMpd.xwax.render();
	window.sliMpd.xwax.showXwaxGui();

});