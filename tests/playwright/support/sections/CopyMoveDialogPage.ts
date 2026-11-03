/*!
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { Locator, Page } from '@playwright/test'

import { expect } from '@playwright/test'
import { DAV_FILES_ENDPOINT } from '../utils/dav.ts'
import { escapeAttributeValue } from '../utils/selectors.ts'

/**
 * The file picker opened by the "Move or copy" action of the files app.
 *
 * Its confirm buttons are worded after the folder it shows: "Copy" / "Move"
 * for the root, "Copy to <name>" / "Move to <name>" for any other folder.
 */
export class CopyMoveDialogPage {
	constructor(private readonly page: Page) {}

	/** The open file picker. */
	dialog(): Locator {
		return this.page.getByRole('dialog', { name: 'Choose destination' })
	}

	/**
	 * A folder listed by the picker. The rows have no accessible name of their
	 * own to match by, so they are matched by the library-owned `data-filename`.
	 *
	 * @param name - Name of the folder
	 */
	getDestination(name: string): Locator {
		return this.dialog().locator(`[data-filename="${escapeAttributeValue(name)}"]`)
	}

	/**
	 * The breadcrumbs of the picker. It has a second navigation, the views on
	 * the side ("All files", "Recent", "Favorites"), told apart by "Favorites".
	 */
	private breadcrumbs(): Locator {
		return this.dialog()
			.getByRole('navigation')
			.filter({ hasNot: this.page.getByRole('button', { name: 'Favorites' }) })
	}

	/**
	 * Wait for the picker to list a folder.
	 *
	 * @param folder - Name of the folder, undefined for the root
	 */
	private async listingLoaded(folder?: string): Promise<void> {
		await this.page.waitForResponse((r) => r.request().method() === 'PROPFIND' && this.isListingOf(r.url(), folder))
		// skeleton rows shown while loading, aria-hidden so without a role
		await expect(this.dialog().locator('.loading-row')).toHaveCount(0)
	}

	/**
	 * Whether a request URL lists the given folder of the user's files.
	 *
	 * @param url - The request URL
	 * @param folder - Name of the folder, undefined for the root
	 */
	private isListingOf(url: string, folder?: string): boolean {
		const path = decodeURIComponent(new URL(url).pathname).replace(/\/+$/, '')
		const match = path.match(/\/(?:remote|public)\.php\/dav\/files\/[^/]+(?<path>\/.*)?$/)
		if (!match) {
			return false
		}
		const relative = match.groups?.path ?? ''
		return folder === undefined ? relative === '' : relative.endsWith(`/${folder}`)
	}

	/**
	 * Navigate the picker back to the root of the user's files.
	 * Only call this while the picker shows another folder, the root is not listed again otherwise.
	 */
	async goToAllFiles(): Promise<void> {
		const loaded = this.listingLoaded()
		await this.breadcrumbs().getByRole('button', { name: 'All files' }).click()
		await loaded
	}

	/**
	 * Navigate the picker into a folder of the folder it shows, path segment by path segment.
	 *
	 * @param path - Path of the folder relative to the folder the picker shows
	 * @return The name of the folder navigated into
	 */
	async navigateTo(path: string): Promise<string> {
		const segments = path.split('/').filter(Boolean)
		for (const segment of segments) {
			const loaded = this.listingLoaded(segment)
			await this.getDestination(segment).click()
			await loaded
		}
		return segments.at(-1)!
	}

	/**
	 * Copy into a folder of the folder the picker shows.
	 *
	 * @param path - Path of the destination, relative to the folder the picker shows
	 */
	async copyTo(path: string): Promise<void> {
		const target = await this.navigateTo(path)
		await this.confirm(`Copy to ${target}`, 'COPY')
	}

	/**
	 * Move into a folder of the folder the picker shows.
	 *
	 * @param path - Path of the destination, relative to the folder the picker shows
	 */
	async moveTo(path: string): Promise<void> {
		const target = await this.navigateTo(path)
		await this.confirm(`Move to ${target}`, 'MOVE')
	}

	/** Copy into the root folder, the picker must show it. */
	async copyToRoot(): Promise<void> {
		await this.confirm('Copy', 'COPY')
	}

	/** Move into the root folder, the picker must show it. */
	async moveToRoot(): Promise<void> {
		await this.confirm('Move', 'MOVE')
	}

	/**
	 * Confirm the picker and wait for the files app to be done with the operation.
	 *
	 * @param label - Label of the confirm button
	 * @param method - HTTP method of the request of the operation
	 */
	private async confirm(label: string, method: 'COPY' | 'MOVE'): Promise<void> {
		const button = this.dialog().getByRole('button', { name: label, exact: true })
		// the picker disables its buttons while it lists a folder
		await expect(button).toBeEnabled({ timeout: 15_000 })

		const done = this.page.waitForResponse((r) => r.request().method() === method && DAV_FILES_ENDPOINT.test(r.url()))
		await button.click()
		await expect(this.dialog()).toBeHidden()
		expect((await done).ok()).toBe(true)

		// The files app shows a progress toast before the request and hides it
		// once the operation and the list update are done
		await expect(this.page.getByRole('status').filter({ hasText: /(Moving|Copying) .* …/ }))
			.toHaveCount(0, { timeout: 15_000 })
	}
}
