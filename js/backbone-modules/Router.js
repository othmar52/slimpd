/*
 * dependencies: jquery, backbonejs, window.sliMpd.modules.PageView
 */
(function() {
    "use strict";
    
    var $ = window.jQuery;
    window.sliMpd = $.extend(true, window.sliMpd, {
        modules : {}
    });
    window.sliMpd.modules.Router = window.Backbone.Router.extend({
    	
    	rendered : false,
    	ajaxLoading : false,

        currentView : null,
        previousView : null,
        $content : null,
        $body : null,

        routes : {
            '' : 'generic',
            '*generic' : 'generic',
            // 'albums/(*generic)' : 'album'
        },

        initialize : function(options) {
            this.$content = $('#main');
            this.$body = $('body');
        },
        
        navigate : function(fragment, options) {
        	if (this.ajaxLoading) {
        		return;
        	}
        	window.Backbone.Router.prototype.navigate.call(this, fragment, options);
        },

        generic : function(route, queryString) {
        	var name = ((route === null) ? 'home' : route + '?' + queryString),
        		url = '/' + ((route === null) ? '' : route + '?' + queryString);

            if (this.currentView && this.currentView.name === name) {
                return;
            }
            
            // remove view on ajax-done
            this.previousView = (this.currentView) ? this.currentView : null;
            
            // first time page loaded markup is delivered by backend, no need for ajax call!
            if (!this.rendered) {
	            this.currentView = new window.sliMpd.modules.PageView({
	                name : name,
	                templateString : '',
	                el : '#main>.main-content'
	            });
	            this.currentView.render(false); // renderMarkup flag false, to prevent markup re-rendering
	            this.rendered = true;
	            return;
            }
            
            // TODO: add proper loading animation
            this.$body.addClass('is-loading');
			$('<div class="modal-backdrop fade in" id="loading-backdrop"></div>').appendTo(this.$body);
			this.ajaxLoading = true;
			$.ajax({
				url: window.sliMpd.setGetParameter(url, 'nosurrounding', '1')
			}).done(function(response) {
				if(this.previousView) {
					this.previousView.remove()
				}
	            this.currentView = new window.sliMpd.modules.PageView({
	                name : name,
	                templateString : response
	            });
	            this.currentView.render(true);
	            this.$content.html(this.currentView.$el);
	            this.$body.removeClass('is-loading');
				$("#loading-backdrop").remove();
				this.ajaxLoading = false;
			}.bind(this));
        },

        album : function(album) {
            if (this.currentView && this.currentView.name === 'album/' + album) {
                return;
            }
            if (this.currentView) {
                this.currentView.remove();
            }

            this.currentView = new window.sliMpd.modules.AlbumView({
                name : 'album/' + album,
                templateString : '' // TODO : get content via ajax
            });
            this.currentView.render();
            this.$content.html(this.currentView.$el);
        },
        // FIXME: how to refresh #main view without pushing anything to history?
        refreshIfName : function(routename) {
        	return;
        	if(this.currentView.name !== routename) {
        		console.log('Router::refreshIfName(' + routename + ') does not match ' + this.currentView.name);
        		return;
        	}
        	console.log('Router::refreshIfName(' + routename + ') matches');
        	
        	console.log(sliMpd.router.$body.context.location.pathname);
        	this.currentView.rendered = false;
        	this.navigate($el.attr(sliMpd.router.$body.context.location.pathname), {
                trigger : true
            });
        	//this.currentView.rendered = false;
        	//this.currentView.render(true);
        }
    });
    
})();