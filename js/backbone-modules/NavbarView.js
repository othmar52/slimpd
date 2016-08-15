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

		searchfield : $("#mainsearch"),

		initialize(options) {
			window.sliMpd.modules.AbstractView.prototype.initialize.call(this, options);
		},

		render() {
			// only render page once (to prevent multiple click listeners)
			if (this.rendered) {
				return;
			}

			var that = this;

			$(".ac-ajax-form", this.$el).on("submit", function(e) {
				e.preventDefault();
				var url = window.sliMpd.setGetParameter($(this).attr("action"), "q", that.searchfield.val());

				// reset form to default action (has been modified by autocomplete)
				$(this).attr("action", $(this).attr("data-defaultaction"));

				
				// make sure autocomplete does not appear after rendering of resultpage
				that.searchfield.autocomplete("close");
				that.searchfield.autocomplete("disable");

				window.sliMpd.router.navigate(url, {
					trigger : true
				});

				that.enableAutocompleteDelayed();
			});

			this.tabAutocomplete = this.searchfield.autocomplete({
				source( request, response ) {
					window.NProgress.start();
					var $this = $(this);
					var $element = $(this.element);
					var previousRequest = $element.data( "jqXHR" );
					if( previousRequest ) {
						// a previous request has been made.
						// though we don"t know if it"s concluded
						// we can try and kill it in case it hasn"t
						// TODO: not sure if killing request really works
						previousRequest.abort();
					}
					// Store new AJAX request
					$element.data( "jqXHR", $.ajax( {
						type: "GET",
						url: window.sliMpd.conf.absRefPrefix + "autocomplete/all/?q=" + decodeURIComponent($("#mainsearch").val()),
						dataType: "json",
						success( data ) {
							window.NProgress.done();
							response( data );
						},
						messages: {
							noResults: "",
							results() {}
						}
					}));
				},

				sourceCategory: "all",
				minLength: 3,
				delay: 300,

				focus( event, ui ) {
					$(".ui-helper-hidden-accessible").hide();
					if(typeof ui.item !== "undefined") {
						ui.item.value = that.stripTags(ui.item.value);
					}
				},

				select( event, ui ) {
					// do not navigate away with visible modal
					if(window.sliMpd.modal.$modal.hasClass("in")) {
						return false;
					}
					//console.log(ui.item);
					if(ui.item) {
						$("#searchform").attr("action", ui.item.url);
						$("#searchform").submit();
					}
				}
			}).data("ui-autocomplete");

			/* override jqueryui"s core function on our instance
			 *   core: navigate through all silblings
			 *   ours: navigate only through ".ui-menu-item"-silblings
			 *
			 * so the tabbed-nav-silbling won"t get selected :)
			 *
			 */
			this.tabAutocomplete.menu._move = function( direction, filter, event ) {
				var next;
				if ( this.active ) {
					if ( direction === "first" || direction === "last" ) {
						next = this.active
							[ direction === "first" ? "prevAll" : "nextAll" ]( ".ui-menu-item" )
							.eq( -1 );
					} else {
						next = this.active
							[ direction + "All" ]( ".ui-menu-item" )
							.eq( 0 );
					}
				}
				if ( !next || !next.length || !this.active ) {
					next = this.activeMenu.find( ".ui-menu-item" )[ filter ]();
				}

				this.focus( event, next );
			};

			/* custom boostrap markup for items */
			this.tabAutocomplete._renderItem = function (ul, item) {
				var additionalMarkup = "";
				switch(item.type) {
					case "track":
						additionalMarkup = $("<a />")
							.attr("class", "trigger-modal")
							.attr("href", window.sliMpd.conf.absRefPrefix + "markup/widget-trackcontrol?item="+ item.itemid )
							.html(" <i class='fa fa-plus-square'></i>")
							.bind("click", function(e){
								// TODO: find another way to disable autocomplete-select-event when modal-opm has been fired
								window.sliMpd.modal.$modal.addClass("in");
								e.preventDefault();
								// TODO: is it possible to use event listener which already exists on all .trigger-modal elements?
								$.ajax({
									url: $(this).attr("href")
								}).done(function(response){
									window.sliMpd.modal.renderModalContent(response);
								});
							});
						break;
					case "label":
					case "genre":
					case "artist":
						additionalMarkup = $("<span />")
							.attr("class", "pull-right dark")
							.html(" <span class='badge'>"+item.trackcount+"</span> Tracks, <span class='badge'>"+item.albumcount+"</span> Albums");
						break;
					case "album":
					case "dirname":
						additionalMarkup = $("<span />")
							.attr("class", "pull-right dark")
							.html(" <span class='badge'>"+item.trackcount+"</span> Tracks");
						break;
				}
				
				var markup = $("<div />", {"class":"row"})
				.append(
					$("<div />", {"class":"col-md-2"}).append(
						$("<img />", {"src":item.img, "width": 50, "height": 50})
					)
				)
				.append(
					$("<div />", {"class":"col-md-10"}).append(
						$("<a />", {"href":item.url, html: item.label, "class": "ajax-link", "data-ajaxtarget": "#main"})
						.bind("click", function(e){
							e.preventDefault();
							var $el = $(e.currentTarget);
							window.sliMpd.router.navigate($el.attr("href"), {
								trigger : true
							});
						})
					)
					.append(
						$("<br/>")
					)
					.append(
						$("<span/>", {"class": "dark", text:item.typelabel })
					)
					.append(
						$(additionalMarkup)
					)	
				);
				return $("<li></li>")
					.data("item.autocomplete", item)
					.append(markup)
					.appendTo(ul);
			};
			// create a few filter links in autocomplete widget
			this.tabAutocomplete._renderMenu = function( ul, items, type ) {
				var $markup = $("<div>").attr("class", "nav nav-pills ac-nav type-nav ");
				var filterLinks = ["all", "artist", "album", "label", "dirname"];
				var cat = this.options.sourceCategory;
				filterLinks.forEach(function(filter){
					$("<button>").attr("type", "button")
					.attr("class", "btn uc btn-primary" + ((cat === filter)?"active":""))
					.attr("data-filter", filter)
					.text(filter).on("click", function() {
						that.changeAutocompleteUrl($(this).data("filter"));
					}).appendTo($markup);
				});
				$("<li class='ui-state-disabled ui-menu-divider' />").append($markup).appendTo(ul);

				$.each( items, function( index, item ) {
					that.tabAutocomplete._renderItemData( ul, item );
				});
			};

			// arrow left right for switching between tabs
			that.searchfield.keydown( function( event ) {

				// check if widget is visile
				var isOpen = $( this ).autocomplete( "widget" ).is( ":visible" );

				// TODO: limit functionality on focused item
				//focused = $("#mainsearch").data("ui-autocomplete").menu.element.find("li.ui-state-focus").length;

				if ( isOpen /*&& focused == 1*/ && event.keyCode === $.ui.keyCode.LEFT) {
					var prev = $(".ac-nav button.btn-primaryactive").prev();
					if(prev.length) {
						that.changeAutocompleteUrl(prev.attr("data-filter"));
						return false;
					}
				}

				if ( isOpen /*&& focused == 1*/ && event.keyCode === $.ui.keyCode.RIGHT) {
					var next = $(".ac-nav button.btn-primaryactive").next();
					if(next.length) {
						that.changeAutocompleteUrl(next.attr("data-filter"));
						return false;
					}
				}
			});
			window.sliMpd.modules.AbstractView.prototype.render.call(this);
			this.rendered = true;
		},
		
		changeAutocompleteUrl(type) {
			// set input value to initial searchterm
			this.searchfield.val(this.searchfield.data("ui-autocomplete").term);

			// change ajax-url
			var that = this;
			this.searchfield.autocomplete("option", "source", function( request, response ) {
				window.NProgress.start();
				$.ajax({
					url: window.sliMpd.conf.absRefPrefix + "autocomplete/"+ type+"/?q=" + decodeURIComponent(that.searchfield.val()),
					dataType: "json",
					type: "get",
					success( data ) {
						window.NProgress.done();
						response( data );
					},
					messages: {
						noResults: "",
						results() {}
					}
				});
			});

			// store active filter in variable
			this.searchfield.autocomplete("option", "sourceCategory", type);

			// trigger refresh with new ajax-url
			this.searchfield.autocomplete().data("ui-autocomplete")._search();
		},

		stripTags(str) {
			str=str.toString();
			return str.replace(/<\/?[^>]+>/gi, "");
		},

		enableAutocompleteDelayed() {
			setTimeout(function(){
				window.sliMpd.navbar.searchfield.autocomplete("enable");
			},
			2000);
		}
	});
}());
