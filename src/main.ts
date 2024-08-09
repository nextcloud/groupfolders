/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
/* eslint-disable */
import { translate as t } from '@nextcloud/l10n'
import { View, getNavigation } from '@nextcloud/files'
import GroupFolderSvg from '../img/app-dark.svg?raw'
import { getContents } from './services/groupfolders'
import './actions/openGroupfolderAction'

const Navigation = getNavigation()
Navigation.register(new View({
	id: appName,
	name: t('groupfolders', 'Group folders'),
	caption: t('groupfolders', 'List of group folders.'),

	emptyTitle: t('groupfolders', 'No group folders yet'),
	emptyCaption: t('groupfolders', 'Group folders will show up here'),

	icon: GroupFolderSvg,
	order: 20,

	columns: [],

	getContents,
}))
