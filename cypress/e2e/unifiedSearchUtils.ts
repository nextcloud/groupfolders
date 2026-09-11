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
	getUnifiedSearchResults().contains('button', `More from ${section}`, { timeout: 5000 }).should('be.visible').click()
	getUnifiedSearchResults().contains('button', 'Back', { timeout: 5000 }).should('be.visible')
	getUnifiedSearchResultsForCurrentOpenSection().find('.result-item').should('have.length.greaterThan', 3)
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
 * Checks that the given file result is found in the current open section
 *
 * @param string fileName the file name in the result
 * @param string path the path in the result
 */
export function currentSearchSectionHasFileResult(fileName: string, path: string) {
	getUnifiedSearchResultsForCurrentOpenSection()
		.contains('.result-item', fileName, { timeout: 5000 })
		.should('be.visible')
		.and('contain', path)
}

/**
 * Checks that more results can be loaded for the current open section
 */
export function currentSearchSectionCanLoadMoreResults() {
	const loadMoreResults = getUnifiedSearchResultsFooterForCurrentOpenSection().contains('Load more results')
	loadMoreResults.should('exist').and('not.be.disabled')
}
