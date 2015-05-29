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

use lithium\g11n\Catalog;

Catalog::config([
	basename(dirname(__DIR__)) => [
		'adapter' => 'Gettext',
		'path' => dirname(__DIR__) . '/resources/g11n/po'
	 ]
] + Catalog::config());

?>