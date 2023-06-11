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

export class PropertyDefinitionList implements Iterable<PropertyDefinition> {

	private readonly properties: Record<string, PropertyDefinition>;

	public constructor( properties: PropertyDefinition[] ) {
		this.properties = {};

		for ( const property of properties ) {
			const name = property.name.toString();

			if ( this.properties[ name ] ) {
				throw new Error( `Duplicate property name: ${name}` );
			}

			this.properties[ name ] = property;
		}
	}

	public get( id: PropertyName ): PropertyDefinition {
		return this.properties[ id.toString() ];
	}

	public has( name: PropertyName ): boolean {
		return name.toString() in this.properties;
	}

	public asRecord(): Record<string, PropertyDefinition> {
		return this.properties;
	}

	public [ Symbol.iterator ](): Iterator<PropertyDefinition> {
		const properties = Object.values( this.properties );
		let index = 0;

		return {
			next: (): IteratorResult<PropertyDefinition> => {
				if ( index < properties.length ) {
					return { value: properties[ index++ ], done: false };
				} else {
					return { value: undefined, done: true };
				}
			}
		};
	}

	public withNames( names: PropertyName[] ): PropertyDefinitionList {
		const stringNames = names.map( ( name ): string => name.toString() );
		return this.filter( ( property ): boolean => stringNames.includes( property.name.toString() ) );
	}

	private filter( callback: ( property: PropertyDefinition ) => boolean ): PropertyDefinitionList {
		return new PropertyDefinitionList(
			Object.values( this.properties ).filter( callback )
		);
	}

}

export type SchemaName = string;

export class Schema {

	public constructor(
		private readonly name: SchemaName,
		private readonly description: string,
		private readonly properties: PropertyDefinitionList
	) {
	}

	public getName(): SchemaName {
		return this.name;
	}

	public getDescription(): string {
		return this.description;
	}

	public getPropertyDefinitions(): PropertyDefinitionList {
		return this.properties;
	}

	public getPropertyDefinition( propertyName: string ): PropertyDefinition {
		return this.properties.get( new PropertyName( propertyName ) );
	}

}
