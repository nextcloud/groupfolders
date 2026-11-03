/*!
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { Locator, Page } from '@playwright/test'

import { expect } from '@playwright/test'

/**
 * The unified search of the header: a search field whose results are grouped
 * by provider, each group being a listbox named after its provider.
 */
export class UnifiedSearchPage {
	constructor(private readonly page: Page) {}

	input(): Locator {
		return this.page.getByRole('combobox', { name: 'Apps, files, messages, and more' })
	}

	/**
	 * Search for a term.
	 *
	 * @param term - The term to search for
	 */
	async search(term: string): Promise<void> {
		await this.input().fill(term)
	}

	/**
	 * Show all results of a provider instead of the first few of every provider.
	 *
	 * @param provider - Name of the provider, e.g. "Files"
	 */
	async showMoreFrom(provider: string): Promise<void> {
		await this.page.getByRole('button', { name: `More from ${provider}` }).click()
		await expect(this.page.getByRole('button', { name: 'Back to all results' })).toBeVisible()
	}

	/**
	 * The results of a provider.
	 *
	 * @param provider - Name of the provider, e.g. "Files"
	 */
	results(provider: string): Locator {
		return this.page.getByRole('listbox', { name: provider }).getByRole('option')
	}

	/**
	 * The result of a provider for a file.
	 *
	 * @param provider - Name of the provider, e.g. "Files"
	 * @param title - Title of the result, i.e. the file name
	 */
	result(provider: string, title: string): Locator {
		return this.results(provider).filter({ hasText: title })
	}

	loadMoreButton(): Locator {
		return this.page.getByRole('button', { name: 'Load more results' })
	}
}
