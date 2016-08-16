$.fn.random = function() {
	return this.eq(Math.floor(Math.random() * this.length));
};

Array.prototype.max = function() {
  return Math.max.apply(null, this);
};

$(document).ready(function() {
	"use strict";
	var $ = window.jQuery;
	var NProgress = window.NProgress;

	window.sliMpd = $.extend(true, window.sliMpd, {
		modules : {},

		drawFaviconTimeout : 0,

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

		drawFavicon() {
			clearTimeout(window.sliMpd.drawFaviconTimeout);
			window.sliMpd.currentPlayer.drawFavicon();
			window.sliMpd.drawFaviconTimeout = setTimeout(window.sliMpd.drawFavicon, 2000);
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

		// TODO: respect playersize + visible xwax gui for positioning
		notify(notifyConf) {
			$.notify({
				// options
				message: notifyConf.message
			},{
				// settings
				type: (notifyConf.type || "info"),
				z_index: 10000,
				offset: {
					x: "10",
					y: "110"
				},
				placement: {
					from: "bottom",
					align: "right"
				},
			});
		},

		notifyError(errorUrl) {
			// TODO: get message from language file
			this.notify({
				message : "<h4>OOOPS!</h4> something went wrong...<br /><a class=\"alert-link\" target=\"_blank\" href=\""+ errorUrl+"\">" + errorUrl + "</a>",
				type : "danger"
			});
		},

		/* toggle between mpd-control and local player (jPlayer) */
		togglePlayer() {
			var TweenMax = window.TweenMax;
			var Back = window.Back;
			var Power2 = window.Power2;

			var perspective = -1000;
			var originPrev = "50% 50%";
			var originNew = "50% 50%";
			var ease = Back.easeInOut.config(1);
			var easeIn = Power2.easeIn;
			var easeOut = Power2.easeOut;
			var speed = 0.5;
			var classToRemove = window.sliMpd.conf.color.mpd.bodyclass;
			var classToAdd = window.sliMpd.conf.color.local.bodyclass;

			var tweenIn;
			var tweenOut;
			var timeScale = 0.7;

			$(".player-local,.player-mpd").removeClass("hidden");

			var transformPreviousPlayerFrom = {
				display: "block",
				transformOrigin: originPrev,
				transformPerspective: perspective,
				zIndex: 1030,
				rotationX: 0,
				y: 0,
				z:0
			};
			var transformPreviousPlayerTo = {
				display: "none",
				rotationX: 90,
				y: $(".player-mpd").height()/2,
				z: -5,
				ease: ease
			};
			var transformNewPlayerFrom = {
				transformOrigin: originNew,
				transformPerspective: perspective,
				display: "block",
				zIndex: 1029,
				rotationX: -90,
				y: -$(".player-mpd").height()/2,
				z: -5
			};
			var transformNewPlayerTo = {
				display: "block",
				rotationX: 0,
				y:0,
				z:0,
				delay:0.02,
				ease: ease
			};


			if(window.sliMpd.currentPlayer.mode === "mpd") {
				// activate local player
				window.sliMpd.currentPlayer = window.sliMpd.localPlayer;

				// reduce poll amount of inactive mpd player
				window.sliMpd.mpdPlayer.pollWorker.postMessage({
					cmd: "setMiliseconds",
					value: window.sliMpd.mpdPlayer.intervalInactive
				});

				// flip animation for both players
				tweenIn = TweenMax.fromTo($(".player-mpd"), speed, transformPreviousPlayerFrom, transformPreviousPlayerTo);
				tweenOut = TweenMax.fromTo($(".player-local"), speed, transformNewPlayerFrom, transformNewPlayerTo);

				tweenIn.timeScale(timeScale);
				tweenOut.timeScale(timeScale);

				//TweenMax.fromTo($(".permaplayer-wrapper"), speed, {rotationX: 0 }, {rotationX: 90});
			} else {
				// pause local player when switching to mpd
				window.sliMpd.currentPlayer.process({"action":"pause"});

				// activate mpd player
				window.sliMpd.currentPlayer = window.sliMpd.mpdPlayer;

				// increase poll amount as mpd player is now active
				window.sliMpd.mpdPlayer.pollWorker.postMessage({
					cmd: "setMiliseconds",
					value: window.sliMpd.mpdPlayer.intervalActive
				});
				window.sliMpd.currentPlayer.refreshInterval();

				// flip animation for both players
				tweenIn = TweenMax.fromTo($(".player-local"), speed, transformPreviousPlayerFrom, transformPreviousPlayerTo);
				tweenOut = TweenMax.fromTo($(".player-mpd"), speed, transformNewPlayerFrom, transformNewPlayerTo);

				tweenIn.timeScale(timeScale);
				tweenOut.timeScale(timeScale);

				//TweenMax.fromTo($(".permaplayer-wrapper"), speed, {rotationX: 90 }, {rotationX: 0});

				classToRemove = window.sliMpd.conf.color.local.bodyclass;
				classToAdd = window.sliMpd.conf.color.mpd.bodyclass;
			}

			// change body-class for colorizing all links in half of animation time
			// TODO: is window.setTimeout() correct or should it be on another element?
			//window.setTimeout(function (){ $("body").addClass(classToAdd).removeClass(classToRemove); }, speed/2*1000);
			$("body").addClass(classToAdd).removeClass(classToRemove);

			$.cookie("playerMode", window.sliMpd.currentPlayer.mode, { expires : 365, path: "/" });
			window.sliMpd.drawFavicon();
			window.sliMpd.currentPlayer.drawWaveform();
		}
	});

	window.sliMpd.navbar = new window.sliMpd.modules.NavbarView({
		el : "nav.main-nav"
	});
	window.sliMpd.navbar.render();

	window.sliMpd.xwax = new window.sliMpd.modules.XwaxView({
		el : ".player-xwax",
		showWaveform : true
	});
	window.sliMpd.xwax.render();

	window.sliMpd.modal = new window.sliMpd.modules.ModalView({
		el : "#global-modal .modal-content"
	});

	window.sliMpd.localPlayer = new window.sliMpd.modules.LocalPlayer({
		el : ".permaplayer.player-local"
	});
	window.sliMpd.mpdPlayer = new window.sliMpd.modules.MpdPlayer({
		el : ".permaplayer.player-mpd"
	});

	window.sliMpd.currentPlayer = ($.cookie("playerMode") === "mpd")
		? window.sliMpd.mpdPlayer
		: window.sliMpd.localPlayer;

	window.sliMpd.router = new window.sliMpd.modules.Router();

	window.Backbone.history.start({
		pushState : true
	});

	window.sliMpd.drawFavicon();

	/* toggle between display tags and display filepath */
	$(".fileModeToggle a").on("click", function(e) {
		e.preventDefault();
		$("body").toggleClass("ffn");
		$(this).find("i").toggleClass("fa-toggle-off").toggleClass("fa-toggle-on");
	});

	// delegate calls to data-toggle="lightbox"
	$(document).delegate("*[data-toggle='lightbox']", "click", function(event) {
		event.preventDefault();
		return $(this).ekkoLightbox({
			always_show_close: true,
			gallery_parent_selector: "body"
		});
	});

	$(document).on("keydown", null, "ctrl+space", function(){
		// FIXME: this does not work with open autocomplete-widget. obviously ac overrides key bindings
		$("#mainsearch").focus().select();
		return false;
	});

	$(".navbar-upper").affix({
		offset: {bottom: 200}
	});

	NProgress.configure({
		showSpinner: false,
		parent: "#nprog-container",
		speed: 100,
		trickleRate: 0.02,
		trickleSpeed: 800
	});

	// TODO: is it correct to place this here (excluded from all bootstrap-views)?
	$(function(){
		$("#top-link-block").removeClass("hidden").affix({
			// how far to scroll down before link "slides" into view
			offset: {top:100}
		});
	});
	$("#top-link-block a").on("click", function(e) {
		e.preventDefault();
		$("html,body").animate({scrollTop:0},"fast");
		return false;
	});

	/*
	 * force confirmation when user leaves sliMpd in case local audio is playing
	 * as the browser is not displaying the text there is no nedd to fetch string from language file
	 */
	window.onbeforeunload=function(){
		if(window.sliMpd.currentPlayer.mode === "local" && window.sliMpd.currentPlayer.nowPlayingState === "play") {
			return "local audio is currently playing";
		}
	}

	/*
	 * add lazy resize listener
	 */
	$(window).bind("resizeEnd", function() {
		window.sliMpd.currentPlayer.drawWaveform();
		window.sliMpd.currentPlayer.drawTimeGrid();
	});
	$(window).resize(function() {
		if(this.resizeTO) {
			clearTimeout(this.resizeTO);
		}
		this.resizeTO = setTimeout(function() {
			$(this).trigger("resizeEnd");
		}, 500);
	});

	// add some smooth animation on initial loading
	var timeScale = 1;
	var TweenMax = window.TweenMax;
	var Quint = window.Quint;
	$(window.sliMpd.localPlayer.el).css("z-index",1027);
	$(window.sliMpd.mpdPlayer.el).css("z-index",1028);
	$(window.sliMpd.currentPlayer.el).css("z-index",1030);
	TweenMax.set([$(".permaplayer"), $(".main-nav")],{opacity:1});
	TweenMax.fromTo($(".main-nav"), 0.75, { y: -$(".main-nav").height() }, { y:0, opacity:1, ease: Quint.easeOut }).timeScale(timeScale);
	TweenMax.fromTo($("#main"), 1, { scale: 0.97 }, { scale:1, opacity:1, ease: Quint.easeOut, delay: 0.15 }).timeScale(timeScale);
	//TweenMax.staggerFrom($(".track-row"),2, { y: -30, opacity:0, ease: Quint.easeOut, delay: 0.15 }, 0.2).timeScale(timeScale);
	TweenMax.fromTo($(".permaplayer"), 0.75, { y: $(window.sliMpd.currentPlayer.el).height() }, { y:0, opacity:1, ease: Quint.easeOut, delay: 1 }).timeScale(timeScale);
});
