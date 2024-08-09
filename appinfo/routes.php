<?php

/**
 * SPDX-FileCopyrightText: 2017 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

return ['routes' => [
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
		'verb' => 'DELETE',
		'requirements' => ['group' => '.+']
	],
	[
		'name' => 'Folder#setPermissions',
		'url' => '/folders/{id}/groups/{group}',
		'verb' => 'POST',
		'requirements' => ['group' => '.+']
	],
	[
		'name' => 'Folder#setManageACL',
		'url' => '/folders/{id}/manageACL',
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
	],
	[
		'name' => 'Delegation#getAllGroups',
		'url' => 'delegation/groups',
		'verb' => 'GET'
	],
	[
		'name' => 'Delegation#getAllCircles',
		'url' => 'delegation/circles',
		'verb' => 'GET'
	],
	[
		'name' => 'Delegation#getAuthorizedGroups',
		'url' => '/delegation/authorized-groups',
		'verb' => 'GET',
	],
]];
