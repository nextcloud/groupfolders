/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
const babelConfig = require('@nextcloud/babel-config')

babelConfig.presets.push('@babel/preset-react')
babelConfig.plugins.push('react-hot-loader/babel')

module.exports = babelConfig
