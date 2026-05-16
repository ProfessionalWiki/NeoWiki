import { defineConfig, mergeConfig } from 'vitest/config';
import viteConfig from './vite.config';

export default mergeConfig( viteConfig, defineConfig( {
	test: {
		environment: 'jsdom',
		globals: true,
		// Pin the timezone so host-local DateTime tests are deterministic
		// regardless of the contributor's machine (CI runners default to UTC).
		env: {
			TZ: 'UTC',
		},
		coverage: {
			provider: 'v8',
			include: [ 'src' ],
			reporter: [ 'text', 'json-summary', 'json' ],
			reportOnFailure: true,
		},
	},
} ) );
