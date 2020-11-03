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

import ACL_PROPERTIES from './model/Properties'
import Rule from './model/Rule'

let client

// Allow nested properties in PROPPATCH
// WIP branch at https://github.com/juliushaertl/davclient.js/tree/enhancement/nested-proppatch
const patchClientForNestedPropPatch = (client) => {
	client._client.getPropertyBody = function(key, propValue) {
		const property = this.parseClarkNotation(key)
		let propName

		if (this.xmlNamespaces[property.namespace]) {
			propName = this.xmlNamespaces[property.namespace] + ':' + property.name
		} else {
			propName = 'x:' + property.name + ' xmlns:x="' + property.namespace + '"'
		}

		if (Array.isArray(propValue)) {
			let body = ''
			for (const ii in propValue) {
				if (propValue[ii].hasOwnProperty('type') && propValue[ii].hasOwnProperty('data')) {
					body += this.getPropertyBody(propValue[ii].type, propValue[ii].data)
				} else {
					body += this.getPropertyBody(ii, propValue[ii])
				}
			}
			return '      <' + propName + '>' + body + '</' + propName + '>'
		} else if (typeof propValue === 'object') {
			let body = ''
			if (propValue.hasOwnProperty('type') && propValue.hasOwnProperty('data')) {
				return this.getPropertyBody(propValue.type, propValue.data)
			}
			for (const ii in propValue) {
				body += this.getPropertyBody(ii, propValue[ii])
			}
			return '      <' + propName + '>' + body + '</' + propName + '>'
		} else {
			// FIXME: hard-coded for now until we allow properties to
			// specify whether to be escaped or not
			if (propName !== 'd:resourcetype') {
				propValue = dav._escapeXml('' + propValue)
			}

			return '      <' + propName + '>' + propValue + '</' + propName + '>'
		}
	}
	client._client._renderPropSet = function(properties) {
		let body = '  <d:set>\n'
			+ '   <d:prop>\n'

		for (const ii in properties) {
			if (!properties.hasOwnProperty(ii)) {
				continue
			}

			body += this.getPropertyBody(ii, properties[ii])
		}
		body += '    </d:prop>\n'
		body += '  </d:set>\n'
		return body
	}
}

const parseAclList = (acls) => {
	const list = []
	for (let i = 0; i < acls.length; i++) {
		const acl = {
			mask: 0,
			permissions: 0,
		}
		for (const ii in acls[i].children) {
			const prop = acls[i].children[ii]
			if (!prop.nodeName) {
				continue
			}

			const propertyName = prop.nodeName.split(':')[1] || ''
			switch (propertyName) {
			case 'acl-mapping-id':
				acl.mappingId = prop.textContent || prop.text
				break
			case 'acl-mapping-type':
				acl.mappingType = prop.textContent || prop.text
				break
			case 'acl-mapping-display-name':
				acl.mappingDisplayName = prop.textContent || prop.text
				break
			case 'acl-mask':
				acl.mask = parseInt(prop.textContent || prop.text, 10)
				break
			case 'acl-permissions':
				acl.permissions = parseInt(prop.textContent || prop.text, 10)
				break
			default:
				break
			}
		}
		list.push(acl)
	}
	return list
}

/** @type OC.Plugin */
const FilesPlugin = {
	attach: function(fileList) {
		client = fileList.filesClient
		client.addFileInfoParser((response) => {
			const data = {}
			const props = response.propStat[0].properties
			const groupFolderId = props[ACL_PROPERTIES.GROUP_FOLDER_ID]
			if (typeof groupFolderId !== 'undefined') {
				data.groupFolderId = groupFolderId
			}
			const aclEnabled = props[ACL_PROPERTIES.PROPERTY_ACL_ENABLED]
			if (typeof aclEnabled !== 'undefined') {
				data.aclEnabled = !!aclEnabled
			}

			const aclCanManage = props[ACL_PROPERTIES.PROPERTY_ACL_CAN_MANAGE]
			if (typeof aclCanManage !== 'undefined') {
				data.aclCanManage = !!aclCanManage
			}

			const acls = props[ACL_PROPERTIES.PROPERTY_ACL_LIST];
			const inheritedAcls = props[ACL_PROPERTIES.PROPERTY_INHERITED_ACL_LIST];

			if (!_.isUndefined(acls)) {
				data.acl = parseAclList(acls);
				data.inheritedAcls = parseAclList(inheritedAcls);

				data.acl.map((acl) => {
					const inheritedAcl = data.inheritedAcls.find((inheritedAclRule) => inheritedAclRule.mappingType === acl.mappingType && inheritedAclRule.mappingId === acl.mappingId)
					if (inheritedAcl) {
						acl.permissions = (acl.permissions & acl.mask) | (inheritedAcl.permissions & ~acl.mask)
					}
					return acl;
				})
			}
			return data;
		});

		patchClientForNestedPropPatch(client)
	},
};

(function(OC) {
	_.extend(OC.Files.Client, ACL_PROPERTIES)
})(window.OC);

OC.Plugins.register('OCA.Files.FileList', FilesPlugin);

class AclDavService {

	propFind(model) {
		return client.getFileInfo(model.path + '/' + model.name, {
			properties: [ACL_PROPERTIES.PROPERTY_ACL_LIST, ACL_PROPERTIES.PROPERTY_INHERITED_ACL_LIST, ACL_PROPERTIES.GROUP_FOLDER_ID, ACL_PROPERTIES.PROPERTY_ACL_ENABLED, ACL_PROPERTIES.PROPERTY_ACL_CAN_MANAGE],
		}).then((status, fileInfo) => {
			if (fileInfo) {
				let acls = []
				for ( let i in fileInfo.acl ) {
					let acl = new Rule()
					acl.fromValues(
						fileInfo.acl[i].mappingType,
						fileInfo.acl[i].mappingId,
						fileInfo.acl[i].mappingDisplayName,
						fileInfo.acl[i].mask,
						fileInfo.acl[i].permissions,
					)
					acls.push(acl);
				}
				return {
					acls,
					aclEnabled: fileInfo.aclEnabled,
					aclCanManage: fileInfo.aclCanManage,
					groupFolderId: fileInfo.groupFolderId,
				}
			}
			// TODO parse inherited permissions here
			return null;
		});
	}

	propPatch(model, acls) {
		const aclList = []
		for (const i in acls) {
			aclList.push({ type: ACL_PROPERTIES.PROPERTY_ACL_ENTRY, data: acls[i].getProperties() })
		}
		const props = {}
		props[ACL_PROPERTIES.PROPERTY_ACL_LIST] = aclList
		return client._client.propPatch(client._client.baseUrl + model.path + '/' + model.name, props)
	}

}

export default new AclDavService()
