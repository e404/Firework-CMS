<?php

/**
 * Handles short links, tracks its usage and expands application functionality.
 *
 * A `LinkTracker` can create links that can be use once or several times.
 * Those `LinkTracker`s can receive a maximum lifetime, so they can automatically get invalid after a predefined amount of time.
 * Also, `LinkTracker`s may have a context and a value assigned. As those values are stored in the `Session`, they are available across the entire application.
 *
 * @example
 * <code>
 * // ...
 * $tracker = new LinkTracker();
 * $tracker->setUrl('http://www.example.com/');
 * $tracker->setLifetime(5); // After 5 days the link gets invalid
 * $tracker->setContextValue('activation', $userid); // The context-value pair will be available after clicking the link
 * $url = $tracker->getLink();
 * // ...
 * // After clicking the link
 * $context_value = LinkTracker::getContextValue(); // $context_value is now ['context' => 'activation', 'value' => '5129872392']
 * </code>
 */
class LinkTracker extends Db {

	use Inject;

	protected $lifetime_days = null;
	protected $url = null;
	protected $desc = null;
	protected $context = null;
	protected $value = null;
	protected $pretty_filename = null;

	/**
	 * Defines when the link should become invalid.
	 * 
	 * @access public
	 * @param int $days The number of days the link should stay valid; if set to `null`, the link will stay valid forever
	 * @return void
	 */
	public function setLifetime($days) {
		$this->lifetime_days = $days;
	}

	/**
	 * Specifies the URL the short link should lead to after clicking.
	 * 
	 * @access public
	 * @param string $url
	 * @return void
	 */
	public function setUrl($url) {
		$this->url = $url;
	}

	/**
	 * Sets an internal description of the link.
	 *
	 * This function has no effect on the link action.
	 * The internal description is for internal use only.
	 * 
	 * @access public
	 * @param string $desc
	 * @return void
	 */
	public function setDesc($desc) {
		$this->desc = $desc;
	}

	/**
	 * Defines a context and a value for the link.
	 *
	 * These two variables are stored within the current `Session` and can be used once the link has been clicked.
	 * The `$value` can be **a number, a string or an array**.
	 * 
	 * @access public
	 * @param string $context
	 * @param mixed $value
	 * @return void
	 * @see self::getContextValue()
	 */
	public function setContextValue($context, $value) {
		$this->context = $context;
		$this->value = is_string($value) ? $value : json_encode($value);
	}

	/**
	 * Attaches a human readable extension to the short link URL.
	 *
	 * `http://www.example.com/link/s8Et2m` could get `http://www.example.com/link/s8Et2m/create-free-account`
	 * 
	 * @access public
	 * @param string $pretty_filename
	 * @return void
	 */
	public function setPrettyFilename($pretty_filename) {
		$this->pretty_filename = $filename;
	}

	/**
	 * Generates and returns the actual link URL.
	 * 
	 * @access public
	 * @return void
	 */
	public function getLink() {
		if(!$this->url) return Error::fatal('URL not set.');
		if(!$this->desc) return Error::fatal('Description not set.');
		do {
			$id = Random::generateString(8);
		} while(self::$db->single("SELECT id FROM links WHERE id='$id' LIMIT 1"));
		$expires = $this->lifetime_days ? date('Y-m-d H:i:s', time()+$this->lifetime_days*3600*24) : null;
		$query = self::$db->prepare("INSERT INTO `links` SET `id`=@VAL, `url`=@VAL, `desc`=@VAL, `context`=@VAL, `value`=@VAL, `expires`=@VAL", $id, $this->url, $this->desc, $this->context, $this->value, $expires);
		if(self::$db->query($query)) {
			return App::getLink("link/$id".($this->pretty_filename ? '/'.$this->pretty_filename : ''));
		}
		return false;
	}

	/**
	 * Executes a link action.
	 *
	 * This method should be called when the link is clicked.
	 * 
	 * @access public
	 * @static
	 * @param string $id (default: null)
	 * @return void
	 */
	public static function action($id=null) {
		if($id===null) {
			if(preg_match('@/link/([^/]+)@', $_SERVER['REQUEST_URI'], $matches)) {
				$id = $matches[1];
			}else{
				Error::fatal('Could not complete link tracker action: ID not found.');
			}
		}
		$link = self::$db->getRow(self::$db->prepare("SELECT `url`, `context`, `value` FROM `links` WHERE `id`=@VAL LIMIT 1", $id));
		if(!$link['url']) App::redirect(404);
		App::getSession()->set('link_id', $id);
		if($link['context']) {
			App::getSession()->set('link_context', $link['context']);
			if($link['value']) App::getSession()->set('link_value', $link['value']);
		}
		self::$db->query(self::$db->prepare("UPDATE `links` SET `t_action`=NOW(), `actions_count`=`actions_count`+1 WHERE `id`=@VAL LIMIT 1", $id));
		App::redirect($link['url'],true);
	}

	/**
	 * Returns the context and value after a link was clicked.
	 *
	 * `['context' => 'my-context', 'value' => 'my-value']`
	 *
	 * @access public
	 * @static
	 * @return array
	 */
	public static function getContextValue() {
		return array(
			'context' => App::getSession()->get('link_context'),
			'value' => App::getSession()->get('link_value')
		);
	}

	/**
	 * Deletes link context and value from current `Session`.
	 * 
	 * @access public
	 * @static
	 * @param mixed $link_id (optional) If `null`, the link ID will automatically be searched in the `Session` store (default: null)
	 * @return void
	 * @see self::setContextValue()
	 */
	public static function purge($link_id=null) {
		if(!$link_id) {
			$link_id = App::getSession()->get('link_id');
			App::getSession()->remove('link_id');
			App::getSession()->remove('link_context');
			App::getSession()->remove('link_value');
		}
		if(!$link_id || !self::$db->single(self::$db->prepare("SELECT id FROM `links` WHERE id=@VAL LIMIT 1", $link_id))) return false;
		return self::$db->query(self::$db->prepare("DELETE FROM `links` WHERE id=@VAL LIMIT 1", $link_id));
	}

}
