/**
 * @copyright Copyright (c) 2018 Julius Härtl <jus@bitgrid.net>
 *
 * @author Cyrille Bollu <cyr.debian@bollu.be> for Arawa (https://www.arawa.fr/)
 * @author Baptiste Fotia <baptiste.fotia@hotmail.com> for Arawa (https://arawa.fr)
 *
 * @license GNU AGPL version 3 or any later version
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU Affero General Public License as
 *  published by the Free Software Foundation, either version 3 of the
 *  License, or (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU Affero General Public License for more details.
 *
 *  You should have received a copy of the GNU Affero General Public License
 *  along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

import * as React from 'react';
import Select from 'react-select';
import {getCurrentUser} from '@nextcloud/auth';
import {Component} from 'react';
import {Group, Api} from './Api';
import {CLASS_NAME_ADMIN_DELEGATION} from '../Constants.js'

interface AdminGroupSelectProps {
	groups: Group[],
	allGroups: Group[],
	delegatedAdminGroups: Group[],
}

class AdminGroupSelect extends Component<AdminGroupSelectProps> {

	state: AdminGroupSelectProps = {
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
		this.api.listDelegatedGroups(CLASS_NAME_ADMIN_DELEGATION).then((groups) => {
			this.setState({delegatedAdminGroups: groups});
		});
	}

	updateDelegatedAdminGroups(options: {value: string, label: string}[]): void {
		if (this.state.groups !== undefined) {
			const groups = options.map(option => {
				return this.state.groups.filter(g => g.gid === option.value)[0];
			});
			this.setState({delegatedAdminGroups: groups}, () => {
				this.api.updateDelegatedGroups(this.state.delegatedAdminGroups, CLASS_NAME_ADMIN_DELEGATION);
			});			
		}
	}

	render () {
		const options = this.state.groups.map(group => {
			return {
				value: group.gid,
				label: group.displayName
			};
		});

		return <Select
			onChange={ this.updateDelegatedAdminGroups.bind(this) }
			isDisabled={getCurrentUser() ? !getCurrentUser()!.isAdmin : true}
			isMulti
			value={this.state.delegatedAdminGroups.map(group => {
				return {
					value: group.gid,
					label: group.displayName
				};
			})}
			className="delegated-admins-select"
			options={options}
			placeholder={t('groupfolders', 'Add group')}
			styles={{
				input: (provided) => ({
					...provided,
					height: '30'
				}),
				control: (provided) => ({
					...provided,
					backgroundColor: 'var(--color-main-background)'
				}),
				menu: (provided) => ({
					...provided,
					backgroundColor: 'var(--color-main-background)',
					borderColor: 'var(--color-border, #888)'
				})
			}}
		/>
	}
}

export default AdminGroupSelect