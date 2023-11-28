/**
 * @copyright Copyright (c) 2023 John Molakvoæ <skjnldsv@protonmail.com>
 *
 * @author John Molakvoæ <skjnldsv@protonmail.com>
 *
 * @license AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */
import type { DAVResultResponseProps, FileStat, ResponseDataDetailed } from 'webdav'

import { getCurrentUser } from '@nextcloud/auth'
import { File, Folder, Permission } from '@nextcloud/files'
import { generateRemoteUrl, generateUrl } from '@nextcloud/router'

import client, { rootPath } from './client'

type ContentsWithRoot = {
	folder: Folder,
	contents: (Folder | File)[]
}

interface Props extends DAVResultResponseProps {
	permissions: string
	fileid: number
	size: number
	'mount-point': string
}

const data = `<?xml version="1.0"?>
<d:propfind  xmlns:d="DAV:"
	xmlns:oc="http://owncloud.org/ns"
	xmlns:nc="http://nextcloud.org/ns">
	<d:prop>
		<d:getcontentlength />
		<d:getcontenttype />
		<d:getetag />
		<d:getlastmodified />
		<d:resourcetype />
		<oc:fileid />
		<oc:owner-id />
		<oc:permissions />
		<oc:size />
		<nc:has-preview />
		<nc:mount-point />
	</d:prop>
</d:propfind>`

const resultToNode = function(node: FileStat): File | Folder {
	const props = node.props as Props

	// force no permissions as we just want one action: to redirect to files
	// TODO: implement real navigation with full support of files actions
	const permissions = Permission.NONE
	const owner = getCurrentUser()?.uid as string
	const previewUrl = generateUrl('/core/preview?fileId={fileid}&x=32&y=32&forceIcon=0', node.props)
	const mountPoint = (props?.['mount-point'] || '').replace(`/files/${getCurrentUser()?.uid}`, '')

	const nodeData = {
		id: props?.fileid || 0,
		source: generateRemoteUrl('dav' + rootPath + node.filename),
		mtime: new Date(node.lastmod),
		mime: node.mime as string,
		size: props?.size || 0,
		permissions,
		owner,
		root: rootPath,
		attributes: {
			...node,
			...node.props,
			previewUrl,
			mountPoint,
		},
	}

	delete nodeData.attributes.props

	return node.type === 'file'
		? new File(nodeData)
		: new Folder(nodeData)
}

export const getContents = async (path = '/'): Promise<ContentsWithRoot> => {
	const contentsResponse = await client.getDirectoryContents(path, {
		details: true,
		data,
		includeSelf: true,
	}) as ResponseDataDetailed<FileStat[]>

	const root = contentsResponse.data.find(node => node.filename === path)
	if (!root) {
		throw new Error('Could not find root in response')
	}

	const contents = contentsResponse.data.filter(node => node !== root)

	return {
		folder: resultToNode(root) as Folder,
		contents: contents.map(resultToNode),
	}
}
