/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

const ACL_PROPERTIES = {
	PROPERTY_ACL_LIST: '{' + OC.Files.Client.NS_NEXTCLOUD + '}acl-list',
	PROPERTY_ACL_ENTRY: '{' + OC.Files.Client.NS_NEXTCLOUD + '}acl',
	PROPERTY_ACL_MAPPING_TYPE: '{' + OC.Files.Client.NS_NEXTCLOUD + '}acl-mapping-type',
	PROPERTY_ACL_MAPPING_ID: '{' + OC.Files.Client.NS_NEXTCLOUD + '}acl-mapping-id',
	PROPERTY_ACL_MASK: '{' + OC.Files.Client.NS_NEXTCLOUD + '}acl-mask',
	PROPERTY_ACL_PERMISSIONS: '{' + OC.Files.Client.NS_NEXTCLOUD + '}acl-permissions',
	PROPERTY_ACL_ENABLED: '{' + OC.Files.Client.NS_NEXTCLOUD + '}acl-enabled',
	PROPERTY_ACL_CAN_MANAGE: '{' + OC.Files.Client.NS_NEXTCLOUD + '}acl-can-manage',
	PROPERTY_INHERITED_ACL_LIST: '{' + OC.Files.Client.NS_NEXTCLOUD + '}inherited-acl-list',
	GROUP_FOLDER_ID: '{' + OC.Files.Client.NS_NEXTCLOUD + '}group-folder-id',
	PROPERTY_ACL_BASE_PERMISSION: '{' + OC.Files.Client.NS_NEXTCLOUD + '}acl-base-permission',
}

export default ACL_PROPERTIES
