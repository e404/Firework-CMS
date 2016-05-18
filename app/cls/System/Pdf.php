<?php

require_once('lib/tcpdf/tcpdf.php');

/** @internal */
class TCPDF_modified extends TCPDF {

	public function Header() {
		$this->Rect(0, 0, 210, 297, 'F', '', array(255, 255, 255));
	}

	public function Close() {
		$this->tcpdflink = false;
		return parent::Close();
	}

	public function Error($msg) {
		$this->_destroy(true);
		Error::warning('TCPDF: '.$msg);
	}

}

/**
 * PDF Generator.
 */
class Pdf extends Instantiable {

	protected $pdf;

	/**
	 * Creates a new PDF in standard A4 format.
	 * 
	 * @access public
	 * @param string $title (optional) The title of the PDF (default: '')
	 * @param mixed $author (optional) The author's name or organization (default: null)
	 * @return void
	 */
	public function __construct($title='',$author=null) {
		$this->pdf = new TCPDF_modified('P','mm','A4',true,'UTF-8',false);
		if($author) $this->pdf->SetAuthor($author);
		if($title) $this->pdf->SetTitle($title);
		$this->pdf->SetMargins(20,20,20);
		$this->pdf->SetPrintHeader(true);
		$this->pdf->SetPrintFooter(false);
		$this->pdf->SetAutoPageBreak(true,20);
		$this->pdf->SetFont('Helvetica','',10);
		$this->pdf->AddPage();
	}

	/**
	 * Writes HTML content to the PDF.
	 *
	 * There are some limitations as the HTML parser is not fully W3C compliant but most `<table>` style layouts should work just fine.
	 * 
	 * @access public
	 * @param string $html
	 * @return void
	 */
	public function addHtml($html) {
		$this->pdf->writeHTML($html,true,0,true,0);
	}

	/**
	 * Writes the PDF document directly to the output.
	 * 
	 * @access public
	 * @param string $filename (optional) Defines a filename the user can see (default: 'document.pdf')
	 * @return void
	 */
	public function inlineOutput($filename='document.pdf') {
		App::clear();
		$this->pdf->output($filename,'I');
	}

	/**
	 * Writes the PDF to a file.
	 * 
	 * @access public
	 * @param string $filepath
	 * @return bool `true` on success, `false` on error
	 */
	public function saveAsFile($filepath) {
		$this->pdf->output($filepath,'F');
		return file_exists($filepath);
	}

	/**
	 * Creates an `EMailAttachment` from the PDF.
	 * 
	 * @access public
	 * @param string $filename (optional) Defines a filename the email recipients can see (default: 'document.pdf')
	 * @return EMailAttachment
	 * @see EMail
	 * @see EMailAttachment
	 */
	public function getEMailAttachment($filename='document.pdf') {
		$att = new EMailAttachment();
		$att->setFilename($filename);
		$att->setMimeType('application/pdf');
		$att->setData($this->pdf->output($filename,'S'));
		return $att;
	}

	/**
	 * Directly attaches the PDF to an existing `EMail`.
	 * 
	 * @access public
	 * @param EMail $mail
	 * @param string $filename (optional) Defines a filename the email recipients can see (default: 'document.pdf')
	 * @return void
	 * @see EMail
	 */
	public function attachToEMail(EMail $mail, $filename='document.pdf') {
		return $mail->addAttachment($this->getEMailAttachment($filename));
	}

}
