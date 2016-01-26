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

        initialize : function(options) {
            window.sliMpd.modules.AbstractView.prototype.initialize.call(this, options);
        },

        render : function() {
        	// only render page once (to prevent multiple click listeners)
            if (this.rendered) {
                return;
            }
            
            $('.ajax-form', this.$el).on('submit', function(e) {
				e.preventDefault();
				var url = window.sliMpd.setGetParameter($(this).attr('action'), 'q', $('#mainsearch').val());
				
				// reset form to default action (has been modified by autocomplete)
				$(this).attr("action", $(this).attr("data-defaultaction"));
				
				window.sliMpd.router.navigate(url, {
					trigger : true
				});
				
				// TODO : insert tabbedautocomplete js code
				// TODO : on rendering of search-results, re-init click listener:
				// $('.ui-autocomplete a.ajax-link').off('click', this.genericClickListener).on('click', this.genericClickListener);
			});
            
            window.sliMpd.modules.AbstractView.prototype.render.call(this);
            this.rendered = true;
        }
        
    });
    
})();
