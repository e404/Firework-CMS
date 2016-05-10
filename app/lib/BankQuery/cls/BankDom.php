<?php

class BankDom extends SimpleXmlElement {

	public static function create($html, $url) {
		$dom = new DomDocument();
		@$dom->loadHTML(preg_replace('@(\r\n|\r|\n)+@',' ',$html));
		$dom = simplexml_import_dom($dom, 'BankDom');
		if(!$dom) return null;
		$dom->makeUrlsAbsolute($url);
		return $dom;
	}

	protected static function absUrl($base, $url, $return_array=false) {
		if(!is_array($base)) $base = parse_url($base);
		if(!is_array($url)) $url = parse_url($url);
		if(!isset($url['scheme'])) $url['scheme'] = $base['scheme'];
		if(!isset($url['host'])) $url['host'] = $base['host'];
		if(!isset($url['path'])) $url['path'] = isset($base['path']) ? $base['path'] : '';
		return $return_array ? $url : BankBrowser::buildUrl($url);
	}

	protected function makeUrlsAbsolute($url) {
		$base = parse_url($url);
		if($base_tag = $this->xpath('//head/base[@href]')) {
			$base = self::absUrl($base, $base_tag[0]->getAttribute('href'), true);
		}
		$base = array_intersect_key($base, array_flip(['scheme', 'host', 'path']));
		foreach([
			['a','href'],
			['form','action']
		] as $tag) {
			$attr = $tag[1];
			$tag = $tag[0];
			if($elements = $this->xpath("//".$tag."[@".$attr."]")) {
				foreach($elements as $element) {
					$element[$attr] = self::absUrl($base, $element->getAttribute($attr));
				}
			}
		}
	}

	protected function single($dom) {
		return (is_array($dom) && $dom) ? $dom[0] : $dom;
	}

	public function getElementById($id) {
		return $this->single($this->xpath(".//*[@id='$id']"));
	}

	public function getElementByName($name) {
		return $this->single($this->xpath(".//*[@name='$name']"));
	}

	public function getElementsByClassName($cls1/* [, $cls2[, ...]] */) {
		$classnames = func_get_args();
		$xpath = ".//*[@class";
		foreach($classnames as $classname) {
			$xpath.= " and contains(concat(' ', normalize-space(@class), ' '), ' $classname ')";
		}
		$xpath.= "]";
		return $this->xpath($xpath);
	}

	public function getElementsByTagName($tag) {
		return $this->xpath(".//$tag");
	}

	public function getElementByText($text) {
		return $this->single($this->xpath(".//*[normalize-space(text())='$text']"));
	}

	public function getElementBySubmitButtonText($text) {
		return $this->single($this->xpath(".//input[@type='submit' and normalize-space(@value)='$text']"));
	}

	public function getElementByLinkText($text) {
		$text = trim(preg_replace('/\s+/s', ' ', $text));
		return $this->single($this->xpath(".//a[normalize-space(text())='$text']"));
	}

	public function getElementByClickableText($text) {
		$element = $this->getElementBySubmitButtonText($text);
		if(!$element) {
			$element = $this->getElementByLinkText($text);
		}
		if($temp_element = $this->single($this->xpath(".//*[normalize-space(text())='$text']"))) {
			$element = $this->getClosestElementByTagName('a');
		}
		return $element;
	}

	public function getFormFieldValues() {
		$elements = $this->xpath(".//input[@name]");
		// TODO: Handle select, textarea, ... as well
		if(!count($elements)) return [];
		$fields = [];
		foreach($elements as $el) {
			$fields[$el->getAttribute('name')] = $el->getAttribute('value');
		}
		return $fields;
	}

	public function getParent($depth=1) {
		$obj = $this->single($this->xpath(".."));
		if(!$obj) return false;
		if($depth>1) return $obj->getParent($depth-1);
		return $obj;
	}

	public function getClosestElementByTagName($tag) {
		return $this->single($this->xpath(".//ancestor::$tag"));
	}

	public function getAttribute($attr) {
		foreach($this->attributes() as $key=>$value) {
			if($key===$attr) return (string) $value;
		}
		return null;
	}

}
