/* Copyright (C) 2016 othmar52 <othmar52@users.noreply.github.com>
 *
 * This file is part of sliMpd - a php based mpd web client
 *
 * This program is free software: you can redistribute it and/or modify it
 * under the terms of the GNU Affero General Public License as published by the
 * Free Software Foundation, either version 3 of the License, or (at your
 * option) any later version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or
 * FITNESS FOR A PARTICULAR PURPOSE. See the GNU Affero General Public License
 * for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */
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

        searchfield : false,

        previousRequest: null,

        initialize : function(options) {
            this.searchfield = $("#mainsearch", this.$el);
            window.sliMpd.modules.AbstractView.prototype.initialize.call(this, options);
        },

        render : function(renderMarkup) {
            // only render page once (to prevent multiple click listeners)
            if (this.rendered) {
                return;
            }

            if (renderMarkup) {
                this.$el.html($(this._template((this.model || {}).attributes)));
                this.searchfield = $("#mainsearch", this.$el);
            }

            var that = this;

            $(".ac-ajax-form", this.$el).on("submit", function(e) {
                e.preventDefault();
                var url = window.sliMpd.router.setGetParameter($(this).attr("action"), "q", that.searchfield.val());

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
                source : function( request, response ) {
                    window.NProgress.start();
                    if( that.previousRequest ) {
                        // a previous request has been made.
                        // though we don"t know if it"s concluded
                        // we can try and kill it in case it hasn"t
                        that.previousRequest.abort();
                    }
                    // Store new AJAX request
                    that.previousRequest = $.ajax( {
                        type: "GET",
                        url: window.sliMpd.conf.absRefPrefix + "autocomplete/all/?q=" + decodeURIComponent($("#mainsearch").val()),
                        dataType: "json",
                        success : function( data ) {
                            window.NProgress.done();
                            response( data );
                        },
                        messages: {
                            noResults: "",
                            results : function() {}
                        }
                    });
                },

                sourceCategory: "all",
                minLength: 3,
                delay: 250, // TODO: delay for up/down/left/right arrow keys should be zero but changing the searchterm should be ~300ms

                focus : function( event, ui ) {
                    $(".ui-helper-hidden-accessible").hide();
                    if(typeof ui.item !== "undefined") {
                        ui.item.value = that.stripTags(ui.item.value);
                    }
                },

                select : function( event, ui ) {
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
                            .attr("href", window.sliMpd.conf.absRefPrefix + "markup/widget-trackcontrol?item="+ item.itemuid )
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
                var filterLinks = ["all", "artist", "album", "label", "dirname", "track", "genre"];
                var cat = this.options.sourceCategory;
                switch (that.searchfield.val().slice(0, 3)) {
                    case '-a ': cat = 'artist'; break;
                    case '-l ': cat = 'label'; break;
                    case '-r ': cat = 'album'; break;
                    case '-d ': cat = 'dirname'; break;
                    case '-t ': cat = 'track'; break;
                    default: break;
                }

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
        
        changeAutocompleteUrl : function(type) {
            // set input value to initial searchterm
            this.searchfield.val(this.searchfield.data("ui-autocomplete").term);

            // change ajax-url
            var that = this;
            this.searchfield.autocomplete("option", "source", function( request, response ) {
                window.NProgress.start();
                if( that.previousRequest ) {
                    // a previous request has been made.
                    // though we don"t know if it"s concluded
                    // we can try and kill it in case it hasn"t
                    that.previousRequest.abort();
                }
                // Store new AJAX request
                that.previousRequest = $.ajax({
                    url: window.sliMpd.conf.absRefPrefix + "autocomplete/"+ type+"/?q=" + decodeURIComponent(that.searchfield.val()),
                    dataType: "json",
                    type: "get",
                    success : function( data ) {
                        window.NProgress.done();
                        response( data );
                    },
                    messages: {
                        noResults: "",
                        results : function() {}
                    }
                });
            });

            // store active filter in variable
            this.searchfield.autocomplete("option", "sourceCategory", type);

            // trigger refresh with new ajax-url
            this.searchfield.autocomplete().data("ui-autocomplete")._search();
        },

        stripTags : function(str) {
            str=str.toString();
            return str.replace(/<\/?[^>]+>/gi, "");
        },

        enableAutocompleteDelayed : function() {
            setTimeout(function(){
                window.sliMpd.navbar.searchfield.autocomplete("enable");
            },
            2000);
        },

        redraw : function(headerMarkup) {
            // place markup in DOM
            this.searchfield.autocomplete("destroy");
            this._template = _.template(headerMarkup);
            this.rendered = false;
            this.render(true);
        }
    });
}());
