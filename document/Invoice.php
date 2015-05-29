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

use IntlDateFormatter;
use lithium\g11n\Message;
use AD\Finance\Money\MoneyIntlFormatter as MoneyFormatter;
use AD\Finance\Money\Monies;
use AD\Finance\Money\MoniesIntlFormatter as MoniesFormatter;

/**
 * An invoice document to be printed on a blank paper with no header/footer.
 */
class Invoice extends \base_document\document\BaseInvoice {

	protected $_vatRegNo;

	protected $_taxNo;

	protected $_bankAccount;

	protected $_paypalEmail;

	protected function _compileHeaderFooter() {
		extract(Message::aliases());

		$backupHeight = $this->_currentHeight;
		$backup = $this->_borderHorizontal;

		$this->_borderHorizontal = [33, 33];
		$this->_currentHeight = 800;

		$this->_drawText($this->_sender['name'], 'right');
		$this->_drawText($this->_sender['street_address'], 'right', [
			'offsetY' => $this->_skipLines()
		]);
		$this->_drawText($this->_sender['postal_code'] . ' ' . $this->_sender['city'], 'right', [
			'offsetY' => $this->_skipLines()
		]);
		$this->_drawText($this->_sender['country'], 'right', [
			'offsetY' => $this->_skipLines()
		]);
		$this->_drawText($this->_sender['phone'], 'right', [
			'offsetY' => $this->_skipLines(2)
		]);
		$this->_drawText($this->_sender['email'], 'right', [
			'offsetY' => $this->_skipLines()
		]);

		$this->_currentHeight = 90;

		if ($this->_vatRegNo) {
			$this->_drawText($t('{:number} — VAT Reg. No.', [
				'scope' => 'base_document',
				'locale' => $this->_recipient->locale,
				'number' => $this->_vatRegNo,
			]), 'right');
		}
		if ($this->_taxNo) {
			$this->_drawText($t('{:number} — Tax No.', [
				'scope' => 'base_document',
				'locale' => $this->_recipient->locale,
				'number' => $this->_taxNo,
			]), 'right', [
				'offsetY' => $this->_skipLines()
			]);
		}
		if ($this->_bankAccount) {
			$text  = $this->_bankAccount['holder'] . ', ';
			$text .= $this->_bankAccount['bank'] . ', ';
			$text .= 'IBAN ' . $this->_bankAccount['iban'] . ', ';
			$text .= 'BIC ' . $this->_bankAccount['bic'] . ' ';
			$text .= '— ' . $t('Bank Account', [
				'scope' => 'base_document',
				'locale' => $this->_recipient->locale
			]);
			$this->_drawText($text, 'right', [
				'offsetY' => $this->_skipLines()
			]);
		}
		if ($this->_paypalEmail) {
			$this->_drawText($t('{:email} — PayPal.', [
				'scope' => 'base_document',
				'locale' => $this->_recipient->locale,
				'email' => $this->_paypalEmail,
			]), 'right', [
				'offsetY' => $this->_skipLines()
			]);
		}
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
			'city' => $this->_sender['locality'],
			'date' => $formatter->format($this->_invoice->date())
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

		$this->_setFont($this->_fontSize, true);
		$this->_drawText($t('{:number} - Client No.', [
			'scope' => 'base_document',
			'locale' => $this->_recipient->locale,
			'number' => $this->_recipient->number
		]), 'right', [
			'offsetY' => 661
		]);
		$this->_drawText($t('{:number} - Invoice No.', [
			'scope' => 'base_document',
			'locale' => $this->_recipient->locale,
			'number' => $this->_invoice->number
		]),  'right', [
			'offsetY' => $this->_skipLines()
		]);
		$this->_setFont($this->_fontSize, false);

		if ($value = $this->_recipient->vat_reg_no) {
			$this->_drawText($t('{:number} - Client VAT Reg. No.', [
				'scope' => 'base_document',
				'locale' => $this->_recipient->locale,
				'number' => $value
			]), 'right', [
				'offsetY' => $this->_skipLines()
			]);
		}

		$this->_borderHorizontal = $backup;
	}

	// 5.
	protected function _compileSubject() {
		$this->_setFont($this->_fontSize, true);

		$this->_drawText($this->_subject, 'left', [
			'offsetY' => 550
		]);
		$this->_setFont($this->_fontSize);
	}

