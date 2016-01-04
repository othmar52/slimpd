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
    
    var tabbedAutocomplete = $('#mainsearch').autocomplete({
		source: function( request, response ) {
			$.ajax({
				url: "/autocomplete/all/" + $('#mainsearch').val(),
          		dataType: "json",
          		type: 'get',
          		success: function( data ) {
					response( data );
				}
			});
		},
		sourceCategory: 'all',
		minLength: 3,
		focus: function( event, ui ) {
			if(typeof ui.item !== 'undefined') {
				ui.item.value = stripTags(ui.item.value);
			}
		},
		select: function( event, ui ) {
			//console.log(ui.item);
			if(ui.item) {
				document.location.href = ui.item.url;
				return false;
				$('#searchform').submit();
			}
		}
	}).data("ui-autocomplete");
     /* custom boostrap markup for items */
     tabbedAutocomplete._renderItem = function (ul, item) {
		var markup = '<div class="row"><div class="col-md-1"><img src="'+item.img+'" width="50" height="50"/></div>';
		markup += '<div class="col-md-11"><a href="'+ item.url +'">'+ item.label+'</a><br /><span class="dark">'+ item.type+'</span></div></div>';
         return $("<li></li>")
             .data("item.autocomplete", item)
             .append(markup)
             .appendTo(ul);
     };
     // create a few filter links in autocomplete widget
    tabbedAutocomplete._renderMenu = function( ul, items, type ) {
		var that = this;
		var markup = $('<div>').attr('class', 'nav nav-pills ac-nav');
		var filterLinks = ["all", "artist", "album", "label"];
		var cat = this.options.sourceCategory;
		filterLinks.forEach(function(filter){
			$('<button>').attr('type', 'button')
			.attr('class', 'btn btn-primary' + ((cat === filter)?'active':''))
			.attr('data-filter', filter)
			.text(filter).bind('click', function(){
				changeAutocompleteUrl(filter);
			}).appendTo(markup);
		});
		$(markup).wrapAll($('<li>').attr('class', 'ui-state-disabled')).appendTo(ul);
		
		$.each( items, function( index, item ) {
			that._renderItemData( ul, item );
		});
	};
	
	// arrow left right for switching between tabs
	$('#mainsearch').keydown( function( event ) {
		
		// check if widget is visile
		var isOpen = $( this ).autocomplete( "widget" ).is( ":visible" );
		
		// TODO: limit functionality on focused item
		//focused = $('#mainsearch').data("ui-autocomplete").menu.element.find("li.ui-state-focus").length;
		
		if ( isOpen /*&& focused == 1*/ && event.keyCode == $.ui.keyCode.LEFT) {
			var prev = $('.ac-nav button.btn-primaryactive').prev();
			if(prev.length) {
				changeAutocompleteUrl(prev.attr('data-filter'));
				return false;
			}
		}
		
		if ( isOpen /*&& focused == 1*/ && event.keyCode == $.ui.keyCode.RIGHT) {
			var next = $('.ac-nav button.btn-primaryactive').next();
			if(next.length) {
				changeAutocompleteUrl(next.attr('data-filter'));
				return false;
			}
		}
	});
	
	$(".dropdown-toggle").dropdown();
});


function changeAutocompleteUrl(type) {
	// set input value to initial searchterm
	$('#mainsearch').val($('#mainsearch').data("ui-autocomplete").term);
	
	// change ajax-url
	$('#mainsearch').autocomplete('option', 'source', function( request, response ) {
		$.ajax({
			url: "/autocomplete/"+ type+"/" + $('#mainsearch').val(),
      		dataType: "json",
      		type: 'get',
      		success: function( data ) {
				response( data );
			}
		});
	});
	
	// store active filter in variable
	$('#mainsearch').autocomplete('option', 'sourceCategory', type);
	
	// trigger refresh with new ajax-url
	$('#mainsearch').autocomplete().data("ui-autocomplete")._search();
}

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
    	
    	//console.log(data);
    	FavIconX.config({
    	  updateTitle: false,
    	  shape: 'doughnut',
    	  doughnutRadius: 7.5,
    	  overlay: ((data.state == 'play')? 'play' : 'pause'),
    	  overlayColor: '#777',
    	  borderColor: '#278DBA',
    	  fillColor: '#278DBA',
    	  titleRenderer: function(v, t){
			return $('.now-playing-string').text();
		  }
		}).setValue(data.percent);
    	
    	
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

function stripTags( str ) {
    str=str.toString();
    return str.replace(/<\/?[^>]+>/gi, '');
}


