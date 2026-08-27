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
registerSubfolderManagementSidebarSection()

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

/**
 * Registers the direct-subfolder administration panel. The server decides
 * whether the selected folder is a managed direct child and whether the user
 * may see or change its manager assignments.
 */
function registerSubfolderManagementSidebarSection() {
	const tagName = 'oca_groupfolders-subfolder_management_sidebar_section'
	const VueComponent = defineAsyncComponent(() => import('./components/SubfolderManagementSidebarView.vue'))
	const WebComponent = defineCustomElement(VueComponent, { shadowRoot: false })
	window.customElements.define(tagName, WebComponent)

	registerSidebarSection({
		id: 'groupfolders-subfolder-management',
		order: 21,
		element: tagName,
		enabled(node) {
			return node.attributes['mount-type'] === 'group' && node.type === 'folder'
		},
	})
}
