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
