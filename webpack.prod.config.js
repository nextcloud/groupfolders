'use strict';

const webpack = require("webpack");
const path = require("path");
const ExtractTextPlugin = require('extract-text-webpack-plugin');
const CleanPlugin = require('clean-webpack-plugin');

module.exports = {
	devtool: 'source-map',
	entry: {
		app: [
			`babel-polyfill`,
			`whatwg-fetch`,
			'./js/index.js'
		],
	},
	output: {
		path: path.join(__dirname, "build"),
		filename: "bundle.js",
		libraryTarget: 'umd',
		publicPath: '/'
	},
	resolve: {
		extensions: ['.js', '.jsx', '.ts', '.tsx'],
	},
	plugins: [
		new CleanPlugin(['build']),
		new ExtractTextPlugin({
			filename: 'bundle.css',
			allChunks: true
		}),
		new webpack.NoEmitOnErrorsPlugin(),
		new webpack.optimize.OccurrenceOrderPlugin(),
		new webpack.optimize.UglifyJsPlugin(),
		new webpack.DefinePlugin({
			'process.env': {
				NODE_ENV: JSON.stringify('production')
			}
		}),
	],
	module: {
		rules: [
			{
				test: /\.tsx?$/,
				use: ['ts-loader']
			},
			{
				test: /.*\.(gif|png|jpe?g|svg|webp)(\?.+)?$/i,
				use: [
					'url-loader?limit=5000&hash=sha512&digest=hex&name=[hash].[ext]'
				]
			},
			{
				test: /\.js$/,
				use: ['babel-loader']
			},
			{
				test: /\.css$/,
				use: ExtractTextPlugin.extract({
					fallback: "style-loader",
					use: ['css-loader', 'postcss-loader']
				})
			}
		]
	}
};
