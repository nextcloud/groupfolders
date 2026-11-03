/*!
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { Locator, Page } from '@playwright/test'
import type { FilesListPage } from './FilesListPage.ts'

import { expect } from '@playwright/test'

/** Accessible names of the version actions, keyed by their id. */
const VERSION_ACTION_NAMES = {
	label: /Name this version|Edit version name/,
	restore: 'Restore version',
	download: 'Download version',
	delete: 'Delete version',
} as const

export type VersionAction = keyof typeof VERSION_ACTION_NAMES

/**
 * Whether a request URL targets the versions DAV collection.
 *
 * @param url - The request URL
 */
function isVersionsRequest(url: string): boolean {
	return /\/dav\/versions\/[^/]+\/versions\//.test(url)
}

/**
 * The "Versions" tab of the files sidebar.
 */
export class VersionsTab {
	constructor(private readonly page: Page) {}

	panel(): Locator {
		return this.page.getByRole('tabpanel', { name: 'Versions' })
	}

	/** All version entries, newest first: index 0 is the current version. */
	versions(): Locator {
		return this.panel().getByRole('list', { name: 'File versions' }).getByRole('listitem')
	}

	/**
	 * A version entry.
	 *
	 * @param index - Position in the list, 0 is the current version
	 */
	version(index: number): Locator {
		return this.versions().nth(index)
	}

	/**
	 * Open the sidebar of a file of the current folder on its Versions tab and
	 * wait for the versions to be listed.
	 *
	 * @param filesList - The files list showing the file
	 * @param name - Name of the file
	 */
	async openFor(filesList: FilesListPage, name: string): Promise<void> {
		await filesList.triggerActionForFile(name, 'details')

		const fetched = this.page.waitForResponse((r) => r.request().method() === 'PROPFIND' && isVersionsRequest(r.url()))
		await this.page.getByRole('tab', { name: 'Versions' }).click()
		await fetched
		await expect(this.panel()).toBeVisible()
	}

	/**
	 * Open the actions menu of a version and return the (teleported) menu.
	 *
	 * The menu toggle can lose its first click while it is still mounting, so
	 * opening is retried until the menu is visible, clicking only while it is
	 * closed so an open menu is never toggled shut again.
	 *
	 * @param index - Position of the version in the list
	 */
	private async openMenu(index: number): Promise<Locator> {
		const toggle = this.menuToggle(index)
		await toggle.scrollIntoViewIfNeeded()

		let menu!: Locator
		await expect(async () => {
			let menuId = await toggle.getAttribute('aria-controls')
			const alreadyOpen = !!menuId && await this.page.locator(`#${menuId}`).isVisible()
			if (!alreadyOpen) {
				await toggle.click()
				menuId = await toggle.getAttribute('aria-controls')
			}
			expect(menuId).toBeTruthy()
			menu = this.page.locator(`#${menuId}`)
			await expect(menu).toBeVisible({ timeout: 2000 })
		}).toPass({ timeout: 15_000 })
		return menu
	}

	/**
	 * Close the actions menu of a version through its toggle. Escape would
	 * close the whole sidebar.
	 *
	 * @param index - Position of the version in the list
	 */
	private async closeMenu(index: number): Promise<void> {
		const toggle = this.menuToggle(index)
		const menuId = await toggle.getAttribute('aria-controls')
		if (menuId && await this.page.locator(`#${menuId}`).isVisible()) {
			await toggle.click()
			await expect(this.page.locator(`#${menuId}`)).toBeHidden()
		}
	}

	private menuToggle(index: number): Locator {
		return this.version(index).getByRole('button', { name: /^Actions for version/ })
	}

	private actionItem(menu: Locator, action: VersionAction): Locator {
		return menu.getByRole('menuitem', { name: VERSION_ACTION_NAMES[action] })
	}

	/**
	 * Restore a version and wait for the restore to be stored.
	 *
	 * @param index - Position of the version in the list
	 */
	async restore(index: number): Promise<void> {
		const restored = this.page.waitForResponse((r) => r.request().method() === 'MOVE' && isVersionsRequest(r.url()))
		const menu = await this.openMenu(index)
		await this.actionItem(menu, 'restore').click()
		expect((await restored).ok()).toBe(true)
	}

	/**
	 * Delete a version and wait for the deletion to be stored.
	 *
	 * @param index - Position of the version in the list
	 */
	async delete(index: number): Promise<void> {
		const deleted = this.page.waitForResponse((r) => r.request().method() === 'DELETE' && isVersionsRequest(r.url()))
		const menu = await this.openMenu(index)
		await this.actionItem(menu, 'delete').click()
		expect((await deleted).ok()).toBe(true)
	}

	/**
	 * Set the label of a version and wait for it to be stored.
	 *
	 * @param index - Position of the version in the list
	 * @param name - The label
	 */
	async nameVersion(index: number, name: string): Promise<void> {
		const labelled = this.page.waitForResponse((r) => r.request().method() === 'PROPPATCH' && isVersionsRequest(r.url()))
		const menu = await this.openMenu(index)
		await this.actionItem(menu, 'label').click()

		const dialog = this.page.getByRole('dialog', { name: 'Name this version' })
		await dialog.getByRole('textbox', { name: 'Version name' }).fill(name)
		await dialog.getByRole('button', { name: 'Save version name' }).click()

		expect((await labelled).ok()).toBe(true)
		await expect(dialog).toBeHidden()
	}

	/**
	 * Fetch the content served by the "Download version" action of a version.
	 *
	 * The link the action points to is fetched with the session of the page
	 * instead of driving a browser download, which is racy to read back.
	 *
	 * @param index - Position of the version in the list
	 */
	async getVersionContent(index: number): Promise<string> {
		const menu = await this.openMenu(index)
		const href = await this.actionItem(menu, 'download').getAttribute('href')
		await this.closeMenu(index)
		expect(href, `version ${index} has no download link`).toBeTruthy()

		const response = await this.page.request.get(href!)
		expect(response.ok()).toBe(true)
		return response.text()
	}

	/**
	 * Assert a version does not offer an action.
	 *
	 * @param index - Position of the version in the list
	 * @param action - The action
	 */
	async expectActionMissing(index: number, action: VersionAction): Promise<void> {
		const menu = await this.openMenu(index)
		await expect(this.actionItem(menu, action)).toHaveCount(0)
		await this.closeMenu(index)
	}
}
