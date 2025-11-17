/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import react from '@eslint-react/eslint-plugin'
import { recommendedVue2 } from '@nextcloud/eslint-config'
import cypress from 'eslint-plugin-cypress'
import { defineConfig } from 'eslint/config'

export default defineConfig([
	cypress.configs.recommended,
	...recommendedVue2,
	{
		...react.configs.recommended,
		files: ['**/*.jsx'],
	},
	{
		...react.configs['recommended-typescript'],
		files: ['**/*.tsx'],
	},

	{
		files: ['cypress/**', '**/*.cy.*'],
		rules: {
			'jsdoc/require-jsdoc': 'off',
		},
	},

	{
		ignores: ['src/types/openapi/*.ts'],
	},

])
