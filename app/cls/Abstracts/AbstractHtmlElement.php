<?php

/**
 * HTML element.
 * 
 * All extending classes **must** declare the following `protected` method:
 * <code>
 * protected function getHtml() {
 * 	return '<div class="my_element">Content</div>';
 * }
 * </code>
 */
abstract class AbstractHtmlElement extends Instantiable {

	protected $js = '';

	abstract public function getHtml();

	/**
	 * Writes the HTML element to the output.
	 * 
	 * @access public
	 * @return void
	 */
	public function renderHtml() {
		echo $this->getHtml();
		if($this->js) {
			echo "<script>\n".$this->js."\n</script>\n";
		}
	}

	public function __toString() {
		return (string) $this->getHtml();
	}

	/**
	 * Executes JavaScript code after the element has been rendered.
	 * 
	 * @access public
	 * @param string $js
	 * @return void
	 */
	public function setJs($js) {
		$this->js = trim($js);
	}

	/**
	 * Adds javascript code to the execution chain after the element has been rendered.
	 * 
	 * @access public
	 * @param string $js
	 * @return void
	 */
	public function addJs($js) {
		$this->js.= trim($js);
	}

}
