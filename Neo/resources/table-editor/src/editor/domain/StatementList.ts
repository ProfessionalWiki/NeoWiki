import type { SubjectLookup } from '@/editor/application/SubjectLookup';
import { SubjectMap } from '@/editor/domain/SubjectMap';
import { SubjectId } from '@/editor/domain/SubjectId';
import { isJsonStatement, Statement } from '@/editor/domain/Statement';
import { PropertyName } from '@/editor/domain/PropertyDefinition';
import { jsonToValue, RelationValue, type Value, valueToJson } from '@/editor/domain/Value';
import type { Schema } from '@/editor/domain/Schema';

export class StatementList implements Iterable<Statement> {

	private readonly statements: Record<string, Statement>;

	public constructor( statements: Statement[] ) {
		this.statements = {};

		for ( const statement of statements ) {
			const propertyName = statement.propertyName.toString();

			if ( this.statements[ propertyName ] ) {
				throw new Error( `Cannot have two statements with property name: ${propertyName}` );
			}

			this.statements[ propertyName ] = statement;
		}
	}

	public get( name: PropertyName ): Statement {
		return this.statements[ name.toString() ];
	}

	public has( name: PropertyName ): boolean {
		return name.toString() in this.statements;
	}

	public [ Symbol.iterator ](): Iterator<Statement> {
		const statements = Object.values( this.statements );
		let index = 0;

		return {
			next: (): IteratorResult<Statement> => {
				if ( index < statements.length ) {
					return { value: statements[ index++ ], done: false };
				} else {
					return { value: undefined, done: true };
				}
			}
		};
	}

	public getPropertyNames(): PropertyName[] {
		return Object.keys( this.statements ).map( ( name ) => new PropertyName( name ) );
	}

	public withNonEmptyValues(): StatementList {
		return this.filter( ( statement ) => statement.hasValue() );
	}

	private filter( callback: ( property: Statement ) => boolean ): StatementList {
		return new StatementList(
			Object.values( this.statements ).filter( callback )
		);
	}

	public asPropertyValueRecord(): Record<string, Value|undefined> {
		const record: Record<string, Value|undefined> = {};

		for ( const statement of this ) {
			record[ statement.propertyName.toString() ] = statement.value;
		}

		return record;
	}

	public static fromJsonValues( record: Record<string, unknown>, schema: Schema ): StatementList {
		return new StatementList(
			Object.entries( record )
				.map( ( [ key, statementJson ] ) => new Statement(
					new PropertyName( key ),
					schema.getPropertyDefinition( key ).format,
					jsonToValue(
						isJsonStatement( statementJson ) ? statementJson.value : null,
						schema.getTypeOf( new PropertyName( key ) )
					)
				) )
		);
	}

	public async getReferencedSubjects( lookup: SubjectLookup ): Promise<SubjectMap> {
		const ids = [ ...this.getIdsOfReferencedSubjects() ];

		return new SubjectMap(
			...await Promise.all(
				ids.map( ( id ) => lookup.getSubject( id ) ) // TODO: error handling: silently ignore missing subjects?
			)
		);
	}

	public getIdsOfReferencedSubjects(): Set<SubjectId> {
		const relationValues = this.getValuesOfType( RelationValue );
		return new Set(
			relationValues.flatMap( ( value ) => value.targetIds.map( ( id ) => new SubjectId( id ) ) )
		);
	}

	private getValuesOfType<T extends Value>( type: new( ...args: any[] ) => T ): T[] {
		return [ ...this ]
			.map( ( statement ) => statement.value )
			.filter( ( value ): value is T => value instanceof type );
	}

}

export function statementsToJson( statements: StatementList ): Record<string, unknown> {
	const valuesJson: Record<string, unknown> = {};

	for ( const statement of statements ) {
		if ( statement.value === undefined ) {
			valuesJson[ statement.propertyName.toString() ] = null;
			continue;
		}

		const value = valueToJson( statement.value );

		if ( typeof value === 'number' && isNaN( value ) ) {
			valuesJson[ statement.propertyName.toString() ] = null;
			continue;
		}

		valuesJson[ statement.propertyName.toString() ] = {
			value: value,
			format: statement.format
		};
	}

	return valuesJson;
}
