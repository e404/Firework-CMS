<?php

class AccountingPdf extends Pdf {

	public function __construct($title='Invoice', $author=null) {
		$lang = App::getLang();
		parent::__construct($lang->translateHtml($title), $lang->translateHtml($author));
	}

	public function generate(array $replaceMap, array $positionsMap=array()) {
		$lang = App::getLang();
		$html = '
			<table>
				<tr>
					<td style="width: 50%;"><br></td>
					<td style="width: 50%;" style="font-size: 2em;">[TITLE]<br><br></td>
				</tr>
				<tr>
					<td>[RECIPIENT]</td>
					<td>[FROM]<br><br><b>[NUMBER]</b><br>[DATE]<br>[PERIOD]<br><br><b>[CUSTOMERNUMBER]</b></td>
				</tr>
			</table>
			<br><br><br><br><br><br>
			[CONTENT]
			<br><br><br>
			[FOOTER]
		';
		if($positionsMap) {
			$positions = '<table cellpadding="2mm" style="border-top: 0.25pt solid #000;">';
			$i = 0;
			$last = count($positionsMap);
			foreach($positionsMap as $pos) {
				if(++$i==$last) {
					$positions.= '<tr><td style="border-top: 1.25pt solid #000;"><b>'.$lang->translateHtml($pos[0]).'</b></td><td style="border-top: 1.25pt solid #000; text-align: right;"><b>'.(isset($pos[1]) ? $lang->number($pos[1],2) : '').'</b></td></tr>';
				}else{
					$positions.= '<tr><td style="border-bottom: 0.25pt solid #000;">'.$lang->translateHtml($pos[0]).'</td><td style="border-bottom: 0.25pt solid #000; text-align: right;">'.(isset($pos[1]) ? $lang->number($pos[1],2) : '').'</td></tr>';
				}
			}
			$positions.= '</table>';
			$html = str_replace('[CONTENT]', $positions, $html);
		}
		foreach($replaceMap as $key=>$value) {
			$key = strtoupper($key);
			if(is_array($value)) {
				if($key==='RECIPIENT') {
					$value = array_filter($value);
					$country = array_pop($value);
					require('inc/countries.php');
					if(strlen($country)===2 && isset($countries[$country])) {
						$value[] = $countries[$country];
					}else{
						$value[] = $country;
					}
				}
				$value = implode('<br>', array_filter($value));
			}
			$html = str_replace('['.$key.']', $lang->translateHtml($value), $html);
		}
		$this->addHtml($html);
	}

}
