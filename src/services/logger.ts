import { getLoggerBuilder } from '@nextcloud/logger'

export default getLoggerBuilder()
	.setApp('groupfolders')
	.detectUser()
	.build()
