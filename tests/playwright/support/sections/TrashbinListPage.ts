/*!
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { Locator } from '@playwright/test'

import { expect } from '@playwright/test'
import { DAV_TRASHBIN_ENDPOINT } from '../utils/dav.ts'
import { escapeAttributeValue } from '../utils/selectors.ts'
import { FilesListPage } from './FilesListPage.ts'

/**
 * The "Deleted files" view of the files app.
 *
 * Trashed nodes are stored as `<name>.d<deletion time>`, which is what their
 * rows are keyed by, so rows are matched by that prefix. The tests never trash
 * two nodes of the same name, which keeps the prefix unambiguous.
 */
export class TrashbinListPage extends FilesListPage {
	/** Open the trash bin. */
	async openTrashbin(): Promise<void> {
		await this.open('trashbin')
	}

	/**
	 * The row of a trashed node.
	 *
	 * @param name - Name the node had before it was deleted
	 */
	getTrashedRow(name: string): Locator {
		return this.page.locator(`[data-cy-files-list-row-name^="${escapeAttributeValue(`${name}.d`)}"]`)
	}

	/**
	 * Open a trashed folder.
	 *
	 * @param name - Name the folder had before it was deleted
	 */
	async openTrashedFolder(name: string): Promise<void> {
		await this.openFolderOfRow(this.getTrashedRow(name))
	}

	/**
	 * Restore a trashed node and wait for it to be gone from the trash bin.
	 *
	 * @param name - Name the node had before it was deleted
	 */
	async restore(name: string): Promise<void> {
		await this.trashAction(name, 'restore', 'MOVE')
	}

	/**
	 * Delete a trashed node permanently and wait for it to be gone from the trash bin.
	 *
	 * @param name - Name the node had before it was deleted
	 */
	async deletePermanently(name: string): Promise<void> {
		await this.trashAction(name, 'delete', 'DELETE')
	}

	/**
	 * Trigger an action on a trashed node and wait for its request and for the row to be gone.
	 *
	 * @param name - Name the node had before it was deleted
	 * @param actionId - Id of the file action
	 * @param method - HTTP method of the request the action sends
	 */
	private async trashAction(name: string, actionId: string, method: string): Promise<void> {
		const row = this.getTrashedRow(name)
		const done = this.page.waitForResponse((r) => r.request().method() === method && DAV_TRASHBIN_ENDPOINT.test(r.url()))

		await this.triggerActionForRow(row, actionId)

		expect((await done).ok()).toBe(true)
		await expect(row).toHaveCount(0)
	}
}
