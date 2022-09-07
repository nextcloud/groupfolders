// SPDX-FileCopyrightText: 2017 Robin Appelman <robin@icewind.nl>
// SPDX-License-Identifier: AGPL-3.0-or-later

'use strict';

import {App} from './App';
import {AppContainer} from 'react-hot-loader';
import React from 'react';
import ReactDom from 'react-dom';

// Enable React devtools
window['React'] = React;

const render = (Component) => {
	ReactDom.render(
		<AppContainer>
			<Component/>
		</AppContainer>,
		document.getElementById('groupfolders-root')
	);
};

$(document).ready(() => {
	render(App);

// Hot Module Replacement API
	if (module.hot) {
		module.hot.accept('./App', () => {
			render(App)
		});
	}
});
