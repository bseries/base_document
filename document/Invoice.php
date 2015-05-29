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

namespace app\extensions\pdf;

use lithium\g11n\Message;
use IntlDateFormatter;
use NumberFormatter;

abstract class InvoiceDocument extends \base_document\document\Base {

	protected $_recipient;

	protected $_invoice;

	protected $_senderContact;

	protected $_type = 'Invoice';

	protected $_subject = 'Your invoice';

	protected $_intro = null;

	protected $_paymentTerms;

	protected $_bankAccount;

	protected $_paypalEmail;

	protected $_vatRegNo;

	protected $_taxNo;

	protected $_borderHorizontal = [80, 50];

	protected $_fontSize = 10;

	protected $_lineHeight = 13;

	public function compile() {
		parent::compile();

		// Meta Data.
		$this->_author($this->_senderContact['name']);
		$this->_creator($this->_senderContact['name']);
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
		$this->_compileHello();

		$this->_compileIntro();

		/* Costs Table */
		$this->_compileCostsTableHeader();

		foreach ($this->_invoice->positions() as $position) {
			$this->_compileCostsTablePosition($position);
		}
		$this->_compileCostsTableFooter();
	}

	protected function _compileHeaderFooter() {}

	// 1.
	protected function _compileRecipientAddressField() {
		foreach (explode("\n", $this->_recipient->address()->format('postal')) as $key => $line) {
			if (!$key) {
				$this->_setFont($this->_fontSize, true);
			}
			$this->_drawText($line, 'left', [
				'offsetY' => $key ? $this->_skipLines() : 672
			]);
			if (!$key) {
				$this->_setFont($this->_fontSize, false);
			}
		}
	}

	// 2.
	protected function _compileDateAndCity() {
		extract(Message::aliases());

		$formatter = new IntlDateFormatter(
			$this->_recipient->locale,
			IntlDateFormatter::SHORT,
			IntlDateFormatter::NONE,
			$this->_recipient->timezone
		);

		$text = sprintf(
			$t('%s, the %s'),
			$this->_senderContact['city'],
			$formatter->format($this->_invoice->date())
		);
		$this->_drawText($text, 'right', [
			'offsetY' => 550
		]);
	}

	// 3.
	protected function _compileType() {}

	// 4.
	protected function _compileNumbers() {
		extract(Message::aliases());

		$this->_setFont($this->_fontSize, true);
		$this->_drawText($t('Client No.') . ': ' . $this->_recipient->number, 'left', [
			'offsetY' => 528
		]);
		$this->_drawText($t('Invoice No.') . ': ' . $this->_invoice->number,  'left', [
			'offsetY' => $this->_skipLines()
		]);
		$this->_setFont($this->_fontSize, false);
		$this->_drawText('(Bitte bei Zahlung angeben)', 'left', [
			'offsetY' => $this->_skipLines()
		]);

		if ($value = $this->_recipient->billing_vat_reg_no) {
			$this->_drawText($t('Client VAT Reg No.') . ': ' . $value, 'left', [
				'offsetY' => $this->_skipLines()
			]);
		}
	}

	// 5.
	protected function _compileSubject() {
		$this->_setFont(13, true);
		$this->_drawText($this->_subject, 'left', [
			'offsetY' => 550
		]);
		$this->_setFont($this->_fontSize);
	}

	// 6.
	protected function _compileHello() {}

	//  7.
	protected function _compileIntro() {}

	// 8.
	protected function _compileCostsTableHeader() {
		extract(Message::aliases());

		$this->_currentHeight = 435;

		$this->_setFont(11, true);

		$this->_drawText($t('Description'), 'left', [
			'width' => 300,
			'offsetX' => $offsetX = 0
		]);
		$this->_drawText($t('Quantity'), 'right', [
			'width' => 100,
			'offsetX' => $offsetX += 300
		]);
		$this->_drawText(
			$t('Unit price'),
			'right',
			['offsetX' => $offsetX += 100, 'width' => 100]
		);
		$this->_drawText(
			$t('Total price'),
			'right',
			['offsetX' => $offsetX += 100, 'width' => 100]
		);

		$this->_currentHeight = $this->_skipLines();

		$this->_setFont($this->_fontSize, false);
		$this->_drawHorizontalLine();
	}

