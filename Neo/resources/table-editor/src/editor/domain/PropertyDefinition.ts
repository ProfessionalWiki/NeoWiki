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

export class PropertyName {

	private readonly name: string;

	public constructor( name: string ) {
		if ( name === '' ) {
			throw new Error( 'Invalid PropertyId' );
		}
		this.name = name;
	}

	public toString(): string {
		return this.name;
	}

}

interface BasePropertyDefinition {

	readonly name: PropertyName;
	readonly type: ValueType;
	readonly format: ValueFormat;
	readonly description: string;
	readonly required: boolean;
	readonly default?: any;

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

	readonly type: ValueType.String;
	readonly format: StringValueFormat;
	readonly multiple?: boolean;
	readonly uniqueItems?: boolean;

}

interface NumberProperty extends BasePropertyDefinition {

	readonly type: ValueType.Number;
	readonly format: ValueFormat.Number;
	readonly precision?: number;
	readonly minimum?: number;
	readonly maximum?: number;

}

interface CurrencyProperty extends BasePropertyDefinition {

	readonly type: ValueType.Number;
	readonly format: ValueFormat.Currency;
	readonly currencyCode: string;
	readonly precision: number;
	readonly minimum?: number;
	readonly maximum?: number;

}

interface ProgressProperty extends BasePropertyDefinition {

	readonly type: ValueType.Number;
	readonly format: ValueFormat.Progress;
	readonly minimum: number;
	readonly maximum: number;
	readonly step: number;

}

interface CheckboxProperty extends BasePropertyDefinition {

	readonly type: ValueType.Boolean;
	readonly format: ValueFormat.Checkbox;

}

interface RelationProperty extends BasePropertyDefinition {

	readonly type: ValueType.Relation;
	readonly format: ValueFormat.Relation;
	readonly relation: string;
	readonly targetSchema: string;
	readonly multiple?: boolean;
	readonly uniqueItems?: boolean;

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

	/**
	 * TODO
	 * Type assertion in createPropertyDefinitionFromJson function: You're using type assertions (as keyword) to cast
	 * the JSON values to certain types. This could potentially cause runtime errors if the JSON data doesn't match
	 * the expected format. It would be safer to add some explicit checks here.
	 */

	const baseDef: BasePropertyDefinition = {
		name: new PropertyName( id ),
		type: json.type as ValueType,
		format: json.format as ValueFormat,
		description: json.description ?? '',
		required: json.required ?? false,
		default: json.default
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
