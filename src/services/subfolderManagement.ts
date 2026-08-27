/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import axios from '@nextcloud/axios'
import { confirmPassword } from '@nextcloud/password-confirmation'
import { generateUrl } from '@nextcloud/router'
// eslint-disable-next-line n/no-unpublished-import
import type { OCSResponse } from '@nextcloud/typings/ocs'

export interface SubfolderManager {
	displayname: string
	id: string
	type: 'user' | 'group' | 'circle'
}

export interface SubfolderManagement {
	file_id: number
	name: string
	size: number
	quota: number | null
	managers: SubfolderManager[]
	can_manage: boolean
	can_assign: boolean
}

function managementUrl(folderId: number, fileId: number): string {
	return generateUrl('apps/groupfolders/folders/{folderId}/subfolder-quotas/{fileId}/management', {
		folderId,
		fileId,
	})
}

export async function getSubfolderManagement(folderId: number, fileId: number): Promise<SubfolderManagement> {
	const response = await axios.get<OCSResponse<SubfolderManagement>>(managementUrl(folderId, fileId))
	return response.data.ocs.data
}

export async function setSubfolderManager(folderId: number, fileId: number, mapping: SubfolderManager, manager: boolean): Promise<SubfolderManagement> {
	await confirmPassword()

	const response = await axios.post<OCSResponse<SubfolderManagement>>(managementUrl(folderId, fileId), {
		mappingType: mapping.type,
		mappingId: mapping.id,
		manager: manager ? 1 : 0,
	})
	return response.data.ocs.data
}
