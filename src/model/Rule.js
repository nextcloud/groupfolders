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

import PROPERTIES from './Properties.js'

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
