<?php

/**
 * Creates a custom HTML tag that can be rendered within the application.
 */
class CustomHtmlTag extends ISystem {

	protected $tag = '';
	protected $atts = array();
	protected $handler = null;

	/**
	 * Sets the HTML tag
	 *
	 * @access public
	 * @param string $tag
	 * @return void
	 */
	public function __construct($tag) {
		$this->tag = $tag;
	}

	/**
	 * Returns the HTML tag.
	 * 
	 * @access public
	 * @return string
	 */
	public function getTag() {
		return $this->tag;
	}

	/**
	 * Adds an attribute to the custom HTML tag.
	 * 
	 * @access public
	 * @param string $attr
	 * @param string $default (optional) Default value; if omitted, the attribute is mandatory and throws an error when empty (default: null)
	 * @return void
	 */
	public function attr($attr, $default=null) {
		$this->atts[$attr] = $default;
	}

	/**
	 * Returns the attributes.
	 * 
	 * @access public
	 * @return array
	 */
	public function getAttributes() {
		return $this->atts;
	}

	/**
	 * Sets the custom HTML tag handler function.
	 * 
	 * @access public
	 * @param callable $handler
	 * @return void
	 */
	public function setHandler(callable $handler) {
		$this->handler = $handler;
	}

	/**
	 * Returns the custom HTML tag handler function.
	 * 
	 * @access public
	 * @return callable
	 */
	public function getHandler() {
		return $this->handler;
	}

	/**
	 * Renders a custom HTML tag within a given HTML string, using the defined handler.
	 * 
	 * @access public
	 * @static
	 * @param mixed $html
	 * @param CustomHtmlTag $customtag
	 * @return void
	 * @see self::setHandler()
	 */
	public static function renderReplacement($html, CustomHtmlTag $customtag) {
		if(!$customtag) {
			Error::warning('No custom HTML tag object set.');
			return $html;
		}
		return preg_replace_callback('@<'.$customtag->getTag().'\s+([^>]+)>@i',function($match) use($customtag) {
			$handler = $customtag->getHandler();
			if(!$handler) {
				Error::warning('No custom HTML tag handler set.');
				return $match[0];
			}
			$attshtml = $match[1];
			$atts = $customtag->getAttributes();
			foreach($atts as $attribute=>$default) {
				if(preg_match('@'.$attribute.'="([^"]+)"@i', $attshtml, $matches)) {
					$atts[$attribute] = $matches[1];
				}elseif($default===null) {
					Error::warning('Attribute '.$attribute.' not set for custom HTML tag "'.$customtag->getTag().'".');
					return $match[0];
				}
			}
			return call_user_func($customtag->getHandler(), $atts);
		}, $html);
	}

}
