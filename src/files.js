/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { getCSPNonce } from '@nextcloud/auth'
import { linkTo } from '@nextcloud/router'
import { initFilesClient } from './client.js'

__webpack_nonce__ = getCSPNonce()
// eslint-disable-next-line no-undef
__webpack_public_path__ = linkTo('groupfolders', 'js/')

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
