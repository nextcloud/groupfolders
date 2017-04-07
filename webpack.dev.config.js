'use strict';

const webpack = require("webpack");
const path = require("path");

module.exports = {
	devtool: 'source-map',
	entry: {
		app: [
			'react-hot-loader/patch',
			// activate HMR for React

			// 'webpack-dev-server/client?http://localhost:8080',
			// bundle the client for webpack-dev-server
			// and connect to the provided endpoint

			'webpack/hot/only-dev-server',
			// bundle the client for hot reloading
			// only- means to only hot reload for successful updates
			'./js/index.js'
		]
	},
	output: {
		path: path.join(__dirname, "build"),
		filename: "bundle.js",
		publicPath: '/'
	},
	resolve: {
		extensions: ['.js', '.jsx'],
	},
	plugins: [
		new webpack.NamedModulesPlugin()
	],
	module: {
		rules: [
			{
				test: /\.js$/,
				use: ['react-hot-loader/webpack', 'babel-loader']
			},
			{
				test: /\.css$/,
				use: ['style-loader', 'css-loader', 'postcss-loader']
			}
		]
	},
	devServer: {
		contentBase: path.resolve(__dirname, './src')
	},
};
