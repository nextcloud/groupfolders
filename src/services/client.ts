/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { getCurrentUser, getRequestToken } from '@nextcloud/auth'
import { generateRemoteUrl } from '@nextcloud/router'
import { createClient } from 'webdav'

export const rootPath = `/groupfolders/${getCurrentUser()?.uid}`
export const rootUrl = generateRemoteUrl('dav' + rootPath)
const client = createClient(rootUrl, {
	headers: {
		requesttoken: getRequestToken() || '',
	},
})
export default client
