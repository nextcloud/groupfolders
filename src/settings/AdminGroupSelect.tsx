/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import * as React from 'react'
import Select from 'react-select'

import { CLASS_NAME_ADMIN_DELEGATION } from '../Constants.js'
import { Component } from 'react'
import { getCurrentUser } from '@nextcloud/auth'
import { Api } from './Api'
import type { DelegationGroup } from '../types'

interface AdminGroupSelectProps {
	groups: DelegationGroup[],
	allGroups: DelegationGroup[],
	delegatedAdminGroups: DelegationGroup[],
}

class AdminGroupSelect extends Component<AdminGroupSelectProps> {

	state: AdminGroupSelectProps = {
		groups: [],
		allGroups: [],
		delegatedAdminGroups: [],
	}

	constructor(props) {
		super(props)
		this.state.groups = props.groups
		this.state.allGroups = props.allGroups
		this.state.delegatedAdminGroups = props.delegatedAdminGroups
	}

	api = new Api()

	componentDidMount() {
		this.api.listGroups().then((groups) => {
			this.setState({ groups })
		})
		this.api.listDelegatedGroups(CLASS_NAME_ADMIN_DELEGATION).then((groups) => {
			this.setState({ delegatedAdminGroups: groups })
		})
	}

	updateDelegatedAdminGroups(options: {value: string, label: string}[]): void {
		if (this.state.groups !== undefined) {
			const groups = options.map(option => {
				return this.state.groups.filter(g => g.gid === option.value)[0]
			})
			this.setState({ delegatedAdminGroups: groups }, () => {
				this.api.updateDelegatedGroups(this.state.delegatedAdminGroups, CLASS_NAME_ADMIN_DELEGATION)
			})
		}
	}

	render() {
		const options = this.state.groups.map(group => {
			return {
				value: group.gid,
				label: group.displayName,
			}
		})

		return <Select
			onChange={ this.updateDelegatedAdminGroups.bind(this) }
			isDisabled={getCurrentUser() ? !getCurrentUser()!.isAdmin : true}
			isMulti
			value={this.state.delegatedAdminGroups.map(group => {
				return {
					value: group.gid,
					label: group.displayName,
				}
			})}
			className="delegated-admins-select"
			options={options}
			placeholder={t('groupfolders', 'Add group')}
			styles={{
				input: (provided) => ({
					...provided,
					height: '30',
					color: 'var(--color-main-text)',
				}),
				control: (provided) => ({
					...provided,
					backgroundColor: 'var(--color-main-background)',
				}),
				menu: (provided) => ({
					...provided,
					backgroundColor: 'var(--color-main-background)',
					borderColor: 'var(--color-border, #888)',
				}),
			}}
		/>
	}

}

export default AdminGroupSelect
