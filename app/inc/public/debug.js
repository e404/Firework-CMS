jQuery(function($){
	if(Cookies.get('debug_msg1')) {
		for(var i=1; true; i++) {
			var msg = Cookies.get('debug_msg'+i);
			if(!msg) break;
			$('#debug-info').append('<div style="font-size:0.7em; margin-top:0.5em;">'+msg+'</div>');
			Cookies.remove('debug_msg'+i);
		}
	}
});