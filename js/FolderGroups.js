import './FolderGroups.css';

export function FolderGroups ({groups, allGroups, onAddGroup, removeGroup, edit, showEdit, onSetPermissions}) {
	if (edit) {
		if (!allGroups) {
			allGroups = {};
		}
		const setPermissions = (change, groupId) => {
			const newPermissions = groups[groupId] ^ change;
			onSetPermissions(groupId, newPermissions);
		};

		const rows = Object.keys(groups).map((groupId) => {
			const permissions = groups[groupId];
			return <tr key={groupId}>
				<td>
					{groupId}
				</td>
				<td className="permissions">
					<input type="checkbox"
						   onChange={setPermissions.bind(null, OC.PERMISSION_UPDATE | OC.PERMISSION_CREATE | OC.PERMISSION_DELETE, groupId)}
						   checked={permissions & (OC.PERMISSION_UPDATE | OC.PERMISSION_CREATE | OC.PERMISSION_DELETE)}/>
				</td>
				<td className="permissions">
					<input type="checkbox"
						   onChange={setPermissions.bind(null, OC.PERMISSION_SHARE, groupId)}
						   checked={permissions & (OC.PERMISSION_SHARE)}/>
				</td>
				<td>
					<a onClick={removeGroup.bind(this, groupId)}>
						<img src={OC.imagePath('core', 'actions/close')}/>
					</a>
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
				<th></th>
			</tr>
			</thead>
			<tbody>
			{rows}
			<tr>
				<td colSpan={4}>
					<GroupSelect allGroups={allGroups.filter(i => !groups[i])}
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
		return <span>
			{Object.keys(groups).join(', ')}
			<a className="icon icon-rename" onClick={showEdit}/>
		</span>
	}
}

function GroupSelect ({allGroups, onChange}) {
	if (allGroups.length === 0) {
		return <div/>;
	}
	const options = allGroups.map((group) => {
		return <option key={group} value={group}>{group}</option>;
	});

	return <select
		onChange={(event) => {
			onChange && onChange(event.target.value)
		}}
	>
		<option>{t('groupfolders', 'Add group')}</option>
		{options}
	</select>
}
