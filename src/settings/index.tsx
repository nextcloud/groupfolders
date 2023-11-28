'use strict'

import { App } from './App'
import { AppContainer } from 'react-hot-loader'
import * as React from 'react'
import * as ReactDom from 'react-dom'

// Enable React devtools
window.React = React

const render = (Component) => {
	ReactDom.render(
		/* @ts-expect-error Typescript error due to async react component */
		<AppContainer>
			{/* @ts-expect-error Typescript error due to async react component */}
			<Component/>
		</AppContainer>,
		document.getElementById('groupfolders-root'),
	)
}

$(document).ready(() => {
	render(App)

	// Hot Module Replacement API
	if (module.hot) {
		module.hot.accept('./App', () => {
			render(App)
		})
	}
})
