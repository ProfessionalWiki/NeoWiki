/** The wiki stopped a search of the Subject list that ran past the time it allows. */
export class SubjectSummarySearchTimeoutError extends Error {

	public constructor() {
		super( 'The search took too long' );
		// Restores the prototype chain, so instanceof holds however this is compiled down.
		Object.setPrototypeOf( this, SubjectSummarySearchTimeoutError.prototype );
		this.name = 'SubjectSummarySearchTimeoutError';
	}

}
