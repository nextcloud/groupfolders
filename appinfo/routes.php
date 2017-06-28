<?php
$application = new \OCA\GroupFolders\AppInfo\Application();

$application->registerRoutes(
	$this,
	[
		'routes' => [
			[
				'name' => 'Folder#getFolders',
				'url' => '/folders',
				'verb' => 'GET'
			],
			[
				'name' => 'Folder#addFolder',
				'url' => '/folders',
				'verb' => 'POST'
			],
			[
				'name' => 'Folder#removeFolder',
				'url' => '/folders/{id}',
				'verb' => 'DELETE'
			],
			[
				'name' => 'Folder#setMountPoint',
				'url' => '/folders/{id}',
				'verb' => 'PUT'
			],
			[
				'name' => 'Folder#addGroup',
				'url' => '/folders/{id}/groups',
				'verb' => 'POST'
			],
			[
				'name' => 'Folder#removeGroup',
				'url' => '/folders/{id}/groups/{group}',
				'verb' => 'DELETE'
			],
			[
				'name' => 'Folder#setPermissions',
				'url' => '/folders/{id}/groups/{group}',
				'verb' => 'POST'
			],
			[
				'name' => 'Folder#setQuota',
				'url' => '/folders/{id}/quota',
				'verb' => 'POST'
			]
		],
	]
);
