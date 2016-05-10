<?php

class SymbolTag extends CustomHtmlTag {

	public function __construct() {

		parent::__construct('symbol');

		$this->attr('icon');

		$this->setHandler(function($atts){
			$svg = '';
			$viewbox = '';
			switch($atts['icon']) {
				case 'heart':
					$viewbox = '0 0 32 32';
					$svg = '<path d="M16,28.261c0,0-14-7.926-14-17.046c0-9.356,13.159-10.399,14-0.454c1.011-9.938,14-8.903,14,0.454 C30,20.335,16,28.261,16,28.261z"/>';
					break;
			}
			return '<svg class="symbol icon '.$atts['icon'].'" viewbox="'.$viewbox.'">'.$svg.'</svg>';
		});

	}

}

App::addCustomHtmlTag(
	SymbolTag::newInstance()
);
