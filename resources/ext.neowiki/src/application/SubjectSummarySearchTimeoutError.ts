export class SubjectSummarySearchTimeoutError extends Error {

	public constructor() {
		super( 'The search took too long' );
		// Restores the prototype chain, so instanceof holds however this is compiled down.
		Object.setPrototypeOf( this, SubjectSummarySearchTimeoutError.prototype );
		this.name = 'SubjectSummarySearchTimeoutError';
	}

}
