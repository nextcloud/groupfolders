/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import * as React from 'react'
import * as ReactDom from 'react-dom'
import { AppContainer } from 'react-hot-loader'
import { App } from './App.tsx'

// Enable React devtools
window.React = React

function render(Component) {
	ReactDom.render(
		<AppContainer>
			<Component />
		</AppContainer>,
		document.getElementById('groupfolders-root'),
	)
}

document.addEventListener('DOMContentLoaded', function() {
	render(App)

	// Hot Module Replacement API
	if (module.hot) {
		module.hot.accept('./App', () => {
			render(App)
		})
	}
})
