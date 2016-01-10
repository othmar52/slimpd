var playerMode = "local";

$(document).ready(function(){
	
	$('body').on('click', '.ajax-link', function(){
		var localObj = $(this).attr('data-localplayer');
		if(typeof localObj == 'undefined' || playerMode !== 'local') {
			$.ajax({
				url: $(this).attr('href')
			}).done(function(response){
				// TODO: notify or replace content
				refreshInterval();
			});
		} else {
			try{
		        var a = JSON.parse(localObj);
		        localPlayer(a);
		    }catch(e){
		    	console.log(e + ' in data-localplayer attribute');
		    }
		}
		return false;
	});
	
	
	/* toggle between mpd-control and local player (jPlayer) */
	  $('.playerModeToggle button').on('click', function () {
	  	if($(this).hasClass('active') === true) {
	  		$(this).html($(this).attr('data-label-local'));
	  		playerMode = "local";
	  	} else {
	  		$(this).html($(this).attr('data-label-mpd'));
	  		playerMode = "mpd";
	  		// pause local player when switching to mpd
	  		$('#jquery_jplayer_1').jPlayer( "pause");
	  	}
	  	$('.player-local,.player-mpd').toggle();
	  });

	
	/* route /maintainance/albumdebug */
    $('.inline-tab-nav a').click(function (e) {
        e.preventDefault();
        $(this).tab('show');
    });
    $(".grid").sortable({
        tolerance: 'pointer',
        revert: 'invalid',
        placeholder: 'span2 well placeholder tile',
        forceHelperSize: true
    });
    
	
	$(".dropdown-toggle").dropdown();
});




//  +------------------------------------------------------------------------+
//  | Formatted time                                                         |
//  +------------------------------------------------------------------------+
function formatTime(seconds) {
	var seconds 	= Math.round(seconds);
	var hour 		= Math.floor(seconds / 3600);
	var minutes 	= Math.floor(seconds / 60) % 60;
	seconds 		= seconds % 60;
		
	if (hour > 0)	return hour + ':' + zeroPad(minutes, 2) + ':' + zeroPad(seconds, 2);
	else			return minutes + ':' + zeroPad(seconds, 2);
}




//  +------------------------------------------------------------------------+
//  | Zero pad                                                               |
//  +------------------------------------------------------------------------+
function zeroPad(number, n) { 
	var zeroPad = '' + number;
	
	while(zeroPad.length < n)
		zeroPad = '0' + zeroPad; 
	
	return zeroPad;
}

