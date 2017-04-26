import './FolderGroups.css';

export function FolderGroups ({groups, allGroups, onAddGroup, removeGroup, edit, showEdit}) {
	if (edit) {
		const rows = Object.keys(groups).map((groupId) => {
			const permissions = groups[groupId];
			return <tr key={groupId}>
				<td>
					{groupId}
				</td>
				<td>
					<a onClick={removeGroup.bind(this, groupId)}>
						<img
							className=""
							src={OC.imagePath('core', 'actions/close')}/>
					</a>
				</td>
			</tr>
		});

		return <table className="group-edit"
					  onClick={event => event.stopPropagation()}>
			<tbody>
			{rows}
			<tr>
				<td colSpan={2}>
					<GroupSelect allGroups={allGroups.filter(i => !groups[i])}
								 onChange={onAddGroup}/>
				</td>
			</tr>
			</tbody>
		</table>
	} else {
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
		<option>Add group</option>
		{options}
	</select>
}
