import type { Plugin } from 'vite';

export function mediawikiImportTransformer(): Plugin {
	return {
		name: 'mediawiki-import-transformer',
		renderChunk( code: string ): { code: string; map: null } {
			// Rewrite imports provided by MediaWiki.
			code = code.replace( "'@wikimedia/codex'", "'../../../codex.js'" );
			code = code.replace( "'@wikimedia/codex-icons'", "'../../../icons.json'" );

			return { code, map: null };
		},
	};
}
