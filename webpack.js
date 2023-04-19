const { CleanWebpackPlugin } = require('clean-webpack-plugin');
const path = require('path')
const webpackConfig = require('@nextcloud/webpack-vue-config')
const webpackRules = require('@nextcloud/webpack-vue-config/rules')

webpackConfig.entry.main = path.resolve(path.join('src', 'main.ts'))
webpackConfig.entry.settings = [
	'whatwg-fetch',
	'./src/settings/index.tsx',
]
webpackConfig.entry.files = [
	'./src/files.js',
]

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
