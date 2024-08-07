/**
 * @copyright Copyright (c) 2023 John Molakvoæ <skjnldsv@protonmail.com>
 *
 * @author John Molakvoæ <skjnldsv@protonmail.com>
 *
 * @license AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
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
