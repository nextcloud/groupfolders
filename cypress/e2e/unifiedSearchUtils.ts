/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/**
 * Searchs for the given string in the unified search modal
 *
 * @param string term the term to search for
 */
export function searchFor(term: string) {
	cy.get('[class="unified-search-input__input"]').type(term)
}

/**
 * Get search results main element
 */
export function getUnifiedSearchResults() {
	return cy.get('#unified-search-results')
}

/**
 * Opens the search results list for a specific section
 *
 * @param string section the section
 */
export function openSearchResultsFor(section: string) {
	getUnifiedSearchResults().contains('button', `More from ${section}`).click()
}

/**
 * Get search results list for the current open section
 */
export function getUnifiedSearchResultsForCurrentOpenSection() {
	return getUnifiedSearchResults().find('[class="result-items"]')
}

/**
 * Get search results footer for the current open section
 */
export function getUnifiedSearchResultsFooterForCurrentOpenSection() {
	return getUnifiedSearchResults().find('[class="result-footer"]')
}

/**
 * Checks that the given result is found in the current open section
 *
 * @param string result the result in the section
 */
export function currentSearchSectionHasResult(result: string) {
	getUnifiedSearchResultsForCurrentOpenSection().contains(result).should('be.visible')
}

/**
 * Checks that more results can be loaded for the current open section
 */
export function currentSearchSectionCanLoadMoreResults() {
	const loadMoreResults = getUnifiedSearchResultsFooterForCurrentOpenSection().contains('Load more results')
	loadMoreResults.scrollIntoView()
	loadMoreResults.should('be.visible')
}
