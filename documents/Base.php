<?php
/**
 * Base Document
 *
 * Copyright (c) 2015 Atelier Disko - All rights reserved.
 *
 * Licensed under the AD General Software License v1.
 *
 * This software is proprietary and confidential. Redistribution
 * not permitted. Unless required by applicable law or agreed to
 * in writing, software distributed on an "AS IS" BASIS, WITHOUT-
 * WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 *
 * You should have received a copy of the AD General Software
 * License. If not, see http://atelierdisko.de/licenses.
 */

namespace base_document\documents;

use BadMethodCallException;
use Exception;
use Media_Info;
use ZendPdf\PdfDocument;
use ZendPdf\Resource\Font\Simple\Standard\Helvetica;
use ZendPdf\Resource\Font\Simple\Standard\HelveticaBold;
use ZendPdf\Resource\Image\ImageFactory;
use lithium\analysis\Logger;
use lithium\core\Libraries;

// Contains mostly document internal methods. Base-subclasses may
// define abstract methods.
abstract class Base {

	protected $_layout = 'blank';

	// N E S W
	protected $_margin = [100, 55, 100, 80];

	protected $_pageWidth = 595;
	// height 842 pixel

	protected $_styles = [];

	protected $_currentStyle = null;

	protected $_encoding = 'UTF-8';

	protected $_currentHeight;

	private $__pdf;

	private $__page;

	private $__pageTemplate;

	public function __construct() {
		$this->_defineStyles();

		if (property_exists($this, '_borderHorizontal')) {
			trigger_error(
				'The _borderHorizontal property is deprecated in favor of _margin.',
				E_USER_DEPRECATED
			);
		}
		if (method_exists($this, '_compileHeaderFooter')) {
			trigger_error(
				'_compileHeaderFooter is deprecated in favor of _preparePage.',
				E_USER_DEPRECATED
			);
		}
	}

	public function compile() {
		Logger::write('debug', 'Compiling document.');

		$this->_currentHeight = $this->_margin[0];

		$this->__pdf = $this->_loadLayout();
		$this->__page = $this->__pdf->pages[0];
		$this->__pageTemplate = clone $this->__page;

		// Must come after initializing __page.
		$this->_useStyle('gamma');

		$this->_preparePage();
	}

	protected function _loadLayout() {
		$files = [
			Libraries::get('app', 'path') . '/documents/layouts/' . $this->_layout . '.pdf',
			Libraries::get('base_document', 'path') . '/documents/layouts/' . $this->_layout . '.pdf'
		];
		foreach ($files as $file) {
			if (file_exists($file)) {
				return PdfDocument::load($file);
			}
		}
		throw new Exception("No document layout `{$this->_layout}` found.");
	}

	protected function _prepareLayout() {}

	// Always use temporary file to get arround mem limit.
	public function render($stream = null) {
		Logger::write('debug', 'Rendering document.');

		if ($stream) {
			$this->__pdf->render(false, $stream);
		} else {
			$stream = fopen('php://temp', 'wb');

			$this->__pdf->render(false, $stream);
			rewind($stream);

			echo stream_get_contents($stream);

			fclose($stream);
		}
		Logger::write('debug', 'Document has been rendered successfully.');
	}

	public function __call($method, $params) {
		if (property_exists($this, '_' . $method)) {
			$this->{"_{$method}"} = $params[0];
			return $this;
		}
		if (method_exists($this, '_' . $method)) {
			$this->{"_{$method}"}($params[0]);
			return $this;
		}
		throw new BadMethodCallException("Unknown method $method.");
	}

	/* Metadata methods: must be called after compile() */

	public function metaAuthor($text) {
		$this->__pdf->properties['Author'] = $text;
	}

	public function metaTitle($text) {
		$this->__pdf->properties['Title'] = $text;
	}

	public function metaSubject($text) {
		$this->__pdf->properties['Subject'] = $text;
	}

	public function metaCreator($text) {
		$this->__pdf->properties['Creator'] = $text;
	}

	/* Basic methods */

	protected function _nextPage() {
		$this->_currentHeight = $this->_margin[0];

		$this->__page = clone $this->__pageTemplate;
		$this->__pdf->pages[] = $this->__page;

		// Must come after initializing __page.
		$this->_useStyle('gamma');

		$this->_preparePage();
	}

	protected function _pages() {
		return $this->__pdf->pages;
	}

