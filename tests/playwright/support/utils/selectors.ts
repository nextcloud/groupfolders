/*!
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/**
 * Escape a value for use inside a double-quoted CSS attribute selector, so
 * names containing quotes or backslashes do not break the selector.
 * (`CSS.escape` is a browser API, not available in Node.)
 *
 * @param value - The attribute value to escape
 */
export function escapeAttributeValue(value: string): string {
	return value.replace(/\\/g, '\\\\').replace(/"/g, '\\"')
}
