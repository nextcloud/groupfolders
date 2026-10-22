/*!
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { isAxiosError } from '@nextcloud/axios'
import { showError } from '@nextcloud/dialogs'
import { logger } from './logger.ts'

/**
 * Read an API error message when the response includes one.
 *
 * @param error - Failure from an API request
 */
function apiErrorMessage(error: unknown): string | undefined {
	if (!isAxiosError(error)) {
		return undefined
	}
	const message = (error.response?.data as { message?: unknown } | undefined)?.message
	return typeof message === 'string' ? message : undefined
}

/**
 * Log a failed API request and tell the user about it.
 *
 * @param error - Failure from an API request
 * @param logMessage - Message for the browser log
 * @param fallback - Translated message shown when the response has none
 */
export function reportApiError(error: unknown, logMessage: string, fallback: string) {
	logger.error(logMessage, { error })
	showError(apiErrorMessage(error) ?? fallback)
}
