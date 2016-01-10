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
			//console.log('playing ' + conf.item);
			
			setMediaOptions = {};
			//setMediaOptions.title = 'testtitle';
			switch(conf.ext) {
				case 'flac': setMediaOptions.flac = '/deliver/' + conf.item;
				case 'mp3': setMediaOptions.mp3 = '/deliver/' + conf.item;
				case 'wav': setMediaOptions.wav = '/deliver/' + conf.item;
				case 'm4a': setMediaOptions.m4a = '/deliver/' + conf.item;
				case 'ogg': setMediaOptions.ogg = '/deliver/' + conf.item;
			}
			setMediaOptions.supplied = conf.ext;
			$('#jquery_jplayer_1').jPlayer('setMedia', setMediaOptions);
			
			// fetch markup with trackinfos
			$.ajax({
    			url: '/markup/localplayer?item='+ conf.item
    		}).done(function(response){
    			// place markup in DOM
    			$('.player-local').html(response);
    			setPlayPauseState('play');
    			
    			// re-bind controls
    			$("#jquery_jplayer_1").jPlayer({cssSelectorAncestor: "#jp_container_1"});
    			
    			// make sure we have the correct play or pause control
    			
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
	return false;
}

