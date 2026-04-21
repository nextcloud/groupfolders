/*!
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { IFileAction } from '@nextcloud/files'

import { DefaultType } from '@nextcloud/files'
import { t } from '@nextcloud/l10n'

export const action: IFileAction = {
	id: 'open-group-folders',
	displayName: () => t('files', 'Open Team folder'),
	iconSvgInline: () => '',

	enabled: ({ view }) => view.id === appName,

	async exec({ nodes }) {
		try {
			await window.OCP.Files.Router.goToRoute(
				null, // use default route
				{ view: 'files', fileid: nodes[0].id },
				{ dir: nodes[0].attributes.mountPoint },
			)
			return true
		} catch (e) {
			// Vue Router throws on duplicated/redirected navigations; those are not
			// real failures from the user's perspective — the target view is reached.
			const name = (e as { name?: string })?.name
			const message = (e as { message?: string })?.message ?? ''
			if (name === 'NavigationDuplicated' || /Redirected/.test(message)) {
				return true
			}
			throw e
		}
	},

	default: DefaultType.DEFAULT,
	// Before openFolderAction
	order: -1000,
}
