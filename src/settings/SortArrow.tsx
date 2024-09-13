/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import * as React from 'react'

export interface SortArrowProps {
    name: string;
    value: string;
    direction: number;
}

// eslint-disable-next-line jsdoc/require-jsdoc
export function SortArrow({ name, value, direction }: SortArrowProps) {
	if (name === value) {
		return (<span className='sort_arrow'>
			{direction < 0 ? '▼' : '▲'}
		</span>)
	} else {
		return <span/>
	}
}
