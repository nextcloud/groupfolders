/*
 * SPDX-FileCopyrightText: 2018 Julius Härtl <jus@bitgrid.net>
 *
 * @author Julius Härtl <jus@bitgrid.net>
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 */

import PROPERTIES from './Properties'

export default class Rule {

	fromProperties(props) {
		this.mappingType = props[PROPERTIES.PROPERTY_ACL_MAPPING_TYPE]
		this.mappingId = props[PROPERTIES.PROPERTY_ACL_MAPPING_ID]
		this.mask = props[PROPERTIES.PROPERTY_ACL_MASK]
		this.permissions = props[PROPERTIES.PROPERTY_ACL_PERMISSIONS]
	}

	fromValues(mappingType, mappingId, mappingDisplayName, mask = 0, permissions = 31, inherited = false) {
		this.mappingType = mappingType
		this.mappingId = mappingId
		this.mappingDisplayName = mappingDisplayName
		this.mask = mask
		this.permissions = permissions
		this.inherited = inherited
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
