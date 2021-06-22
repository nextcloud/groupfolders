const webpackConfig = require('@nextcloud/webpack-vue-config')
const webpackRules = require('@nextcloud/webpack-vue-config/rules')
const {CleanWebpackPlugin} = require('clean-webpack-plugin');


webpackConfig.entry = {
	settings: [
		'whatwg-fetch',
		'./src/settings/index.tsx'
	],
	files: [
		'./src/files.js'
	]
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
		'ts-loader'
	]
}
webpackConfig.module.rules = Object.values(webpackRules)

module.exports = webpackConfig
