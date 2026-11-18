import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';
import { mediawikiImportTransformer } from './mediawikiImportTransformer';

// The ext.neowiki.modelBundles module of the NeoWikiModelBundles extension (extension-model-bundles.json). It reaches
// NeoWiki through require( 'ext.neowiki' ), so a page holds one copy of NeoWiki's code and its singletons.
export default defineConfig( {
	plugins: [ vue(), mediawikiImportTransformer() ],
	build: {
		lib: {
			entry: 'modelBundles/src/modelBundles.ts',
			fileName: () => 'modelBundles.js',
			formats: [ 'cjs' ],
		},
		// The NeoWiki build writes to the same directory.
		emptyOutDir: false,
		rollupOptions: {
			external: [ 'ext.neowiki', 'vue', '@wikimedia/codex', '@wikimedia/codex-icons', 'pinia' ],
			output: {
				format: 'cjs',
				assetFileNames: ( assetInfo ) => {
					if ( assetInfo.name && assetInfo.name.endsWith( '.css' ) ) {
						return 'modelBundles.css';
					}
					return assetInfo.name || 'unknown';
				},
				sourcemapBaseUrl: 'http://localhost:8484/extensions/NeoWiki/resources/ext.neowiki/dist/',
			},
		},
		target: 'es2015',
		sourcemap: true,
		minify: true,
	},
} );
