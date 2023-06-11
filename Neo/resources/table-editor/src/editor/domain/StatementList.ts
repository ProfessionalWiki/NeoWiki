import { PropertyName } from '@/editor/domain/Schema';
import type { SubjectLookup } from '@/editor/application/SubjectLookup';
import { SubjectMap } from '@/editor/domain/SubjectMap';
import { SubjectId } from '@/editor/domain/SubjectId';
import { Statement } from '@/editor/domain/Statement';

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

	// TODO: dedicated test
	public asPropertyValueRecord(): Record<string, unknown> {
		const record: Record<string, unknown> = {};

		for ( const statement of this ) {
			record[ statement.propertyName.toString() ] = statement.value;
		}

		return record;
	}

	// TODO: dedicated test
	public static fromPropertyValueRecord( record: Record<string, unknown> ): StatementList {
		return new StatementList(
			Object.entries( record )
				.map( ( [ key, value ] ) => new Statement( new PropertyName( key ), value ) )
		);
	}

	public async getReferencedSubjects( lookup: SubjectLookup ): Promise<SubjectMap> {
		return new SubjectMap(
			...await Promise.all(
				// TODO: error handling: silently ignore missing subjects?
				this.getIdsOfReferencedSubjects().map( ( id ) => lookup.getSubject( id ) )
			)
		);
	}

	public getIdsOfReferencedSubjects(): SubjectId[] {
		const ids: SubjectId[] = [];

		// TODO: use schema information to determine which properties are references.
		// Or... use type information inside the subject if we decided to include it.
		for ( const statement of this ) {
			const value = Array.isArray( statement.value ) ? statement.value : [ statement.value ];

			for ( const relation of value ) {
				if ( typeof relation === 'object' && relation.target ) {
					ids.push( new SubjectId( relation.target ) );
				}
			}
		}

		return ids;
	}

}
