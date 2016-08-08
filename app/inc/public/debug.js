jQuery(function($){
	var found = false;
	for(var i=1; true; i++) {
		var msg = Cookies.get('debug_msg'+i);
		if(!msg) {
			if(found || i>10) break; // Sometimes debug messages can't be viewed and numbers are skipped.
			else continue;
		}
		found = true;
		$('#debug-info').append('<div style="font-size:0.7em; margin-top:0.5em;">'+msg+'</div>');
		Cookies.remove('debug_msg'+i);
	}
});