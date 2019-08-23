module.exports = {
  plugins: [
    '@babel/plugin-transform-arrow-functions',
    'transform-class-properties',
    'react-hot-loader/babel'
  ],
  presets: [
    [
      '@babel/preset-env',
      {
        targets: {
          browsers: ['last 2 versions', 'ie >= 11']
        }
      }
    ],
    '@babel/preset-react'
  ]
}

