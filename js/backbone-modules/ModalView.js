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
    window.sliMpd.modules.ModalView = window.sliMpd.modules.AbstractView.extend({

        rendered : false,
        $modal : null,

        initialize : function(options) {
        	//console.log(options);
        	this.$modal = $('#global-modal');
            window.sliMpd.modules.AbstractView.prototype.initialize.call(this, options);
        },

        render : function() {
            
            window.sliMpd.modules.AbstractView.prototype.render.call(this);
            this.rendered = true;
        },
        
        renderModalContent : function(markup) {
        	$('.modal-content', this.$modal).html(markup);
        	//this.el = (this.$el = this.$modal.find('.modal-content'))[0];
			this.rendered = false;
			this.render();
			this.$modal.modal('show');
        }
        
    });
    
})();
