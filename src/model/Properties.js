/*
 * @copyright Copyright (c) 2018 Julius Härtl <jus@bitgrid.net>
 *
 * @author Julius Härtl <jus@bitgrid.net>
 *
 * @license GNU AGPL version 3 or any later version
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU Affero General Public License as
 *  published by the Free Software Foundation, either version 3 of the
 *  License, or (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU Affero General Public License for more details.
 *
 *  You should have received a copy of the GNU Affero General Public License
 *  along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
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
}

export default ACL_PROPERTIES
