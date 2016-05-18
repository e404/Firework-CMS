<?php

/**
 * Custom HTML tag `<youtube>`.
 *
 * @example `<youtube id="youtube_id" aspect="21:9">`
 */
class YoutubeVideo extends CustomHtmlTag {

	/** @internal */
	static $counter = 0;

	/** @internal */
	public function __construct() {

		parent::__construct('youtube');

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
			$element_id = 'youtube'.self::$counter;
			return '<div class="video youtube"><div class="placeholder" style="padding-top:'.$h.'%;"></div><div class="video-wrapper"><iframe id="'.$element_id.'" src="https://www.youtube.com/embed/'.$atts['id'].'?rel=0&amp;showinfo=0&amp;cc_load_policy=1&amp;iv_load_policy=3&amp;enablejsapi=1" frameborder="0" allowfullscreen></iframe></div></div>';
		});

	}

}

App::addCustomHtmlTag(
	YoutubeVideo::newInstance()
);
