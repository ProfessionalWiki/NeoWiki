import { describe, expect, it } from 'vitest';
import { DependentSubjectLoader } from '@/application/DependentSubjectLoader.ts';
import { InMemorySchemaLookup } from '@/application/SchemaLookup.ts';
import { PropertyDefinitionList } from '@/domain/PropertyDefinitionList.ts';
import { newRelationProperty, RelationType } from '@/domain/propertyTypes/Relation.ts';
import { PageIdentifiers } from '@/domain/PageIdentifiers.ts';
import { PropertyName } from '@/domain/PropertyDefinition.ts';
import { Statement } from '@/domain/Statement.ts';
import { StatementList } from '@/domain/StatementList.ts';
import { newRelation, RelationValue } from '@/domain/Value.ts';
import { SubjectId } from '@/domain/SubjectId.ts';
import type { Schema } from '@/domain/Schema.ts';
import type { Subject } from '@/domain/Subject.ts';
import { newSchema, newSubject, unresolvedPage } from '@/TestHelpers.ts';
import { PageSubjects } from '@/domain/PageSubjects.ts';
import type { DeserializedPageSubjects } from '@/persistence/PageSubjectsDeserializer.ts';

const HOST_PAGE = new PageIdentifiers( 1, 'Pablo Picasso' );
const OTHER_PAGE = new PageIdentifiers( 2, 'Elsewhere' );

const HOST_ID = 's1hostAAAAAAAA1';
const BIRTH_ID = 's1birthAAAAAAA1';
const RECORD_ID = 's1recordAAAAAA1';
const PLACE_ID = 's1spotAAAAAAAA1';
// What the loader mints, in order.
const FIRST_MINTED_ID = 's1mintedAAAAAA1';
const SECOND_MINTED_ID = 's1mintedAAAAAA2';

type RelationAttributes = Parameters<typeof newRelationProperty>[ 0 ];

function schemaOf( name: string, subjectsAreDependent: boolean, ...relations: RelationAttributes[] ): Schema {
	return newSchema( {
		title: name,
		subjectsAreDependent,
		properties: new PropertyDefinitionList( relations.map( ( relation ) => newRelationProperty( relation ) ) ),
	} );
}

const placeSchema = schemaOf( 'Place', false );
const recordSchema = schemaOf( 'Record', true );
const nameSchema = schemaOf( 'Name', true );
const pseudonymSchema = schemaOf( 'Pseudonym', true );
const birthSchema = schemaOf( 'Birth', true,
	{ name: 'Place', targetSchemas: [ 'Place' ] },
	{ name: 'Record', targetSchemas: [ 'Record' ] },
);
const personSchema = schemaOf( 'Person', false,
	{ name: 'Birth', targetSchemas: [ 'Birth' ] },
	{ name: 'Names', targetSchemas: [ 'Name', 'Pseudonym' ], multiple: true },
	{ name: 'Residence', targetSchemas: [ 'Place' ] },
);

const SCHEMAS = [ placeSchema, recordSchema, nameSchema, pseudonymSchema, birthSchema, personSchema ];

function subjectOf( id: string, schemaName: string, page: PageIdentifiers, relations: Record<string, string> = {} ): Subject {
	return newSubject( {
		id,
		label: null,
		schemaName,
		pageIdentifiers: page,
		statements: new StatementList( Object.entries( relations ).map( ( [ property, target ] ) => new Statement(
			new PropertyName( property ),
			RelationType.typeName,
			new RelationValue( [ newRelation( undefined, target ) ] ),
		) ) ),
	} );
}

class FakeSubjectRepository {

	private readonly subjects: Map<string, Subject>;

	private readonly unminted = [ FIRST_MINTED_ID, SECOND_MINTED_ID ];

	public readonly pageReads: number[] = [];

	public async mintSubjectId(): Promise<SubjectId> {
		return new SubjectId( this.unminted.shift() as string );
	}

	public constructor( subjects: Subject[] ) {
		this.subjects = new Map( subjects.map( ( subject ) => [ subject.getId().text, subject ] ) );
	}

