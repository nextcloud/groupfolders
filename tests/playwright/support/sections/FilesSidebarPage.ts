/*!
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { Locator, Page } from '@playwright/test'

import { expect } from '@playwright/test'

/**
 * The sidebar of the files app.
 */
export class FilesSidebarPage {
	constructor(private readonly page: Page) {}

	sidebar(): Locator {
		return this.page.locator('#app-sidebar-vue')
	}

	async close(): Promise<void> {
		await this.sidebar().getByRole('button', { name: 'Close sidebar' }).click()
		await expect(this.sidebar()).toBeHidden()
	}
}
