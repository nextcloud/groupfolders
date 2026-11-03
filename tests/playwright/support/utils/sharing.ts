/*!
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { User } from '@nextcloud/e2e-test-server'
import type { APIRequestContext } from '@playwright/test'

import { expect } from '@playwright/test'
import { getChildPermissions } from './dav.ts'

const SHARE_TYPE_USER = 0

/**
 * Share a file or folder with a user through the OCS Share API, granting all
 * permissions the node allows.
 *
 * @param request - A request context authenticated as the share owner
 * @param path - The path to share, relative to the owner's root
 * @param recipient - The user to share with
 */
export async function createUserShare(request: APIRequestContext, path: string, recipient: User): Promise<void> {
	const response = await request.post('/ocs/v2.php/apps/files_sharing/api/v1/shares?format=json', {
		headers: { 'OCS-APIRequest': 'true' },
		form: { path, shareType: SHARE_TYPE_USER, shareWith: recipient.userId },
	})
	const { ocs } = await response.json()
	// OCS answers HTTP 200 even on failure, the real status is in its meta data
	expect(ocs?.meta?.statuscode, `Sharing ${path} failed: ${ocs?.meta?.message}`).toBe(200)
}

/**
 * Wait for a share to be mounted in the files of its recipient.
 *
 * @param request - A request context authenticated as the recipient
 * @param recipient - The recipient
 * @param parentPath - The folder the share is mounted in, '' for the root
 * @param name - The name of the shared node
 */
export async function waitForShare(request: APIRequestContext, recipient: User, parentPath: string, name: string): Promise<void> {
	await expect.poll(
		() => getChildPermissions(request, recipient, parentPath, name).catch(() => ''),
		{ message: `share ${parentPath}/${name} did not reach ${recipient.userId}`, timeout: 20_000 },
	).not.toBe('')
}
