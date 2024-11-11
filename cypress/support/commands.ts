/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
/* eslint-disable n/no-unpublished-import */
import axios from '@nextcloud/axios'
import { addCommands, User } from '@nextcloud/cypress'
import { basename } from 'path'
import '@testing-library/cypress/add-commands'

// Add custom commands
import 'cypress-wait-until'
addCommands()

// Register this file's custom commands types
declare global {
	// eslint-disable-next-line @typescript-eslint/no-namespace
	namespace Cypress {
		interface Chainable<Subject = any> {
			/**
			 * Upload a file from the fixtures folder to a given user storage.
			 * **Warning**: Using this function will reset the previous session
			 */
			uploadFile(user: User, fixture?: string, mimeType?: string, target?: string): Cypress.Chainable<void>,

			/**
			 * Upload a raw content to a given user storage.
			 * **Warning**: Using this function will reset the previous session
			 */
			uploadContent(user: User, content: Blob, mimeType: string, target: string): Cypress.Chainable<void>,

			/**
			 * Create a new directory
			 * **Warning**: Using this function will reset the previous session
			 */
			mkdir(user: User, target: string): Cypress.Chainable<void>,

			/**
			 * Run an occ command in the docker container.
			 */
			runOccCommand(command: string, options?: Partial<Cypress.ExecOptions>): Cypress.Chainable<Cypress.Exec>,

			/**
			 * Create a snapshot of the current database
			 */
			backupDB(): Cypress.Chainable<string>,

			/**
			 * Restore a snapshot of the database
			 * Default is the post-setup state
			 */
			restoreDB(snapshot?: string): Cypress.Chainable

			backupData(users?: string[]): Cypress.Chainable<string>

			restoreData(snapshot?: string): Cypress.Chainable
		}
	}
}

const url = (Cypress.config('baseUrl') || '').replace(/\/index.php\/?$/g, '')
Cypress.env('baseUrl', url)

Cypress.Commands.add('mkdir', (user: User, target: string) => {
	// eslint-disable-next-line cypress/unsafe-to-chain-command
	cy.clearCookies()
		.then({ timeout: 8000 }, async () => {
			try {
				const rootPath = `${Cypress.env('baseUrl')}/remote.php/dav/files/${encodeURIComponent(user.userId)}`
				const filePath = target.split('/').map(encodeURIComponent).join('/')
				const response = await axios({
					url: `${rootPath}${filePath}`,
					method: 'MKCOL',
					auth: {
						username: user.userId,
						password: user.password,
					},
				})
				cy.log(`Created directory ${target}`, response)
			} catch (error) {
				cy.log('error', error)
				throw new Error('Unable to process fixture')
			}
		})
})

/**
 * cy.uploadedFile - uploads a file from the fixtures folder
 * TODO: standardise in @nextcloud/cypress
 *
 * @param {User} user the owner of the file, e.g. admin
 * @param {string} fixture the fixture file name, e.g. image1.jpg
 * @param {string} mimeType e.g. image/png
 * @param {string} [target] the target of the file relative to the user root
 */
Cypress.Commands.add('uploadFile', (user, fixture = 'image.jpg', mimeType = 'image/jpeg', target = `/${fixture}`) => {
	// get fixture
	return cy.fixture(fixture, 'base64').then(async file => {
		// convert the base64 string to a blob
		const blob = Cypress.Blob.base64StringToBlob(file, mimeType)
		cy.uploadContent(user, blob, mimeType, target)
	})
})

/**
 * cy.uploadedContent - uploads a raw content
 * TODO: standardise in @nextcloud/cypress
 *
 * @param {User} user the owner of the file, e.g. admin
 * @param {Blob} blob the content to upload
 * @param {string} mimeType e.g. image/png
 * @param {string} target the target of the file relative to the user root
 */
Cypress.Commands.add('uploadContent', (user, blob, mimeType, target) => {
	cy.clearCookies()
		.then({ timeout: 8000 }, async () => {
			const fileName = basename(target)

			// Process paths
			const rootPath = `${Cypress.env('baseUrl')}/remote.php/dav/files/${encodeURIComponent(user.userId)}`
			const filePath = target.split('/').map(encodeURIComponent).join('/')
			try {
				const file = new File([blob], fileName, { type: mimeType })
				await axios({
					url: `${rootPath}${filePath}`,
					method: 'PUT',
					data: file,
					headers: {
						'Content-Type': mimeType,
					},
					auth: {
						username: user.userId,
						password: user.password,
					},
				}).then(response => {
					cy.log(`Uploaded content as ${fileName}`, response)
				})
			} catch (error) {
				cy.log('error', error)
				throw new Error('Unable to process fixture')
			}
		})
})

Cypress.Commands.add('runOccCommand', (command: string, options?: Partial<Cypress.ExecOptions>) => {
	const env = Object.entries(options?.env ?? {}).map(([name, value]) => `-e '${name}=${value}'`).join(' ')
	return cy.exec(`docker exec --user www-data ${env} nextcloud-cypress-tests_groupfolders php ./occ ${command}`, options)
})

Cypress.Commands.add('backupDB', (): Cypress.Chainable<string> => {
	const randomString = Math.random().toString(36).substring(7)
	cy.exec(`docker exec --user www-data nextcloud-cypress-tests_groupfolders cp /var/www/html/data/owncloud.db /var/www/html/data/owncloud.db-${randomString}`)
	cy.log(`Created snapshot ${randomString}`)
	return cy.wrap(randomString)
})

Cypress.Commands.add('restoreDB', (snapshot: string = 'init') => {
	cy.exec(`docker exec --user www-data nextcloud-cypress-tests_groupfolders cp /var/www/html/data/owncloud.db-${snapshot} /var/www/html/data/owncloud.db`)
	cy.log(`Restored snapshot ${snapshot}`)
})

Cypress.Commands.add('backupData', () => {
	const snapshot = Math.random().toString(36).substring(7)
	cy.exec(`docker exec --user www-data rm /var/www/html/data/data-${snapshot}.tar`, { failOnNonZeroExit: false })
	cy.exec(`docker exec --user www-data --workdir /var/www/html/data nextcloud-cypress-tests_groupfolders tar cf /var/www/html/data/data-${snapshot}.tar .`)
	return cy.wrap(snapshot as string)
})

Cypress.Commands.add('restoreData', (snapshot?: string) => {
	snapshot = snapshot ?? 'init'
	snapshot.replaceAll('\\', '').replaceAll('"', '\\"')
	cy.exec(`docker exec --user www-data --workdir /var/www/html/data nextcloud-cypress-tests_groupfolders rm -vfr $(tar --exclude='*/*' -tf '/var/www/html/data/data-${snapshot}.tar')`)
	cy.exec(`docker exec --user www-data --workdir /var/www/html/data nextcloud-cypress-tests_groupfolders tar -xf '/var/www/html/data/data-${snapshot}.tar'`)
})
