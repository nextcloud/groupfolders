'use strict';

const path = require("path");

module.exports = {
	devtool: 'source-map',
	mode: 'development',
	entry: {
		app: [
			'webpack/hot/only-dev-server',
			'./js/index.js'
		]
	},
	output: {
		path: path.join(__dirname, "build"),
		filename: "bundle.js",
		publicPath: '/'
	},
	resolve: {
		extensions: ['.js', '.jsx', '.ts', '.tsx'],
	},
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
				use: ['style-loader', 'css-loader', 'postcss-loader']
			}
		]
	},
	devServer: {
		contentBase: path.resolve(__dirname, './src')
	},
};