	protected function _width($text) {
		$font = $this->__page->getFont();

		$text = iconv('UTF-8', 'UTF-16BE//IGNORE', $text);
		$chars = [];
		$length = strlen($text);

		for($i = 0; $i < $length; $i++) {
			$chars[] = ord($text[$i++]) << 8 | ord($text[$i]);
		}
		$glyphs = $font->glyphNumbersForCharacters($chars);
		$widths = $font->widthsForGlyphs($glyphs);

		return (array_sum($widths) / $font->getUnitsPerEm()) * $this->__page->getFontSize();
	}

	/* Text Handling */

	protected function _skipLines($number = 1) {
		$offsetY = $this->_currentHeight;
		return $offsetY - ($number * $this->_currentStyle['lineHeight']);
	}

	// $align may be numeric then it is used as offsetX
	protected function _drawText($text, $align = 'left', array $options = []) {
		$text = str_replace("\r\n", "\n", $text); // Normalize line endings.

		$options += [
			'width' => $this->_pageWidth,
			'offsetY' => $this->_currentHeight
		];

		list($offsetX, $offsetY) = $this->_alignText($text, $align, $options);

		$unwrapped = explode("\n", $text);
		$wrapped = [];

		foreach ($unwrapped as $line) {
			$wrapped = array_merge(
				$wrapped,
				$this->_wrapText($line, $options['width'])
			);
		}

		// Ensure lines skip only between lines.
		$this->__page->drawText(array_shift($wrapped), $offsetX, $offsetY, $this->_encoding);

		// Empty lines are returned as an empty string (falsey).
		while (($line = array_shift($wrapped)) !== null) {
			$offsetY -= $this->_currentStyle['lineHeight']; // Skip 1 line.

			if ($line) {
				$this->__page->drawText($line, $offsetX, $offsetY, $this->_encoding);
			}
		}
		$this->_currentHeight = $offsetY;
	}

	// Wraps text inside given max width.
	//
	// Adds words to line one by one until the line
	// exceeds the max width. Then starts new line.
	protected function _wrapText($text, $maxWidth) {
		$results = [];

		if ($this->_width($text) <= $maxWidth) {
			return $results = [$text];
		}
		$words = explode(' ', $text);

		$line = '';
		while (true) {
			if (!$words) {
				$results[] = $line;
				break;
			}
			$word = array_shift($words);

			if ($this->_width($line . ' ' . $word) <= $maxWidth) {
				if ($line) {
					$line .= ' '; // Prevent spaces at line start.
				}
				$line .= $word;
				continue;
			}
			$results[] = $line;
			$line = $word;
		}
		return $results;
	}

	protected function _alignText($text, $align, array $range = []) {
		$range += [
			'width' => null,
			'offsetX' => 0,
			'offsetY' => null
		];
		if ($align == 'center') {
			$range['width'] = $range['width'] ?: $this->_pageWidth;

			return [
				($range['width'] - $this->_width($text) + $range['offsetX']) / 2,
				$range['offsetY']
			];
		} elseif ($align == 'right') {
			$range['width'] = $range['width'] ?: $this->_pageWidth;

			return [
				$range['width'] - $this->_width($text) - $this->_margin[1] + $range['offsetX'],
				$range['offsetY']
			];
		} elseif ($align === 'left') {
			$range['width'] = $range['width'] ?: $this->_pageWidth - ($this->_margin[1] + $this->_margin[3]);

			return [
				$this->_margin[3] + $range['offsetX'],
				$range['offsetY']
			];
		}
		throw new Exception("Invalid text alignment {$align}.");
	}

	/* Transformations */

	protected function _rotate($angle = 90) {
		$this->__page->rotate(
			$this->__page->getWidth() / 2,
			$this->__page->getHeight() / 2,
			deg2rad($angle)
		);
	}

	/* Drawing */

	protected function _drawHorizontalLine($thickness = 0.5, array $dashingPattern = []) {
		$this->__page->setLineWidth($thickness);
		$this->__page->setLineDashingPattern($dashingPattern);

		$this->__page->drawLine(
			$this->_margin[3], ceil($this->_currentHeight + ($this->_currentStyle['lineHeight'] / 2)),
			$this->_pageWidth - $this->_margin[1] + 5, ceil($this->_currentHeight + ($this->_currentStyle['lineHeight'] / 2))
		);
	}

	/* Image Handling */

