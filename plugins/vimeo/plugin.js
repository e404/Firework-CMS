app.plugins.vimeo = {
	iframes: {},
	init: function(){
		app.plugins.vimeo.iframes = $('.video.vimeo iframe');
		if(!app.plugins.vimeo.iframes.length) return;
		app.plugins.vimeo.iframes.load(function(){
			$(this).closest('.video').addClass('loaded');
			var subtitles = $(this).data('subtitles');
			if(subtitles) {
				this.contentWindow.postMessage('{"method":"enableTextTrack","value":{"language":"'+subtitles+'","kind":"subtitles"}}', '*');
			}else{
				this.contentWindow.postMessage('{"method":"disableTextTrack","value":{"kind":"subtitles"}}', '*');
			}
		});
	}
};