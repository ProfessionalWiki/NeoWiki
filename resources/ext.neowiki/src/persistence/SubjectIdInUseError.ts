/**
 * A create was refused because the Subject id it carried is already taken. Only a client that
 * supplies the id can meet this. For an id it minted for this very Subject, that Subject already
 * exists, so its next write is an update rather than another create; an id it was given may be any
 * Subject's.
 */
export class SubjectIdInUseError extends Error {

	public constructor( public readonly subjectId: string ) {
		super( `Subject id already in use: ${ subjectId }` );
		// Restores the prototype chain, so instanceof holds however this is compiled down.
		Object.setPrototypeOf( this, SubjectIdInUseError.prototype );
		this.name = 'SubjectIdInUseError';
	}

}
