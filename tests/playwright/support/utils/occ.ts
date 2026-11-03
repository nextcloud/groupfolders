/*!
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { runOcc } from '@nextcloud/e2e-test-server/docker'
import { retryWithBackoff } from './retry.ts'

/**
 * Run an occ command in the test container and return its trimmed output.
 *
 * @param args - The command and its arguments, e.g. `['group:add', 'admins']`
 */
export async function occ(args: string[]): Promise<string> {
	const { stdout } = await retryWithBackoff(`Running occ ${args.join(' ')}`, () => runOcc(args))
	return stdout.trim()
}

/**
 * Run an occ command for cleaning up after a test, ignoring failures so a
 * cleanup step never hides the outcome of the test itself.
 *
 * @param args - The command and its arguments
 */
export async function occCleanup(args: string[]): Promise<void> {
	await runOcc(args, { failOnError: false })
}