	// 6.
	protected function _compileHello() {
		extract(Message::aliases());

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
	protected function _compileCostsTableHeader() {
		extract(Message::aliases());

		$showNet = in_array($this->_recipient->role, ['merchant', 'admin']);
		$this->_currentHeight = 435;

		$this->_setFont(11, true);

		$this->_drawText($t('Description', [
			'scope' => 'base_document',
			'locale' => $this->_recipient->locale
		]), 'left', [
			'width' => 300,
			'offsetX' => $offsetX = 0
		]);
		$this->_drawText($t('Quantity', [
			'scope' => 'base_document',
			'locale' => $this->_recipient->locale
		]), 'right', [
			'width' => 100,
			'offsetX' => $offsetX += 300
		]);
		$this->_drawText($t('Unit Price', [
			'scope' => 'base_document',
			'locale' => $this->_recipient->locale
		]), 'right', [
			'offsetX' => $offsetX += 100,
			'width' => 100
		]);
		$this->_drawText($t('Total', [
			'scope' => 'base_document',
			'locale' => $this->_recipient->locale
		]), 'right', [
			'offsetX' => $offsetX += 100,
			'width' => 100
		]);

		$this->_currentHeight = $this->_skipLines();

		$this->_setFont($this->_fontSize, false);
		$this->_drawHorizontalLine();
	}

	// 9.
	protected function _compileCostsTablePosition($position) {
		extract(Message::aliases());

		$showNet = in_array($this->_recipient->role, ['merchant', 'admin']);
		$moneyFormatter = new MoneyFormatter($this->_recipient->locale);

		$this->_currentHeight = $this->_skipLines();

		$this->_drawText($position->description, 'left', [
			'width' => 300,
			'offsetX' => $offsetX = 0
		]);
		$this->_drawText((integer) $position->quantity, 'right', [
			'width' => 100,
			'offsetX' => $offsetX += 300
		]);

		$this->_drawText(
			$moneyFormatter->format($showNet ? $position->amount()->getNet() : $position->amount()->getGross()),
			'right',
			['offsetX' => $offsetX += 100, 'width' => 100]
		);
		$value = $showNet ? $position->total()->getNet() : $position->total()->getGross();
		$this->_drawText(
			$moneyFormatter->format($showNet ? $position->total()->getNet() : $position->total()->getGross()),
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

		$moniesFormatter = new MoniesFormatter($this->_recipient->locale);

		$this->_setFont($this->_fontSize, true);

		$this->_currentHeight = $this->_skipLines(3);
		$this->_drawHorizontalLine();

		$this->_currentHeight = $this->_skipLines();

		$this->_drawText($t('Total (net)', [
			'scope' => 'base_document',
			'locale' => $this->_recipient->locale
		]), 'left');
		$this->_drawText(
			$moniesFormatter->format($this->_invoice->totals()->getNet()),
			'right',
			['offsetX' => 500, 'width' => 100]
		);

		foreach ($this->_invoice->taxes() as $rate => $monies) {
			if ($rate === 0) {
				continue;
			}
			$this->_currentHeight = $this->_skipLines();

			$this->_drawText($t('Tax ({:tax_rate}%)', [
				'scope' => 'base_document',
				'locale' => $this->_recipient->locale,
				'tax_rate' => $rate
			]), 'left');
			$this->_drawText(
				$moniesFormatter->format($monies),
				'right',
				['offsetX' => 500, 'width' => 100]
			);
		}

		$this->_setFont(11, true);

		$this->_currentHeight = $this->_skipLines(1.5);
		$this->_drawHorizontalLine();
		$this->_currentHeight = $this->_skipLines();

		$this->_drawText($t('Grand Total', [
			'scope' => 'base_document',
			'locale' => $this->_recipient->locale
		]), 'left');
		$this->_drawText(
			$moniesFormatter->format($this->_invoice->totals()->getGross()),
			'right',
			['offsetX' => 500, 'width' => 100]
		);

		$this->_setFont($this->_fontSize);

		$this->_currentHeight = $this->_skipLines(2.5);
		$this->_drawText($this->_invoice->terms);

		$this->_currentHeight = $this->_skipLines(2);
		$this->_drawText($this->_invoice->note);

		$this->_currentHeight = $this->_skipLines(2);
		$text = $t("This invoice has been automatically generated and is valid even without a signature.", [
			'scope' => 'base_document',
			'locale' => $this->_recipient->locale
		]);
		$this->_drawText($text);
	}
}

?>