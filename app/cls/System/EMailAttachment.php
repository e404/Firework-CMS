<?php

/**
 * EMailAttachment creation for use with `EMail`.
 *
 * @see EMail
 */
class EMailAttachment extends ISystem {

	private $data;
	private $mimeType;
	private $filename;

	/**
	 * Creates an attachment object.
	 *
	 * If `$filename` is set, **you don't need to call `setData`, `setFilename` and `setMimeType`** as these are automatically called when a valid file is given.
	 * 
	 * @access public
	 * @param string $filename (optional) The file to attach (default: null)
	 * @return void
	 */
	public function __construct($filename=null) {
		if($filename) {
			if(!file_exists($filename)) {
				return Error::fatal('Attachment file not found');
			}
			$this->data = file_get_contents($filename);
			$this->filename = basename($filename);
			$this->mimeType = preg_replace('/;.*$/','',finfo_file(finfo_open(FILEINFO_MIME),$data));
		}
	}

	/**
	 * Sets the attachment's file data.
	 * 
	 * @access public
	 * @param string $data
	 * @return void
	 */
	public function setData($data) {
		$this->data = $data;
	}

	/**
	 * Returns the attachment's file data.
	 * 
	 * @access public
	 * @return string
	 */
	public function getData() {
		return $this->data;
	}

	/**
	 * Defines the attachment's filename.
	 *
	 * This name can be chosen entirely freely, but remember that its extension should match the MIME type of the data.
	 * 
	 * @access public
	 * @param mixed $filename
	 * @return void
	 */
	public function setFilename($filename) {
		$this->filename = $filename;
	}

	/**
	 * Returns the attachment's filename.
	 * 
	 * @access public
	 * @return string
	 */
	public function getFilename() {
		return $this->filename;
	}

	/**
	 * Defines the attachment's MIME type.
	 * 
	 * @access public
	 * @param string $mimeType
	 * @return void
	 */
	public function setMimeType($mimeType) {
		$this->mimeType = $mimeType;
	}

	/**
	 * Returns the attachment's MIME type.
	 * 
	 * @access public
	 * @return string
	 */
	public function getMimeType() {
		return $this->mimeType;
	}

}
