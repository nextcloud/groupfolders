/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
const { CleanWebpackPlugin } = require('clean-webpack-plugin');
const path = require('path')
const webpackConfig = require('@nextcloud/webpack-vue-config')
const webpackRules = require('@nextcloud/webpack-vue-config/rules')

webpackConfig.entry = {
	init: path.resolve(path.join('src', 'init.ts')),
	files: [
		'./src/files.js',
	],
	settings: [
		'whatwg-fetch',
		'./src/settings/index.tsx',
	],
}

webpackConfig.resolve.extensions = [...webpackConfig.resolve.extensions, '.jsx', '.ts', '.tsx']

webpackConfig.plugins.push(new CleanWebpackPlugin())
webpackRules.RULE_TSX = {
	test: /\.tsx?$/,
	use: [
		{
			loader: 'babel-loader',
			options: {
				babelrc: false,
				plugins: ['react-hot-loader/babel'],
			},
		},
		'ts-loader',
	],
}
webpackRules.RULE_RAW = {
	resourceQuery: /raw/,
	type: 'asset/source',
}

webpackConfig.module.rules = Object.values(webpackRules)

module.exports = webpackConfig
