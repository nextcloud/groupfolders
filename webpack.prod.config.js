'use strict';

const path = require("path");
const CleanPlugin = require('clean-webpack-plugin');
const MiniCssExtractPlugin = require("mini-css-extract-plugin");
const VueLoaderPlugin = require('vue-loader/lib/plugin');

module.exports = {
	devtool: 'source-map',
	mode: 'production',
	entry: {
		settings: [
			`whatwg-fetch`,
			'./js/index.tsx'
		],
		files: [
			'./src/files.js'
		]
	},
	output: {
		path: path.join(__dirname, "build"),
		filename: "[name].js",
		libraryTarget: 'umd',
		publicPath: '/',
		jsonpFunction: 'webpackJsonpGroupFolder'
	},
	resolve: {
		extensions: ['.js', '.jsx', '.ts', '.tsx', '.vue'],
	},
	plugins: [
		new CleanPlugin(),
		new MiniCssExtractPlugin({
			filename: '[name].css',
			chunks: ['app']
		}),
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
				test: /\.vue$/,
				loader: 'vue-loader'
			},
			{
				test: /.*\.(gif|png|jpe?g|svg|webp)(\?.+)?$/i,
				use: [
					'url-loader?limit=5000&hash=sha512&digest=hex&name=[hash].[ext]'
				]
			},
			{
				test: /\.css$/,
				use: [
					'vue-style-loader',
					'css-loader',
					'postcss-loader'
				]
			}
		]
	}
};
