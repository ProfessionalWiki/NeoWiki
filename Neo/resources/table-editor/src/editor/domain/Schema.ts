export enum ValueType {

	String = 'string',
	Number = 'number',
	Integer = 'integer',
	Boolean = 'boolean',
	Array = 'array', // TODO: figure out how to handle arrays

}

export enum ValueFormat {

	Text = 'text',

	Email = 'email',
	Url = 'url',
	PhoneNumber = 'phoneNumber',

	Date = 'date',
	Time = 'time',
	DateTime = 'dateTime',
	Duration = 'duration',

	Number = 'number',
	Percentage = 'percentage',
	Currency = 'currency',
	Slider = 'slider',

	Checkbox = 'checkbox',
	Toggle = 'toggle',

}

export class PropertyDefinition {

	public constructor(
		public readonly type: ValueType,
		public readonly format: ValueFormat,
		public readonly description?: string,
		public readonly minimum?: number,
		public readonly maximum?: number,
		public readonly currencyCode?: string,
		public readonly renderPrecision?: number,
		public readonly items?: PropertyDefinition,
		public readonly uniqueItems?: boolean
	) {
	}

	public static fromJson( definition: Record<string, any> ): PropertyDefinition {
		return new PropertyDefinition(
			definition.type as ValueType,
			definition.format as ValueFormat,
			definition.description,
			definition.minimum,
			definition.maximum,
			definition.currencyCode,
			definition.renderPrecision,
			definition.items ? PropertyDefinition.fromJson( definition.items ) : undefined,
			definition.uniqueItems
		);
	}

}

export class Schema {

	public constructor(
		private readonly title: string,
		private readonly description: string,
		private readonly properties: Record<string, PropertyDefinition>
	) {
	}

	public getTitle(): string {
		return this.title;
	}

	public getDescription(): string {
		return this.description;
	}

	public getPropertyDefinitions(): Record<string, PropertyDefinition> {
		return this.properties;
	}

	public getPropertyDefinition( propertyName: string ): PropertyDefinition {
		return this.properties[ propertyName ];
	}

	public getPropertyValueType( propertyName: string ): ValueType {
		if ( this.properties[ propertyName ] ) {
			return this.properties[ propertyName ].type;
		}

		return ValueType.String; // TODO: is that what we want?
	}

}
