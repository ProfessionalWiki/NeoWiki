export enum ValueType {

	String = 'string',
	Number = 'number',
	Boolean = 'boolean',
	Relation = 'relation',

}

export enum ValueFormat {

	Text = 'text',

	Email = 'email',
	Url = 'url',
	PhoneNumber = 'phoneNumber',

	Date = 'date',
	Time = 'time',
	DateTime = 'dateTime',
	Duration = 'duration', // TODO

	Number = 'number',
	Currency = 'currency',
	Progress = 'progress',

	Checkbox = 'checkbox',
	// Toggle = 'toggle',

	Relation = 'relation',

}

export class PropertyId {

	private readonly id: string;

	public constructor( id: string ) {
		if ( id === '' ) {
			throw new Error( 'Invalid PropertyId' );
		}
		this.id = id;
	}

	public toString(): string {
		return this.id;
	}

}


interface BasePropertyDefinition {

	id: PropertyId;
	type: ValueType;
	format: ValueFormat;
	description: string;
	required: boolean;

}

type StringValueFormat =
	ValueFormat.Text
	| ValueFormat.Email
	| ValueFormat.Url
	| ValueFormat.PhoneNumber
	| ValueFormat.Date
	| ValueFormat.Time
	| ValueFormat.DateTime
	| ValueFormat.Duration;

interface StringProperty extends BasePropertyDefinition {

	type: ValueType.String;
	format: StringValueFormat;
	multiple?: boolean;
	uniqueItems?: boolean;

}

interface NumberProperty extends BasePropertyDefinition {

	type: ValueType.Number;
	format: ValueFormat.Number;
	precision?: number;
	minimum?: number;
	maximum?: number;

}

interface CurrencyProperty extends BasePropertyDefinition {

	type: ValueType.Number;
	format: ValueFormat.Currency;
	currencyCode: string;
	precision: number;
	minimum?: number;
	maximum?: number;

}

interface ProgressProperty extends BasePropertyDefinition {

	type: ValueType.Number;
	format: ValueFormat.Progress;
	minimum: number;
	maximum: number;
	step: number;

}

interface CheckboxProperty extends BasePropertyDefinition {

	type: ValueType.Boolean;
	format: ValueFormat.Checkbox;

}

interface RelationProperty extends BasePropertyDefinition {

	type: ValueType.Relation;
	format: ValueFormat.Relation;
	relation: string;
	targetSchema: string;
	multiple?: boolean;
	uniqueItems?: boolean;

}

export type PropertyDefinition =
	StringProperty
	| NumberProperty
	| CurrencyProperty
	| ProgressProperty
	| CheckboxProperty
	| RelationProperty;

// TODO: is this really the best way to have type safety for formats?
export function isCurrencyProperty( property: PropertyDefinition ): property is CurrencyProperty {
	return property.format === ValueFormat.Currency;
}

// eslint-disable-next-line @typescript-eslint/explicit-module-boundary-types
export function createPropertyDefinitionFromJson( id: string, json: any ): PropertyDefinition {

	const baseDef: BasePropertyDefinition = {
		id: new PropertyId( id ),
		type: json.type as ValueType,
		format: json.format as ValueFormat,
		description: json.description ?? '',
		required: json.required ?? false
	};

	switch ( json.type ) {
		case ValueType.String:
			return {
				...baseDef,
				multiple: json.multiple ?? false,
				uniqueItems: json.uniqueItems ?? true
			} as StringProperty;
		case ValueType.Number:
			switch ( json.format ) {
				case ValueFormat.Number:
					return {
						...baseDef,
						minimum: json.minimum,
						maximum: json.maximum,
						precision: json.precision
					} as NumberProperty;
				case ValueFormat.Currency:
					return {
						...baseDef,
						currencyCode: json.currencyCode,
						precision: json.precision,
						minimum: json.minimum,
						maximum: json.maximum
					} as CurrencyProperty;
				case ValueFormat.Progress:
					return {
						...baseDef,
						minimum: json.minimum,
						maximum: json.maximum,
						step: json.step
					} as ProgressProperty;
				default:
					throw new Error( `Unsupported number format: ${json.format}` );
			}
		case ValueType.Boolean:
			return baseDef as CheckboxProperty;
		case ValueType.Relation:
			return {
				...baseDef,
				relation: json.relation,
				targetSchema: json.targetSchema,
				multiple: json.multiple ?? false,
				uniqueItems: json.uniqueItems ?? true
			} as RelationProperty;
		default:
			throw new Error( `Unsupported type: ${json.type}` );
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

}
