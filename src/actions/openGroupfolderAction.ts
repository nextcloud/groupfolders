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
		const dir = nodes[0].attributes.mountPoint
		window.OCP.Files.Router.goToRoute(
			null, // use default route
			{ view: 'files' },
			{ dir },
		)
		return null
	},

	default: DefaultType.DEFAULT,
	// Before openFolderAction
	order: -1000,
}
