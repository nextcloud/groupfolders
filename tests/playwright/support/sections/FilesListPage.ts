/*!
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { Locator, Page } from '@playwright/test'

import { expect } from '@playwright/test'
import { DAV_FILES_ENDPOINT } from '../utils/dav.ts'
import { escapeAttributeValue } from '../utils/selectors.ts'

/**
 * The file list of the files app.
 */
export class FilesListPage {
	constructor(public readonly page: Page) {}

	/**
	 * Open a view of the files app, the "All files" list by default.
	 *
	 * @param viewId - Id of the view to open, e.g. "trashbin"
	 */
	async open(viewId?: string): Promise<void> {
		await this.page.goto(viewId ? `apps/files/${viewId}` : 'apps/files')
		await this.waitForListSettled()
	}

	/**
	 * Open a folder of the "All files" list directly by its path.
	 *
	 * @param path - Path of the folder relative to the user's root, e.g. "/folder/subfolder"
	 */
	async openFolder(path: string): Promise<void> {
		await this.page.goto(`apps/files/files?dir=${encodeURIComponent(path)}`)
		await this.waitForListSettled()
	}

	/**
	 * The list's loading indicator: in the list header ("File list is
	 * reloading") when the folder already has contents to keep showing, in
	 * place of the list ("Loading current folder") when it does not.
	 */
	getLoadingIndicator(): Locator {
		return this.page.getByRole('img', { name: /^(File list is reloading|Loading current folder)$/ })
	}

	/**
	 * Wait for a pending list fetch to settle, i.e. for the loading indicator to
	 * come and go.
	 *
	 * The indicator is only waited for briefly: a fetch that resolves before the
	 * first poll is never observed as visible, and since the app raises the
	 * loading state synchronously with the action that triggers the fetch, an
	 * absent indicator means the fetch has already finished.
	 */
	async waitForListLoaded(): Promise<void> {
		const indicator = this.getLoadingIndicator()
		await indicator.waitFor({ state: 'visible', timeout: 2000 }).catch(() => {})
		await indicator.waitFor({ state: 'hidden' })
	}

	/**
	 * Wait until the current folder is listed completely: either the list with
	 * its summary footer or the empty folder placeholder is shown.
	 *
	 * Await this before asserting that a row is missing, otherwise the
	 * assertion passes on a list that simply did not render yet.
	 */
	async waitForListSettled(): Promise<void> {
		await this.waitForListLoaded()
		await expect(this.page.locator('[data-cy-files-list-tfoot], [data-cy-files-content-empty]').first())
			.toBeVisible({ timeout: 20_000 })
	}

	/** All rows currently rendered in the list. */
	getRows(): Locator {
		return this.page.locator('[data-cy-files-list-row-fileid]')
	}

	/**
	 * The row of a file or folder of the current folder.
	 *
	 * @param name - Name of the file or folder
	 */
	getRowForFile(name: string): Locator {
		return this.page.locator(`[data-cy-files-list-row-name="${escapeAttributeValue(name)}"]`)
	}

	/**
	 * Open a folder of the current folder through its name in the list, and
	 * wait until it is listed.
	 *
	 * @param row - The row of the folder
	 */
	async openFolderOfRow(row: Locator): Promise<void> {
		const link = row.locator('[data-cy-files-list-row-name-link]')
		await expect(link).toBeVisible()
		await link.click()
		await this.waitForListSettled()
	}

	/** The breadcrumbs navigation ("All files › folder › …"). */
	getBreadcrumbs(): Locator {
		return this.page.getByRole('navigation', { name: 'Current directory path' })
	}

	/**
	 * Trigger a row action wherever it renders: as an inline button next to the
	 * actions menu button, or as an entry of that menu. Inline actions move into
	 * the menu once the list runs out of room for the name column, so where an
	 * action renders depends on the view's columns and the viewport.
	 *
	 * @param row - The row of the file or folder
	 * @param actionId - Id of the file action, e.g. "details"
	 */
	async triggerActionForRow(row: Locator, actionId: string): Promise<void> {
		await expect(row).toBeVisible()
		await row.hover()

		// Inline buttons and the menu button render in the same pass, so once any
		// button of the actions cell is visible the inline button is there if it
		// renders at all
		await expect(row.locator('[data-cy-files-list-row-actions] button').first()).toBeVisible()

		const inlineButton = row.locator(`button[data-cy-files-list-row-action="${escapeAttributeValue(actionId)}"]`)
		if (await inlineButton.isVisible()) {
			await inlineButton.click()
			return
		}

		const menu = await this.openActionsMenuForRow(row)
		// NcActionButton renders a <button role="menuitem"> inside the tagged <li>
		const entry = menu.locator(`[data-cy-files-list-row-action="${escapeAttributeValue(actionId)}"] button`)
		await entry.click()
	}

	/**
	 * Trigger a row action of a file or folder of the current folder.
	 *
	 * @param name - Name of the file or folder
	 * @param actionId - Id of the file action, e.g. "details"
	 */
	async triggerActionForFile(name: string, actionId: string): Promise<void> {
		await this.triggerActionForRow(this.getRowForFile(name), actionId)
	}

	/**
	 * Open the actions menu of a row and return the (teleported) menu.
	 *
	 * A row's NcActions can still be (re-)mounting right after the list
	 * rendered, so the first click may be lost while `aria-controls` is already
	 * set. Opening is retried until the menu is visible, clicking only while it
	 * is closed so an open menu is never toggled shut again.
	 *
	 * @param row - The row of the file or folder
	 */
	private async openActionsMenuForRow(row: Locator): Promise<Locator> {
		const actionsButton = row.getByRole('button', { name: 'Actions' })
		await actionsButton.scrollIntoViewIfNeeded()

		let menu!: Locator
		await expect(async () => {
			let menuId = await actionsButton.getAttribute('aria-controls')
			const alreadyOpen = !!menuId && await this.page.locator(`#${menuId}`).isVisible()
			if (!alreadyOpen) {
				// force: the sticky list header can overlap the button
				await actionsButton.click({ force: true })
				menuId = await actionsButton.getAttribute('aria-controls')
			}
			expect(menuId).toBeTruthy()
			menu = this.page.locator(`#${menuId}`)
			await expect(menu).toBeVisible({ timeout: 2000 })
		}).toPass({ timeout: 15_000 })
		return menu
	}

	/**
	 * Rename a file or folder through its row action and wait for the rename to
	 * be stored and listed.
	 *
	 * @param name - Current name of the file or folder
	 * @param newName - New name
	 */
	async rename(name: string, newName: string): Promise<void> {
		const moved = this.page.waitForResponse((r) => r.request().method() === 'MOVE' && DAV_FILES_ENDPOINT.test(r.url()))

		await this.triggerActionForFile(name, 'rename')
		const input = this.getRowForFile(name).getByRole('textbox')
		await input.fill(newName)
		await input.press('Enter')

		expect((await moved).ok()).toBe(true)
		await expect(this.getRowForFile(newName)).toBeVisible()
	}
}
