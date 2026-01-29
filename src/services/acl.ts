/*!
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { FileStat, ResponseDataDetailed } from 'webdav'

import { defaultRootPath, getClient } from '@nextcloud/files/dav'
import { join } from '@nextcloud/paths'
import Rule from '../model/Rule'
import { AclEntryProperties, AclProperties, AclRootProperties, namespace } from '../model/Properties'
import { logger } from './logger'

export class HintException extends Error {}

/**
 * WebDAV PROPFIND body to load ACL props.
 */
const ACL_PROPFIND = `
<d:propfind xmlns:d="DAV:" xmlns:nc="http://nextcloud.org/ns">
	<d:prop>
		${Object.values(AclRootProperties).map((prop) => `<nc:${prop}/>`).join('\n')}
	</d:prop>
</d:propfind>
`

export async function setAcls(path: string, rules: Rule[]): Promise<void> {
	const client = getClient()
	try {
		await client.customRequest(join(defaultRootPath, path), {
			method: 'PROPPATCH',
			data: generatePropPatch(rules),
		})
	} catch (error) {
		logger.error('Failed to set ACLs', { path, error })
		if (typeof error === 'object' && error && 'response' in error) {
			const response = error.response as Response
			const text = await response.text()
			const parser = new DOMParser()
			const dom = parser.parseFromString(text, 'application/xml')
			const message = dom.querySelector('error')?.querySelector('message')?.textContent
			if (message) {
				throw new HintException(message, { cause: error })
			}
		}
		throw error
	}
}

/**
 * Loads the ACLs for a given path.
 *
 * @param path - The path to lookup (relative to DAV root)
 */
export async function getAcls(path: string) {
	const client = getClient()
	const result = await client.stat(join(defaultRootPath, path), { details: true, data: ACL_PROPFIND })
	const { props } = (result as ResponseDataDetailed<FileStat>).data

	if (props) {
		return parseAclProps(props as unknown as Record<typeof AclRootProperties[keyof typeof AclRootProperties], unknown>)
	}
}

/**
 * Generate WebDAV PROPPATCH body to set ACLs.
 *
 * @param rules - Array of Rule objects representing ACL entries
 */
function generatePropPatch(rules: Rule[]): string {
	const props = rules.map((rule) => rule.serialize())
	return `<?xml version="1.0"?>
<d:propertyupdate xmlns:d="DAV:" xmlns:nc="${namespace}">
	<d:set>
		<d:prop>
			<nc:${AclProperties.ACL_LIST}>
				${props.join('')}
			</nc:${AclProperties.ACL_LIST}>
		</d:prop>
	</d:set>
</d:propertyupdate>`
}

/**
 * Parse WebDAV ACL props to internal format.
 *
 * @param props - WebDAV props
 */
function parseAclProps(props: Record<typeof AclRootProperties[keyof typeof AclRootProperties], unknown>) {
	type ACLEntry = Record<typeof AclEntryProperties[keyof typeof AclEntryProperties], string | number>

	const inheritedListProp: ACLEntry[] = typeof props[AclRootProperties.INHERITED_ACL_LIST] === 'object'
		? [(props[AclRootProperties.INHERITED_ACL_LIST] as { [AclProperties.ACL_ENTRY]: ACLEntry | ACLEntry[] })[AclProperties.ACL_ENTRY]].flat()
		: []

	const inheritedAclList = inheritedListProp
		.map((entry) => Rule.fromValues(
			entry[AclProperties.ACL_MAPPING_TYPE] as 'user' | 'group' | 'circle',
			entry[AclProperties.ACL_MAPPING_ID] as string,
			entry[AclProperties.ACL_MAPPING_DISPLAY_NAME] as string,
			entry[AclProperties.ACL_MASK] as number,
			entry[AclProperties.ACL_PERMISSIONS] as number,
			true,
			entry[AclProperties.ACL_PERMISSIONS] as number
		))

	const listProp: ACLEntry[] = typeof props[AclRootProperties.ACL_LIST] === 'object'
		? [(props[AclRootProperties.ACL_LIST] as { [AclProperties.ACL_ENTRY]: ACLEntry | ACLEntry[] })[AclProperties.ACL_ENTRY]].flat()
		: []

	const aclList = listProp
		.map((entry) => Rule.fromValues(
			entry[AclProperties.ACL_MAPPING_TYPE] as 'user' | 'group' | 'circle',
			entry[AclProperties.ACL_MAPPING_ID] as string,
			entry[AclProperties.ACL_MAPPING_DISPLAY_NAME] as string,
			entry[AclProperties.ACL_MASK] as number,
			entry[AclProperties.ACL_PERMISSIONS] as number,
			false,
			Number(props[AclRootProperties.ACL_BASE_PERMISSION])
		))
		.map((rule) => {
			const inheritedRule = inheritedAclList.find((r) => r.getUniqueMappingIdentifier() === rule.getUniqueMappingIdentifier())
			if (inheritedRule) {
				rule.permissions = (rule.permissions & rule.mask) | (inheritedRule.permissions & ~rule.mask)
			}
			return rule
		})

	return {
		basePermission: Number(props[AclRootProperties.ACL_BASE_PERMISSION] ?? 0),
		canManage: Boolean(props[AclRootProperties.ACL_CAN_MANAGE]),
		enabled: Boolean(props[AclRootProperties.ACL_ENABLED]),
		groupFolderId: props[AclRootProperties.GROUP_FOLDER_ID] as number,

		aclList,
		inheritedAclList,
	}
}
