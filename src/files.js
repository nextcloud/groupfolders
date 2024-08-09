/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { generateUrl, imagePath } from '@nextcloud/router'
import { initFilesClient } from './client.js'

// eslint-disable-next-line
__webpack_nonce__ = btoa(OC.requestToken)
// eslint-disable-next-line
__webpack_public_path__ = OC.linkTo('groupfolders', 'js/')

window.addEventListener('DOMContentLoaded', () => {
	if (OCA.Theming) {
		OC.MimeType._mimeTypeIcons['dir-group'] = generateUrl('/apps/theming/img/groupfolders/folder-group.svg?v=' + OCA.Theming.cacheBuster)
	} else {
		OC.MimeType._mimeTypeIcons['dir-group'] = imagePath('groupfolders', 'folder-group')
	}

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
