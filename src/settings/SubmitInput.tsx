/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import * as React from 'react'
import {
	Component, InputHTMLAttributes,
	SyntheticEvent,
} from 'react'

export interface SubmitInputProps extends InputHTMLAttributes<HTMLInputElement> {
	initialValue?: string;
	onSubmitValue: (value: string) => void;
}

export interface SubmitInputState {
	value: string;
}

export class SubmitInput extends Component<SubmitInputProps, SubmitInputState> {

	state: SubmitInputState = {
		value: '',
	}

	constructor(props: SubmitInputProps) {
		super(props)
		this.state.value = props.initialValue || ''
	}

	onSubmit = (event: SyntheticEvent<unknown>) => {
		event.preventDefault()
		this.props.onSubmitValue(this.state.value)
	}

	render() {
		return <form onSubmit={this.onSubmit}>
			<input type="text" value={this.state.value}
				   {...this.props}
				   onChange={event => this.setState({ value: event.currentTarget.value })}/>
		</form>
	}

}
