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

namespace base_document\document;

use IntlDateFormatter;
use lithium\g11n\Message;

class Shipment extends \base_document\document\BaseFinancial {

	protected $_template = 'shipment';

	protected function _compileHeaderFooter() {
		$backupHeight = $this->_currentHeight;
		$backup = $this->_borderHorizontal;

		$this->_borderHorizontal = [33, 33];
		$this->_currentHeight = 800;

		foreach (explode("\n", $this->_sender->address()->format('postal')) as $key => $line) {
			$this->_drawText($line, 'right', [
				'offsetY' => $key ? $this->_skipLines() : $this->_currentHeight
			]);
		}

		$this->_currentHeight = 90;

		$this->_borderHorizontal = $backup;
		$this->_currentHeight = $backupHeight;
	}

	// 1.
	protected function _compileRecipientAddressField() {
		foreach (explode("\n", $this->_recipient->address()->format('postal')) as $key => $line) {
			$this->_drawText($line, 'left', [
				'offsetY' => $key ? $this->_skipLines() : 685
			]);
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

		$text = $t('{:city}, the {:date}', [
			'scope' => 'base_document',
			'locale' => $this->_recipient->locale,
			'city' => $this->_sender->address()->locality,
			'date' => $formatter->format($this->_entity->date())
		]);
		$this->_drawText($text, 'right', [
			'offsetY' => 560
		]);
	}

	// 3.
	protected function _compileType() {
		$backup = $this->_borderHorizontal;
		$this->_borderHorizontal = [33, 33];
		$this->_setFont(24, true);

		$this->_drawText(strtoupper($this->_type), 'right', [
			'offsetY' => 680
		]);

		$this->_setFont($this->_fontSize);
		$this->_borderHorizontal = $backup;
	}

	// 4.
	protected function _compileNumbers() {
		extract(Message::aliases());

		$backup = $this->_borderHorizontal;
		$this->_borderHorizontal = [33, 33];

		$this->_drawText($t('{:number} — Client No.', [
			'scope' => 'base_document',
			'locale' => $this->_recipient->locale,
			'number' => $this->_recipient->number
		]), 'right', [
			'offsetY' => 661
		]);
		$this->_drawText($t('{:number} — Shipment No.', [
			'scope' => 'base_document',
			'locale' => $this->_recipient->locale,
			'number' => $this->_entity->number
		]),  'right', [
			'offsetY' => $this->_skipLines()
		]);

		$this->_borderHorizontal = $backup;
	}

	// 5.
	protected function _compileSubject() {
		$this->_setFont($this->_fontSize, true);

		$this->_drawText($this->_subject, 'left', [
			'offsetY' => 540
		]);
		$this->_setFont($this->_fontSize);
	}

	// 6.
	protected function _compileHello() {
		$this->_drawText($t('Dear {:name},', [
			'scope' => 'base_document',
			'locale' => $this->_recipient->locale,
			'name' => $this->_recipient->name
		]), 'left', [
			'offsetY' => $this->_skipLines(2)
		]);
	}

	//  7.
	protected function _compileIntro() {
		$this->_drawText($this->_intro, 'left', [
			'offsetY' => $this->_skipLines(2)
		]);
	}

	// 8.
	protected function _compileTableHeader() {
		extract(Message::aliases());

		$showNet = in_array($this->_recipient->role, ['merchant', 'admin']);
		$this->_currentHeight = 435;

		$this->_setFont(11, true);

		$this->_drawText($t('Description', [
			'scope' => 'base_document',
			'locale' => $this->_recipient->locale
		]), 'left', [
			'width' => 500,
			'offsetX' => $offsetX = 0
		]);
		$this->_drawText($t('Quantity', [
			'scope' => 'base_document',
			'locale' => $this->_recipient->locale
		]), 'right', [
			'width' => 100,
			'offsetX' => $offsetX += 500
		]);

		$this->_currentHeight = $this->_skipLines();

		$this->_setFont($this->_fontSize, false);
		$this->_drawHorizontalLine();
	}

	// 9.
	protected function _compileTablePosition($position) {
		extract(Message::aliases());

		$this->_currentHeight = $this->_skipLines();

		$this->_drawText($position->description, 'left', [
			'width' => 500,
			'offsetX' => $offsetX = 0
		]);
		$this->_drawText((integer) $position->quantity, 'right', [
			'width' => 100,
			'offsetX' => $offsetX += 500
		]);

		// Page break; redraw costs table header.
		if ($this->_currentHeight <= 250) {
			$this->_nextPage();
			$this->_compileTableHeader();
		}
	}

	// 10.
	protected function _compileTableFooter() {
		$this->_setFont($this->_fontSize);
		$this->_currentHeight = $this->_skipLines(3);
		$this->_drawHorizontalLine();

		$this->_currentHeight = $this->_skipLines(2.5);
		$this->_drawText($this->_entity->terms);

		$this->_currentHeight = $this->_skipLines(2);
		$this->_drawText($this->_entity->note);
	}
}

?>