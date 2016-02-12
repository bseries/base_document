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

namespace base_document\config;

use lithium\core\Libraries;

// Enable document classes and paths via i.e. Libraries::locate('document') and
// Libraries::locate('document.billing').
Libraries::paths([
	'document' => [
		'{:library}\documents\{:name}' => ['libraries' => 'app'],
		'{:library}\documents\{:name}' => ['libraries' => 'base_document'],
		'{:library}\documents\{:name}'
	]
]);

?>