import { defineConfig } from 'tsup';
import { replaceInFile } from 'replace-in-file';

export default defineConfig( {
	entry: [ 'src/index.ts' ],
	format: [ 'cjs', 'esm' ],
	dts: true,
	sourcemap: true,
	clean: true,
	onSuccess: async () => {
		try {
			const results = await replaceInFile( {
				files: 'dist/**/*.{js,mjs}',
				from: [
					'//# sourceMappingURL=index.js.map',
					'//# sourceMappingURL=index.mjs.map'
				],
				to: [
					'//# sourceMappingURL=/extensions/NeoWiki/Neo/neojs/dist/index.js.map',
					'//# sourceMappingURL=/extensions/NeoWiki/Neo/neojs/dist/index.mjs.map'
				]
			} );
			console.log( 'Modified sourceMappingURL:', results );
		} catch ( error ) {
			console.error( 'Error modifying sourceMappingURL:', error );
		}
	}
} );
