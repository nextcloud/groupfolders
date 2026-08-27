/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import * as React from 'react'
import { Component, FormEvent } from 'react'
import { formatFileSize } from '@nextcloud/files'
import { t } from '@nextcloud/l10n'
import type { SubfolderQuota } from '../types'
import { QuotaSelect } from './QuotaSelect'

interface SubfolderQuotasProps {
	subfolders: SubfolderQuota[];
	quotaOptions: { [name: string]: number };
	parentQuota: number;
	onCreate: (name: string, quota: number | null) => Promise<boolean>;
	onSetQuota: (subfolder: SubfolderQuota, quota: number | null) => Promise<void>;
}

interface SubfolderQuotasState {
	name: string;
	quota: number | null;
}

export class SubfolderQuotas extends Component<SubfolderQuotasProps, SubfolderQuotasState> {

	state: SubfolderQuotasState = {
		name: '',
		quota: null,
	}

	createSubfolder = async (event: FormEvent) => {
		event.preventDefault()
		if (this.state.name === '') {
			return
		}

		if (await this.props.onCreate(this.state.name, this.state.quota)) {
			this.setState({ name: '', quota: null })
		}
	}

	getQuotaOptions(): { [name: string]: number } {
		return Object.fromEntries(Object.entries(this.props.quotaOptions).filter(([, quota]) => (
			quota >= 0 && (this.props.parentQuota < 0 || quota <= this.props.parentQuota)
		)))
	}

	render() {
		const quotaOptions = this.getQuotaOptions()
		return <div className="subfolder-quotas">
			<h4>{t('groupfolders', 'Subfolder quotas')}</h4>
			<p>{t('groupfolders', 'A subfolder limit applies to all of its contents and also counts towards the Team folder quota.')}</p>
			<form onSubmit={this.createSubfolder} className="subfolder-quotas__create">
				<input
					value={this.state.name}
					placeholder={t('groupfolders', 'Subfolder name')}
					aria-label={t('groupfolders', 'Subfolder name')}
					onChange={(event) => this.setState({ name: event.target.value })}/>
				<QuotaSelect
					options={quotaOptions}
					value={this.state.quota}
					size={0}
					maxSize={this.props.parentQuota}
					noneLabel={t('groupfolders', 'No separate limit')}
					onChange={(quota) => this.setState({ quota })}/>
				<button type="submit">{t('groupfolders', 'Create subfolder')}</button>
			</form>
			<table className="subfolder-quotas__table">
				<thead>
					<tr>
						<th>{t('groupfolders', 'Subfolder')}</th>
						<th>{t('groupfolders', 'Used')}</th>
						<th>{t('groupfolders', 'Quota')}</th>
					</tr>
				</thead>
				<tbody>
					{this.props.subfolders.length === 0
						? <tr><td colSpan={3}>{t('groupfolders', 'No direct subfolders yet')}</td></tr>
						: this.props.subfolders.map((subfolder) => <tr key={subfolder.file_id}>
							<td>{subfolder.name}</td>
							<td>{formatFileSize(subfolder.size)}</td>
							<td className="quota">
								<QuotaSelect
									options={quotaOptions}
									value={subfolder.quota}
									size={subfolder.size}
									maxSize={this.props.parentQuota}
									noneLabel={t('groupfolders', 'No separate limit')}
									onChange={(quota) => this.props.onSetQuota(subfolder, quota)}/>
							</td>
						</tr>)}
				</tbody>
			</table>
		</div>
	}

}
