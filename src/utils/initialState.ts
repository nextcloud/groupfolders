/*!
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { loadState } from '@nextcloud/initial-state'

export const isCirclesEnabled = loadState('groupfolders', 'isCirclesEnabled', false)

export const isAdminNextcloud = loadState('groupfolders', 'isAdminNextcloud', false)

export const checkAppsInstalled = loadState('groupfolders', 'checkAppsInstalled', false)
