<?php

use OCA\GroupFolders\AppInfo\Application;

$app = new Application();
$app->register();

\OC_Util::addScript('groupfolders', 'files');
