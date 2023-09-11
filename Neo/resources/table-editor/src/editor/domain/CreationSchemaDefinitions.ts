export interface CreationSchema {
	title: string;
	propertyDefinitions: CreationProperty[];
}

export type CreationPropertyValue = string|number|boolean|null;

export interface CreationProperty {
	[key: string]: CreationPropertyValue;
}

interface CreationFormat {
	[key: string]: string;
}

export interface CreationFormatRelation {
	[key: string]: string[];
}

export const CreationFormatValues: CreationFormat = {
	string: 'string',
	email: 'string',
	url: 'string',
	phoneNumber: 'string',
	date: 'string',
	dateTime: 'string',
	time: 'string',
	number: 'number',
	currency: 'number',
	progress: 'number',
	checkbox: 'boolean',
	relation: 'string'
};

const DefaultGlobalRelations = [ 'required' ];

const DefaultStringRelations = [
	'default',
	'multiple'
];

const DefaultNumberRelations = [
	'minimum',
	'maximum',
	'precision'
];

export const CreationFormatRelationValues: CreationFormatRelation = {
	string: DefaultGlobalRelations.concat( DefaultStringRelations ),
	email: DefaultGlobalRelations.concat( DefaultStringRelations ),
	url: DefaultGlobalRelations.concat( DefaultStringRelations ),
	phoneNumber: DefaultGlobalRelations.concat( DefaultStringRelations ),
	date: DefaultGlobalRelations,
	dateTime: DefaultGlobalRelations,
	time: DefaultGlobalRelations,
	number: DefaultGlobalRelations.concat( DefaultNumberRelations ),
	currency: DefaultGlobalRelations.concat( DefaultNumberRelations, [ 'currencyCode' ] ),
	progress: DefaultGlobalRelations.concat( DefaultNumberRelations ),
	checkbox: DefaultGlobalRelations,
	relation: DefaultGlobalRelations.concat( [ 'relation', 'targetSchema', 'multiple' ] )
};
