import * as React from 'react';
import {
	Component, InputHTMLAttributes,
	SyntheticEvent
} from 'react';

export interface SubmitInputProps extends InputHTMLAttributes<HTMLInputElement> {
	initialValue?: string;
	onSubmitValue: (value: string) => void;
}

export interface SubmitInputState {
	value: string;
}

export class SubmitInput extends Component<SubmitInputProps, SubmitInputState> {
	state: SubmitInputState = {
		value: ''
	};

	constructor(props: SubmitInputProps) {
		super(props);
		this.state.value = props.initialValue || '';
	}

	onSubmit = (event: SyntheticEvent<any>) => {
		event.preventDefault();
		this.props.onSubmitValue(this.state.value);
	};

	render() {
		const {initialValue, onSubmitValue, ...props} = this.props;

		return <form onSubmit={this.onSubmit}>
			<input type="text" value={this.state.value}
				   {...props}
				   onChange={event => this.setState({value: event.currentTarget.value})}/>
		</form>;
	}
}
