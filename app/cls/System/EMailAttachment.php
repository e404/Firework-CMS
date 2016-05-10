<?php

class EMailAttachment {

	private $data;
	private $mimeType;
	private $filename;

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

	public function setData($data) {
		$this->data = $data;
	}

	public function getData() {
		return $this->data;
	}

	public function setFilename($filename) {
		$this->filename = $filename;
	}

	public function getFilename() {
		return $this->filename;
	}

	public function setMimeType($mimeType) {
		$this->mimeType = $mimeType;
	}

	public function getMimeType() {
		return $this->mimeType;
	}

}
