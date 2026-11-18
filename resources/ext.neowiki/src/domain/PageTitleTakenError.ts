/**
 * Creating a Subject with a page of its own was refused because a page of that title is already
 * there. Nothing was created: the caller decides whether they meant that page, and adds the
 * Subject to it instead.
 */
export class PageTitleTakenError extends Error {

	public constructor( public readonly pageTitle: string ) {
		super( `A page named "${ pageTitle }" already exists` );
		// Restores the prototype chain, so instanceof holds however this is compiled down.
		Object.setPrototypeOf( this, PageTitleTakenError.prototype );
		this.name = 'PageTitleTakenError';
	}

}
