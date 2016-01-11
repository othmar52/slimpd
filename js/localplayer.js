$(document).ready(function(){
	
	  
	/* init local player */
	$("#jquery_jplayer_1").jPlayer({
        cssSelectorAncestor: "#jp_container_1",
        swfPath: "/vendor/happyworm/jplayer/dist/jplayer", // TODO: get & prefix with conf.absRefPrefix
        supplied: "mp3",
        useStateClassSkin: false,
        autoBlur: false,
        smoothPlayBar: true,
        keyEnabled: true,
        remainingDuration: false,
        toggleDuration: true,
        ended: function() {
            localPlayer({'action': 'soundEnded'});
        },
        progress: function(e,data){
        	// TODO: check why jPlayer event 'loadedmetadata' sometimes has no duration (timegrid fails to render)
        	// draw the timegrid only once as soon as we know the total duration and remove the progress eventListener
        	// @see: http://jplayer.org/latest/developer-guide/#jPlayer-events
		  	drawTimeGrid($(this).data('jPlayer').status.duration, 'player-local');
		}
      });
      
	$("#jquery_jplayer_1").jPlayer("play");
	
	
	$('.player-local').on('click', '.localplayer-play-pause', function(e){
		localPlayer({'action': 'togglePause'});
		e.preventDefault();
	});
	
});

function localPlayer(conf) {
	//console.log(conf);
	switch (conf.action) {
		case 'play':
		
			// TODO: check why conf.ext is sometimes 'vorbis' instead of 'ogg' 			
			conf.ext = (conf.ext == 'vorbis') ? 'ogg' : conf.ext;
			
			// WARNING: jPlayer's essential Audio formats are: mp3 or m4a.
			// wav, flac, ogg, m4a plays fine in chrome but we have to add an unused mp3-property...
			// TODO: really provide alternative urls instead of adding an invalid url for mp3
			$('#jquery_jplayer_1').jPlayer(
				'setMedia',
				{
					[conf.ext] : '/deliver/' + conf.item,
					'mp3' : '/deliver/' + conf.item,
					'supplied': conf.ext + ',mp3'
				}
			);
			
			// fetch markup with trackinfos
			$.ajax({
    			url: '/markup/localplayer?item='+ conf.item
    		}).done(function(response){
    			// place markup in DOM
    			$('.player-local').html(response);
    			setPlayPauseState('play');
    			
    			// re-bind controls
    			$("#jquery_jplayer_1").jPlayer({cssSelectorAncestor: "#jp_container_1"});
    		});
			break;
		case 'togglePause':
			var localPlayerStatus = $('#jquery_jplayer_1').data('jPlayer').status;
			if(localPlayerStatus.paused === false) {
				setPlayPauseState('pause');
			} else {
				setPlayPauseState('play');
			}
			break;
		case 'soundEnded':
			// for now take any rendered track and play it
			// TODO: add functionality "current playlist" (like mpd) for local player 
			var playable = $( "a.is-playlink[data-localplayer]").length;
			if(playable) {
				$("a.is-playlink[data-localplayer]").eq(Math.floor(Math.random()*playable)).click();
			}
			break;
		default:
			console.log('invalid action: ' + conf.action);
	}
	//return false;
}


function setPlayPauseState(what) {
	var player = $("#jquery_jplayer_1");
	var control = $('.localplayer-play-pause');
	if(what == 'play') {
		$(player).jPlayer( "play");
		$(control).addClass('localplayer-pause').removeClass('localplayer-play').html('<i class="fa fa-pause sign-ctrl"></i>');
	} else {
		$(player).jPlayer( "pause");
		$(control).addClass('localplayer-play').removeClass('localplayer-pause').html('<i class="fa fa-play sign-ctrl"></i>');
	}
	drawFavicon(false, false);
	return false;
}

