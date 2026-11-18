import { defineConfig, mergeConfig } from 'vite';
import viteConfig from './vite.config';

// The ext.neowiki.modelBundles module of the NeoWikiModelBundles extension (extension-model-bundles.json). It reaches
// NeoWiki through require( 'ext.neowiki' ), so a page holds one copy of NeoWiki's code and its singletons.
export default mergeConfig( viteConfig, defineConfig( {
	build: {
		lib: {
			entry: 'modelBundles/src/modelBundles.ts',
			fileName: () => 'modelBundles.js',
		},
		rollupOptions: {
			external: [ 'ext.neowiki' ],
			output: {
				assetFileNames: ( assetInfo ) => assetInfo.name?.endsWith( '.css' ) ? 'modelBundles.css' : assetInfo.name || 'unknown',
			},
		},
	},
} ) );
