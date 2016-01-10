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
        toggleDuration: true
      });
      
	$("#jquery_jplayer_1").jPlayer("play");
	
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
			$('#jquery_jplayer_1').jPlayer( "play");
			
			
			// fetch markup with trackinfos
			$.ajax({
    			url: '/markup/localplayer?item='+ conf.item
    		}).done(function(response){
    			// place markup in DOM
    			$('.player-local').html(response);
    			
    			// re-bind controls
    			$("#jquery_jplayer_1").jPlayer({cssSelectorAncestor: "#jp_container_1"});
    		});
			break;
		case 'togglepause':
			
			break;
		default:
			console.log('invalid action: ' + conf.action);
	}
	//return false;
}
