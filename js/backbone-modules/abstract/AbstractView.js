/*
 * dependencies: jquery, backbonejs, underscorejs, window.sliMpd.router
 */
(function() {
    "use strict";
    
    var $ = window.jQuery,
        _ = window._;
    window.sliMpd = $.extend(true, window.sliMpd, {
        modules : {}
    });
    window.sliMpd.modules.AbstractView = window.Backbone.View.extend({

        rendered : false,

        initialize : function(options) {
            window.Backbone.View.prototype.initialize.call(this, options);
            _.bindAll.apply(_, [this].concat(_.functions(this)));
        },

        render : function() {
        	// only render page once (to prevent multiple click listeners)
            if (this.rendered) {
                return;
            }
            $("body", this.$el).animate({ scrollTop: 0 }, 300);
            
            $('a.ajax-link', this.$el).off('click', this.genericClickListener).on('click', this.genericClickListener);
            $('.ajax-rqst', this.$el).off('click', this.ajaxRequestClickListener).on('click', this.ajaxRequestClickListener);
            $('.player-ctrl', this.$el).off('click', this.playerCtrlClickListener).on('click', this.playerCtrlClickListener);
            $('.ajax-partial', this.$el).off('click', this.ajaxPartialClickListener).on('click', this.ajaxPartialClickListener);
            $('.trigger-modal', this.$el).off('click', this.triggerModalClickListener).on('click', this.triggerModalClickListener);
            $('.glue-hover-trigger', this.$el).off('mouseenter', this.itemGlueMouseenterListener).on('mouseenter', this.itemGlueMouseenterListener);
            $('.glue-hover-trigger', this.$el).off('mouseleave', this.itemGlueMouseleaveListener).on('mouseleave', this.itemGlueMouseleaveListener);
            $('.toggle-content', this.$el).off('click', this.itemToggleClickListener).on('click', this.itemToggleClickListener);
            //$('*[data-toggle="lightbox"]', this.$el).off('click', this.triggerLightboxClickListener).on('click', this.triggerLightboxClickListener);
            
            // TODO: whats best practice to toggle playermode triggered by any element? @see app.js:'.playerModeToggle a'-Eventbinding
            $('.playerModeToggleTrigger', this.$el).off('click', this.playerModeToggleTriggerListener).on('click', this.playerModeToggleTriggerListener);
		    
		    
		    /* display selected value in dropdown instead of dropdown-label */
		    // TODO: choose more generic attribute-names. maybe we add "label-dropdowns" which has nothing to do with sorting...
			var that = this;
		    $('.dropdown-label', that.$el).each(function(index, item){
		    	$(item).find('.btn:first-child').html(
		    		$('*[data-sortkey="'+ $(item, that.$el).attr('data-activesorting')+'"]', that.$el).html()
		    	);
		    });
		    
		    $('.ajax-form', this.$el).on('submit', function(e) {
				e.preventDefault();
				var url = $(this).attr('action') + '?' + $(this).serialize();
				console.log(url);
				window.sliMpd.router.navigate(url, {
					trigger : true
				});
				
				//that.searchfield.autocomplete('close');
			});
		    
		    
		    $('.dropdown-toggle', this.$el).dropdown();
		    $('.toggle-tooltip', this.$el).tooltip();
  			$('[data-toggle="popover"]', this.$el).popover({
  				html: 'true',
  				content: function() {
  					return ($(this).data('imageUrl'))
  						? "<img class='popover-image' src='"+$(this).data('imageUrl')+"'>"
  						: $(this).attr('title');
  				}
  			});
		    
		    /* route /maintainance/albumdebug */
		    $('.inline-tab-nav a', this.$el).click(function (e) {
		        e.preventDefault();
		        $(this).tab('show');
		    });
		    $('.grid', this.$el).sortable({
		        tolerance: 'pointer',
		        revert: 'invalid',
		        placeholder: 'span2 well placeholder tile',
		        forceHelperSize: true
		    });
		    
		    $('object.svg-ajax-object', that.$el).each(function(index, item){
		    	var obj = item;
			    $.ajax({
	    			url: $(obj).attr('data-svgurl')
	    		}).retry({
	    			times: 10,
	    			timeout: 3000
	    		}).then(function(response){
	    			$(obj).attr('data', $(obj).attr('data-svgurl'));
	  			});
	  		});
            
            window.Backbone.View.prototype.render.call(this);
            this.rendered = true;
        },
        
        remove : function() {
            $('a.ajax-link', this.$el).off('click', this.genericClickListener);
            $('.ajax-rqst', this.$el).off('click', this.ajaxRequestClickListener);
            $('.player-ctrl', this.$el).off('click', this.playerCtrlClickListener);
            $('.ajax-partial', this.$el).off('click', this.ajaxPartialClickListener);
            $('.trigger-modal', this.$el).off('click', this.triggerModalClickListener);
            $('.playerModeToggleTrigger', this.$el).off('click', this.playerModeToggleTriggerListener);
            $('.glue-hover-trigger', this.$el).off('mouseenter', this.itemGlueMouseenterListener);
            $('.glue-hover-trigger', this.$el).off('mouseleave', this.itemGlueMouseleaveListener);
            $('.toggle-content', this.$el).off('click', this.itemToggleClickListener);
            
            window.Backbone.View.prototype.remove.call(this);
        },
        
        close : function() {
        	console.log('AbstractView::destroyView');
    		this.remove();
    		this.unbind();
        },
        
        genericClickListener : function(e) {
            e.preventDefault();
        	var $el = $(e.currentTarget);
            window.sliMpd.router.navigate($el.attr('href'), {
                trigger : true
            });
            if($el.hasClass('trigger-hide-modal')) {
            	window.sliMpd.modal.$modal.modal('hide');
            }
        },
        
        playerCtrlClickListener : function(e) {
            e.preventDefault();
        	var $el = $(e.currentTarget);
        	if(typeof $el.attr('data-player') == 'undefined') {
        		console.log('ERROR: missing player-item. exiting...');
        		return;
        	}
			try {
		        var item = JSON.parse($el.attr('data-player'));
		        window.sliMpd.currentPlayer.process(item);
		        if($el.hasClass('trigger-hide-modal')) {
	            	window.sliMpd.modal.$modal.modal('hide');
	            }
		    } catch(e) {
		    	//console.log(e + ' in data-player attribute');
			}
		},
       
		ajaxPartialClickListener : function(e) {
       		e.preventDefault();
        	var $el = $(e.currentTarget);
        	if(typeof $el.attr('data-ajaxtarget') == 'undefined') {
        		console.log('ERROR: data-ajaxtarget. exiting...');
        		return;
        	}
        	var that = this;
        	NProgress.start();
        	$.ajax({
				url: window.sliMpd.setGetParameter($el.attr('href'), 'nosurrounding', '1')
			}).done(function(response) {
				window.sliMpd.checkNotify(response);
				$($el.attr('data-ajaxtarget')).html(response);
				that.rendered = false;
				that.render();
				NProgress.done();
			}).fail(function() {
				window.sliMpd.notifyError($el.attr('href'));
				NProgress.done();
    			return;
  			});
		},
		
		ajaxRequestClickListener : function(e) {
			e.preventDefault();
        	var $el = $(e.currentTarget);
        	$.ajax({
				url: window.sliMpd.setGetParameter($el.attr('data-href'), 'nosurrounding', '1')
			}).done(function(response) {
				window.sliMpd.checkNotify(response);
				if($el.hasClass('trigger-hide-modal')) {
	            	window.sliMpd.modal.$modal.modal('hide');
	            }
			}).fail(function() {
				window.sliMpd.notifyError($el.attr('data-href'));
    			return;
  			});
		},
		
       	triggerModalClickListener : function(e) {
       		e.preventDefault();
       		var $el = $(e.currentTarget);
	        $.ajax({
				url: $el.attr('data-href')
			}).done(function(response){
				try {
		        	var notifyConf = JSON.parse(response);
		        	if (typeof notifyConf.notify !== 'undefined') {
		        		window.sliMpd.checkNotify(response);
		        		window.sliMpd.modal.$modal.modal('hide');
		        		return;
		        	}
			    } catch(e) {
			    	//console.log(e + ' no json response in SliMpd::checkNotify()');
				}
				
				window.sliMpd.modal.renderModalContent(response);
			}).fail(function() {
				window.sliMpd.notifyError($el.attr('data-href'));
    			return;
  			});
       	},
       	
       	/*
       	// FIXME: why does this not work?
       	// @see: app.js:document.ready()
       	triggerLightboxClickListener : function(e) {
       		event.preventDefault();
			return $(this).ekkoLightbox({
				always_show_close: true,
				type: 'image'
			});
		},
        */
        // TODO: whats best practice to toggle playermode triggered by any element? @see app.js:'.playerModeToggle a'-Eventbinding
        playerModeToggleTriggerListener : function(e) {
       		e.preventDefault();
       		$('.playerModeToggle a').trigger('click');
       	},
       	
       	itemGlueMouseenterListener : function(e) {
       		e.preventDefault();
       		$(e.currentTarget).closest('.glue-hover-wrap').addClass('underline');
       	},
       	
       	itemGlueMouseleaveListener : function(e) {
       		e.preventDefault();
       		$(e.currentTarget).closest('.glue-hover-wrap').removeClass('underline');
       	},
       	
       	itemToggleClickListener : function(e) {
       		e.preventDefault();
       		var $el = $(e.currentTarget);
       		var $target = $($el.attr('data-toggle'));
       		$target.toggle();
       		$el.text((($target.is(":hidden")) ? $el.attr('data-text1') : $el.attr('data-text2') ) );
       	},

        process : function(e) {
           // 
        },
        
    });
    
})();