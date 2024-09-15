import { defineConfig } from 'tsup';
import { replaceInFile } from 'replace-in-file';

export default defineConfig( {
	entry: [ 'src/neo.ts' ],
	format: [ 'cjs', 'esm' ],
	dts: true,
	sourcemap: true,
	clean: true,
	onSuccess: async () => {
		try {
			const results = await replaceInFile( {
				files: 'dist/**/*.{js,mjs}',
				from: [
					'//# sourceMappingURL=neo.js.map',
					'//# sourceMappingURL=neo.mjs.map'
				],
				to: [
					'//# sourceMappingURL=/extensions/NeoWiki/Neo/neojs/dist/neo.js.map',
					'//# sourceMappingURL=/extensions/NeoWiki/Neo/neojs/dist/neo.mjs.map'
				]
			} );
			console.log( 'Modified sourceMappingURL:', results );
		} catch ( error ) {
			console.error( 'Error modifying sourceMappingURL:', error );
		}
	}
} );
