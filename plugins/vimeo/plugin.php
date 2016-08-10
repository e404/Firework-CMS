<?php

/**
 * Custom HTML tag `<vimeo>`.
 *
 * @example `<vimeo id="vimeo_id" aspect="21:9">`
 */
class VimeoVideo extends CustomHtmlTag {

	/** @internal */
	static $counter = 0;

	/** @internal */
	public function __construct() {

		parent::__construct('vimeo');

		$this->attr('id');
		$this->attr('aspect', '16:9');
		$this->attr('loop', 'false');
		$this->attr('nointeraction', 'false');

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
			$loop = in_array(strtolower($atts['loop']), ['true', 'yes', '1', 'on', true]);
			$nointeraction = in_array(strtolower($atts['nointeraction']), ['true', 'yes', '1', 'on', true]);
			$options = [];
			if($loop) {
				$options[] = 'autopause=0';
				$options[] = 'autoplay=1';
				$options[] = 'badge=0';
				$options[] = 'byline=0';
				$options[] = 'loop=1';
				$options[] = 'portrait=0';
				$options[] = 'title=0';
			}
			if($nointeraction) {
				$option[] = 'background=1';
			}
			self::$counter++;
			$element_id = 'vimeo'.self::$counter;
			$html = '<div class="video vimeo"><div class="placeholder" style="padding-top:'.$h.'%;"></div><div class="video-wrapper"><iframe id="'.$element_id.'" src="https://player.vimeo.com/video/'.$atts['id'].'?api=1&amp;player_id='.$element_id.($options ? '&amp;'.implode('&amp;',$options) : '').'" frameborder="0" allowfullscreen></iframe></div>';
			if(in_array(strtolower($atts['nointeraction']), ['true', 'yes', '1', 'on'])) {
				$html.= '<div class="video-cover"></div>';
			}
			$html.= '</div>';
			return $html;
		});

	}

}

App::addCustomHtmlTag(
	VimeoVideo::newInstance()
);
