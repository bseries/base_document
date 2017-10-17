<?php
/**
 * Copyright 2015 David Persson. All rights reserved.
 * Copyright 2016 Atelier Disko. All rights reserved.
 *
 * Use of this source code is governed by a BSD-style
 * license that can be found in the LICENSE file.
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