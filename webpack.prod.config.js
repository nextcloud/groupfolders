'use strict';

const path = require("path");
const {CleanWebpackPlugin} = require('clean-webpack-plugin');
const VueLoaderPlugin = require('vue-loader/lib/plugin');

module.exports = {
	devtool: 'source-map',
	mode: 'production',
	entry: {
		settings: [
			`whatwg-fetch`,
			'./src/settings/index.tsx'
		],
		files: [
			'./src/files.js'
		]
	},
	output: {
		path: path.join(__dirname, "js"),
		filename: "[name].js",
		chunkFilename: '[name].js?v=[contenthash]',
		libraryTarget: 'umd',
		publicPath: '/',
		jsonpFunction: 'webpackJsonpGroupFolder'
	},
	resolve: {
		extensions: ['.js', '.jsx', '.ts', '.tsx', '.vue'],
	},
	stats: {
		colors: true,
		errorDetails: true
	},
	plugins: [
		new CleanWebpackPlugin(),
		new VueLoaderPlugin()
	],
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
				test: /\.js$/,
				loader: 'babel-loader',
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
	}
};
