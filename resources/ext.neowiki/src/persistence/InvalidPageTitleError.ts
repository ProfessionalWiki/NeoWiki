/**
 * Creating a Subject with a page of its own was refused because the title chosen for that page can
 * title no page here. Nothing was created, and no other title was used in its place: only the
 * caller knows what they meant by it.
 */
export class InvalidPageTitleError extends Error {

	public constructor( public readonly pageTitle: string ) {
		super( `"${ pageTitle }" cannot title a page here` );
		// Restores the prototype chain, so instanceof holds however this is compiled down.
		Object.setPrototypeOf( this, InvalidPageTitleError.prototype );
		this.name = 'InvalidPageTitleError';
	}

}
