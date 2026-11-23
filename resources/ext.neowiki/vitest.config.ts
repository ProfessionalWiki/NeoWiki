import { defineConfig, mergeConfig } from 'vitest/config';
import { fileURLToPath, URL } from 'node:url';
import viteConfig from './vite.config';

export default mergeConfig( viteConfig, defineConfig( {
	resolve: {
		alias: {
			'ext.neowiki': fileURLToPath( new URL( './modelBundles/tests/neoWikiPublicApi.ts', import.meta.url ) ),
		},
	},
	test: {
		environment: 'jsdom',
		globals: true,
		coverage: {
			provider: 'v8',
			include: [ 'src', 'modelBundles/src' ],
			reporter: [ 'text', 'json-summary', 'json' ],
			reportOnFailure: true,
		},
	},
} ) );
