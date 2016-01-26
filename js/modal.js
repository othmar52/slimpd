$(document).ready(function(){
	$('[data-toggle="popover"]').popover();
});

/* TODO: fix non-working popover inside modalbox ... */
$(document).ajaxComplete(function() {
  $("[data-toggle=\"popover\"]").popover();
});
