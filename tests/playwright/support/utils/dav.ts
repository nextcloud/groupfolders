/*!
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { User } from '@nextcloud/e2e-test-server'
import type { APIRequestContext } from '@playwright/test'

/**
 * The files DAV endpoint — `remote.php` for a logged-in session, `public.php`
 * for a public share. Match request URLs against this so a wait works on both.
 */
export const DAV_FILES_ENDPOINT = /\/(remote|public)\.php\/dav\/files\//

/**
 * The trash bin DAV endpoint, team folder trash included.
 */
export const DAV_TRASHBIN_ENDPOINT = /\/remote\.php\/dav\/trashbin\//

/**
 * Create a directory.
 *
 * @param request - A request context authenticated as `user`
 * @param user - The user whose root the path is relative to
 * @param path - The path of the directory to create
 */
export async function mkdir(request: APIRequestContext, user: User, path: string): Promise<void> {
	await davRequest(request, user, path, 'MKCOL')
}

/**
 * Upload content to a file.
 *
 * @param request - A request context authenticated as `user`
 * @param user - The user whose root the path is relative to
 * @param path - The path of the file
 * @param content - The content to upload
 * @param mtime - Optional modification time in seconds (sets the `X-OC-MTime` header)
 */
export async function uploadContent(request: APIRequestContext, user: User, path: string, content: string, mtime?: number): Promise<void> {
	await davRequest(request, user, path, 'PUT', {
		data: content,
		headers: {
			'Content-Type': 'text/plain',
			...(mtime !== undefined ? { 'X-OC-MTime': String(Math.floor(mtime)) } : {}),
		},
	})
}

/**
 * Delete a file or directory, moving it to the trash bin.
 *
 * @param request - A request context authenticated as `user`
 * @param user - The user whose root the path is relative to
 * @param path - The path to delete
 */
export async function rm(request: APIRequestContext, user: User, path: string): Promise<void> {
	await davRequest(request, user, path, 'DELETE')
}

/**
 * Get the content of a file.
 *
 * @param request - A request context authenticated as `user`
 * @param user - The user whose root the path is relative to
 * @param path - The path of the file
 */
export async function getFileContent(request: APIRequestContext, user: User, path: string): Promise<string> {
	const response = await davRequest(request, user, path, 'GET')
	return response.text()
}

/**
 * Return the WebDAV permission letters (`oc:permissions`, e.g. "SRGDNVCK") of
 * an entry of a directory, or '' if there is no such entry.
 *
 * @param request - A request context authenticated as `user`
 * @param user - The user whose root the path is relative to
 * @param parentPath - The directory to list, '' for the root
 * @param childName - The name of the entry
 */
export async function getChildPermissions(request: APIRequestContext, user: User, parentPath: string, childName: string): Promise<string> {
	const response = await davRequest(request, user, parentPath, 'PROPFIND', {
		headers: { Depth: '1' },
		data: '<?xml version="1.0"?><d:propfind xmlns:d="DAV:" xmlns:oc="http://owncloud.org/ns"><d:prop><oc:permissions/></d:prop></d:propfind>',
	})
	const body = await response.text()
	for (const entry of body.split(/<\/d:response>/i)) {
		const href = entry.match(/<d:href>([^<]*)<\/d:href>/)?.[1] ?? ''
		const name = decodeURIComponent(href.replace(/\/$/, '').split('/').pop() ?? '')
		if (name === childName) {
			return entry.match(/<oc:permissions>([^<]*)<\/oc:permissions>/)?.[1] ?? ''
		}
	}
	return ''
}

/**
 * Send a request to the files DAV endpoint of a user and fail on an error status.
 *
 * @param request - A request context authenticated as `user`
 * @param user - The user whose root the path is relative to
 * @param path - The path relative to the user's root
 * @param method - The HTTP method
 * @param options - Request headers and body
 * @param options.headers - Additional request headers
 * @param options.data - The request body
 */
async function davRequest(
	request: APIRequestContext,
	user: User,
	path: string,
	method: string,
	{ headers = {}, data }: { headers?: Record<string, string>, data?: string } = {},
) {
	const tokenResponse = await request.get('./csrftoken', { failOnStatusCode: true })
	const { token } = await tokenResponse.json()

	const response = await request.fetch(davUrl(user, path), {
		method,
		headers: { requesttoken: token, ...headers },
		data,
	})
	if (!response.ok()) {
		throw new Error(`${method} ${path} failed with status ${response.status()}`)
	}
	return response
}

/**
 * The DAV URL of a path of a user.
 *
 * @param user - The user the path belongs to
 * @param path - The path relative to the user's root directory
 */
function davUrl(user: User, path: string): string {
	const encodedPath = ('/' + path).replace(/\/+/g, '/')
		.split('/')
		.map((segment) => encodeURIComponent(segment))
		.join('/')
	return `/remote.php/dav/files/${encodeURIComponent(user.userId)}${encodedPath}`
}
