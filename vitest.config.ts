import { defineConfig, mergeConfig } from 'vitest/config';
import viteConfig from './vite.config';

export default mergeConfig( viteConfig, defineConfig( {
	test: {
		environment: 'jsdom',
		globals: true,
		include: [ 'resources/ext.neowiki/**/*.spec.ts' ],
		coverage: {
			provider: 'v8',
			include: [ 'resources/ext.neowiki/src' ],
			reporter: [ 'text', 'json-summary', 'json' ],
			reportOnFailure: true
		}
	}
} ) );
