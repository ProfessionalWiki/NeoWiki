import { SubjectId } from '@neo/domain/SubjectId';

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
	readonly strings: string[]; // TODO: rename to parts
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

	public get targetIds(): SubjectId[] {
		return this.relations.map( ( relation ) => relation.target );
	}
}

export class Relation {

	public constructor(
		public readonly id: string | undefined,
		public readonly target: SubjectId
		// TODO: add relation properties (like on backend)
	) {
	}

}

export type Value = StringValue | NumberValue | BooleanValue | RelationValue;

export function newStringValue( ...parts: string[] | [ string[] ] ): StringValue {
	const strings = Array.isArray( parts[ 0 ] ) ? parts[ 0 ] : parts as string[];

	return {
		type: ValueType.String,
		strings: strings
			.map( ( part ) => part.trim() )
			.filter( ( part ) => part !== '' )
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

export function newRelation( id: string | undefined, target: SubjectId | string ): Relation {
	return new Relation(
		id,
		typeof target === 'string' ? new SubjectId( target ) : target
	);
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
			return ( value as RelationValue ).relations.map(
				( relation ) => ( { id: relation.id, target: relation.target.text } )
			);
		default:
			throw new Error( `Unsupported value type: ${ ( value as Value ).type }` );
	}
}
