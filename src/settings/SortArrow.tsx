/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import * as React from 'react'
import { t } from '@nextcloud/l10n'

export interface SortArrowProps {
    name: string;
    value: string;
    direction: number;
}

// eslint-disable-next-line jsdoc/require-jsdoc
export function SortArrow({ name, value, direction }: SortArrowProps) {
	if (name !== value) {
		return null
	}

	const label = direction < 0
		? t('groupfolders', 'sorted descending')
		: t('groupfolders', 'sorted ascending')

	return (
		<>
			<span className="sort_arrow" aria-hidden="true">
				{direction < 0 ? '▼' : '▲'}
			</span>
			<span className="hidden-visually">{label}</span>
		</>
	)
}
