import * as React from 'react';
import './FolderGroups.scss';
import {SyntheticEvent} from "react";
import {Group} from "./Api";
import Select from 'react-select'

function hasPermissions(value: number, check: number): boolean {
	return (value & check) === check;
}

export interface FolderGroupsProps {
	groups: { [group: string]: number },
	allGroups?: Group[],
	onAddGroup: (name: string) => void;
	removeGroup: (name: string) => void;
	edit: boolean;
	showEdit: (event: SyntheticEvent<any>) => void;
	onSetPermissions: (name: string, permissions: number) => void;
}

export function FolderGroups({groups, allGroups = [], onAddGroup, removeGroup, edit, showEdit, onSetPermissions}: FolderGroupsProps) {
	if (edit) {
		const setPermissions = (change: number, groupId: string): void => {
			const newPermissions = groups[groupId] ^ change;
			onSetPermissions(groupId, newPermissions);
		};

		const rows = Object.keys(groups).map(groupId => {
			const permissions = groups[groupId];
			return <tr key={groupId}>
				<td>
					{(
						allGroups
							.find(group => group.gid === groupId) || {
							id: groupId,
							displayName: groupId
						}
					).displayName
					}
				</td>
				<td className="permissions">
					<input type="checkbox"
						   onChange={setPermissions.bind(null, OC.PERMISSION_UPDATE | OC.PERMISSION_CREATE, groupId)}
						   checked={hasPermissions(permissions, (OC.PERMISSION_UPDATE | OC.PERMISSION_CREATE))}/>
				</td>
				<td className="permissions">
					<input type="checkbox"
						   onChange={setPermissions.bind(null, OC.PERMISSION_SHARE, groupId)}
						   checked={hasPermissions(permissions, OC.PERMISSION_SHARE)}/>
				</td>
				<td className="permissions">
					<input type="checkbox"
						   onChange={setPermissions.bind(null, OC.PERMISSION_DELETE, groupId)}
						   checked={hasPermissions(permissions, (OC.PERMISSION_DELETE))}/>
				</td>
				<td>
					<a onClick={removeGroup.bind(this, groupId)} className="close-btn"></a>
				</td>
			</tr>
		});

		return <table className="group-edit"
					  onClick={event => event.stopPropagation()}>
			<thead>
			<tr>
				<th>Group</th>
				<th>Write</th>
				<th>Share</th>
				<th>Delete</th>
				<th/>
			</tr>
			</thead>
			<tbody>
			{rows}
			<tr>
				<td colSpan={5}>
					<AdminGroupSelect
						allGroups={allGroups.filter(i => !groups[i.gid])}
						onChange={onAddGroup}/>
				</td>
			</tr>
			</tbody>
		</table>
	} else {
		if (Object.keys(groups).length === 0) {
			return <span>
				<em>none</em>
				<a className="icon icon-rename" onClick={showEdit}/>
			</span>
		}
		return <a className="action-rename" onClick={showEdit}>
			{Object.keys(groups)
				.map(groupId => allGroups.find(group => group.gid === groupId) || {
					id: groupId,
					displayName: groupId
				})
				.map(group => group.displayName)
				.join(', ')
			}
		</a>
	}
}

interface AdminGroupSelectProps {
	allGroups: Group[];
	onChange: (name: string) => void;
}

function AdminGroupSelect({allGroups, onChange}: AdminGroupSelectProps) {
	if (allGroups.length === 0) {
		return <div className="no-options-available">
			<p>No other groups available</p>
		</div>;
	}
	const options = allGroups.map(group => {
		return {
			value: group.gid,
			label: group.displayName
		};
	});

	return <Select
		onChange={option => {
			onChange && option && onChange(option.value)
		}}
		options={options}
		placeholder={t('groupfolders', 'Add group')}
		styles={{
			input: (provided) => ({
				...provided,
				height: 30,
				color: 'var(--color-primary-text)'
			}),
			control: (provided) => ({
				...provided,
				backgroundColor: 'var(--color-main-background)',
			}),
			menu: (provided) => ({
				...provided,
				backgroundColor: 'var(--color-main-background)',
				borderColor: '#888'
			}),
			option: (provided, state) => ({
				...provided,
				backgroundColor: state.isFocused ? 'var(--color-background-dark)' : 'transparent'
			})
		}}
	/>
}
