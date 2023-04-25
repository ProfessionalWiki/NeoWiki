export type SchemaProperty = {
	type: string;
	format?: string;
	description?: string;
	minimum?: number;
	maximum?: number;
	currencyCode?: string;
	renderPrecision?: number;
	items?: SchemaProperty;
	uniqueItems?: boolean;
};

export type Schema = {
	$schema: string;
	title: string;
	description: string;
	type: string;
	properties: Record<string, SchemaProperty>;
};

export enum ValueType {

	String = 'string',
	Number = 'number',
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
