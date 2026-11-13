/**
 * A write was refused, and nothing written, because what it writes changed after the revision it was based on.
 */
export class EditConflictError extends Error {

	public constructor( public readonly subjectId: string ) {
		super( `Subject changed after the revision the edit was based on: ${ subjectId }` );
		// Restores the prototype chain, so instanceof holds however this is compiled down.
		Object.setPrototypeOf( this, EditConflictError.prototype );
		this.name = 'EditConflictError';
	}

}
