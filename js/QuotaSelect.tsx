import * as React from 'react';
import {Component} from 'react';

import './EditSelect.css';

export interface QuotaSelectProps {
	options: { [name: string]: number };
	value: number;
	size: number;
	onChange: (quota: number) => void;
}

export interface QuotaSelectState {
	options: { [name: string]: number };
	isEditing: boolean;
	isValidInput: boolean;
}

export class QuotaSelect extends Component<QuotaSelectProps, QuotaSelectState> {
	state: QuotaSelectState = {
		options: {},
		isEditing: false,
		isValidInput: true
	};

	constructor(props) {
		super(props);
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

	getUsedPercentage() {
		if (this.props.value >= 0) {
			return Math.min((this.props.size / this.props.value) * 100, 100);
		} else {
			const usedInGB = this.props.size / (10 * Math.pow(2, 30));
			//asymptotic curve approaching 50% at 10GB to visualize used stace with infinite quota
			return 95 * (1 - (1 / (usedInGB + 1)));
		}
	}

	render() {
		if (this.state.isEditing) {
			return <input
				onBlur={() => {
					this.setState({isEditing: false})
				}}
				onKeyPress={(e) => {
					(e.key === 'Enter' ? this.onEditedValue((e.target as HTMLInputElement).value) : null)
				}}
				className={'editselect-input' + (this.state.isValidInput ? '' : ' error')}
				autoFocus={true}/>
		} else {
			const usedPercentage = this.getUsedPercentage();
			const humanSize = OC.Util.humanFileSize(this.props.size);
			const options = Object.keys(this.state.options).map((key) => <option
				value={this.state.options[key]} key={key}>{key}</option>);

			return <div className="quotabar-holder">
				<div className="quotabar"
					 style={{width: usedPercentage + '%'}}/>
				<select className="editselect"
						onChange={this.onSelect}
						ref={(ref) => {
							ref && $(ref).tooltip({
								title: t('settings', '{size} used', {size: humanSize}, 0, {escape: false}).replace('&lt;', '<'),
								delay: {
									show: 100,
									hide: 0
								}
							});
						}}
						value={this.props.value}>
					{options}
					<option value="other">
						{t('groupfolders', 'Other …')}
					</option>
				</select>
			</div>
		}
	}
}
