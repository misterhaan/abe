<?php
/**
 * ajax return class for responding to ajax requests.  currently supports json
 * and may be extendd to add xml and / or others.
 * @author misterhaan
 *
 */
class abeAjax {
	// return data in this format.  can override via $_GET['format'] but only json works currently
	private $format = 'json';

	/**
	 * returned data object.  starts with ->fail set to false and should have other data added.
	 * @var object
	 */
	public $Data;

	/**
	 * sets the format based on $_GET['format'] (default json) and initializes
	 * return data object.
	 */
	public function abeAjax() {
		$this->Data = new stdClass();
		$this->Data->fail = false;
		if(isset($_GET['format']))
			switch(strtolower($_GET['format'])) {
				case 'json':
				case 'xml':
				case 'txt':
					$this->format = strtolower($_GET['format']);
					break;
				default:
					$this->Fail('format "' . strtolower($_GET['format']) . '" is not supported.  please choose from json, xml, or txt.');
					break;
			}
	}

	/**
	 * mark the request failed and add a reason.
	 * @param string $message failure reason
	 */
	public function Fail($message) {
		$this->Data->fail = true;
		$this->Data->message = $message;
	}

	/**
	 * Send the ajax response.
	 */
	public function Send() {
		switch($this->format) {
			case 'json':
				$this->SendJson();
				break;
			// this is where txt, xml, etc. would be added
		}
	}

	/**
	 * Send the ajax response in json format.
	 */
	private function SendJson() {
		header('Content-Type: application/json');
		echo json_encode($this->Data);
	}
}
?>
