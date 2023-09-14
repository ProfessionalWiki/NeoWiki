export interface SchemaObject {
	title: string;
	description?: string;
	propertyDefinitions: {
		[key: string]: PropertyObject;
	};
}

export type PropertyValue = string|number|boolean|null;

export interface PropertyObject {
	[key: string]: PropertyValue;
}
