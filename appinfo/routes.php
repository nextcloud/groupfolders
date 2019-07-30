<?php

return ['ocs' => [
	[
		'name' => 'Folder#getFolders',
		'url' => '/folders',
		'verb' => 'GET'
	],
	[
		'name' => 'Folder#getFolder',
		'url' => '/folders/{id}',
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
	],

	[
		'name' => 'Folder#setACL',
		'url' => '/folders/{id}/acl',
		'verb' => 'POST'
	],
	[
		'name' => 'Folder#renameFolder',
		'url' => '/folders/{id}/mountpoint',
		'verb' => 'POST'
	],
	[
		'name' => 'Folder#aclMappingSearch',
		'url' => '/folders/{id}/search',
		'verb' => 'GET'
	]
]];
