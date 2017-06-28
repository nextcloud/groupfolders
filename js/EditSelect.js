import {Component} from 'react';

import './EditSelect.css';

export class EditSelect extends Component {
	state = {
		options: {},
		isEditing: false,
		isValidInput: true
	};

	constructor (props) {
		super(props);
		this.state.value = props.value;
		this.state.options = props.options;
		if (props.value >= 0) {
			const valueText = OC.Util.humanFileSize(props.value);
			this.state.options[valueText] = props.value;
		}
	}

	onSelect = event => {
		const value = event.target.value;
		if (value === 'other') {
			this.setState({isEditing: true});
		} else {
			this.props.onChange(value);
		}
	};

	onEditedValue = (value) => {
		const size = OC.Util.computerFileSize(value);
		if (!size) {
			this.setState({isValidInput: false});
		} else {
			this.setState({isValidInput: true, isEditing: false});
			const options = this.state.options;
			options[OC.Util.humanFileSize(size)] = size;
			this.props.onChange(size);
		}
	};

	render () {
		if (this.state.isEditing) {
			return <input
				onBlur={() => {
					this.setState({isEditing: false})
				}}
				onKeyPress={(e) => {
					(e.key === 'Enter' ? this.onEditedValue(e.target.value) : null)
				}}
				className={'editselect-input' + (this.state.isValidInput ? '' : ' error')}
				autoFocus={true}/>
		} else {
			const options = Object.keys(this.state.options).map((key) => <option
				value={this.state.options[key]} key={key}>{key}</option>);

			return <select className="editselect" onChange={this.onSelect}
						   value={this.props.value}>
				{options}
				<option value="other">{t('groupfolders', 'Other...')}</option>
			</select>
		}
	}
}
