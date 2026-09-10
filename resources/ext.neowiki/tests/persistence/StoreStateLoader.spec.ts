import { beforeEach, describe, expect, it, vi } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import { StoreStateLoader } from '@/persistence/StoreStateLoader';
import { StubSubjectRepository } from '@/domain/SubjectRepository';
import type { SubjectRepository, SubjectWithReferencedSubjects } from '@/domain/SubjectRepository';
import { InMemorySchemaRepository } from '@/application/SchemaRepository';
import type { SchemaRepository } from '@/application/SchemaRepository';
import { InMemoryLayoutLookup } from '@/application/LayoutLookup';
import type { LayoutLookup } from '@/application/LayoutLookup';
import { SubjectId } from '@/domain/SubjectId';
import { Subject } from '@/domain/Subject';
import { Layout } from '@/domain/Layout';
import { newSchema, newSubject } from '@/TestHelpers';
import { useSubjectStore } from '@/stores/SubjectStore';
import { useSchemaStore } from '@/stores/SchemaStore';
import { useLayoutStore } from '@/stores/LayoutStore';
import { RelationType } from '@/domain/propertyTypes/Relation';
import { Neo } from '@/Neo';

const mainId = new SubjectId( 's11111111111111' );
const referencedId1 = new SubjectId( 's22222222222222' );
const referencedId2 = new SubjectId( 's33333333333333' );

/**
 * Records how the loader reaches the repository so tests can assert that
 * referenced Subjects come from the single bundle rather than per-target fetches.
 */
class RecordingSubjectRepository extends StubSubjectRepository {

	public getSubjectCallCount = 0;

	public getSubjectWithReferencedSubjectsCallCount = 0;

	public constructor( private readonly bundle: SubjectWithReferencedSubjects ) {
		super( [ bundle.requestedSubject, ...bundle.referencedSubjects ] );
	}

	public override async getSubject( id: SubjectId ): Promise<Subject> {
		this.getSubjectCallCount++;
		return super.getSubject( id );
	}

	public override async getSubjectWithReferencedSubjects( _id: SubjectId ): Promise<SubjectWithReferencedSubjects> {
		this.getSubjectWithReferencedSubjectsCallCount++;
		return this.bundle;
	}

}

function newMainSubjectWithRelationsTo( ...targets: SubjectId[] ): Subject {
	return newSubject( {
		id: mainId,
		schemaName: 'Company',
		statements: Neo.getInstance().getSubjectDeserializer().deserializeStatements( {
			Products: {
				value: targets.map( ( target ) => ( { target: target.text } ) ),
				propertyType: RelationType.typeName,
			},
		} ),
	} );
}

function newLoader( repository: RecordingSubjectRepository ): StoreStateLoader {
	return new StoreStateLoader(
		repository,
		new InMemorySchemaRepository( [ newSchema( { title: 'Company' } ) ] ),
		new InMemoryLayoutLookup( [] ),
	);
}

describe( 'StoreStateLoader', () => {

	beforeEach( () => {
		setActivePinia( createPinia() );
	} );

	it( 'stores the requested Subject and its referenced Subjects', async () => {
		const main = newMainSubjectWithRelationsTo( referencedId1, referencedId2 );
		const referenced1 = newSubject( { id: referencedId1, label: 'Product One', schemaName: 'Product' } );
		const referenced2 = newSubject( { id: referencedId2, label: 'Product Two', schemaName: 'Product' } );
		const repository = new RecordingSubjectRepository(
			{ requestedSubject: main, referencedSubjects: [ referenced1, referenced2 ] },
		);

		await newLoader( repository ).loadSubjectsAndSchemas( new Set( [ mainId.text ] ) );

		const subjectStore = useSubjectStore();
		expect( subjectStore.getSubject( mainId ) ).toEqual( main );
		expect( subjectStore.getSubject( referencedId1 ) ).toEqual( referenced1 );
		expect( subjectStore.getSubject( referencedId2 ) ).toEqual( referenced2 );
	} );

	it( 'loads referenced Subjects from the bundle without re-fetching them individually', async () => {
		const main = newMainSubjectWithRelationsTo( referencedId1, referencedId2 );
		const referenced1 = newSubject( { id: referencedId1, label: 'Product One', schemaName: 'Product' } );
		const referenced2 = newSubject( { id: referencedId2, label: 'Product Two', schemaName: 'Product' } );
		const repository = new RecordingSubjectRepository(
			{ requestedSubject: main, referencedSubjects: [ referenced1, referenced2 ] },
		);

		await newLoader( repository ).loadSubjectsAndSchemas( new Set( [ mainId.text ] ) );

		expect( repository.getSubjectWithReferencedSubjectsCallCount ).toBe( 1 );
		expect( repository.getSubjectCallCount ).toBe( 0 );
	} );

	it( 'stores the schema of the requested Subject', async () => {
		const main = newMainSubjectWithRelationsTo( referencedId1 );
		const referenced1 = newSubject( { id: referencedId1, label: 'Product One', schemaName: 'Product' } );
		const repository = new RecordingSubjectRepository(
			{ requestedSubject: main, referencedSubjects: [ referenced1 ] },
		);

		await newLoader( repository ).loadSubjectsAndSchemas( new Set( [ mainId.text ] ) );

		expect( useSchemaStore().getSchema( 'Company' ) ).toEqual( newSchema( { title: 'Company' } ) );
	} );

	it( 'discards layout write-backs that a mutation overtook', async () => {
		let resolveLayout!: ( layout: Layout ) => void;
		const pending = new Promise<Layout>( ( resolve ) => {
			resolveLayout = resolve;
		} );
		const loader = new StoreStateLoader(
			{} as unknown as SubjectRepository,
			{} as unknown as SchemaRepository,
			{ getLayout: () => pending } as unknown as LayoutLookup,
		);
		const layoutStore = useLayoutStore();

		const loading = loader.loadLayouts( new Set( [ 'CompanyInfo' ] ) );
		layoutStore.removeLayout( 'CompanyInfo' );
		resolveLayout( new Layout( 'CompanyInfo', 'Company', 'infobox', '', [], {} ) );
		await loading;

		expect( layoutStore.getLayout( 'CompanyInfo' ) ).toBeUndefined();
	} );

	it( 'discards subject and schema write-backs that mutations overtook', async () => {
		const subject = newSubject( { id: 's11111111111111' } );
		let resolveSubjects!: ( value: unknown ) => void;
		const pending = new Promise( ( resolve ) => {
			resolveSubjects = resolve;
		} );
		const loader = new StoreStateLoader(
			{ getSubjectWithReferencedSubjects: () => pending } as unknown as SubjectRepository,
			{ getSchema: vi.fn().mockResolvedValue( newSchema() ) } as unknown as SchemaRepository,
			{} as unknown as LayoutLookup,
		);
		const subjectStore = useSubjectStore();
		const schemaStore = useSchemaStore();

		const loading = loader.loadSubjectsAndSchemas( new Set( [ 's11111111111111' ] ) );
		subjectStore.mutationEpoch++; // Stands in for any acknowledged subject mutation — SubjectStore has no repo-free mutating action.
		schemaStore.removeSchema( subject.getSchemaName() );
		resolveSubjects( { requestedSubject: subject, referencedSubjects: [] } );
		await loading;

		expect( subjectStore.subjects.has( 's11111111111111' ) ).toBe( false );
		expect( () => schemaStore.getSchema( subject.getSchemaName() ) ).toThrow();
	} );

} );
