/**
 * The wiki serves no Subject under this id. A Subject on a page the reader may not read answers the
 * same way by design (#1046), so a caller must not report this as "it exists but is yours to unlock".
 */
export class SubjectNotFoundError extends Error {

	public constructor( public readonly subjectId: string ) {
		super( `Subject not found: ${ subjectId }` );
		// Restores the prototype chain, so instanceof holds however this is compiled down.
		Object.setPrototypeOf( this, SubjectNotFoundError.prototype );
		this.name = 'SubjectNotFoundError';
	}

}
