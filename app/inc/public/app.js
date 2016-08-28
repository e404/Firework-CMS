if(!window.$) $ = jQuery;

var app = {
	init: function(){
		app.preload.init();
		app.layout.init();
		app.forms.init();
		app.email.init();
		app.fullwidth.init();
		app.clickzoom.init();
		app.scroll.init();
		if(app.plugins) {
			for(var id in app.plugins) {
				if(typeof(app.plugins[id].init)==='function') {
					app.plugins[id].init();
				}
			}
		}
		$(window).resize(function(){
			app.mobile = $('html').width()<800;
			$(document).scroll();
		});
		$(window).resize();
		if(app.firstvisit) {
			setTimeout(function(){
				$('#langswitch').addClass('blink');
			}, 1000);
			setTimeout(function(){
				$('#langswitch').removeClass('blink');
			}, 2500);
		}
	},
	plugins: {},
	firstvisit: false,
	mobile: false,
	loadingIndicator: function(show) {
		if(show) {
			if($('#loadingindicator').length) return;
			$('body').append('<div id="loadingindicator"><div class="icon"/></div>');
			if(document.activeElement) document.activeElement.blur();
		}else{
			$('#loadingindicator').remove();
		}
	},
	chain: function(_args){
		if(typeof(_args)==='object' && _args.length) {
			var args = _args;
		}else{
			if(!arguments.length) return;
			var args = [];
			Array.prototype.push.apply(args, arguments);
		}
		var arg = args.shift();
		switch(typeof(arg)) {
			case 'number':
				if(!args.length) return;
				setTimeout(function(){
					app.chain(args);
				},arg);
				break;
			case 'function':
				arg();
			default:
				if(!args.length) return;
				app.chain(args);
		}
	},
	preload: {
		init: function(){
			$('body').addClass('loading');
			$(window).on('load', function(){
				$('body').removeClass('loading');
				$('body').addClass('loaded');
			});
		}
	},
	layout: {
		init: function(){
			$('.row .box:last-child').each(function(){
				var box = $(this);
				var parentWidth = box.parent().width();
				var relativeRight = box.position().left + box.width() + parseInt(box.css('padding-left')) + parseInt(box.css('padding-right')) + 2; // add a little tolerance
				if(relativeRight < parentWidth) box.addClass('trailing');
			});
		}
	},
	scroll: {
		init: function(){
			$('a[href*=#]:not([href=#])').click(function() {
				if(location.pathname.replace(/^\//,'')===this.pathname.replace(/^\//,'') && location.hostname===this.hostname) {
					var hash = this.hash;
					var target = $(hash);
					target = target.length ? target : $('[name=' + hash.slice(1) +']');
					if(target.length) {
						app.scroll.to(target, function(){
						app.chain(
							300,
							function(){
								target.addClass('anchor-target-active');
							},
							300,
							function(){
								target.removeClass('anchor-target-active');
							},
							150,
							function(){
								target.addClass('anchor-target-active');
							},
							300,
							function(){
								target.removeClass('anchor-target-active');
							}
						);
						});
						return false;
					};
				}
			});
		},
		to: function(target, callback){
			target = $(target);
			if(!target.length) return false;
			$('html,body').animate({
				scrollTop: target.offset().top - parseInt(target.css('margin-top')) / 2
			},function(){
				if(callback) callback();
			});
			return true;
		}
	},
	fullwidth: {
		elements: {},
		counter: 0,
		init: function(){
			app.fullwidth.elements = $('.row.fullwidth[data-bg]');
			if(!app.fullwidth.elements.length) return;
			var style = '';
			app.fullwidth.elements.each(function(){
				var src = $(this).data('bg');
				var id = this.id = this.id || 'prlx' + (++app.fullwidth.counter);
				style+= '#'+id+':before {background-image: url("'+src+'");} ';
			});
			$('<style>'+style+'</style>').appendTo('head');
		}
	},
	clickzoom: {
		init: function(){
			$('[data-clickzoom]').each(function(){
				var box = $(this);
				var trigger = $('<div class="clickzoom-trigger"/>').click(function(){
					app.clickzoom.trigger(this);
				});
				box.append(trigger);
			});
		},
		trigger: function(el){
			var box = $(el).closest('[data-clickzoom]');
			if(!box.length) box = $(el).find('[data-clickzoom]');
			if(!box.length) box = $(el).closest('.row').find('[data-clickzoom]');
			if(!box.length) return false;
			var row = box.closest('.row');
			box.attr('data-clickzoom', null);
			box.attr('class', 'box fullwidth');
			box.find('.clickzoom-trigger').remove();
			row.before('<div class="clickzoom-overlay" onclick="return app.clickzoom.close()"/>');
			row.before($('<div class="row clickzoomed"/>').append(box));
			row.after('<div class="row clickzoom-close"><a href="javascript:void(0)" onclick="return app.clickzoom.close()">X</a></div>');
			app.remove('.row',row);
			return false;
		},
		close: function(){
			$('.clickzoom-overlay, .row.clickzoomed, .clickzoom-close').remove();
			return false;
		}
	},
	forms: {
		init: function(){
			$('form[method="post"] input, form[method="post"] select, form[method="post"] textarea').change(app.forms.triggerChange);
			$('form:not(.no-loading-indicator)').submit(function(){
				app.loadingIndicator(true);
				$(this).find('input[type="submit"]').prop('disabled', true);
				window.onbeforeunload = null;
				var form = $(this);
			});
			$('.field input, .field select, .field textarea').focus(function(){
				$(this).closest('.field').addClass('focus');
			});
			$('.field input, .field select, .field textarea').blur(function(){
				$(this).closest('.field').removeClass('focus');
			});
		},
		triggerChange: function(){
			window.onbeforeunload = function() {
				return 'You changed data in the form. Do you really want to leave this page?';
			};
		}
	},
	notify: {
		query: function(){
			$.ajax({
				url: 'ajax/Notify',
				success: function(result){
					if(!result || $('#notify').length) return;
					result = JSON.parse(result);
					if(!result || !result.msgs) return;
					var msgs = $(result.msgs);
					if(!msgs.length) return;
					$('<div id="notify"/>').appendTo('body');
					var notify = $('#notify');
					var counter = 0;
					$(msgs).each(function(){
						notify.append($('<div class="msg"/>').html(this));
						counter++;
					});
					setTimeout(function(){
						notify.addClass('show');
						if(!result.sticky) {
							setTimeout(app.notify.close, 7000 + 1500 * (counter-1));
						}
					},1000);
				}
			});
		},
		close: function(){
			var notify = $('#notify');
			if(!$('#notify').length) return;
			notify.removeClass('show').addClass('hide');
			setTimeout(function(){
				notify.remove();
			},3000);
		}
	},
	email: {
		init: function(){
			$('.m-protected').each(function(){
				var code = $(this).data('real');
				var html = '';
				for(var i=0; i<code.length; i+=2) {
					html+= String.fromCharCode(parseInt(code.substr(i,2),16));
				}
				$(this).replaceWith(html);
			});
		}
	},
	remove: function(what, el) {
		$(el).closest(what).remove();
		return false;
	},
	webcron: {
		execute: function(){
			$(window).load(function(){
				var base = (document.getElementsByTagName('base')[0].href+'').replace(/^https?:\/\//, '').replace(/\/$/, '');
				$.ajax('//'+base+'/app/inc/public/webcron.php');
			});
		}
	},
	translate: function(strings, callback){
		$.ajax({
			type: 'POST',
			timeout: 10000,
			url: 'ajax/Language',
			data: {s: JSON.stringify(strings)},
			success: function(response){
				if(typeof response==='string') response = JSON.parse(response);
				callback(response);
			},
			error: function(){
				callback(false);
			}
		});
	}
};

$(app.init);
