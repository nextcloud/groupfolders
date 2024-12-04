/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import * as React from 'react'
import Select from 'react-select'
import { getCurrentUser } from '@nextcloud/auth'
import { Component } from 'react'
import { Api } from './Api'
import type { DelegationGroup } from '../types'
import { CLASS_NAME_SUBADMIN_DELEGATION } from '../Constants.js'

interface SubAdminGroupSelectProps {
	groups: DelegationGroup[],
	allGroups: DelegationGroup[],
	delegatedSubAdminGroups: DelegationGroup[],
}

class SubAdminGroupSelect extends Component<SubAdminGroupSelectProps> {

	state: SubAdminGroupSelectProps = {
		groups: [],
		allGroups: [],
		delegatedSubAdminGroups: [],
	}

	constructor(props) {
		super(props)
		this.state.groups = props.groups
		this.state.allGroups = props.allGroups
		this.state.delegatedSubAdminGroups = props.delegatedSubAdminGroups
	}

	api = new Api()

	componentDidMount() {
		this.api.listGroups().then((groups) => {
			this.setState({ groups })
		})
		this.api.listDelegatedGroups(CLASS_NAME_SUBADMIN_DELEGATION).then((groups) => {
			this.setState({ delegatedSubAdminGroups: groups })
		})
	}

	updateDelegatedSubAdminGroups(options: {value: string, label: string}[]): void {
		if (this.state.groups !== undefined) {
			const groups = options.map(option => {
				return this.state.groups.filter(g => g.gid === option.value)[0]
			})
			this.setState({ delegatedSubAdminGroups: groups }, () => {
				this.api.updateDelegatedGroups(this.state.delegatedSubAdminGroups, CLASS_NAME_SUBADMIN_DELEGATION)
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
			onChange={ this.updateDelegatedSubAdminGroups.bind(this) }
			isDisabled={getCurrentUser() ? !getCurrentUser()!.isAdmin : true}
			isMulti
			value={this.state.delegatedSubAdminGroups.map(group => {
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

export default SubAdminGroupSelect
