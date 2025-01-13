/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import type { Node } from '@nextcloud/files'

import { FileAction, DefaultType } from '@nextcloud/files'
import { t } from '@nextcloud/l10n'

export const action = new FileAction({
	id: 'open-group-folders',
	displayName: () => t('files', 'Open Team folder'),
	iconSvgInline: () => '',

	enabled: (files, view) => view.id === appName,

	async exec(node: Node) {
		const dir = node.attributes.mountPoint
		window.OCP.Files.Router.goToRoute(
			null, // use default route
			{ view: 'files' },
			{ dir },
		)
		return null as unknown as boolean
	},

	default: DefaultType.DEFAULT,
	// Before openFolderAction
	order: -1000,
})