	// Aligned NW
	protected function _drawImage($file, $offset, $image, $box, $align = 'topleft') {
		Logger::write('debug', sprintf(
			"Document is drawing image `%s` (%.2f MB).",
			$file, filesize($file) / 1000 * 1000
		));

		$Image = ImageFactory::factory($file);

		list($offsetX, $offsetY) = $offset;
		list($boxWidth, $boxHeight) = $box;
		$imageWidth = $image->getPixelWidth();
		$imageHeight = $image->getPixelHeight();

		$media = Media_Info::factory(['source' => $file]);

		list($width, $height) = $this->_imageMaxDimensions(
			$media->width(),
			$media->height(),
			$imageWidth,
			$imageHeight
		);

		list($boxOffsetX, $boxOffsetY) = $this->_boxifyImage(
			$boxWidth,
			$boxHeight,
			$width,
			$height
		);
		$offsetX += $boxOffsetX;
		$offsetY -= $boxOffsetY;

		$this->__page->drawImage($image,
			$offsetX,
			$offsetY - $height,
			$offsetX + $width,
			$offsetY
		);
	}

	protected function _imageMaxDimensions($oW, $oH, $mW, $mH) {
		if ($oW <= $mW && $oH <= $mH) {
			return [$oW, $oH];
		}
		$rW = $mW / $oW;
		$rH = $mH / $oH;

		if ($rW > $rH) {
			$r = $rH;
		} else {
			$r = $rW;
		}
		return [(integer) $oW * $r, (integer) $oH * $r];
	}

	protected function _boxifyImage($bWidth, $bHeight, $iWidth, $iHeight, $gravity = 'center') {
		switch ($gravity) {
			case 'center':
				$left = max(0, ($bWidth - $iWidth) / 2);
				$top = max(0, ($bHeight - $iHeight) / 2);
				break;
			case 'topleft':
				$left = $top = 0;
				break;
			case 'topright':
				$left = max(0, $bWidth - $iWidth);
				$top = 0;
				break;
			case 'bottomleft':
				$left = 0;
				$top = max(0, $bHeight - $iHeight);
				break;
			case 'bottomright':
				$left = max(0, $bWidth - $iWidth);
				$top = max(0, $bHeight - $iHeight);
				break;
			default:
				throw new InvalidArgumentException("Unsupported gravity `{$gravity}`.");
		}
		return [$left, $top];
	}

	protected function _checkImageResolution($file, $box) {
		$media = Media_Info::factory(['source' => $file]);

		list($bW, $bH) = $box;

		$result = $media->width() >= ($bW * 1);
		$result = $result || $media->height() >= ($bH * 1);

		return $result;
	}

	/* Styles */

	protected function _defineStyles() {
		$this->_addStyle('beta', [
			'fontFamily' => new Helvetica(),
			'fontSize' => 24
		]);
		$this->_addStyle('beta--bold', [
			'fontFamily' => new HelveticaBold(),
			'fontSize' => 24
		]);
		$this->_addStyle('gamma', [
			'fontFamily' => new Helvetica(),
			'fontSize' => 10
		]);
		$this->_addStyle('gamma--bold', [
			'fontFamily' => new HelveticaBold(),
			'fontSize' => 10
		]);
		$this->_addStyle('epsilon', [
			'fontFamily' => new Helvetica(),
			'fontSize' => 7
		]);
	}

	protected function _addStyle($name, array $definition) {
		$this->_styles[$name] = $definition + [
			'fontFamily' => new Helvetica(),
			'fontSize' => 10,
			'lineHeight' => 13
		];
	}

	protected function _useStyle($name) {
		$this->_currentStyle = $this->_styles[$name];

		$this->__page->setFont(
			$this->_currentStyle['fontFamily'],
			$this->_currentStyle['fontSize']
		);
	}

	/* Depreacted / BC */

	protected function _author($text) {
		trigger_error(
			__METHOD__  . ' is deprecated use public metaXXXX() method instead',
			E_USER_DEPRECATED
		);
		return $this->metaAuthor($text);
	}

	protected function _title($text) {
		trigger_error(
			__METHOD__  . ' is deprecated use public metaXXXX() method instead',
			E_USER_DEPRECATED
		);
		return $this->metaTitle($text);
	}

	protected function _subject($text) {
		trigger_error(
			__METHOD__  . ' is deprecated use public metaXXXX() method instead',
			E_USER_DEPRECATED
		);
		return $this->metaSubject($text);
	}

	protected function _creator($text) {
		trigger_error(
			__METHOD__  . ' is deprecated use public metaXXXX() method instead',
			E_USER_DEPRECATED
		);
		return $this->metaCreator($text);
	}
}

?>