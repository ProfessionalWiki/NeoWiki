import { beforeEach, describe, expect, it } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import { StoreStateLoader } from '@/persistence/StoreStateLoader';
import { StubSubjectRepository } from '@/domain/SubjectRepository';
import type { SubjectWithReferencedSubjects } from '@/domain/SubjectRepository';
import { InMemorySchemaRepository } from '@/application/SchemaRepository';
import { InMemoryLayoutLookup } from '@/application/LayoutLookup';
import { SubjectId } from '@/domain/SubjectId';
import { Subject } from '@/domain/Subject';
import { newSchema, newSubject } from '@/TestHelpers';
import { useSubjectStore } from '@/stores/SubjectStore';
import { useSchemaStore } from '@/stores/SchemaStore';
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
				type: RelationType.typeName,
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

} );
