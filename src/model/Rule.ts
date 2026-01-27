/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { AclProperties } from './Properties.js'

export default class Rule {

	mappingType: 'user' | 'group' | 'circle'
	mappingId: string
	mappingDisplayName: string
	mask: number
	permissions: number
	inherited: boolean
	inheritedMask: number
	inheritedPermissions: number

	static fromValues(
		mappingType: 'user' | 'group' | 'circle',
		mappingId: string,
		mappingDisplayName: string,
		mask = 0,
		permissions = 31,
		inherited = false,
		inheritedPermissions = 31,
	) {
		const rule = new this()

		rule.mappingType = mappingType
		rule.mappingId = mappingId
		rule.mappingDisplayName = mappingDisplayName
		rule.mask = mask
		rule.permissions = permissions
		rule.inherited = inherited
		rule.inheritedMask = 0
		rule.inheritedPermissions = inheritedPermissions

		return rule
	}

	getProperties() {
		return {
			[AclProperties.ACL_MAPPING_TYPE]: this.mappingType,
			[AclProperties.ACL_MAPPING_ID]: this.mappingId,
			[AclProperties.ACL_MASK]: this.mask,
			[AclProperties.ACL_PERMISSIONS]: this.permissions,
		}
	}

	serialize(): string {
		const properties = Object.entries(this.getProperties())
			.map(([prop, value]) => `<nc:${prop}>${value}</nc:${prop}>`)
			.join('')
		return `<nc:${AclProperties.ACL_ENTRY}>${properties}</nc:${AclProperties.ACL_ENTRY}>`
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
