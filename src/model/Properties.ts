/*!
 * SPDX-FileCopyrightText: Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

export const namespace = 'http://nextcloud.org/ns'

/**
 * WebDAV Properties available on the Node itself
 */
export const AclRootProperties = Object.freeze({
	ACL_ENABLED: 'acl-enabled',
	ACL_CAN_MANAGE: 'acl-can-manage',
	GROUP_FOLDER_ID: 'group-folder-id',
	ACL_BASE_PERMISSION: 'acl-base-permission',

	ACL_LIST: 'acl-list',
	INHERITED_ACL_LIST: 'inherited-acl-list',
})

export const AclEntryProperties = Object.freeze({
	ACL_MAPPING_TYPE: 'acl-mapping-type',
	ACL_MAPPING_ID: 'acl-mapping-id',
	ACL_MAPPING_DISPLAY_NAME: 'acl-mapping-display-name',
	ACL_MASK: 'acl-mask',
	ACL_PERMISSIONS: 'acl-permissions',
})

export const AclProperties = Object.freeze({
	...AclRootProperties,

	// entry prop
	ACL_ENTRY: 'acl',
	// entry attribute props
	...AclEntryProperties,
})
