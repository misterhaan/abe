<?php

/**
 * Ajax return class for responding to ajax requests with json.
 * @author misterhaan
 */
class abeAjax {
	/**
	 * returned data object.  starts with ->fail set to false and should have other data added.
	 * @var object
	 */
	public $Data;

	/**
	 * Initializes return data object.
	 */
	public function __construct() {
		$this->Data = new stdClass();
		$this->Data->fail = false;
	}

	/**
	 * mark the request failed and add a reason.
	 * @param string $message failure reason
	 */
	public function Fail(string $message) {
		$this->Data->fail = true;
		$this->Data->message = $message;
	}

	/**
	 * Send the ajax response.
	 */
	public function Send() {
		header('Content-Type: application/json');
		echo json_encode($this->Data);
	}
}
