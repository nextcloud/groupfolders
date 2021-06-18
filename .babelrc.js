const babelConfig = require('@nextcloud/babel-config')

babelConfig.presets.push('@babel/preset-react')
babelConfig.plugins.push('react-hot-loader/babel')

module.exports = babelConfig