	// 9.
	protected function _compileCostsTablePosition($position) {
		extract(Message::aliases());

		$formatter = new NumberFormatter($this->_recipient->locale, NumberFormatter::CURRENCY);

		$this->_currentHeight = $this->_skipLines();

		$this->_drawText($position->description, 'left', [
			'width' => 300,
			'offsetX' => $offsetX = 0
		]);
		$this->_drawText((integer) $position->quantity, 'right', [
			'width' => 100,
			'offsetX' => $offsetX += 300
		]);

		$value = $position->amount()->getGross();
		$this->_drawText(
			$formatter->formatCurrency($value->getAmount() / 100, $value->getCurrency()),
			'right',
			['offsetX' => $offsetX += 100, 'width' => 100]
		);
		$value = $position->totalAmount()->getGross();
		$this->_drawText(
			$formatter->formatCurrency($value->getAmount() / 100, $value->getCurrency()),
			'right',
			['offsetX' => $offsetX += 100, 'width' => 100]
		);

		// Page break; redraw costs table header.
		if ($this->_currentHeight <= 250) {
			$this->_nextPage();
			$this->_compileCostsTableHeader();
		}
	}

	// 10.
	protected function _compileCostsTableFooter() {
		extract(Message::aliases());

		$formatter = new NumberFormatter($this->_recipient->locale, NumberFormatter::CURRENCY);


		$this->_setFont($this->_fontSize, true);

		$this->_currentHeight = $this->_skipLines(3);
		$this->_drawHorizontalLine();

		$value = $this->_invoice->totalAmount();
		$this->_currentHeight = $this->_skipLines();

		$this->_drawText($t('Total (gross)'), 'left');
		$this->_drawText(
			$formatter->formatCurrency($value->getGross()->getAmount() / 100, $value->getCurrency()),
			'right',
			['offsetX' => 500, 'width' => 100]
		);

		$this->_currentHeight = $this->_skipLines();
		$value = $this->_invoice->totalAmount();
		$this->_drawText($t('Total (net)'), 'left');
		$this->_drawText(
			$formatter->formatCurrency($value->getNet()->getAmount() / 100, $value->getCurrency()),
			'right',
			['offsetX' => 500, 'width' => 100]
		);

		$this->_currentHeight = $this->_skipLines();
		$value = $this->_invoice->totalTax();
		$this->_drawText($t('Tax ({:tax_rate}%)', $this->_invoice->data()), 'left');
		$this->_drawText(
			$formatter->formatCurrency($value->getAmount() / 100, $value->getCurrency()),
			'right',
			['offsetX' => 500, 'width' => 100]
		);

		$this->_setFont(11, true);

		$this->_currentHeight = $this->_skipLines(1.5);
		$this->_drawHorizontalLine();
		$this->_currentHeight = $this->_skipLines();

		$value = $this->_invoice->totalAmount();
		$this->_drawText($t('Invoice total'), 'left');
		$this->_drawText(
			$formatter->formatCurrency($value->getGross()->getAmount() / 100, $value->getCurrency()),
			'right',
			['offsetX' => 500, 'width' => 100]
		);

		$this->_setFont($this->_fontSize);

		$this->_currentHeight = $this->_skipLines(2.5);
		$this->_drawText($this->_invoice->tax_note);

		$this->_currentHeight = $this->_skipLines(2);
		$this->_drawText($this->_invoice->note);

		$this->_currentHeight = $this->_skipLines(2);
		$this->_drawText($this->_invoice->terms);

		$this->_currentHeight = $this->_skipLines();
		$text = $t("This invoice has been automatically generated and is valid even without a signature.");
		$this->_drawText($text);
	}
}

?>