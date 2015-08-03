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

use Exception;
use BadMethodCallException;
use lithium\core\Libraries;
use lithium\analysis\Logger;
use ZendPdf\PdfDocument;
use ZendPdf\Resource\Font\Simple\Standard\Helvetica;
use ZendPdf\Resource\Font\Simple\Standard\HelveticaBold;
use ZendPdf\Resource\Image\ImageFactory;
use Media_Info;

abstract class Base {

	protected $_layout = 'blank';

	protected $_fontSize = 10;

	protected $_lineHeight = 13;

	protected $_borderHorizontal = [80, 55];

	protected $_pageWidth = 594;

	protected $_currentHeight;

	protected $_font;

	protected $_fontBold;

	protected $_encoding = 'UTF-8';

	private $__pdf;

	private $__page;

	private $__pageTemplate;

	public function __construct() {
		$this->_font = new Helvetica();
		$this->_fontBold = new HelveticaBold();
	}

	public function compile() {
		Logger::write('debug', 'Compiling document.');

		$this->__pdf = $this->_loadLayout();
		$this->__page = $this->__pdf->pages[0];
		$this->_setFont($this->_fontSize);
		$this->_compileHeaderFooter();

		// Cloning after inserting header and footer, so
		// that from now on we don't have to insert header/footer
		// on each new page.
		$this->__pageTemplate = clone $this->__page;
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
		throw new BadMethodCallException("Unknown method $method.");
	}

	abstract protected function _compileHeaderFooter();

	/* Metadata */

	protected function _author($text) {
		$this->__pdf->properties['Author'] = $text;
	}

	protected function _title($text) {
		$this->__pdf->properties['Title'] = $text;
	}

	protected function _subject($text) {
		$this->__pdf->properties['Subject'] = $text;
	}

	protected function _creator($text) {
		$this->__pdf->properties['Creator'] = $text;
	}

	/* Basic methods */

	protected function _nextPage() {
		$this->__page = clone $this->__pageTemplate;
		$this->__pdf->pages[] = $this->__page;
		$this->_setFont($this->_fontSize);
		$this->_currentHeight = $this->_heightHeader();
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

	protected function _setFont($size, $bold = false) {
		if ($bold) {
			$this->__page->setFont($this->_fontBold, $size);
		} else {
			$this->__page->setFont($this->_font, $size);
		}
	}

	protected function _skipLines($number = 1) {
		$offsetY = $this->_currentHeight;
		return $offsetY - ($number * $this->_lineHeight);
	}

	// $align may be numeric then it is used as offsetX
	protected function _drawText($text, $align = 'left', array $options = []) {
		$options += [
			'offsetY' => $this->_currentHeight
		];
		if (strpos($text, "\n") !== false) {
			$text = explode("\n", $text);

			foreach ($text as $t) {
				$this->_drawText($t, $align, $options);
				$options['offsetY'] -= $this->_lineHeight; // Skip 1 line.
			}
			return true;
		}
		if ($align == 'center') {
			list($offsetX, $offsetY) = $this->_alignText($text, 'center', $options);
			$this->__page->drawText($text, $offsetX, $offsetY, $this->_encoding);

		} elseif ($align == 'right') {
			list($offsetX, $offsetY) = $this->_alignText($text, 'right', $options);
			$this->__page->drawText($text, $offsetX, $offsetY, $this->_encoding);

		} elseif ($align == 'left') {
			list($offsetX, $offsetY) = $this->_alignText($text, 'left', $options);
			$maxWidth = $this->_pageWidth - (array_sum($this->_borderHorizontal));

			if ($this->_width($text) > $maxWidth) {
				$text = wordwrap($text, 100, "\n", false);

				$tokens = explode("\n", $text);
				foreach ($tokens as $token) {
					$this->__page->drawText($token, $offsetX, $offsetY, $this->_encoding);
					$offsetY -= $this->_lineHeight; // Skip 1 line.
				}
			} else {
				$this->__page->drawText($text, $offsetX, $offsetY, $this->_encoding);
			}
		} else {
			throw new Exception("Invalid text alignment {$align}.");
		}
		$this->_currentHeight = $offsetY;
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
				$range['width'] - $this->_width($text) - $this->_borderHorizontal[1] + $range['offsetX'],
				$range['offsetY']
			];
		} else {
			$range['width'] = $range['width'] ?: $this->_pageWidth - (array_sum($this->_borderHorizontal));

			return [
				$this->_borderHorizontal[0] + $range['offsetX'],
				$range['offsetY']
			];
		}
	}

	/* Drawing */
	protected function _drawHorizontalLine() {
		$this->__page->drawLine(
			$this->_borderHorizontal[0], ceil($this->_currentHeight + ($this->_lineHeight / 2)),
			$this->_pageWidth - $this->_borderHorizontal[1] + 5, ceil($this->_currentHeight + ($this->_lineHeight / 2))
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
}

?>