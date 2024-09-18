export class Uuid {

	public static isValid( str: string ): boolean {
		const regexExp = /^[0-9a-fA-F]{8}\b-[0-9a-fA-F]{4}\b-[0-9a-fA-F]{4}\b-[0-9a-fA-F]{4}\b-[0-9a-fA-F]{12}$/gi;
		return regexExp.test( str );
	}

	public static getRandomUUID(): string {
		// TODO: replace temporary AI implementation
		// return window.crypto.randomUUID();

		const hex = '0123456789abcdef';
		let uuid = '';

		for ( let i = 0; i < 36; i++ ) {
			if ( i === 8 || i === 13 || i === 18 || i === 23 ) {
				uuid += '-';
			} else if ( i === 14 ) {
				uuid += '4'; // Version 4 UUID always has the third segment start with 4
			} else if ( i === 19 ) {
				uuid += hex[ ( Math.random() * 4 ) | 8 ]; // Variant bits
			} else {
				uuid += hex[ ( Math.random() * 16 ) | 0 ];
			}
		}

		return uuid;
	}

}