	public async getPageSubjects( pageId: number ): Promise<DeserializedPageSubjects> {
		this.pageReads.push( pageId );

		if ( !Number.isInteger( pageId ) ) {
			throw new Error( 'Error fetching page subjects' );
		}

		const subjects = [ ...this.subjects.values() ];

		// As the REST read expands relations: the Subjects of other pages come along as referenced ones.
		return {
			pageSubjects: new PageSubjects( pageId, null, subjects.filter( ( subject ) => onPage( subject, pageId ) ) ),
			referencedSubjects: subjects.filter( ( subject ) => !onPage( subject, pageId ) ),
			notFoundTargetIds: [],
			schemas: [],
		};
	}

}

function onPage( subject: Subject, pageId: number ): boolean {
	return subject.getPageIdentifiers().getPageId() === pageId;
}

class CountingSchemaLookup extends InMemorySchemaLookup {

	public readonly schemaReads: string[] = [];

	public constructor( schemas: Schema[], private readonly unloadable: string[] = [] ) {
		super( schemas );
	}

	public override async getSchema( schemaName: string ): Promise<Schema> {
		this.schemaReads.push( schemaName );

		if ( this.unloadable.includes( schemaName ) ) {
			throw new Error( `Schema ${ schemaName } does not load` );
		}

		return super.getSchema( schemaName );
	}

}

function newLoader(
	stored: Subject[] = [],
	schemas: Schema[] = SCHEMAS,
	repository = new FakeSubjectRepository( stored ),
	schemaLookup = new CountingSchemaLookup( schemas ),
): DependentSubjectLoader {
	return new DependentSubjectLoader(
		repository,
		schemaLookup,
		async () => ( await schemaLookup.getSchemaSummaries( null, schemas.length ) ).schemas,
	);
}

function idsOf( subjects: readonly Subject[] ): string[] {
	return subjects.map( ( subject ) => subject.getId().text );
}

function namesOf( schemas: readonly Schema[] ): string[] {
	return schemas.map( ( schema ) => schema.getName() ).sort();
}

function targetIdsOf( subject: Subject, propertyName: string ): string[] {
	const name = new PropertyName( propertyName );
	const value = subject.getStatements().has( name ) ? subject.getStatements().get( name ).value : undefined;

	return value instanceof RelationValue ? value.relations.map( ( relation ) => relation.target.text ) : [];
}

