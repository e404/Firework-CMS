if(!window.$) $ = jQuery;

var app = {
	init: function(){
		app.preload.init();
		app.layout.init();
		app.email.init();
		app.fullwidth.init();
		app.clickzoom.init();
		app.scroll.init();
		app.navigation.init();
		app.forms.init();
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
			$('body').addClass('has-overlay');
			$('body').append('<div id="loadingindicator"><div class="icon"/></div>');
			if(document.activeElement) document.activeElement.blur();
		}else{
			$('#loadingindicator').remove();
			$('body').removeClass('has-overlay');
		}
	},
	overlay: function(show,fadein){
		if(show) {
			if($('#loadingindicator').length) return false;
			$('body').addClass('has-overlay');
			var li = $('<div id="loadingindicator"/>').appendTo(document.body);
			if(fadein) {
				li.css({opacity: 0});
				setTimeout(function(){
					li.addClass('animate-slowly').css({opacity: 1});
				},10);
			}
			return true;
		}else{
			if(!$('#loadingindicator').length) return false;
			$('body').removeClass('has-overlay');
			$('#loadingindicator').remove();
			return true;
		}
	},
	dialog: function(userOptions){
		if($('#dialog').length) return;
		var options = {
			msg: '{{Should this action really be performed?}}',
			ok: '{{OK}}',
			cancel: '{{Cancel}}',
			input: false,
			placeholder: '',
			callback: function(){},
		};
		$.extend(true, options, userOptions);
		var dialog = $('<div id="dialog"><div class="msg">'+app.lang.basehtml(options.msg)+'</div><div class="ok"><a class="button" href="javascript:void(0)">'+app.lang.basehtml(options.ok)+'</a></div><div class="cancel"><a class="button red" href="javascript:void(0)">'+app.lang.basehtml(options.cancel)+'</a></div></div>');
		$('body').append(dialog).addClass('dialog-present');
		if(!options.cancel) dialog.find('.cancel').remove();
		if(options.input) {
			var input = $('<div class="dialog-input"><input type="text" autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false" placeholder="'+app.utils.htmlescape(options.placeholder)+'"></div>');
			dialog.find('.msg').after(input);
			input.on('keydown', function(event){
				switch(event.key) {
					case 'Enter':
						dialog.find('.ok a').click();
						break;
					case 'Escape':
						dialog.find('.cancel a').click();
						break;
				}
			});
		}
		dialog.find('.ok > a, .cancel > a').click(function(){
			var inputValue = options.input ? dialog.find('.dialog-input input').val() : null;
			app.overlay(false);
			dialog.hide().attr('id', 'dialog-sentenced');
			if($(this).parent().hasClass('ok')) {
				options.callback(true, inputValue, dialog);
			}else{
				options.callback(false, inputValue, dialog);
			}
			dialog.remove();
			$('body').removeClass('dialog-present');
		});
		app.lang.translate({
			msg: options.msg,
			ok: options.ok,
			cancel: options.cancel,
			placeholder: options.placeholder || ''
		},function(lang){
			if(!$('#dialog').length) return;
			dialog.find('.msg').html(lang.msg);
			dialog.find('.ok > a').html(lang.ok);
			dialog.find('.cancel > a').html(lang.cancel);
			dialog.find('.dialog-input > input').attr('placeholder', lang.placeholder);
		});
		setTimeout(function(){
			app.overlay(true, true);
			dialog.addClass('appear');
			if(options.input) {
				dialog.find('.dialog-input input').focus();
			}
		},10);
	},
	utils: {
		htmlescape: function(str){
			return (new Option(str)).innerHTML; // Very fast native method of escaping HTML special chars
		},
		remove: function(what, el) {
			$(el).closest(what).remove();
			return false;
		},
		timechain: function(_args){
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
						app.utils.timechain(args);
					},arg);
					break;
				case 'function':
					arg();
				default:
					if(!args.length) return;
					app.utils.timechain(args);
			}
		}
	},
	navigation: {
		check: false,
		changedCallbacks: [],
		msg: '{{You changed something on this page. Are you sure you want to leave it?}}',
		init: function(){
			$('body').click(function(event){
				if(!app.navigation.check) return;
				if($('#dialog').length) return; // no checks when dialog is open
				if($(event.target).closest('#dialog-sentenced').length) return; // no ckecks if dialog is about to close
				var el = $(event.target).closest('a');
				if(!el.length) return;
				var target = el.attr('target');
				if(target && target!=='_self' && target!=='_parent' && target!=='_top') { // let _blank and addressed window links happen
					return;
				}
				if(el.hasClass('disabled')) { // don't execute clicks on disabled 'a.button' elements
					console.log('disabled link clicked');
					event.preventDefault();
					event.stopPropagation();
					return false;
				}
				var actionurl = el.attr('href');
				if(actionurl.substr(0,11)==='javascript:') return;
				event.preventDefault();
				event.stopPropagation();
				app.navigation.confirm(function(confirmed){
					if(!confirmed) return;
					app.loadingIndicator(true);
					try {
						location.href = actionurl;
					}catch(e) {}
				});
			});
			$(window).on('beforeunload', function(){
				if(app.navigation.check) {
					app.loadingIndicator(false);
					return 'You changed something on this page. Are you sure you want to leave it?';
				}else{
					app.loadingIndicator(true);
				}
			});
			$(window).on('beforeunload', function(){
				app.loadingIndicator(true);
				if(app.navigation.check) {
					setTimeout(function(){
						app.loadingIndicator(false);
					}, 100);
					return 'You changed something on this page. Are you sure you want to leave it?';
				}
			});
			app.changed = function(changed){
				switch(typeof changed) {
					case 'undefined':
						return app.navigation.check;
					case 'function':
						app.navigation.changedCallbacks.push(changed);
						break;
					default:
						app.navigation.check = !!changed;
						for(var i=0; i<app.navigation.changedCallbacks.length; i++) {
							app.navigation.changedCallbacks[i](app.navigation.check);
						}
				}
			};
		},
		confirm: function(callback, msg){
			app.dialog({msg: msg ? msg : app.navigation.msg, ok: '{{Stay Here}}', cancel: '{{Leave Page}}', callback: function(stay){
				if(!stay) {
					app.changed(false);
				}
				callback(!stay);
			}});
		}
	},
	forms: {
		init: function(){
			$('form[method="post"] input, form[method="post"] select, form[method="post"] textarea').change(function(){
				app.changed(true);
			});
			$('form:not(.no-loading-indicator)').submit(function(){
				app.loadingIndicator(true);
				$(this).find('input[type="submit"]').prop('disabled', true);
				app.changed(false);
				var form = $(this);
			});
			$('.field input, .field select, .field textarea').focus(function(){
				$(this).closest('.field').addClass('focus');
			});
			$('.field input, .field select, .field textarea').blur(function(){
				$(this).closest('.field').removeClass('focus');
			});
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
						app.utils.timechain(
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
			app.utils.remove('.row',row);
			return false;
		},
		close: function(){
			$('.clickzoom-overlay, .row.clickzoomed, .clickzoom-close').remove();
			return false;
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
	webcron: {
		execute: function(){
			$(window).load(function(){
				var base = (document.getElementsByTagName('base')[0].href+'').replace(/^https?:\/\//, '').replace(/\/$/, '');
				$.ajax('//'+base+'/app/inc/public/webcron.php');
			});
		}
	},
	lang: {
		translationcache: {},
		translate: function(strings, callback){
			var json = JSON.stringify(strings);
			if(app.lang.translationcache[json]) {
				setTimeout(function(){
					callback(app.lang.translationcache[json]);
				},0);
				return;
			}
			$.ajax({
				type: 'POST',
				timeout: 10000,
				url: 'ajax/Language',
				data: {s: json},
				success: function(response){
					if(typeof response==='string') response = JSON.parse(response);
					app.lang.translationcache[json] = response;
					callback(response);
				},
				error: function(){
					callback(false);
				}
			});
		},
		basehtml: function(str){
			if(!str) return '';
			return (str+'').replace(/\{\{/g, '').replace(/\}\}/g, '');
		}
	}
};

$(app.init);
