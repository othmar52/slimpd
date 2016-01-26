/*
 * dependencies: jquery, backbonejs, underscorejs
 */
(function() {
    "use strict";
    
    var $ = window.jQuery,
        _ = window._;
    $.extend(true, window.sliMpd, {
        modules : {}
    });
    window.sliMpd.modules.LocalPlayer = window.sliMpd.modules.PageView.extend({

        name : null,
        rendered : false,

        initialize : function(options) {
            window.Backbone.View.prototype.initialize.call(this, options);
        },

        render : function() {
            if (this.rendered) {
                return;
            }
            
            window.sliMpd.modules.PageView.call(this);
            
            this.rendered = true;
        },
        
        play : function() {
			console.log('localPlayer:play()');
        }
        
    });
    
})();