import { Subject } from '@/domain/Subject';
import { SubjectId } from '@/domain/SubjectId';
import type { Schema } from '@/domain/Schema';
import type { SubjectRepository } from '@/domain/SubjectRepository';
import type { PageIdentifiers } from '@/domain/PageIdentifiers';
import { Statement } from '@/domain/Statement';
import { StatementList } from '@/domain/StatementList';
import { type SchemaLookup, type SchemaSummary, subjectsAreDependentIn } from '@/application/SchemaLookup';
import { isValueEmpty, newRelation, RelationValue } from '@/domain/Value';
import {
	localTargetSchemaNames,
	propertiesTargetingDependentSubjects,
	type RelationProperty,
	RelationType,
	relationProperties,
} from '@/domain/propertyTypes/Relation';

/**
 * The Dependent Subjects an editor shows inline, and the Schemas it needs for them, however deep they nest.
 */
export interface DependentSubjectsForEditing {
	readonly subjects: readonly Subject[];
	readonly schemas: readonly Schema[];
}

/**
 * By name; null for a name that resolves to no Schema.
 */
export type LoadedSchemas = ReadonlyMap<string, Schema | null>;

export class DependentSubjectLoader {

	public constructor(
		private readonly subjectRepository: Pick<SubjectRepository, 'getPageSubjects' | 'mintSubjectId'>,
		private readonly schemaLookup: Pick<SchemaLookup, 'getSchema'>,
		private readonly schemaSummaries: () => Promise<readonly SchemaSummary[]>,
	) {
	}

	/**
	 * For a Subject the wiki holds, from the latest revision of its page. A target that is not a Dependent
	 * Subject on that page is left out: a save could not write it along.
	 */
	public async loadFor( subject: Subject, schema: Schema, known: LoadedSchemas ): Promise<DependentSubjectsForEditing> {
		const schemas = await this.loadSchemas( [ schema ], known );
		const subjects = dependentTargetIds( subject, schemas ).length === 0 ?
			[] :
			await this.loadStoredDependentSubjects( subject, schemas );

		return { subjects, schemas: loadedSchemasOf( schemas ) };
	}

	/**
	 * For a Subject the wiki does not hold yet: each required relation property whose only target Schema has
	 * Dependent Subjects starts with one. The Subject answered points at them.
	 */
	public async startFor(
		subject: Subject,
		schema: Schema,
		known: LoadedSchemas,
	): Promise<{ subject: Subject; dependentSubjects: DependentSubjectsForEditing }> {
		const schemas = await this.loadSchemas( [ schema ], known );
		const started = await this.withStartingDependentSubjects( subject, schema, schemas, [ schema.getName() ] );

		return {
			subject: started.subject,
			dependentSubjects: { subjects: started.added, schemas: loadedSchemasOf( schemas ) },
		};
	}

	/**
	 * The new Dependent Subject first, then the ones it starts with. Expects the Schemas they need among
	 * `schemas`.
	 */
	public async newDependentSubject( schema: Schema, page: PageIdentifiers, schemas: LoadedSchemas ): Promise<Subject[]> {
		return this.newDependentSubjectWithin( schema, page, schemas, [ schema.getName() ] );
	}

	/**
	 * Adds the Schemas of the Dependent Subjects that relation properties target, however deep they nest. A
	 * Schema that does not load maps to null.
	 */
	public async loadSchemas( start: readonly Schema[], known: LoadedSchemas ): Promise<Map<string, Schema | null>> {
		const schemas = new Map( known );
		const expanded = new Set<string>();
		let subjectsAreDependent: ( ( schemaName: string ) => boolean | undefined ) | null = null;
		let frontier = [ ...start ];

		start.forEach( ( schema ) => schemas.set( schema.getName(), schema ) );

		while ( frontier.length > 0 ) {
			frontier.forEach( ( schema ) => expanded.add( schema.getName() ) );

			const targetNames = [ ...new Set( frontier.flatMap(
				( schema ) => relationProperties( schema ).flatMap( localTargetSchemaNames ),
			) ) ];
			const unknownNames = targetNames.filter( ( name ) => !schemas.has( name ) );

			if ( unknownNames.length > 0 ) {
				subjectsAreDependent ??= subjectsAreDependentIn( await this.schemaSummaries() );
				await this.loadMissingSchemas( unknownNames, subjectsAreDependent, schemas );
			}

			frontier = targetNames
				.map( ( name ) => schemas.get( name ) )
				.filter( ( schema ): schema is Schema =>
					schema !== undefined && schema !== null && schema.subjectsAreDependent() && !expanded.has( schema.getName() ) );
		}

		return schemas;
	}

