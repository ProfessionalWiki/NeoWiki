import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';
import { fileURLToPath, URL } from 'node:url';
import { mediawikiImportTransformer } from './mediawikiImportTransformer';

export default defineConfig( {
	plugins: [ vue(), mediawikiImportTransformer() ],
	resolve: {
		alias: {
			'@': fileURLToPath( new URL( './resources/ext.neowiki/src', import.meta.url ) )
		}
	},
	build: {
		outDir: 'resources/ext.neowiki/dist',
		lib: {
			entry: 'resources/ext.neowiki/src/neowiki.ts',
			name: 'NeoWiki',
			fileName: () => 'neowiki.js',
			formats: [ 'cjs' ]
		},
		rollupOptions: {
			external: [ 'vue', '@wikimedia/codex', '@wikimedia/codex-icons', 'pinia' ],
			output: {
				globals: {
					pinia: 'Pinia',
					vue: 'Vue',
					'@wikimedia/codex': 'Codex',
					'@wikimedia/codex-icons': 'CodexIcons'
				},
				format: 'cjs',
				exports: 'named',
				assetFileNames: ( assetInfo ) => {
					if ( assetInfo.name === 'style.css' ) {
						return 'neowiki.css';
					}
					return assetInfo.name || 'unknown';
				},
				sourcemapBaseUrl: 'http://localhost:8484/extensions/NeoWiki/resources/ext.neowiki/dist/'
			}
		},
		target: 'es2015',
		sourcemap: true,
		minify: false
	}
} );
