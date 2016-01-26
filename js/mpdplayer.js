var pollInterval = null; // do we need this in window scope?

var playerMode = $.cookie("playerMode");

$(document).ready(function(){
	nowPlayingSongId = 0;
	
	pollMpdData();
	
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

// IMPORTANT TODO: how to avoid growing memory consumption on those frequent poll-requests?
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
		
		
		// TODO: find out why this snippet does not work
		//if(data.state !== 'play' && $('.mpd-status-playpause').hasClass('fa-pause')) {
		//	$('.mpd-status-playpause').toggleClass('fa-pause fa-play');
		//}
		
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
    	
    	
		// TODO: is this the right place for trigger local-player-favicon-update? - for now it is convenient to use this existing interval...
    	drawFavicon(data.percent, data.state);


    	
    	// update trackinfo only onTrackChange()
    	if(nowPlayingSongId != data.songid) {
    		nowPlayingSongId = data.songid;
    		$('.toogle-tooltip').tooltip('hide');
    		$.ajax({
    			url: '/markup/mpdplayer'
    		}).done(function(response){
    			//console.log(response);
    			$('.player-mpd').html(response);
    			drawTimeGrid(data.duration, 'player-mpd');
    			
    			$('#css-mpdplayer').attr(
    				'href',
    				'/css/mpdplayer/'+ $('.player-mpd .now-playing-string').attr('data-hash')
    			);
    			
    		});
    	}
    	delete data;
        pollInterval = setTimeout(pollMpdData, 2000);
    });
}



function drawTimeGrid(duration, target) {
	
	if(duration <= 0) {
		return;
	}
	
	// TODO get the colors from a theme configuration instead of having it hardcoded here....
	var selectorCanvas = 'timegrid-mpd';
	var selectorSeekbar = 'mpd-ctrl-seekbar';
	var strokeColor = '#7B6137';
	var strokeColor2 = '#FCC772';
	
	
	if(target == 'player-local') {
		selectorCanvas = 'timegrid-local';
		selectorSeekbar = 'jp-seek-bar';
		strokeColor = '#157720';
		strokeColor2 = '#2DFF45';
	}
	
	var cnv = document.getElementById(selectorCanvas);
	var width = $('.' + selectorSeekbar).width();
	var height = 10;
	
	$('.'+selectorCanvas).css('width', width + 'px');
	cnv.width = width;
	cnv.height = height;
	var ctx = cnv.getContext('2d');
	
	var strokePerHour = 60;
	var changeColorAfter = 5;
	//$.jPlayer.timeFormat.showHour = false;
	
	// longer than 30 minutes
	if(duration > 1800) {
		strokePerHour = 12;
		changeColorAfter = 6;
	}
	
	// longer than 1 hour
	if(duration > 3600) {
		strokePerHour = 6;
		changeColorAfter = 6;
		//$.jPlayer.timeFormat.showHour = true;
	}
	var pixelGap = width / duration * (3600/ strokePerHour); 

	for (var i=0; i < duration/(3600/strokePerHour); i++) {
    	ctx.fillStyle = ((i+1)%changeColorAfter == 0) ? strokeColor2 : strokeColor;
    	ctx.fillRect(pixelGap*(i+1),0,1,height);
    }
    
    ctx.globalCompositeOperation = 'destination-out';
    ctx.fill();
  //cnv.style.zIndex = '2';
  


  
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

function drawFavicon(percent, state) {
	
	var doghnutColor = '#278DBA';
	var overlay;
	var titleText;
	if(playerMode === 'mpd') {
		overlay = (state == 'play')? 'play' : 'pause';
		titleText = $('.player-mpd .now-playing-string').text();
	} else {
		var localPlayerStatus = $('#jquery_jplayer_1').data('jPlayer').status;
		//console.log(localPlayerStatus);
		percent = localPlayerStatus.currentPercentAbsolute;
		overlay = (localPlayerStatus.paused == false)? 'play' : 'pause';
		doghnutColor = 'rgb(45,146,56)';
		titleText = $('.player-local .now-playing-string').text();
	}
	
	FavIconX.config({
		updateTitle: false,
		shape: 'doughnut',
		doughnutRadius: 7.5,
		overlay: overlay,
		overlayColor: '#777',
		borderColor: doghnutColor,
		fillColor: doghnutColor,
		titleRenderer: function(v, t){
			return titleText;
		}
	}).setValue(percent);
}