describe( 'DependentSubjectLoader', () => {

	describe( 'loadFor', () => {

		it( 'reads the Dependent Subjects the Subject points at, as its page holds them', async () => {
			const birth = subjectOf( BIRTH_ID, 'Birth', HOST_PAGE );
			const host = subjectOf( HOST_ID, 'Person', HOST_PAGE, { Birth: BIRTH_ID } );

			const loaded = await newLoader( [ birth ] ).loadFor( host, personSchema, new Map() );

			expect( loaded.subjects ).toEqual( [ birth ] );
		} );

		it( 'reads the Dependent Subjects of the Dependent Subjects', async () => {
			const birth = subjectOf( BIRTH_ID, 'Birth', HOST_PAGE, { Record: RECORD_ID } );
			const record = subjectOf( RECORD_ID, 'Record', HOST_PAGE );
			const host = subjectOf( HOST_ID, 'Person', HOST_PAGE, { Birth: BIRTH_ID } );

			const loaded = await newLoader( [ birth, record ] ).loadFor( host, personSchema, new Map() );

			expect( idsOf( loaded.subjects ) ).toEqual( [ BIRTH_ID, RECORD_ID ] );
		} );

		it( 'leaves out a target stored on another page', async () => {
			const birth = subjectOf( BIRTH_ID, 'Birth', OTHER_PAGE );
			const host = subjectOf( HOST_ID, 'Person', HOST_PAGE, { Birth: BIRTH_ID } );

			const loaded = await newLoader( [ birth ] ).loadFor( host, personSchema, new Map() );

			expect( loaded.subjects ).toEqual( [] );
		} );

		it( 'leaves out a target whose Schema is not dependent', async () => {
			const place = subjectOf( PLACE_ID, 'Place', HOST_PAGE );
			const host = subjectOf( HOST_ID, 'Person', HOST_PAGE, { Birth: PLACE_ID } );

			const loaded = await newLoader( [ place ] ).loadFor( host, personSchema, new Map() );

			expect( loaded.subjects ).toEqual( [] );
		} );

		it( 'reads the Schemas of the Dependent Subjects every relation field targets', async () => {
			const host = subjectOf( HOST_ID, 'Person', HOST_PAGE );

			const loaded = await newLoader().loadFor( host, personSchema, new Map() );

			expect( namesOf( loaded.schemas ) ).toEqual( expect.arrayContaining( [ 'Birth', 'Name', 'Person', 'Pseudonym', 'Record' ] ) );
		} );

		it( 'reads the page of the Subject once, however many Dependent Subjects it holds', async () => {
			const birth = subjectOf( BIRTH_ID, 'Birth', HOST_PAGE, { Record: RECORD_ID } );
			const record = subjectOf( RECORD_ID, 'Record', HOST_PAGE );
			const host = subjectOf( HOST_ID, 'Person', HOST_PAGE, { Birth: BIRTH_ID } );
			const repository = new FakeSubjectRepository( [ birth, record ] );

			await newLoader( [], SCHEMAS, repository ).loadFor( host, personSchema, new Map() );

			expect( repository.pageReads ).toEqual( [ HOST_PAGE.getPageId() ] );
		} );

		it( 'reads no page for a Subject that points at no Dependent Subject', async () => {
			const place = subjectOf( PLACE_ID, 'Place', HOST_PAGE );
			const host = subjectOf( HOST_ID, 'Person', HOST_PAGE, { Residence: PLACE_ID } );
			const repository = new FakeSubjectRepository( [ place ] );

			await newLoader( [], SCHEMAS, repository ).loadFor( host, personSchema, new Map() );

			expect( repository.pageReads ).toEqual( [] );
		} );

		it( 'reads no Dependent Subjects for a Subject whose page did not resolve', async () => {
			const host = subjectOf( HOST_ID, 'Person', unresolvedPage(), { Birth: BIRTH_ID } );

			const loaded = await newLoader().loadFor( host, personSchema, new Map() );

			expect( loaded.subjects ).toEqual( [] );
		} );

		it( 'reads no Schema of a relation field whose target Schemas are standalone', async () => {
			const host = subjectOf( HOST_ID, 'Person', HOST_PAGE );
			const schemaLookup = new CountingSchemaLookup( SCHEMAS );

			await newLoader( [], SCHEMAS, undefined, schemaLookup ).loadFor( host, personSchema, new Map() );

			expect( schemaLookup.schemaReads ).not.toContain( 'Place' );
		} );

		it( 'leaves out a target Schema that does not load', async () => {
			const host = subjectOf( HOST_ID, 'Person', HOST_PAGE );
			const schemaLookup = new CountingSchemaLookup( SCHEMAS, [ 'Name' ] );

			const loaded = await newLoader( [], SCHEMAS, undefined, schemaLookup ).loadFor( host, personSchema, new Map() );

			expect( namesOf( loaded.schemas ) ).toEqual( expect.arrayContaining( [ 'Birth', 'Person', 'Pseudonym', 'Record' ] ) );
			expect( namesOf( loaded.schemas ) ).not.toContain( 'Name' );
		} );

	} );

	describe( 'startFor', () => {

		const requiringSchema = schemaOf( 'Person', false,
			{ name: 'Birth', targetSchemas: [ 'Birth' ], required: true },
		);
		const schemasBesidePerson = SCHEMAS.filter( ( each ) => each !== personSchema );

		it( 'starts a required property with one dependent target Schema with a new Dependent Subject', async () => {
			const host = subjectOf( HOST_ID, 'Person', HOST_PAGE );

			const started = await newLoader( [], [ requiringSchema, ...schemasBesidePerson ] )
				.startFor( host, requiringSchema, new Map() );

			expect( targetIdsOf( started.subject, 'Birth' ) ).toEqual( [ FIRST_MINTED_ID ] );
			expect( idsOf( started.dependentSubjects.subjects ) ).toEqual( [ FIRST_MINTED_ID ] );
			expect( started.dependentSubjects.subjects[ 0 ].getSchemaName() ).toBe( 'Birth' );
			expect( started.dependentSubjects.subjects[ 0 ].getLabel() ).toBeNull();
		} );

		it.each( [
			[ 'an optional property', { name: 'Birth', targetSchemas: [ 'Birth' ] } ],
			[ 'one with several target Schemas', { name: 'Names', targetSchemas: [ 'Name', 'Pseudonym' ], required: true } ],
			[ 'one whose target Schema is standalone', { name: 'Residence', targetSchemas: [ 'Place' ], required: true } ],
		] )( 'starts %s with nothing', async ( _case, relation ) => {
			const startingSchema = schemaOf( 'Person', false, relation );
			const host = subjectOf( HOST_ID, 'Person', HOST_PAGE );

			// The editor hands in the Schemas it holds already, a standalone one among them.
			const started = await newLoader( [], [ startingSchema, ...schemasBesidePerson ] )
				.startFor( host, startingSchema, new Map( [ [ 'Place', placeSchema ] ] ) );

			expect( started.subject ).toEqual( host );
			expect( started.dependentSubjects.subjects ).toEqual( [] );
		} );

		it( 'starts nothing where the Subject already holds a value', async () => {
			const host = subjectOf( HOST_ID, 'Person', HOST_PAGE, { Birth: BIRTH_ID } );

			const started = await newLoader( [], [ requiringSchema, ...schemasBesidePerson ] )
				.startFor( host, requiringSchema, new Map() );

			expect( targetIdsOf( started.subject, 'Birth' ) ).toEqual( [ BIRTH_ID ] );
			expect( started.dependentSubjects.subjects ).toEqual( [] );
		} );

		it( 'starts the Dependent Subjects it starts with their own required ones', async () => {
			const birthRequiringRecord = schemaOf( 'Birth', true, { name: 'Record', targetSchemas: [ 'Record' ], required: true } );
			const host = subjectOf( HOST_ID, 'Person', HOST_PAGE );

			const started = await newLoader( [], [ requiringSchema, birthRequiringRecord, recordSchema ] )
				.startFor( host, requiringSchema, new Map() );

			const [ birth, record ] = started.dependentSubjects.subjects;
			expect( targetIdsOf( birth, 'Record' ) ).toEqual( [ record.getId().text ] );
			expect( record.getSchemaName() ).toBe( 'Record' );
		} );

		it( 'starts no Dependent Subject inside one of its own Schema', async () => {
			const sectionSchema = schemaOf( 'Section', true, { name: 'Subsection', targetSchemas: [ 'Section' ], required: true } );
			const bookSchema = schemaOf( 'Book', false, { name: 'Section', targetSchemas: [ 'Section' ], required: true } );
			const host = subjectOf( HOST_ID, 'Book', HOST_PAGE );

			const started = await newLoader( [], [ bookSchema, sectionSchema ] ).startFor( host, bookSchema, new Map() );

			expect( idsOf( started.dependentSubjects.subjects ) ).toEqual( [ FIRST_MINTED_ID ] );
		} );

	} );

	describe( 'newDependentSubject', () => {

		it( 'adds one of the Schema under a minted id, with the ones it starts with', async () => {
			const birthRequiringRecord = schemaOf( 'Birth', true, { name: 'Record', targetSchemas: [ 'Record' ], required: true } );
			const schemas = new Map<string, Schema | null>( [ [ 'Birth', birthRequiringRecord ], [ 'Record', recordSchema ] ] );

			const [ birth, record ] = await newLoader().newDependentSubject( birthRequiringRecord, HOST_PAGE, schemas );

			expect( birth.getId().text ).toBe( FIRST_MINTED_ID );
			expect( birth.getLabel() ).toBeNull();
			expect( targetIdsOf( birth, 'Record' ) ).toEqual( [ record.getId().text ] );
		} );

	} );

} );
