/*!
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { User } from '@nextcloud/e2e-test-server'
import type { APIRequestContext } from '@playwright/test'

import { uploadContent } from './dav.ts'

/**
 * The contents of the three versions seeded by {@link seedThreeVersions},
 * oldest first. "v3" is the current content of the file.
 */
export const VERSION_CONTENTS = ['v1', 'v2', 'v3'] as const

/**
 * Create a file with three versions by uploading it three times.
 *
 * Team folder versions are keyed by the modification time of the file, so two
 * uploads within the same second would end up as one version. The uploads set
 * explicit modification times 120s apart in the recent past instead, which
 * keeps them apart without waiting and clear of the automatic expiration,
 * which keeps one version per minute within the last hour.
 *
 * @param request - A request context authenticated as `user`
 * @param user - The user whose root the path is relative to
 * @param path - The path of the file
 */
export async function seedThreeVersions(request: APIRequestContext, user: User, path: string): Promise<void> {
	const now = Math.floor(Date.now() / 1000)
	for (const [index, content] of VERSION_CONTENTS.entries()) {
		const mtime = now - (VERSION_CONTENTS.length - index) * 120
		await uploadContent(request, user, path, content, mtime)
	}
}
