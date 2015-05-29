<?php
/**
 * Atelier Disko Distribution
 *
 * Copyright (c) 2014 Atelier Disko - All rights reserved.
 *
 * This software is proprietary and confidential. Redistribution
 * not permitted. Unless required by applicable law or agreed to
 * in writing, software distributed on an "AS IS" BASIS, WITHOUT-
 * WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 */

namespace base_document\document;

abstract class BaseInvoice extends \base_document\document\Base {

	protected $_invoice;

	protected $_type = 'Invoice';

	protected $_subject = 'Your invoice';

	protected $_recipient;

	protected $_sender;

	protected $_intro;

	public function compile() {
		parent::compile();

		// Meta Data.
		$this->_author($this->_sender->name);
		$this->_creator($this->_sender->name);
		$this->_subject($this->_subject);

		/* Address field */
		$this->_compileRecipientAddressField();

		/* Numbers and type of letter right */
		$this->_compileType();
		$this->_compileNumbers();

		/* Date and City */
		$this->_compileDateAndCity();

		/* Subject */
		$this->_compileSubject();

		/* Intro Text */
		if ($this->_intro) {
			$this->_compileHello();
			$this->_compileIntro();
		}

		/* Costs Table */
		$this->_compileCostsTableHeader();

		foreach ($this->_invoice->positions() as $position) {
			$this->_compileCostsTablePosition($position);
		}
		$this->_compileCostsTableFooter();
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
	abstract protected function _compileCostsTableHeader();

	// 9.
	abstract protected function _compileCostsTablePosition($position);

	// 10.
	abstract protected function _compileCostsTableFooter();
}

?>