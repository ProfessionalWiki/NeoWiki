import { defineConfig } from 'vitest/config';
import path from 'path';

export default defineConfig( {
	resolve: {
		alias: {
			'@neo': path.resolve( __dirname, './src' )
		}
	},
	test: {
		coverage: {
			provider: 'v8',
			include: [ 'src' ],
			reporter: [ 'text', 'json-summary', 'json' ],
			reportOnFailure: true
		},
		environment: 'jsdom'
	}
} );
