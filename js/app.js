var playerMode = $.cookie("playerMode");

/* on page load and on ajax content load */
function initStuff() {
	$('.trigger-modal').on('click', function (e) {
        e.preventDefault();
        $.ajax({
			url: $(this).attr('data-href')
		}).done(function(response){
			$('#global-modal .modal-content').html(response);
			$('#global-modal').modal('show');
		});
    });
    
    $(".dropdown-toggle").dropdown();
    $('.toggle-tooltip').tooltip();
    
    
    
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
    
    
    
}


$(document).ready(function(){
	
	$('body').on('click', '.ajax-btn, .ajax-link', function(){
		var ajaxTarget = $(this).attr('data-ajaxtarget');
		if(ajaxTarget) {
			// TODO: create proper curently loading visualizing
			$('<div class="modal-backdrop fade in" id="loading-backdrop"></div>').appendTo(document.body);
			$.ajax({
				url: setGetParameter($(this).attr('href'), 'nosurrounding', '1')
			}).done(function(response){
				$(ajaxTarget).html(response);
				$("#loading-backdrop").remove();
				initStuff();
				return;
			});
		}
		var localObj = $(this).attr('data-localplayer');
		if(typeof localObj == 'undefined' || playerMode !== 'local') {
			$.ajax({
				url: $(this).attr('data-href')
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
	
	
	
	$('.ajax-form').on('submit', function(e){
		e.preventDefault();
		var url = setGetParameter($(this).attr('action'), 'q', $('#mainsearch').val());
		
		// reset form to default action (has been modified by autocomplete)
		$(this).attr("action", $(this).attr("data-defaultaction"));
		
		var ajaxTarget = $(this).attr('data-ajaxtarget');
		// TODO: create proper curently loading visualizing
		$('<div class="modal-backdrop fade in" id="loading-backdrop"></div>').appendTo(document.body);
		$.ajax({
			url: setGetParameter(url, 'nosurrounding', '1')
		}).done(function(response){
			$(ajaxTarget).html(response);
			if($("#mainsearch").data('ui-autocomplete') != undefined) {
				$("#mainsearch").autocomplete( "close" );
			}
			
			// TODO: create proper curently loading visualizing
			$("#loading-backdrop").remove();
			
			initStuff();
			return;
		});
	});
	
	/* toggle between mpd-control and local player (jPlayer) */
	  $('.playerModeToggle a').on('click', function(e) {
	  	e.preventDefault();
	  	if($(this).hasClass('active-mpd') === true) {
	  		$(this).addClass('active-local').removeClass('active-mpd').html($(this).attr('data-label-local'));
	  		playerMode = "local";
	  	} else {
	  		$(this).addClass('active-mpd').removeClass('active-local').html($(this).attr('data-label-mpd'));
	  		playerMode = "mpd";
	  		// pause local player when switching to mpd
	  		setPlayPauseState('pause');
	  	}
	  	$.cookie("playerMode", playerMode, { expires : 365, path: '/' });
	  	$('.player-local,.player-mpd').toggle();
	  	drawFavicon(false, false);
	  });
	  
	  $('#global-modal').on('click', '.playerModeToggleTrigger', function(e) {
	  	e.preventDefault();
	  	$('.playerModeToggle a').trigger('click');
	  });
	  


    
    
	
	
	
	
	$('[data-toggle="popover"]').popover();
	
	initStuff(); 
});

/* TODO: fix non-working popover inside modalbox ... */
$(document).ajaxComplete(function() {
  $("[data-toggle=\"popover\"]").popover();
});


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


/**
 * adds get-paramter to url, respecting existing and not-existing params
 * @param {string} urlstring
 * @param {string} paramName
 * @param {string} paramValue
 */
function setGetParameter(urlstring, paramName, paramValue)
{
    if (urlstring.indexOf(paramName + "=") >= 0)
    {
        var prefix = urlstring.substring(0, urlstring.indexOf(paramName));
        var suffix = urlstring.substring(urlstring.indexOf(paramName));
        suffix = suffix.substring(suffix.indexOf("=") + 1);
        suffix = (suffix.indexOf("&") >= 0) ? suffix.substring(suffix.indexOf("&")) : "";
        urlstring = prefix + paramName + "=" + paramValue + suffix;
    }
    else
    {
    if (urlstring.indexOf("?") < 0)
        urlstring += "?" + paramName + "=" + paramValue;
    else
        urlstring += "&" + paramName + "=" + paramValue;
    }
    return urlstring;
}
