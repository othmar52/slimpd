/*
 * dependencies: jquery, backbonejs, underscorejs, window.sliMpd.router, window.sliMpd.modules.AbstractView
 */
(function() {
    "use strict";
    
    var $ = window.jQuery,
        _ = window._;
    window.sliMpd = $.extend(true, window.sliMpd, {
        modules : {}
    });
    window.sliMpd.modules.NavbarView = window.sliMpd.modules.AbstractView.extend({

        rendered : false,
        
        tabAutocomplete : false,
        
        searchfield : $('#mainsearch'),

        initialize : function(options) {
            window.sliMpd.modules.AbstractView.prototype.initialize.call(this, options);
        },

        render : function() {
        	// only render page once (to prevent multiple click listeners)
            if (this.rendered) {
                return;
            }
            
            var that = this;
            
            $('.ac-ajax-form', this.$el).on('submit', function(e) {
				e.preventDefault();
				var url = window.sliMpd.setGetParameter($(this).attr('action'), 'q', that.searchfield.val());
				
				// reset form to default action (has been modified by autocomplete)
				$(this).attr("action", $(this).attr("data-defaultaction"));
				
				
				// make sure autocomplete does not appear after rendering of resultpage
				that.searchfield.autocomplete('close');
				that.searchfield.autocomplete('disable');
				
				window.sliMpd.router.navigate(url, {
					trigger : true
				});
				
				that.enableAutocompleteDelayed();
				
				// TODO : insert tabbedautocomplete js code
				// TODO : on rendering of search-results, re-init click listener:
				// $('.ui-autocomplete a.ajax-link').off('click', this.genericClickListener).on('click', this.genericClickListener);
			});
			
			this.tabAutocomplete = that.searchfield.autocomplete({
				source: function( request, response ) {
					NProgress.start();
					$.ajax({
						url: "/autocomplete/all/" + $('#mainsearch').val(),
		          		dataType: "json",
		          		type: 'get',
		          		success: function( data ) {
		          			NProgress.done();
							response( data );
						},
						messages: {
					        noResults: '',
					        results: function() {}
					    }
					});
				},
				sourceCategory: 'all',
				minLength: 3,
				focus: function( event, ui ) {
					$(".ui-helper-hidden-accessible").hide();
					if(typeof ui.item !== 'undefined') {
						ui.item.value = that.stripTags(ui.item.value);
					}
				},
				select: function( event, ui ) {
					// do not navigate away with visible modal
					if(window.sliMpd.modal.$modal.hasClass('in')) {
						return false;
					}
					//console.log(ui.item);
					if(ui.item) {
						$('#searchform').attr('action', ui.item.url);
						$('#searchform').submit();
					}
				}
			}).data("ui-autocomplete");
		     /* custom boostrap markup for items */
			this.tabAutocomplete._renderItem = function (ul, item) {
		     	
		     	
		     	var widgetLink = '';
		     	if(item.type == 'track') {
		     		widgetLink = $('<a />')
		     			.attr('class', 'trigger-modal')
		     			.attr('href', '/markup/widget-trackcontrol?item='+ item.itemid )
		     			.html(' <i class="fa fa-plus-square"></i>')
		     			.bind('click', function(e){
		     				// TODO: find another way to disable autocomplete-select-event when modal-opm has been fired
		     				window.sliMpd.modal.$modal.addClass('in');
		     				e.preventDefault();
		     				// TODO: is it possible to use event listener which already exists on all .trigger-modal elements?
					        $.ajax({
								url: $(this).attr('href')
							}).done(function(response){
								window.sliMpd.modal.renderModalContent(response);
							});
		     			});
		     	}
		     	
				var markup = $('<div />', {'class':'row'})
				.append(
					$('<div />', {'class':'col-md-2'}).append(
						$('<img />', {'src':item.img, 'width': 50, 'height': 50})
					)
				)
				.append(
					$('<div />', {'class':'col-md-10'}).append(
						$('<a />', {'href':item.url, html: item.label, 'class': 'ajax-link', 'data-ajaxtarget': '#main'})
						.bind('click', function(e){
							e.preventDefault();
							var $el = $(e.currentTarget);
							window.sliMpd.router.navigate($el.attr('href'), {
								trigger : true
							});
						})
					)
					.append(
						$('<br/>')
					)
					.append(
						$('<span/>', {'class': 'dark', text:item.typelabel })
					)
					.append(
						$(widgetLink)
					)	
				 );
		         return $("<li></li>")
		             .data("item.autocomplete", item)
		             .append(markup)
		             .appendTo(ul);
		     };
		     // create a few filter links in autocomplete widget
			this.tabAutocomplete._renderMenu = function( ul, items, type ) {
				var that = this;
				var markup = $('<div>').attr('class', 'nav nav-pills ac-nav type-nav ');
				var filterLinks = ["all", "artist", "album", "label", "dirname"];
				var cat = this.options.sourceCategory;
				filterLinks.forEach(function(filter){
					$('<button>').attr('type', 'button')
					.attr('class', 'btn uc btn-primary' + ((cat === filter)?'active':''))
					.attr('data-filter', filter)
					.text(filter).bind('click', function(){
						that.changeAutocompleteUrl(filter);
					}).appendTo(markup);
				});
				$(markup).wrapAll($('<li>').attr('class', 'ui-state-disabled')).appendTo(ul);
				
				$.each( items, function( index, item ) {
					that._renderItemData( ul, item );
				});
			};
			
			// arrow left right for switching between tabs
			that.searchfield.keydown( function( event ) {
				
				// check if widget is visile
				var isOpen = $( this ).autocomplete( "widget" ).is( ":visible" );
				
				// TODO: limit functionality on focused item
				//focused = $('#mainsearch').data("ui-autocomplete").menu.element.find("li.ui-state-focus").length;
				
				if ( isOpen /*&& focused == 1*/ && event.keyCode == $.ui.keyCode.LEFT) {
					var prev = $('.ac-nav button.btn-primaryactive').prev();
					if(prev.length) {
						that.changeAutocompleteUrl(prev.attr('data-filter'));
						return false;
					}
				}
				
				if ( isOpen /*&& focused == 1*/ && event.keyCode == $.ui.keyCode.RIGHT) {
					var next = $('.ac-nav button.btn-primaryactive').next();
					if(next.length) {
						that.changeAutocompleteUrl(next.attr('data-filter'));
						return false;
					}
				}
			});
            window.sliMpd.modules.AbstractView.prototype.render.call(this);
            this.rendered = true;
       },
       
       changeAutocompleteUrl : function (type) {
			// set input value to initial searchterm
			this.searchfield.val(this.searchfield.data("ui-autocomplete").term);
			
			// change ajax-url
			var that = this;
			this.searchfield.autocomplete('option', 'source', function( request, response ) {
				NProgress.start();
				$.ajax({
					url: "/autocomplete/"+ type+"/" + that.searchfield.val(),
		      		dataType: "json",
		      		type: 'get',
		      		success: function( data ) {
		      			NProgress.done();
						response( data );
					},
					messages: {
				        noResults: '',
				        results: function() {}
				    }
				});
			});
			
			// store active filter in variable
			this.searchfield.autocomplete('option', 'sourceCategory', type);
			
			// trigger refresh with new ajax-url
			this.searchfield.autocomplete().data("ui-autocomplete")._search();
		},
		
		stripTags : function ( str ) {
		    str=str.toString();
		    return str.replace(/<\/?[^>]+>/gi, '');
		},
		
		enableAutocompleteDelayed : function() {
			setTimeout(function(){
				window.sliMpd.navbar.searchfield.autocomplete("enable");
			},
			2000);
		}
    });
    
})();