	private async loadMissingSchemas(
		names: readonly string[],
		subjectsAreDependent: ( schemaName: string ) => boolean | undefined,
		schemas: Map<string, Schema | null>,
	): Promise<void> {
		names.filter( ( name ) => subjectsAreDependent( name ) === undefined ).forEach( ( name ) => schemas.set( name, null ) );

		const dependentNames = names.filter( ( name ) => subjectsAreDependent( name ) === true );
		const loaded = await Promise.all( dependentNames.map( ( name ) => this.schemaOrNull( name ) ) );

		dependentNames.forEach( ( name, index ) => schemas.set( name, loaded[ index ] ) );
	}

	private async schemaOrNull( name: string ): Promise<Schema | null> {
		try {
			return await this.schemaLookup.getSchema( name );
		} catch {
			return null;
		}
	}

	private async loadStoredDependentSubjects( host: Subject, schemas: LoadedSchemas ): Promise<Subject[]> {
		const pageId = host.getPageIdentifiers().getPageId();

		if ( !Number.isInteger( pageId ) ) {
			return [];
		}

		const page = await this.subjectRepository.getPageSubjects( pageId );
		const seen = new Set( [ host.getId().text ] );
		const loaded: Subject[] = [];
		let frontier = [ host ];

		while ( frontier.length > 0 ) {
			const ids = [ ...new Set( frontier.flatMap( ( subject ) => dependentTargetIds( subject, schemas ) ) ) ]
				.filter( ( id ) => !seen.has( id ) );

			ids.forEach( ( id ) => seen.add( id ) );

			frontier = ids
				.map( ( id ) => page.pageSubjects.getSubject( new SubjectId( id ) ) )
				.filter( ( subject ): subject is Subject =>
					subject !== undefined && schemas.get( subject.getSchemaName() )?.subjectsAreDependent() === true );

			loaded.push( ...frontier );
		}

		return loaded;
	}

	private async withStartingDependentSubjects(
		subject: Subject,
		schema: Schema,
		schemas: LoadedSchemas,
		ancestorSchemaNames: readonly string[],
	): Promise<{ subject: Subject; added: Subject[] }> {
		let started = subject;
		const added: Subject[] = [];

		for ( const property of relationProperties( schema ) ) {
			const target = startingTargetSchema( property, started, schemas );

			// A Schema the chain already started would start itself again, without end.
			if ( target === null || ancestorSchemaNames.includes( target.getName() ) ) {
				continue;
			}

			const [ dependentSubject, ...nested ] = await this.newDependentSubjectWithin(
				target,
				subject.getPageIdentifiers(),
				schemas,
				[ ...ancestorSchemaNames, target.getName() ],
			);

			started = started.withStatements( started.getStatements().withStatement( new Statement(
				property.name,
				RelationType.typeName,
				new RelationValue( [ newRelation( undefined, dependentSubject.getId() ) ] ),
			) ) );
			added.push( dependentSubject, ...nested );
		}

		return { subject: started, added };
	}

	private async newDependentSubjectWithin(
		schema: Schema,
		page: PageIdentifiers,
		schemas: LoadedSchemas,
		ancestorSchemaNames: readonly string[],
	): Promise<Subject[]> {
		const id = await this.subjectRepository.mintSubjectId();
		const blank = new Subject(
			id,
			null,
			// What the server derives for a Subject with no label of its own.
			schema.getName(),
			true,
			schema.getName(),
			new StatementList( [] ),
			page,
			null,
		);

		const started = await this.withStartingDependentSubjects( blank, schema, schemas, ancestorSchemaNames );

		return [ started.subject, ...started.added ];
	}

}

function dependentTargetIds( subject: Subject, schemas: LoadedSchemas ): string[] {
	const schema = schemas.get( subject.getSchemaName() );

	if ( schema === undefined || schema === null ) {
		return [];
	}

	return propertiesTargetingDependentSubjects( schema, ( schemaName ) => schemas.get( schemaName )?.subjectsAreDependent() )
		.flatMap( ( property ) => {
			const value = subject.getStatements().has( property.name ) ?
				subject.getStatements().get( property.name ).value :
				undefined;

			return value instanceof RelationValue ? value.relations.map( ( relation ) => relation.target.text ) : [];
		} );
}

function startingTargetSchema( property: RelationProperty, subject: Subject, schemas: LoadedSchemas ): Schema | null {
	const targetNames = localTargetSchemaNames( property );

	if ( !property.required || targetNames.length !== 1 ) {
		return null;
	}

	const target = schemas.get( targetNames[ 0 ] );
	const value = subject.getStatements().has( property.name ) ? subject.getStatements().get( property.name ).value : undefined;

	return target?.subjectsAreDependent() === true && isValueEmpty( value ) ? target : null;
}

export function loadedSchemasOf( schemas: LoadedSchemas ): Schema[] {
	return [ ...schemas.values() ].filter( ( schema ): schema is Schema => schema !== null );
}
