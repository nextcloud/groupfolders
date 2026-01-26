/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { initFilesClient } from './client.js'

import 'vite/modulepreload-polyfill'

window.addEventListener('DOMContentLoaded', () => {
	if (!OCA?.Sharing?.ShareTabSections) {
		return
	}
	import(/* webpackChunkName: "sharing" */'./SharingSidebarApp.js').then((Module) => {
		initFilesClient(OC.Files.getClient())
		OCA.Sharing.ShareTabSections.registerSection((el, fileInfo) => {
			if (fileInfo.mountType !== 'group') {
				return
			}
			return Module.default
		})
	})
})
