$.fn.random = function() {
	return this.eq(Math.floor(Math.random() * this.length));
};

$(document).ready(function() {
	"use strict";

	var $ = window.jQuery;
	window.sliMpd = $.extend(true, window.sliMpd, {
		modules : {},
		xwax : false,

		/**
		 * adds get-paramter to url, respecting existing and not-existing params
		 * TODO: currently not compatible with urlstring that contains a #hash
		 * @param {string} urlstring
		 * @param {string} paramName
		 * @param {string} paramValue
		 */
		setGetParameter(urlstring, paramName, paramValue) {
			if (urlstring.indexOf(paramName + "=") >= 0) {
				var prefix = urlstring.substring(0, urlstring.indexOf(paramName));
				var suffix = urlstring.substring(urlstring.indexOf(paramName));
				suffix = suffix.substring(suffix.indexOf("=") + 1);
				suffix = (suffix.indexOf("&") >= 0) ? suffix.substring(suffix.indexOf("&")) : "";
				urlstring = prefix + paramName + "=" + paramValue + suffix;
			} else {
				urlstring += (urlstring.indexOf("?") < 0)
					? "?" + paramName + "=" + paramValue
					: "&" + paramName + "=" + paramValue;
			}
			return urlstring;
		},

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