/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
module.exports = {
	extends: [
		'@nextcloud/eslint-config/typescript',
	],
	overrides: [
		{
			files: ['src/types/openapi/*.ts'],
			rules: {
				'@typescript-eslint/no-explicit-any': 'off',
				quotes: 'off',
				'no-multiple-empty-lines': 'off',
				'no-use-before-define': 'off',
			},
		},
	],
}
