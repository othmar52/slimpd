$(document).ready(function() {
	"use strict";
	var $ = window.jQuery;
	var NProgress = window.NProgress;

	window.sliMpd = $.extend(true, window.sliMpd, {
		modules : {},

		drawFaviconTimeout : 0,

		xwax : false,

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
				ease
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
				ease
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
	 * TODO: make this optinal via config
	 */
	window.onbeforeunload = function(){
		if(window.sliMpd.currentPlayer.mode === "local" && window.sliMpd.currentPlayer.nowPlayingState === "play") {
			return "local audio is currently playing";
		}
	};

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
	var basicDelay = 0.15;
	var TweenMax = window.TweenMax;
	var Quint = window.Quint;
	$(window.sliMpd.localPlayer.el).css("z-index",1027);
	$(window.sliMpd.mpdPlayer.el).css("z-index",1028);
	$(window.sliMpd.currentPlayer.el).css("z-index",1030);

	//animate basic layout
	TweenMax.set([$(".permaplayer"), $(".main-nav")],{opacity:1});
	TweenMax.fromTo($(".main-nav"), 0.75, { force3D:true, z:0.01, rotationZ:0.01, y: -$(".main-nav").height() }, { force3D:true, z:0, rotationZ:0, y:0, opacity:1, ease: Quint.easeOut, delay: basicDelay }).timeScale(timeScale);
	//TweenMax.fromTo($("#main"), 1, { scale: 1.5, transformOrigin: "top center" }, { scale:1, transformOrigin: "top center", ease: Quint.easeOut, delay: 0.15 }).timeScale(timeScale);
	//TweenMax.fromTo($("#main"), 0.5, { y:-10, z:0.01, rotationZ: 0.01 }, { force3D:true, z:0.01, rotationZ:0, y:0, ease: Quint.easeOut, delay: basicDelay+0.15 }).timeScale(timeScale);
	TweenMax.fromTo($("#main"), 0.75, { opacity:0 }, { opacity:1, ease: Cubic.easeOut, delay: basicDelay+0.15 }).timeScale(timeScale);
	TweenMax.fromTo($(".permaplayer"), 0.75, { y: $(window.sliMpd.currentPlayer.el).height() }, { y:0, opacity:1, ease: Quint.easeOut, delay: basicDelay+1 }).timeScale(timeScale);

	//animate track rows
	TweenMax.staggerFrom($(".track-row"), 0.75, { force3D:true, rotationZ: 0.01, y: 30, ease: Quint.easeOut, delay: basicDelay+0.35 }, 0.1);
	TweenMax.staggerFrom($(".track-row"), 0.35, { force3D:true, rotationZ: 0.01, opacity:0, ease: Quint.easeOut, delay: basicDelay+0.35 }, 0.1);

	//click animations
	$.support.transition = false;

	var blurElement = {a:0};


	//
	$('.overlay-backdrop').on('click', function() {
			window.sliMpd.modal.$modal.modal("hide");
	});
	window.sliMpd.modal.$modal.on('show.bs.modal', function () {
			TweenMax.to($('.overlay-backdrop'),0.5,{ display: 'block', opacity: 1 });
			TweenMax.from($('.modal-content'),0.5,{ scaleY:0, ease: Cubic.easeInOut, delay:0.5 });
			TweenMax.from($('.modal-content h2'),0.25,{ alpha:0, ease: Cubic.easeInOut, delay:0.75 });
			TweenMax.staggerFrom($('.modal-content .row'), 0.5,{ y:40, opacity: 0, ease: Cubic.easeOut, delay: 0.75 }, 0.05);
			//TweenMax.to(blurElement, 0, {a:10, onUpdate: applyBlur, ease: Expo.easeOut});
			//TweenMax.set([$('.container'), $('.permaplayer')], { webkitFilter:"blur(" + 4 + "px)", filter:"blur(" + 4 + "px)"});
	});
	window.sliMpd.modal.$modal.on('hide.bs.modal', function () {
			TweenMax.to($('.overlay-backdrop'),0.25,{ display: 'none', opacity: 0 });
			//TweenMax.to(blurElement, 0, {a:0, onUpdate: applyBlur, ease: Expo.easeOut});
			//TweenMax.set([$('.container'), $('.permaplayer')], { webkitFilter:"blur(" + 0 + "px)", filter:"blur(" + 0 + "px)"});

	});

	function applyBlur()
	{
			TweenMax.set([$('.container'), $('.permaplayer')], { webkitFilter:"blur(" + blurElement.a + "px)", filter:"blur(" + blurElement.a + "px)"});
	}

});
