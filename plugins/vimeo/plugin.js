app.plugins.vimeo = {
	iframes: {},
	init: function(){
		$(function(){
			app.plugins.vimeo.iframes = $('.video.vimeo iframe');
			if(!app.plugins.vimeo.iframes.length) return;
			app.plugins.vimeo.iframes.load(function(){
				var iframe = $(this);
				iframe.closest('.video').addClass('loaded');
				var subtitles = iframe.data('subtitles');
				var ensureSubtitles = function(){
					if(subtitles) {
						iframe[0].contentWindow.postMessage('{"method":"enableTextTrack","value":{"language":"'+subtitles+'","kind":"subtitles"}}', '*');
					}else{
						iframe[0].contentWindow.postMessage('{"method":"disableTextTrack","value":{"kind":"subtitles"}}', '*');
					}
				};
				setTimeout(ensureSubtitles, 100);
				setTimeout(ensureSubtitles, 1000);
				setTimeout(ensureSubtitles, 2000);
			});
		});
	}
};