<?php

use OCA\GroupFolders\AppInfo\Application;

$app = \OC::$server->query(Application::class);
$app->register();

$eventDispatcher = \OC::$server->getEventDispatcher();
$eventDispatcher->addListener(
	'OCA\Files::loadAdditionalScripts',
	function () {
		\OCP\Util::addScript('groupfolders', '../build/files');
	}
);

$eventDispatcher->addListener('OCA\Files_Sharing::loadAdditionalScripts', function () {
	\OCP\Util::addScript('groupfolders', '../build/files');

});
