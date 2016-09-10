/* Copyright
 *
 */
$(document).ready(function() {
	"use strict";
	
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
	TweenMax.fromTo($("#main"), 0.75, { opacity:0 }, { opacity:1, ease: window.Cubic.easeOut, delay: basicDelay+0.15 }).timeScale(timeScale);
	TweenMax.fromTo($(".permaplayer"), 0.75, { y: $(window.sliMpd.currentPlayer.el).height() }, { y:0, opacity:1, ease: Quint.easeOut, delay: basicDelay+1 }).timeScale(timeScale);

	//animate track rows
	TweenMax.staggerFrom($(".track-row"), 0.75, { force3D:true, rotationZ: 0.01, y: 30, ease: Quint.easeOut, delay: basicDelay+0.35 }, 0.1);
	TweenMax.staggerFrom($(".track-row"), 0.35, { force3D:true, rotationZ: 0.01, opacity:0, ease: Quint.easeOut, delay: basicDelay+0.35 }, 0.1);

	//click animations
	$.support.transition = false;

	var blurElement = {a:0};

	$(".overlay-backdrop").on("click", function() {
		window.sliMpd.modal.$modal.modal("hide");
	});
	window.sliMpd.modal.$modal.on("show.bs.modal", function () {
		TweenMax.to($(".overlay-backdrop"),0.5,{ display: "block", opacity: 1 });
		TweenMax.from($(".modal-content"),0.5,{ scaleY:0, ease: window.Cubic.easeInOut, delay:0.5 });
		TweenMax.from($(".modal-content h2"),0.25,{ alpha:0, ease: window.Cubic.easeInOut, delay:0.75 });
		TweenMax.staggerFrom($(".modal-content .row"), 0.5,{ y:40, opacity: 0, ease: window.Cubic.easeOut, delay: 0.75 }, 0.05);
		//TweenMax.to(blurElement, 0, {a:10, onUpdate: applyBlur, ease: Expo.easeOut});
		//TweenMax.set([$(".container"), $(".permaplayer")], { webkitFilter:"blur(" + 4 + "px)", filter:"blur(" + 4 + "px)"});
	});
	window.sliMpd.modal.$modal.on("hide.bs.modal", function () {
		TweenMax.to($(".overlay-backdrop"),0.25,{ display: "none", opacity: 0 });
		//TweenMax.to(blurElement, 0, {a:0, onUpdate: applyBlur, ease: Expo.easeOut});
		//TweenMax.set([$(".container"), $(".permaplayer")], { webkitFilter:"blur(" + 0 + "px)", filter:"blur(" + 0 + "px)"});

	});

	function applyBlur() {
		TweenMax.set([$(".container"), $(".permaplayer")], { webkitFilter:"blur(" + blurElement.a + "px)", filter:"blur(" + blurElement.a + "px)"});
	}
});
