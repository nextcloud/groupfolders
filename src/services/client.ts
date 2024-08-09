/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { createClient } from 'webdav'
import { generateRemoteUrl } from '@nextcloud/router'
import { getCurrentUser, getRequestToken } from '@nextcloud/auth'

export const rootPath = `/groupfolders/${getCurrentUser()?.uid}`
export const rootUrl = generateRemoteUrl('dav' + rootPath)
const client = createClient(rootUrl, {
	headers: {
		requesttoken: getRequestToken() || '',
	},
})
export default client
