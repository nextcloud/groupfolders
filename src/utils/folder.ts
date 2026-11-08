/*!
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { Folder } from '../types/index.ts'

import { t } from '@nextcloud/l10n'

const bytesInOneGibibyte = 1024 ** 3

/**
 * Quota presets keyed by their label.
 * Negative values are the special "default" and "unlimited" quotas.
 */
export const quotaPresets: Record<string, number> = {
	[t('groupfolders', 'Default')]: -4,
	'1 GB': bytesInOneGibibyte,
	'5 GB': bytesInOneGibibyte * 5,
	'10 GB': bytesInOneGibibyte * 10,
	[t('groupfolders', 'Unlimited')]: -3,
}

/**
 * Whether this folder is owned by a team.
 *
 * @param folder - Folder to check
 */
export function isTeamSpace(folder: Folder): boolean {
	return folder.managed_by === 'teams'
}
