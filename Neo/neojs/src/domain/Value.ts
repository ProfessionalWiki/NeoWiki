export enum ValueType {

	String = 'string',
	Number = 'number',
	Boolean = 'boolean',
	Relation = 'relation',

}

export interface BaseValueRepresentation {
	readonly type: ValueType;
}

export interface StringValue extends BaseValueRepresentation {
	readonly type: ValueType.String;
	readonly strings: string[];
}

export interface NumberValue extends BaseValueRepresentation {
	readonly type: ValueType.Number;
	readonly number: number;
}

export interface BooleanValue extends BaseValueRepresentation {
	readonly type: ValueType.Boolean;
	readonly boolean: boolean;
}

export class RelationValue implements BaseValueRepresentation {
	public readonly type = ValueType.Relation;

	public constructor(
		public readonly relations: Relation[]
	) {
	}

	public get targetIds(): string[] {
		return this.relations.map( ( relation ) => relation.target );
	}
}

export class Relation {

	public constructor(
		public readonly id: string | undefined,
		public readonly target: string
	) {
	}

}

export type Value = StringValue | NumberValue | BooleanValue | RelationValue;

// Note: if we add type info as per ADR 11, then we should use that type info here.
// TODO: even without ADR 11, we should probably use the types from the Schema here.
export function jsonToValue( json: any, type: ValueType|undefined = undefined ): Value { // TODO: unit tests. Including tests that verify errors are caught
	if ( Array.isArray( json ) ) {
		if ( json.length === 0 ) {
			if ( type === ValueType.String ) {
				return newStringValue();
			}

			if ( type === ValueType.Relation ) {
				return new RelationValue( [] );
			}

			throw new Error( 'Invalid array value: ' + JSON.stringify( json ) );
		}

		if ( typeof json[ 0 ] === 'string' ) {
			return {
				type: ValueType.String,
				strings: json
			} as StringValue;
		}

		if ( typeof json[ 0 ] === 'object' && typeof json[ 0 ].target === 'string' ) {
			return new RelationValue( json.map( ( relationJson: any ) => new Relation( relationJson.id, relationJson.target ) ) );
		}

		throw new Error( 'Invalid value array: ' + JSON.stringify( json ) );
	}

	if ( typeof json === 'number' ) {
		return newNumberValue( json );
	}

	if ( typeof json === 'boolean' ) {
		return newBooleanValue( json );
	}

	if ( typeof json === 'string' ) {
		return {
			type: ValueType.String,
			strings: [ json ]
		} as StringValue;
	}

	if ( typeof json === 'object' && typeof json.target === 'string' ) {
		return new RelationValue( [ new Relation( json.id, json.target ) ] );
	}

	throw new Error( 'Invalid value: ' + JSON.stringify( json ) );
}

export function newStringValue( ...strings: string[] | [ string[] ] ): StringValue {
	return {
		type: ValueType.String,
		strings: Array.isArray( strings[ 0 ] ) ? strings[ 0 ] : strings
	} as StringValue;
}

export function newNumberValue( number: number ): NumberValue {
	return {
		type: ValueType.Number,
		number: number
	} as NumberValue;
}

export function newBooleanValue( boolean: boolean ): BooleanValue {
	return {
		type: ValueType.Boolean,
		boolean: boolean
	} as BooleanValue;
}

export function valueToJson( value: Value ): unknown {
	switch ( value.type ) {
		case ValueType.String:
			return ( value as StringValue ).strings;
		case ValueType.Number:
			return ( value as NumberValue ).number;
		case ValueType.Boolean:
			return ( value as BooleanValue ).boolean;
		case ValueType.Relation:
			return ( value as RelationValue ).relations.map( ( relation ) => ( { id: relation.id, target: relation.target } ) );
		default:
			throw new Error( `Unsupported value type: ${ ( value as Value ).type }` );
	}
}
