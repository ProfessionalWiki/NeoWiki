export class SubjectId {

	public readonly text: string;

	public constructor( text: string ) {
		if ( !SubjectId.isValid( text ) ) {
			throw new Error( 'Subject ID has the wrong format. ID: ' + text );
		}

		this.text = text;
	}

	public static isValid( text: string ): boolean {
		return /^s[123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz]{14}$/.test( text );
	}

}
