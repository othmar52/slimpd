var pollInterval = null; // do we need this in window scope?

$(document).ready(function(){
	nowPlayingSongId = 0;
	
	pollMpdData();
	
	
	$('body').on('click', '.ajax-link', function(){
		
		$.ajax({
			url: $(this).attr('href')
		}).done(function(response){
			// TODO: notify or replace content
			refreshInterval();
		});
		//console.log($(this).attr('href'));
		return false;
	});
	$('body').on('click', '.mpd-ctrl-seekbar', function(e){
		// TODO: how to respect parents padding (15px) on absolute positioned div with width 100% ?
		var percent = Math.round((e.pageX - $(this).offset().left) / (($(this).width()+15)/100));
		$.ajax({
			url: '/mpdctrl/seekPercent/' + percent
		}).done(function(response){
			refreshInterval();
		});
		
    	$('.mpd-status-progressbar').css('width', 'calc('+ percent+'% - 15px)');
	});
	
		
});

function refreshInterval() {
	clearInterval(pollInterval);
	pollMpdData();
}


function pollMpdData(){
    $.get('/mpdstatus', function(data) {
    	data = JSON.parse(data);
    	
    	
    	
    	['repeat', 'random', 'consume'].forEach(function(prop) {
		    if(data[prop] == '1') {
    		$('.mpd-status-'+prop).addClass('active');
	    	} else {
	    		$('.mpd-status-'+prop).removeClass('active');
	    	}
		});
		
		if(data.state == 'play') {
			$('.mpd-status-playpause').addClass('fa-pause');
			$('.mpd-status-playpause').removeClass('fa-play');
		} else {
			$('.mpd-status-playpause').removeClass('fa-pause');
			$('.mpd-status-playpause').addClass('fa-play');
		}
		
		
    	
    	$('.mpd-status-elapsed').text(formatTime(data.elapsed));
    	$('.mpd-status-total').text(formatTime(data.duration));
    	
    	// TODO: simulate seamless progressbar-growth and seamless secondscounter
    	// TODO: how to respect parents padding on absolute positioned div with width 100% ?
    	$('.mpd-status-progressbar').css('width', 'calc('+ data.percent+'% - 15px)');
    	
    	
    	// update trackinfo only onTrackChange()
    	if(nowPlayingSongId != data.songid) {
    		nowPlayingSongId = data.songid;
    		$.ajax({
    			url: '/markup/mpdplayer'
    		}).done(function(response){
    			//console.log(response);
    			$('.player-mpd').html(response);
    			
    		});
    	}
    	
        pollInterval = setTimeout(pollMpdData, 2000);
    });
}







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




