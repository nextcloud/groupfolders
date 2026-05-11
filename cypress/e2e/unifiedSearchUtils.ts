/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/**
 * Get the unified search modal (if open)
 */
export function getUnifiedSearchModal() {
	return cy.get('[role="dialog"][id="unified-search"]')
}

/**
 * Open the unified search modal
 */
export function openUnifiedSearch() {
	cy.get('button[aria-label="Unified search"]').click({ force: true })
	// wait for it to be open
	getUnifiedSearchModal().should('be.visible')
}

/**
 * Searchs for the given string in the unified search modal
 *
 * @param string term the term to search for
 */
export function searchFor(term: string) {
	getUnifiedSearchModal().find('[data-cy-unified-search-input]').type(term)
}

/**
 * Get search results main element
 */
export function getUnifiedSearchResults() {
	return getUnifiedSearchModal().find('[class="unified-search-modal__results"]')
}

/**
 * Get search results list for a specific section
 *
 * @param string section the section
 */
export function getUnifiedSearchResultsForSection(section: string) {
	return getUnifiedSearchResults().contains('[class="result-title"]', section).next('ul')
}

/**
 * Get search results footer for a specific section
 *
 * @param string section the section
 */
export function getUnifiedSearchResultsFooterForSection(section: string) {
	return getUnifiedSearchResults().contains('[class="result-title"]', section).siblings('[class="result-footer"]').first()
}

/**
 * Checks that the given result is found in the given section
 *
 * @param string section the section
 * @param string result the result in the section
 */
export function searchHasResult(section: string, result: string) {
	getUnifiedSearchResultsForSection(section).contains(result).should('be.visible')
}

/**
 * Checks that more results can be loaded for the given section
 *
 * @param string section the section
 */
export function searchCanLoadMoreResults(section: string) {
	getUnifiedSearchResultsFooterForSection(section).contains('Load more results').should('be.visible')
}
