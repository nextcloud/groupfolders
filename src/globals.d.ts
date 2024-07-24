/// <reference types="@nextcloud/typings" />

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
