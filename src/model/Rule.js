/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import PROPERTIES from './Properties.js'

export default class Rule {

	fromValues(mappingType, mappingId, mappingDisplayName, mask = 0, permissions = 31, inherited = false, inheritedPermissions = 31) {
		this.mappingType = mappingType
		this.mappingId = mappingId
		this.mappingDisplayName = mappingDisplayName
		this.mask = mask
		this.permissions = permissions
		this.inherited = inherited
		this.inheritedMask = 0
		this.inheritedPermissions = inheritedPermissions
	}

	getProperties() {
		const acl = {}
		acl[PROPERTIES.PROPERTY_ACL_MAPPING_TYPE] = this.mappingType
		acl[PROPERTIES.PROPERTY_ACL_MAPPING_ID] = this.mappingId
		acl[PROPERTIES.PROPERTY_ACL_MASK] = this.mask
		acl[PROPERTIES.PROPERTY_ACL_PERMISSIONS] = this.permissions
		return acl
	}

	getUniqueMappingIdentifier() {
		return this.mappingType + ':' + this.mappingId
	}

	clone() {
		const rule = new Rule()
		Object.getOwnPropertyNames(this)
			.forEach(prop => {
				rule[prop] = this[prop]
			})

		return rule
	}

}
