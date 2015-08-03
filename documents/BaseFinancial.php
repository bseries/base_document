<?php
/**
 * Base Document
 *
 * Copyright (c) 2015 Atelier Disko - All rights reserved.
 *
 * This software is proprietary and confidential. Redistribution
 * not permitted. Unless required by applicable law or agreed to
 * in writing, software distributed on an "AS IS" BASIS, WITHOUT-
 * WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 */

namespace base_document\documents;

abstract class BaseFinancial extends \base_document\documents\Base {

	protected $_layout = 'blank';

	protected $_entity;

	protected $_type;

	protected $_subject;

	protected $_recipient;

	protected $_sender;

	protected $_intro;

	protected $_vatRegNo;

	protected $_taxNo;

	protected $_bank = [];

	protected $_paypal = [];

	public function compile() {
		parent::compile();

		// Meta Data.
		$this->_author($this->_sender->name);
		$this->_creator($this->_sender->name);

		if ($this->_subject) {
			$this->_subject($this->_subject);
		}

		/* Address field */
		$this->_compileRecipientAddressField();

		/* Numbers and type of letter right */
		if ($this->_type) {
			$this->_compileType();
		}
		$this->_compileNumbers();

		/* Date and City */
		$this->_compileDateAndCity();

		/* Subject */
		if ($this->_subject) {
			$this->_compileSubject();
		}

		/* Intro Text */
		if ($this->_intro) {
			$this->_compileHello();
			$this->_compileIntro();
		}

		/* Financial Table */
		$this->_compileTableHeader();

		foreach ($this->_entity->positions() as $position) {
			$this->_compileTablePosition($position);
		}
		$this->_compileTableFooter();
	}

	// 1.
	abstract protected function _compileRecipientAddressField();

	// 2.
	abstract protected function _compileDateAndCity();

	// 3.
	abstract protected function _compileType();

	// 4.
	abstract protected function _compileNumbers();

	// 5.
	abstract protected function _compileSubject();

	// 6.
	abstract protected function _compileHello();

	//  7.
	abstract protected function _compileIntro();

	// 8.
	abstract protected function _compileTableHeader();

	// 9.
	abstract protected function _compileTablePosition($position);

	// 10.
	abstract protected function _compileTableFooter();
}

?>