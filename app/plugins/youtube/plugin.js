app.plugins.youtube = {
	iframes: {},
	init: function(){
		app.plugins.youtube.iframes = $('.video.youtube iframe');
		if(!app.plugins.youtube.iframes.length) return;
		window.addEventListener('message', function(e) {
			try {
				var json = JSON.parse(e.data);
				var playerState = json.info.playerState;
			}catch(error){
				return;
			}
			if(typeof(playerState)==='undefined') return;
			var iframe;
			app.plugins.youtube.iframes.each(function(){
				if(this.contentWindow===e.source) {
					iframe = $(this);
					iframe.data('playerState', playerState);
					return false;
				}
			});
			if(!iframe) return;
			switch(playerState) {
				case 1: case 3: // playing/buffering
				app.plugins.youtube.iframes.each(function(){
					if($(this).is(iframe)) {
						return;
					}
					switch($(this).data('playerState')) {
					case 1: case 3: // playing/buffering
						this.contentWindow.postMessage('{"event":"command","func":"pauseVideo","args":""}', '*');
					}
				});
			}
		});
		app.plugins.youtube.iframes.load(function(){
			$(this).closest('.video').addClass('loaded');
			this.contentWindow.postMessage('{"event":"listening","id":"apiID"}', '*');
		});
	}
};