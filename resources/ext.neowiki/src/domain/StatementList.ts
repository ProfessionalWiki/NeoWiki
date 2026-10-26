import { SubjectId } from '@/domain/SubjectId';
import { Statement } from '@/domain/Statement';
import { PropertyName } from '@/domain/PropertyDefinition';
import { RelationValue, type Value, valueToJson } from '@/domain/Value';

export class StatementList implements Iterable<Statement> {

	private readonly statements = new Map<string, Statement>();

	public constructor( statements: Statement[] ) {
		for ( const statement of statements ) {
			const propertyName = statement.propertyName.toString();

			if ( this.statements.has( propertyName ) ) {
				throw new Error( `Cannot have two statements with property name: ${ propertyName }` );
			}

			this.statements.set( propertyName, statement );
		}
	}

	public get( name: PropertyName ): Statement {
		return this.statements.get( name.toString() ) as Statement;
	}

	public has( name: PropertyName ): boolean {
		return this.statements.has( name.toString() );
	}

	public [ Symbol.iterator ](): IterableIterator<Statement> {
		return this.statements.values();
	}

	public getPropertyNames(): PropertyName[] {
		return [ ...this ].map( ( statement ) => statement.propertyName );
	}

	public withNonEmptyValues(): StatementList {
		return this.filter( ( statement ) => statement.hasValue() );
	}

	public withOnlyRelationValues(): StatementList {
		return this.filter( ( statement ) => statement.value instanceof RelationValue );
	}

	/**
	 * Replaces the statement of the same property in place, or appends.
	 */
	public withStatement( statement: Statement ): StatementList {
		if ( !this.has( statement.propertyName ) ) {
			return new StatementList( [ ...this, statement ] );
		}

		return new StatementList( [ ...this ].map(
			( existing ) => existing.propertyName.toString() === statement.propertyName.toString() ? statement : existing,
		) );
	}

	private filter( callback: ( property: Statement ) => boolean ): StatementList {
		return new StatementList(
			[ ...this ].filter( callback ),
		);
	}

	public asPropertyValueRecord(): Record<string, Value|undefined> {
		return Object.fromEntries(
			[ ...this ].map( ( statement ) => [ statement.propertyName.toString(), statement.value ] ),
		);
	}

	public getIdsOfReferencedSubjects(): Set<SubjectId> {
		const relationValues = this.getValuesOfType( RelationValue );
		return new Set(
			relationValues.flatMap( ( value ) => value.targetIds ),
		);
	}

	private getValuesOfType<T extends Value>( type: new( ...args: any[] ) => T ): T[] {
		return [ ...this ]
			.map( ( statement ) => statement.value )
			.filter( ( value ): value is T => value instanceof type );
	}

}

export function statementsToJson( statements: StatementList ): Record<string, unknown> {
	return Object.fromEntries(
		[ ...statements ].map( ( statement ) => [ statement.propertyName.toString(), statementToJson( statement ) ] ),
	);
}

function statementToJson( statement: Statement ): unknown {
	if ( statement.value === undefined ) {
		return null;
	}

	const value = valueToJson( statement.value );

	if ( typeof value === 'number' && isNaN( value ) ) {
		return null;
	}

	return {
		value: value,
		propertyType: statement.propertyType,
	};
}
