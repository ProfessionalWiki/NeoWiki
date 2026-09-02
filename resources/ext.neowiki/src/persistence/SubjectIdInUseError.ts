/**
 * A create was refused because the Subject id it carried is already taken. Only a client that
 * mints ids up front can meet this: it means that very Subject already exists, so the caller's
 * next write is an update rather than another create.
 */
export class SubjectIdInUseError extends Error {

	public constructor( public readonly subjectId: string ) {
		super( `Subject id already in use: ${ subjectId }` );
		// Restores the prototype chain, so instanceof holds however this is compiled down.
		Object.setPrototypeOf( this, SubjectIdInUseError.prototype );
		this.name = 'SubjectIdInUseError';
	}

}
