<?php

abstract class AbstractHtmlElement extends Instantiable {

	protected $js = '';

	abstract public function getHtml();

	public function renderHtml() {
		echo $this->getHtml();
		if($this->js) {
			echo "<script>\n".$this->js."\n</script>\n";
		}
	}

	public function __toString() {
		return (string) $this->getHtml();
	}

	public function setJs($js) {
		$this->js = trim($js);
	}

	public function addJs($js) {
		$this->js.= trim($js);
	}

}
