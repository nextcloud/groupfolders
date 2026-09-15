/*!
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { t } from '@nextcloud/l10n'
import { View, getNavigation, registerFileAction } from '@nextcloud/files'
import { registerSidebarSection } from '@nextcloud/sharing/ui'
import { defineAsyncComponent, defineCustomElement } from 'vue'
import { action as openGroupfolderAction} from './actions/openGroupfolderAction'
import { getContents } from './services/groupfolders'
import GroupFolderSvg from '../img/app-dark.svg?raw'

import 'vite/modulepreload-polyfill'

registerFilesView()
registerFileAction(openGroupfolderAction)
registerSharingSidebarSection()

/**
 * Registers the Groupfolders view in the Nextcloud Files app navigation.
 */
function registerFilesView() {
	const Navigation = getNavigation()
	Navigation.register(new View({
		id: appName,
		name: t('groupfolders', 'Team folders'),
		caption: t('groupfolders', 'List of Team folders.'),

		emptyTitle: t('groupfolders', 'No Team folders yet'),
		emptyCaption: t('groupfolders', 'Team folders will show up here'),

		icon: GroupFolderSvg,
		order: 20,

		columns: [],

		getContents,
	}))
}

/**
 * Registers the Groupfolders sharing sidebar section.
 */
function registerSharingSidebarSection() {
	const tagName = 'oca_groupfolders-sharing_sidebar_section'
	const VueComponent = defineAsyncComponent(() => import('./components/SharingSidebarView.vue'))
	const WebComponent = defineCustomElement(VueComponent, { shadowRoot: false })
	window.customElements.define(tagName, WebComponent)

	registerSidebarSection({
		id: 'groupfolders',
		order: 20,
		element: tagName,
		enabled(node) {
			return node.attributes['mount-type'] === 'group'
		},
	})
}
