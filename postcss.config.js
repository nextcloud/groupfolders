/**
 * SPDX-FileCopyrightText: 2017 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
module.exports = {
	plugins: [
		require("postcss-preset-env")(),
		require('postcss-nested')
	]
};
