app.plugins.vimeo = {
	iframes: {},
	init: function(){
		app.plugins.vimeo.iframes = $('.video.vimeo iframe');
		if(!app.plugins.vimeo.iframes.length) return;
		app.plugins.vimeo.iframes.load(function(){
			$(this).closest('.video').addClass('loaded');
		});
	}
};