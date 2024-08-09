/// <reference types="@nextcloud/typings" />
/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
declare global {
	interface Window {
		OC: Nextcloud.v29.OC
		OCP: Nextcloud.v29.OCP
		// eslint-disable-next-line @typescript-eslint/no-explicit-any
		OCA: any
	}
	/**
	 * Injected by webpack
	 */
	const appName: string
}

export {}
