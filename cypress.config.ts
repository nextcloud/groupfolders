import { configureNextcloud, startNextcloud, stopNextcloud, waitOnNextcloud } from '@nextcloud/cypress/docker'
import { defineConfig } from 'cypress'
import cypressSplit from 'cypress-split'

export default defineConfig({
	projectId: 'xcmgay',

	// 16/9 screen ratio
	viewportWidth: 1280,
	viewportHeight: 720,

	// Tries again 2 more times on failure
	retries: {
		runMode: 2,
		// do not retry in `cypress open`
		openMode: 0,
	},

	// Needed to trigger `after:run` events with cypress open
	experimentalInteractiveRunEvents: true,

	// faster video processing
	videoCompression: false,

	// Prevent elements to be scrolled under a top bar during actions (click, clear, type, etc). Default is 'top'.
	// https://github.com/cypress-io/cypress/issues/871
	scrollBehavior: 'center',

	e2e: {
		// Disable session isolation
		testIsolation: false,

		// We've imported your old cypress plugins here.
		// You may want to clean this up later by importing these.
		async setupNodeEvents(on, config) {
			cypressSplit(on, config)

			// Remove container after run
			on('after:run', () => {
				if (!process.env.CI) {
					stopNextcloud()
				}
			})

			// Before the browser launches
			// starting Nextcloud testing container
			const ip = await startNextcloud(process.env.BRANCH)
			// Setting container's IP as base Url
			config.baseUrl = `http://${ip}/index.php`
			await waitOnNextcloud(ip)
			await configureNextcloud(['groupfolders']) // pass empty array as WE are already the viewer
			return config
		},
	},
})