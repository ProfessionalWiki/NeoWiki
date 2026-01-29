export class PropertyName {

	private readonly name: string;

	/**
	 * @param {string} name - The name of the property.
	 * @param {boolean} placeholder - Whether the name is a placeholder, used when creating a new property.
	 */
	public constructor( name: string, placeholder: boolean = false ) {
		this.name = name.trim();

		if ( !PropertyName.isValid( name ) && !placeholder ) {
			throw new Error( 'Invalid PropertyName' );
		}
	}

	public toString(): string {
		return this.name;
	}

	public static isValid( name: string ): boolean {
		return name.trim() !== '';
	}

}
