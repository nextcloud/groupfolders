import * as React from 'react';
import Select from 'react-select';
import {getCurrentUser} from '@nextcloud/auth';
import {Component} from 'react';
import {Group, Api} from './Api';

interface GroupSelectProps {
	groups: Group[],
	allGroups: Group[],
	delegatedAdminGroups: Group[],
}

class GroupSelect extends Component<GroupSelectProps> {

	state: GroupSelectProps = {
		groups: [],
		allGroups: [],
		delegatedAdminGroups: [],
	}

	constructor (props) {
		super(props)
		this.state.groups = props.groups
		this.state.allGroups = props.allGroups
		this.state.delegatedAdminGroups = props.delegatedAdminGroups
	}

	api = new Api()

	componentDidMount() {
		this.api.listGroups().then((groups) => {
			this.setState({groups});
		});
		this.api.listDelegatedAdmins().then((groups) => {
			this.setState({delegatedAdminGroups: groups});
		});
	}

	updateDelegatedAdminGroups(options: {value: string, label: string}[]): void {
		if (this.state.groups !== undefined) {
			const groups = options.map(option => {
				return this.state.groups.filter(g => g.id === option.value)[0];
			});
			this.setState({delegatedAdminGroups: groups}, () => {
				this.api.updateDelegatedAdminGroups(this.state.delegatedAdminGroups);
			});			
		}
	}

	render () {
		const options = this.state.groups.map(group => {
			return {
				value: group.id,
				label: group.displayname
			};
		});

		return <Select
			onChange={ this.updateDelegatedAdminGroups.bind(this) }
			isDisabled={getCurrentUser() ? !getCurrentUser()!.isAdmin : true}
			isMulti
			value={this.state.delegatedAdminGroups.map(group => {
				return {
					value: group.id,
					label: group.displayname
				};
			})}
			className="delegated-admins-select"
			options={options}
			placeholder={t('groupfolders', 'Add group')}
			styles={{
				input: (provided) => ({
					...provided,
					height: 30
				}),
				control: (provided) => ({
					...provided,
					backgroundColor: 'var(--color-main-background)'
				}),
				menu: (provided) => ({
					...provided,
					backgroundColor: 'var(--color-main-background)',
					borderColor: '#888'
				})
			}}
		/>
	}
}

// function GroupSelectFunc({allGroups, delegatedAdminGroups, onChange}: GroupSelectProps) {

// }

export default GroupSelect