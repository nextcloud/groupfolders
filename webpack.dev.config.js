'use strict';

const path = require("path");
const VueLoaderPlugin = require('vue-loader/lib/plugin');

module.exports = {
	devtool: 'source-map',
	mode: 'development',
	entry: {
		settings: [
			'webpack/hot/only-dev-server',
			'./src/settings/index.tsx'
		],
		files: [
			'./src/files.js'
		]
	},
	output: {
		path: path.join(__dirname, "js"),
		filename: "[name].js",
		publicPath: '/',
		jsonpFunction: 'webpackJsonpGroupFolder'
	},
	resolve: {
		extensions: ['.js', '.jsx', '.ts', '.tsx', '.vue'],
	},
	plugins: [new VueLoaderPlugin()],
	module: {
		rules: [
			{
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
			},
			{
				test: /\.vue$/,
				loader: 'vue-loader'
			},
			{
				test: /.*\.(gif|png|jpe?g|svg|webp)(\?.+)?$/i,
				use: [
					{
						loader: 'url-loader',
						options: {
							limit: 5000,
							hash: 'sha512',
							digest: 'hex',
							name: '[hash].[ext]'
						}
					}
				]
			},
			{
				test: /\.css$/,
				use: ['vue-style-loader', 'style-loader', 'css-loader', 'postcss-loader']
			}
		]
	},
	devServer: {
		contentBase: path.resolve(__dirname, './src')
	},
};
