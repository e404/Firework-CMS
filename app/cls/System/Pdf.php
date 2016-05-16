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

class Pdf {

	protected $pdf;

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

	public function addHtml($htmlContent) {
		$this->pdf->writeHTML($htmlContent,true,0,true,0);
	}

	public function inlineOutput($filename='document.pdf') {
		$this->pdf->output($filename,'I');
	}

	public function saveAsFile($filepath) {
		$this->pdf->output($filepath,'F');
		return file_exists($filepath);
	}

	public function getEMailAttachment($filename='document.pdf') {
		$att = new EMailAttachment();
		$att->setFilename($filename);
		$att->setMimeType('application/pdf');
		$att->setData($this->pdf->output($filename,'S'));
		return $att;
	}

	public function attachToEMail(EMail $mail, $filename='document.pdf') {
		return $mail->addAttachment($this->getEMailAttachment($filename));
	}

}
