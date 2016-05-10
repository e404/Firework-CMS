<?php

class VimeoVideo extends CustomHtmlTag {

	static $counter = 0;

	public function __construct() {

		parent::__construct('vimeo');

		$this->attr('id');
		$this->attr('aspect', '16:9');

		$this->setHandler(function($atts){
			switch($atts['aspect']) {
				case '16:9':
					$h = '56.25';
					break;
				case '16:10':
					$h = '62.5';
					break;
				default:
					list($w, $h) = explode(':', $atts['aspect'], 2);
					$h = round((100/$w) * $h, 5);
			}
			self::$counter++;
			$element_id = 'vimeo'.self::$counter;
			return '<div class="video vimeo"><div class="placeholder" style="padding-top:'.$h.'%;"></div><div class="video-wrapper"><iframe id="'.$element_id.'" src="https://player.vimeo.com/video/'.$atts['id'].'?api=1&amp;player_id='.$element_id.'" frameborder="0" allowfullscreen></iframe></div></div>';
		});

	}

}

App::addCustomHtmlTag(
	VimeoVideo::newInstance()
);
