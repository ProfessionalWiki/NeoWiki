export class PageCreationError extends Error {
	public constructor(
		public readonly title: string,
		public readonly code: string | undefined,
	) {
		super( `Could not create the page "${ title }"` );
		this.name = 'PageCreationError';
	}

	public titleTaken(): boolean {
		return this.code === 'articleexists';
	}
}

/**
 * Creates an empty page through MediaWiki's own API rather than through a NeoWiki endpoint, so the
 * createpage right is enforced by core. Resolves to the new page's id.
 */
export async function createEmptyPage( title: string, summary: string ): Promise<number> {
	let response;

	try {
		response = await new mw.Api().create( title, { summary }, '' );
	} catch ( error ) {
		throw new PageCreationError( title, apiErrorCode( error ) );
	}

	if ( response.result !== 'Success' ) {
		throw new PageCreationError( title, undefined );
	}

	return response.pageid;
}

function apiErrorCode( error: unknown ): string | undefined {
	return typeof error === 'string' ? error : ( error as { code?: string } )?.code;
}
