export type PropertyDefinition = {
	type: string;
	format?: string;
	description?: string;
	minimum?: number;
	maximum?: number;
	currencyCode?: string;
	renderPrecision?: number;
	items?: PropertyDefinition;
	uniqueItems?: boolean;
};

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

	Percentage = 'percentage',
	Currency = 'currency',
	Slider = 'slider',

	Checkbox = 'checkbox',
	Toggle = 'toggle',

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

	public getPropertyValueType( propertyName: string ): ValueType {
		if ( this.properties[ propertyName ] ) {
			return this.properties[ propertyName ].type as ValueType;
		}

		return ValueType.String; // TODO: is that what we want?
	}

}
