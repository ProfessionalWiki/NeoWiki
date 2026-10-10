/**
 * Thrown by a host's write handler to stop a save over a refusal the host shows the user itself,
 * where it can be answered. The editor stops the save there and adds no message of its own.
 */
export class WriteRefusedError extends Error {

	public constructor( message: string ) {
		super( message );
		// Restores the prototype chain, so instanceof holds however this is compiled down.
		Object.setPrototypeOf( this, WriteRefusedError.prototype );
		this.name = 'WriteRefusedError';
	}

}
