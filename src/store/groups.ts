/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { DelegationGroup } from '../types/index.ts'

import { t } from '@nextcloud/l10n'
import { defineStore } from 'pinia'
import { ref } from 'vue'
import { Api } from '../services/api.ts'
import { reportApiError } from '../services/apiError.ts'

/**
 * Every Nextcloud group that can be picked in the group folders settings.
 * The list is loaded once per page and shared by every select that renders it.
 */
export const useGroupsStore = defineStore('groups', () => {
	const api = new Api()
	const groups = ref<DelegationGroup[]>([])
	// Stays false after a failed load so the next call tries again.
	const loaded = ref(false)
	let inflight: Promise<void> | null = null

	/**
	 * Load groups when they are not already cached.
	 * Callers that overlap share one request.
	 */
	function fetchGroups(): Promise<void> {
		if (loaded.value) {
			return Promise.resolve()
		}
		if (inflight) {
			return inflight
		}

		inflight = api.listGroups()
			.then(result => {
				groups.value = result
				loaded.value = true
			})
			.catch(error => {
				reportApiError(error, 'Error while loading groups', t('groupfolders', 'Groups could not be loaded'))
			})
			.finally(() => {
				inflight = null
			})
		return inflight
	}

	return {
		groups,
		fetchGroups,
	}
})
