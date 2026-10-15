import { afterEach, beforeEach, describe, expect, it, vi, type Mock } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import { useSubjectStore } from '@/stores/SubjectStore';
import { NeoWikiExtension } from '@/NeoWikiExtension';
import { newSubject, newSchema } from '@/TestHelpers.ts';
import { PageSubjects } from '@/domain/PageSubjects';
import { PageIdentifiers } from '@/domain/PageIdentifiers.ts';
import { SubjectId } from '@/domain/SubjectId';
import { Subject } from '@/domain/Subject';
import { useSchemaStore } from '@/stores/SchemaStore.ts';
import { StatementList } from '@/domain/StatementList.ts';
import { PageTitleTakenError } from '@/persistence/PageTitleTakenError';
import { SubjectNotFoundError } from '@/persistence/SubjectNotFoundError';
import { setupMwMock } from '../VueTestHelpers.ts';
import type { Schema } from '@/domain/Schema.ts';

describe( 'SubjectStore — subjectCreatorOpen', () => {
	beforeEach( () => {
		setActivePinia( createPinia() );
	} );

	it( 'starts closed by default', () => {
		const store = useSubjectStore();
		expect( store.subjectCreatorOpen ).toBe( false );
	} );

	it( 'opens the creator when openSubjectCreator is called', () => {
		const store = useSubjectStore();
		store.openSubjectCreator();
		expect( store.subjectCreatorOpen ).toBe( true );
	} );

	it( 'closes the creator when closeSubjectCreator is called', () => {
		const store = useSubjectStore();
		store.openSubjectCreator();
		store.closeSubjectCreator();
		expect( store.subjectCreatorOpen ).toBe( false );
	} );

	it( 'opens the creator on no Subject in particular after one it was opened on', () => {
		const store = useSubjectStore();
		store.openSubjectCreatorFor( new SubjectId( 's33333333333333' ), [ 'City' ] );

		store.openSubjectCreator();

		expect( store.subjectCreatorTarget ).toBeNull();
	} );
} );

function writeResult( subject: Subject | null, schema: Schema | null = null ): Record<string, unknown> {
	return { subjectId: new SubjectId( 's11111111111111' ), subject, schema };
}

function pageRead( pageId: number, ...subjects: Subject[] ): Record<string, unknown> {
	return {
		pageSubjects: new PageSubjects( pageId, null, subjects ),
		referencedSubjects: [],
		notFoundTargetIds: [],
		schemas: [],
	};
}

function withSubjectRepository( repository: Record<string, unknown> ): void {
	vi.spyOn( NeoWikiExtension, 'getInstance' ).mockReturnValue(
		{ getSubjectRepository: () => repository } as unknown as NeoWikiExtension,
	);
}

const REPOSITORY_WRITE_PARAMETERS = {
	createMainSubject: [ 'pageId', 'label', 'schemaName', 'statements', 'dependentSubjects', 'comment', 'id' ],
	createOtherSubject: [ 'pageId', 'label', 'schemaName', 'statements', 'dependentSubjects', 'comment', 'id' ],
	createSubjectPage: [ 'label', 'schemaName', 'statements', 'dependentSubjects', 'comment', 'pageTitle', 'id' ],
	updateSubject: [ 'id', 'label', 'statements', 'dependentSubjects', 'comment' ],
};

function callsOf(
	repository: Partial<Record<keyof typeof REPOSITORY_WRITE_PARAMETERS, unknown>>,
	write: keyof typeof REPOSITORY_WRITE_PARAMETERS,
): Record<string, unknown>[] {
	return ( repository[ write ] as Mock ).mock.calls.map( ( args ) =>
		Object.fromEntries( REPOSITORY_WRITE_PARAMETERS[ write ].map( ( name, index ) => [ name, args[ index ] ] ) ) );
}

describe( 'SubjectStore createSubject', () => {

	const pageId = 7;
	const drafted = newSubject( { id: 's33333333333333', label: 'Anvil', schemaName: 'Product' } );

	beforeEach( () => {
		setActivePinia( createPinia() );
	} );

	afterEach( () => {
		vi.restoreAllMocks();
	} );

	function repositoryWriting( subjectFirst: boolean ): { createOtherSubject: ReturnType<typeof vi.fn>; createSubjectPage: ReturnType<typeof vi.fn> } {
		setupMwMock( { config: { wgNeoWikiSubjectFirst: subjectFirst } } );

		const repository = {
			createOtherSubject: vi.fn().mockResolvedValue( writeResult( null ) ),
			createSubjectPage: vi.fn().mockResolvedValue(
				{ ...writeResult( null ), pageTitle: 'Anvil', pageId: 12 },
			),
			getPageSubjects: vi.fn( ( readPageId: number ) => Promise.resolve( pageRead( readPageId ) ) ),
		};

		withSubjectRepository( repository );

		return repository;
	}

	it( 'stores the Subject on the page it was given on a page-first wiki', async () => {
		const repository = repositoryWriting( false );

		await useSubjectStore().createSubject( drafted, pageId, [], 'why' );

		expect( callsOf( repository, 'createOtherSubject' ) ).toContainEqual( expect.objectContaining( {
			pageId,
			label: 'Anvil',
			schemaName: 'Product',
			statements: drafted.getStatements(),
			dependentSubjects: [],
			comment: 'why',
			id: drafted.getId(),
		} ) );
		expect( repository.createSubjectPage ).not.toHaveBeenCalled();
	} );

	it( 'gives the Subject a page of its own on a subject-first wiki', async () => {
		const repository = repositoryWriting( true );

		await useSubjectStore().createSubject( drafted, pageId, [], 'why' );

		expect( callsOf( repository, 'createSubjectPage' ) ).toContainEqual( expect.objectContaining( {
			label: 'Anvil',
			schemaName: 'Product',
			statements: drafted.getStatements(),
			dependentSubjects: [],
			comment: 'why',
			pageTitle: undefined,
			id: drafted.getId(),
		} ) );
		expect( repository.createOtherSubject ).not.toHaveBeenCalled();
	} );

	// The wiki titles the page by the Subject's id, so a title it reports taken is not one a
	// second write could get round.
	it( 'does not retry a write refused for its title', async () => {
		const repository = repositoryWriting( true );
		repository.createSubjectPage.mockRejectedValueOnce( new PageTitleTakenError( 'Anvil' ) );

		await expect( useSubjectStore().createSubject( drafted, pageId, [], 'why' ) )
			.rejects.toBeInstanceOf( PageTitleTakenError );
		expect( repository.createSubjectPage ).toHaveBeenCalledTimes( 1 );
	} );

} );

describe( 'SubjectStore deleteSubject', () => {

	const pageId = 7;
	const kept = newSubject( { id: 's11111111111111', pageIdentifiers: new PageIdentifiers( pageId, 'Page' ) } );
	const doomed = newSubject( { id: 's22222222222222', pageIdentifiers: new PageIdentifiers( pageId, 'Page' ) } );

	beforeEach( () => {
		setActivePinia( createPinia() );
	} );

	afterEach( () => {
		vi.restoreAllMocks();
	} );

	it( 're-syncs pageSubjects from the backend after deleting a listed subject', async () => {
		const getPageSubjects = vi.fn().mockResolvedValue( {
			pageSubjects: new PageSubjects( pageId, null, [ kept ] ),
			referencedSubjects: [],
			notFoundTargetIds: [],
			schemas: [],
		} );
		withSubjectRepository( { deleteSubject: vi.fn().mockResolvedValue( true ), getPageSubjects } );
		const store = useSubjectStore();
		store.setSubject( kept );
		store.setSubject( doomed );
		store.pageSubjects = new PageSubjects( pageId, null, [ kept, doomed ] );

		await store.deleteSubject( doomed.getId() );

		expect( getPageSubjects ).toHaveBeenCalledWith( pageId );
		expect( store.pageSubjects?.getSubjects() ).toEqual( [ kept ] );
		// The registry entry is only dropped once pageSubjects itself no longer names the id
		// (see the deleteSubject invariant comment): confirm both sides of that invariant hold
		// after a successful re-sync — not just that the id is absent from the listing, but that
		// getSubject actually throws for it too.
		expect( store.subjects.has( doomed.getId().text ) ).toBe( false );
		expect( () => store.getSubject( doomed.getId() ) ).toThrow( 'Unknown subject: ' + doomed.getId().text );
	} );

	it( 'does not refetch the page listing when the subject is not listed on it', async () => {
		const getPageSubjects = vi.fn();
		withSubjectRepository( { deleteSubject: vi.fn().mockResolvedValue( true ), getPageSubjects } );
		const store = useSubjectStore();
		store.setSubject( doomed );
		store.pageSubjects = new PageSubjects( pageId, null, [ kept ] );

		await store.deleteSubject( doomed.getId() );

		expect( getPageSubjects ).not.toHaveBeenCalled();
	} );

	it( 'still resolves when the post-delete re-sync fetch fails, leaving the registry consistent', async () => {
		const consoleError = vi.spyOn( console, 'error' ).mockImplementation( () => undefined );
		const getPageSubjects = vi.fn().mockRejectedValue( new Error( 'sync failed' ) );
		withSubjectRepository( { deleteSubject: vi.fn().mockResolvedValue( true ), getPageSubjects } );
		const store = useSubjectStore();
		store.setSubject( kept );
		store.setSubject( doomed );
		const preDeleteListing = new PageSubjects( pageId, null, [ kept, doomed ] );
		store.pageSubjects = preDeleteListing;

		await expect( store.deleteSubject( doomed.getId() ) ).resolves.toBeUndefined();

		// Stale until the next refresh: the re-sync failed, so the listing still shows the
		// pre-delete state rather than being patched or cleared.
		expect( store.pageSubjects ).toStrictEqual( preDeleteListing );
		// The registry invariant (every id pageSubjects lists resolves via getSubject) requires
		// the subject to REMAIN in `subjects` here — removing it while pageSubjects still names it
		// would leave a ghost id that throws on the next render, which is exactly the live
		// Critical "Unknown subject" bug this test guards against.
		expect( store.subjects.has( doomed.getId().text ) ).toBe( true );
		expect( () => store.getSubject( doomed.getId() ) ).not.toThrow();

		consoleError.mockRestore();
	} );

	it( 'keeps every pageSubjects id resolvable via getSubject while the post-delete re-sync is in flight', async () => {
		let resolveGetPageSubjects!: ( value: unknown ) => void;
		const pending = new Promise( ( resolve ) => {
			resolveGetPageSubjects = resolve;
		} );
		const getPageSubjects = vi.fn().mockReturnValue( pending );
		withSubjectRepository( { deleteSubject: vi.fn().mockResolvedValue( true ), getPageSubjects } );
		const store = useSubjectStore();
		store.setSubject( kept );
		store.setSubject( doomed );
		store.pageSubjects = new PageSubjects( pageId, null, [ kept, doomed ] );

		const request = store.deleteSubject( doomed.getId() );

		// Let deleteSubject's own repo call resolve and hand off into the re-sync fetch, which
		// then hangs on our deferred. This is the exact window the live bug occupied: the DELETE
		// is acknowledged server-side but the re-sync has not landed yet. Two ticks is more than
		// the one hop actually needed and costs nothing, since nothing but the explicit resolve
		// below can advance `pending` further.
		await Promise.resolve();
		await Promise.resolve();

		// Sanity check that this is really the mid-flight window, not the post-resolve state.
		expect( store.pageSubjects?.getSubjects() ).toEqual( [ kept, doomed ] );
		// Every id pageSubjects still lists — including the one just deleted server-side — must
		// resolve via getSubject without throwing. SubjectsManagerPage's `subjects` computed maps
		// the listing through this exact getter on every render; a throw here is the live defect.
		for ( const subject of store.pageSubjects?.getSubjects() ?? [] ) {
			expect( () => store.getSubject( subject.getId() ) ).not.toThrow();
		}

		resolveGetPageSubjects( {
			pageSubjects: new PageSubjects( pageId, null, [ kept ] ),
			referencedSubjects: [],
			notFoundTargetIds: [],
			schemas: [],
		} );
		await request;

		expect( store.subjects.has( doomed.getId().text ) ).toBe( false );
		expect( store.pageSubjects?.getSubjects() ).toEqual( [ kept ] );
	} );

} );

describe( 'SubjectStore ordering guards', () => {

	beforeEach( () => {
		setActivePinia( createPinia() );
	} );

	afterEach( () => {
		vi.restoreAllMocks();
	} );

	it( 'discards a getOrFetchSubject miss-path fetch that a mutation overtook', async () => {
		const stale = newSubject( { id: 's11111111111111', label: 'stale' } );
		const updated = newSubject( { id: 's11111111111111', label: 'updated' } );
		let resolveFetch!: ( subject: Subject ) => void;
		const pending = new Promise<Subject>( ( resolve ) => {
			resolveFetch = resolve;
		} );
		withSubjectRepository( {
			getSubject: () => pending,
			updateSubject: vi.fn().mockResolvedValue( writeResult( updated ) ),
			getPageSubjects: vi.fn().mockResolvedValue( pageRead( 0, updated ) ),
		} );
		const store = useSubjectStore();

		const request = store.getOrFetchSubject( new SubjectId( 's11111111111111' ) );
		await store.updateSubject( updated, [] );
		resolveFetch( stale );

		expect( await request ).toStrictEqual( updated );
		expect( store.getSubject( new SubjectId( 's11111111111111' ) ) ).toStrictEqual( updated );
	} );

	it( 'does not fetch when getOrFetchSubject finds the subject in the registry', async () => {
		const seeded = newSubject( { id: 's11111111111111', label: 'seeded' } );
		const getSubject = vi.fn();
		withSubjectRepository( { getSubject } );
		const store = useSubjectStore();
		store.setSubject( seeded );

		const result = await store.getOrFetchSubject( new SubjectId( 's11111111111111' ) );

		expect( result ).toStrictEqual( seeded );
		expect( getSubject ).not.toHaveBeenCalled();
	} );

	it( 'discards loadPageSubjects subject writes when a mutation lands mid-flight', async () => {
		const onPage = new PageIdentifiers( 7, 'Page' );
		const fromServer = newSubject( { id: 's11111111111111', label: 'from-server', pageIdentifiers: onPage } );
		const updated = newSubject( { id: 's11111111111111', label: 'updated', pageIdentifiers: onPage } );
		let resolveLoad!: ( value: unknown ) => void;
		const pending = new Promise( ( resolve ) => {
			resolveLoad = resolve;
		} );
		withSubjectRepository( {
			getPageSubjects: vi.fn().mockReturnValueOnce( pending ).mockResolvedValue( pageRead( 7, updated ) ),
			updateSubject: vi.fn().mockResolvedValue( writeResult( updated ) ),
		} );
		const store = useSubjectStore();

		const request = store.loadPageSubjects( 7 );
		await store.updateSubject( updated, [] );
		resolveLoad( pageRead( 7, fromServer ) );
		await request;

		expect( store.pageSubjects?.getSubjects() ).toEqual( [ updated ] );
		expect( store.getSubject( new SubjectId( 's11111111111111' ) ) ).toStrictEqual( updated );
	} );

	it( 'discards loadPageSubjects schema writes when a schema mutation lands mid-flight', async () => {
		const fromServer = newSchema( { title: 'Person', description: 'from-server' } );
		const saved = newSchema( { title: 'Person', description: 'saved' } );
		let resolveLoad!: ( value: unknown ) => void;
		const pending = new Promise( ( resolve ) => {
			resolveLoad = resolve;
		} );
		const repositories = {
			getSubjectRepository: () => ( { getPageSubjects: () => pending } ),
			getSchemaRepository: () => ( { saveSchema: vi.fn().mockResolvedValue( undefined ) } ),
		};
		vi.spyOn( NeoWikiExtension, 'getInstance' ).mockReturnValue( repositories as unknown as NeoWikiExtension );
		const subjectStore = useSubjectStore();
		const schemaStore = useSchemaStore();

		const request = subjectStore.loadPageSubjects( 7 );
		await schemaStore.saveSchema( saved );
		resolveLoad( {
			pageSubjects: new PageSubjects( 7, null, [] ),
			referencedSubjects: [],
			notFoundTargetIds: [],
			schemas: [ fromServer ],
		} );
		await request;

		expect( schemaStore.getSchema( 'Person' ) ).toStrictEqual( saved );
		expect( subjectStore.pageSubjects?.getPageId() ).toBe( 7 );
	} );

} );

describe( 'SubjectStore write results', () => {

	const id = new SubjectId( 's11111111111111' );

	beforeEach( () => {
		setActivePinia( createPinia() );
		vi.spyOn( console, 'error' ).mockImplementation( () => undefined );
	} );

	afterEach( () => {
		vi.restoreAllMocks();
	} );

	// The page read that follows each write fails, so the registry holds what the write itself recorded.
	function withWritingRepository( repository: Record<string, unknown> ): void {
		withSubjectRepository( { ...repository, getPageSubjects: vi.fn().mockRejectedValue( new Error( 'read failed' ) ) } );
	}

	it( 'records the Subject the update returned, not the one it was given', async () => {
		// Only the server's copy carries whatever normalisation the write applied.
		const sent = newSubject( { id: id.text, label: 'as typed' } );
		const canonical = newSubject( { id: id.text, label: 'as persisted' } );
		withWritingRepository( {
			updateSubject: vi.fn().mockResolvedValue( writeResult( canonical ) ),
		} );
		const store = useSubjectStore();

		await store.updateSubject( sent, [] );

		expect( store.getSubject( id ) ).toStrictEqual( canonical );
	} );

	it( 'records the Schema the update bundled', async () => {
		const schema = newSchema( { title: 'Person', description: 'as persisted' } );
		withWritingRepository( {
			updateSubject: vi.fn().mockResolvedValue( writeResult( newSubject( { id: id.text } ), schema ) ),
		} );
		const subjectStore = useSubjectStore();

		await subjectStore.updateSubject( newSubject( { id: id.text } ), [] );

		expect( useSchemaStore().getSchema( 'Person' ) ).toStrictEqual( schema );
	} );

	it( 'discards the bundled Schema when a Schema mutation landed while the save was in flight', async () => {
		const bundled = newSchema( { title: 'Person', description: 'bundled with the save' } );
		const savedElsewhere = newSchema( { title: 'Person', description: 'saved elsewhere' } );
		let resolveUpdate!: ( result: unknown ) => void;
		const repositories = {
			getSubjectRepository: () => ( {
				updateSubject: () => new Promise( ( resolve ) => {
					resolveUpdate = resolve;
				} ),
				getPageSubjects: vi.fn().mockRejectedValue( new Error( 'read failed' ) ),
			} ),
			getSchemaRepository: () => ( { saveSchema: vi.fn().mockResolvedValue( undefined ) } ),
		};
		vi.spyOn( NeoWikiExtension, 'getInstance' ).mockReturnValue( repositories as unknown as NeoWikiExtension );
		const subjectStore = useSubjectStore();
		const schemaStore = useSchemaStore();

		const request = subjectStore.updateSubject( newSubject( { id: id.text } ), [] );
		await schemaStore.saveSchema( savedElsewhere );
		resolveUpdate( writeResult( newSubject( { id: id.text } ), bundled ) );
		await request;

		expect( schemaStore.getSchema( 'Person' ) ).toStrictEqual( savedElsewhere );
	} );

	it( 'creates the Subject under the id it was given', async () => {
		const createMainSubject = vi.fn().mockResolvedValue( writeResult( newSubject( { id: id.text } ) ) );
		withWritingRepository( { createMainSubject } );

		await useSubjectStore().createMainSubject( 7, null, 'Person', new StatementList( [] ), [], 'why', id );

		expect( callsOf( { createMainSubject }, 'createMainSubject' ) ).toContainEqual(
			expect.objectContaining( { pageId: 7, label: null, schemaName: 'Person', dependentSubjects: [], comment: 'why', id } ),
		);
	} );

	it( 'records the Subject the creation returned', async () => {
		const created = newSubject( { id: id.text, label: 'as persisted' } );
		withWritingRepository( {
			createMainSubject: vi.fn().mockResolvedValue( writeResult( created ) ),
		} );
		const store = useSubjectStore();

		const returnedId = await store.createMainSubject( 7, 'as typed', 'Person', new StatementList( [] ), [] );

		expect( returnedId ).toStrictEqual( id );
		expect( store.getSubject( id ) ).toStrictEqual( created );
	} );

	it( 'keeps the previous copy when the response omitted the page context', async () => {
		// A Subject without page identifiers cannot back the links that read it, so the registry
		// keeps what it had rather than recording a page-less copy.
		const seeded = newSubject( { id: id.text, label: 'seeded' } );
		withWritingRepository( { updateSubject: vi.fn().mockResolvedValue( writeResult( null ) ) } );
		const store = useSubjectStore();
		store.setSubject( seeded );

		await store.updateSubject( newSubject( { id: id.text, label: 'as typed' } ), [] );

		expect( store.getSubject( id ) ).toStrictEqual( seeded );
	} );

	it( 'records the Subject created together with its page, and reports that page', async () => {
		const created = newSubject( { id: id.text, label: 'as persisted' } );
		withWritingRepository( {
			createSubjectPage: vi.fn().mockResolvedValue( {
				...writeResult( created ),
				pageTitle: 'As Persisted',
			} ),
		} );
		const store = useSubjectStore();

		const result = await store.createSubjectPage( 'as typed', 'Person', new StatementList( [] ), [] );

		expect( result.subjectId ).toStrictEqual( id );
		expect( result.pageTitle ).toBe( 'As Persisted' );
		expect( store.getSubject( id ) ).toStrictEqual( created );
	} );

	it( 'records the Subject that createOtherSubject returned', async () => {
		const created = newSubject( { id: id.text, label: 'as persisted' } );
		withWritingRepository( {
			createOtherSubject: vi.fn().mockResolvedValue( writeResult( created ) ),
		} );
		const store = useSubjectStore();

		await store.createOtherSubject( 7, 'as typed', 'Person', new StatementList( [] ), [] );

		expect( store.getSubject( id ) ).toStrictEqual( created );
	} );

} );

describe( 'SubjectStore moveSubject', () => {

	const pageId = 7;
	const targetPageId = 12;
	const kept = newSubject( { id: 's11111111111111', pageIdentifiers: new PageIdentifiers( pageId, 'Page' ) } );
	const moved = newSubject( { id: 's22222222222222', pageIdentifiers: new PageIdentifiers( pageId, 'Page' ) } );

	beforeEach( () => {
		setActivePinia( createPinia() );
	} );

	afterEach( () => {
		vi.restoreAllMocks();
	} );

	it( 'passes the target page and the promotion choice to the repository', async () => {
		const moveSubject = vi.fn().mockResolvedValue( undefined );
		withSubjectRepository( { moveSubject, getPageSubjects: vi.fn().mockResolvedValue( {
			pageSubjects: new PageSubjects( pageId, null, [ kept ] ),
			referencedSubjects: [],
			notFoundTargetIds: [],
			schemas: [],
		} ) } );
		const store = useSubjectStore();
		store.setSubject( moved );
		store.pageSubjects = new PageSubjects( pageId, null, [ kept, moved ] );

		await store.moveSubject( moved.getId(), targetPageId, true, 'Filed properly' );

		expect( moveSubject ).toHaveBeenCalledWith( moved.getId(), targetPageId, true, 'Filed properly' );
	} );

	it( 're-syncs the page listing so the moved subject leaves it', async () => {
		const getPageSubjects = vi.fn().mockResolvedValue( {
			pageSubjects: new PageSubjects( pageId, null, [ kept ] ),
			referencedSubjects: [],
			notFoundTargetIds: [],
			schemas: [],
		} );
		withSubjectRepository( { moveSubject: vi.fn().mockResolvedValue( undefined ), getPageSubjects } );
		const store = useSubjectStore();
		store.setSubject( kept );
		store.setSubject( moved );
		store.pageSubjects = new PageSubjects( pageId, null, [ kept, moved ] );

		await store.moveSubject( moved.getId(), targetPageId, false );

		expect( getPageSubjects ).toHaveBeenCalledWith( pageId );
		expect( store.pageSubjects?.getSubjects() ).toEqual( [ kept ] );
	} );

	it( 'drops the registry copy, whose page context the move has just changed', async () => {
		withSubjectRepository( {
			moveSubject: vi.fn().mockResolvedValue( undefined ),
			getPageSubjects: vi.fn().mockResolvedValue( {
				pageSubjects: new PageSubjects( pageId, null, [ kept ] ),
				referencedSubjects: [],
				notFoundTargetIds: [],
				schemas: [],
			} ),
		} );
		const store = useSubjectStore();
		store.setSubject( kept );
		store.setSubject( moved );
		store.pageSubjects = new PageSubjects( pageId, null, [ kept, moved ] );

		await store.moveSubject( moved.getId(), targetPageId, false );

		expect( store.subjects.has( moved.getId().text ) ).toBe( false );
	} );

	// A Subject this page still points at comes back in the re-sync, under the page it moved to.
	// Dropping it then throws away the copy just fetched, and every relation display pointing at it
	// falls back to an unresolved target.
	it( 'keeps the copy the post-move re-sync supplied', async () => {
		const movedElsewhere = newSubject( {
			id: moved.getId(),
			pageIdentifiers: new PageIdentifiers( targetPageId, 'Elsewhere' ),
		} );
		withSubjectRepository( {
			moveSubject: vi.fn().mockResolvedValue( undefined ),
			getPageSubjects: vi.fn().mockResolvedValue( {
				pageSubjects: new PageSubjects( pageId, null, [ kept ] ),
				referencedSubjects: [ movedElsewhere ],
				schemas: [],
			} ),
		} );
		const store = useSubjectStore();
		store.setSubject( kept );
		store.setSubject( moved );
		store.pageSubjects = new PageSubjects( pageId, null, [ kept, moved ] );

		await store.moveSubject( moved.getId(), targetPageId, false );

		expect( store.findSubject( moved.getId() ) ).toStrictEqual( movedElsewhere );
	} );

	it( 'does not refetch the page listing for a subject that page does not list', async () => {
		const getPageSubjects = vi.fn();
		withSubjectRepository( { moveSubject: vi.fn().mockResolvedValue( undefined ), getPageSubjects } );
		const store = useSubjectStore();
		store.setSubject( moved );
		store.pageSubjects = new PageSubjects( pageId, null, [ kept ] );

		await store.moveSubject( moved.getId(), targetPageId, false );

		expect( getPageSubjects ).not.toHaveBeenCalled();
	} );

	it( 'keeps the registry entry when the post-move re-sync fails, so the listing stays renderable', async () => {
		const consoleError = vi.spyOn( console, 'error' ).mockImplementation( () => undefined );
		withSubjectRepository( {
			moveSubject: vi.fn().mockResolvedValue( undefined ),
			getPageSubjects: vi.fn().mockRejectedValue( new Error( 'sync failed' ) ),
		} );
		const store = useSubjectStore();
		store.setSubject( kept );
		store.setSubject( moved );
		store.pageSubjects = new PageSubjects( pageId, null, [ kept, moved ] );

		await expect( store.moveSubject( moved.getId(), targetPageId, false ) ).resolves.toBeUndefined();

		// The registry invariant: every id pageSubjects lists must resolve through the throwing
		// getSubject getter, and the failed re-sync left this one listed.
		expect( store.subjects.has( moved.getId().text ) ).toBe( true );
		expect( () => store.getSubject( moved.getId() ) ).not.toThrow();

		consoleError.mockRestore();
	} );

	it( 'rejects without touching the listing when the move itself fails', async () => {
		const getPageSubjects = vi.fn();
		withSubjectRepository( {
			moveSubject: vi.fn().mockRejectedValue( new Error( 'Subject is already on the target page' ) ),
			getPageSubjects,
		} );
		const store = useSubjectStore();
		store.setSubject( moved );
		store.pageSubjects = new PageSubjects( pageId, null, [ kept, moved ] );

		await expect( store.moveSubject( moved.getId(), targetPageId, false ) )
			.rejects.toThrow( 'Subject is already on the target page' );

		expect( getPageSubjects ).not.toHaveBeenCalled();
		expect( store.subjects.has( moved.getId().text ) ).toBe( true );
	} );

} );

describe( 'SubjectStore relation targets that were not found', () => {

	const missingId = new SubjectId( 's33333333333333' );

	beforeEach( () => {
		setActivePinia( createPinia() );
	} );

	afterEach( () => {
		vi.restoreAllMocks();
	} );

	function notFoundAmong( ...ids: SubjectId[] ): string[] {
		const store = useSubjectStore();

		return ids.filter( ( id ) => store.wasNotFound( id ) ).map( ( id ) => id.text );
	}

	it( 'records an id the wiki answers it holds no Subject for as not found', async () => {
		withSubjectRepository( {
			getSubject: vi.fn().mockRejectedValue( new SubjectNotFoundError( missingId.text ) ),
		} );

		await expect( useSubjectStore().getOrFetchSubject( missingId ) ).rejects.toBeInstanceOf( SubjectNotFoundError );

		expect( notFoundAmong( missingId ) ).toEqual( [ missingId.text ] );
	} );

	// Only an answer that the Subject is not there makes a red link: a read that failed says nothing either way.
	it( 'does not take a failed read for a Subject that was not found', async () => {
		withSubjectRepository( {
			getSubject: vi.fn().mockRejectedValue( new Error( 'Error fetching subject' ) ),
		} );

		await expect( useSubjectStore().getOrFetchSubject( missingId ) ).rejects.toThrow( 'Error fetching subject' );

		expect( notFoundAmong( missingId ) ).toEqual( [] );
	} );

} );

describe( 'SubjectStore writes of a Host Subject', () => {

	const pageId = 7;
	const otherPageId = 8;
	const onPage = new PageIdentifiers( pageId, 'Pablo Picasso' );
	const host = newSubject( { id: 's11111111111111', label: 'Pablo Picasso', pageIdentifiers: onPage } );
	const birth = newSubject( { id: 's1birthAAAAAAA1', label: null, schemaName: 'Birth', pageIdentifiers: onPage } );
	const name = newSubject( { id: 's1nameAAAAAAAA1', label: null, schemaName: 'Name', pageIdentifiers: onPage } );

	beforeEach( () => {
		setActivePinia( createPinia() );
	} );

	afterEach( () => {
		vi.restoreAllMocks();
	} );

	function pageHolding( ...subjects: Subject[] ): Record<string, unknown> {
		return {
			pageSubjects: new PageSubjects( pageId, host.getId(), subjects ),
			referencedSubjects: [],
			notFoundTargetIds: [],
			schemas: [],
		};
	}

	function repositoryAnswering( page: Record<string, unknown> ): { updateSubject: Mock; getPageSubjects: Mock } {
		const repository = {
			updateSubject: vi.fn().mockResolvedValue( writeResult( host ) ),
			getPageSubjects: vi.fn().mockResolvedValue( page ),
		};

		withSubjectRepository( repository );

		return repository;
	}

	it( 'sends the Dependent Subjects along with the Subject it writes', async () => {
		const repository = repositoryAnswering( pageHolding( host, birth ) );

		await useSubjectStore().updateSubject( host, [ birth ], 'why' );

		expect( callsOf( repository, 'updateSubject' ) ).toContainEqual( expect.objectContaining( {
			id: host.getId(),
			label: 'Pablo Picasso',
			statements: host.getStatements(),
			dependentSubjects: [ birth ],
			comment: 'why',
		} ) );
	} );

	it( 'holds the Dependent Subjects the write created, read back from the page', async () => {
		repositoryAnswering( pageHolding( host, birth ) );
		const store = useSubjectStore();

		await store.updateSubject( host, [ birth ], 'why' );

		expect( store.findSubject( birth.getId() ) ).toStrictEqual( birth );
	} );

	it( 'drops a Dependent Subject the write removed', async () => {
		repositoryAnswering( pageHolding( host ) );
		const store = useSubjectStore();
		store.setSubject( name );

		await store.updateSubject( host, [], 'why' );

		expect( store.findSubject( name.getId() ) ).toBeUndefined();
	} );

	it( 'keeps a Subject of another page the read does not list', async () => {
		repositoryAnswering( pageHolding( host ) );
		const elsewhere = newSubject( { id: 's1otherAAAAAAA1', pageIdentifiers: new PageIdentifiers( otherPageId, 'Elsewhere' ) } );
		const store = useSubjectStore();
		store.setSubject( elsewhere );

		await store.updateSubject( host, [], 'why' );

		expect( store.findSubject( elsewhere.getId() ) ).toStrictEqual( elsewhere );
	} );

	it( 'follows the listing of the page it wrote to', async () => {
		repositoryAnswering( pageHolding( host, birth ) );
		const store = useSubjectStore();
		store.pageSubjects = new PageSubjects( pageId, host.getId(), [ host, name ] );
		store.setSubject( name );

		await store.updateSubject( host, [ birth ], 'why' );

		expect( store.pageSubjects?.getSubjects() ).toEqual( [ host, birth ] );
	} );

	it( 'leaves the listing of another page alone', async () => {
		repositoryAnswering( pageHolding( host, birth ) );
		const store = useSubjectStore();
		store.pageSubjects = new PageSubjects( otherPageId, null, [] );

		await store.updateSubject( host, [ birth ], 'why' );

		expect( store.pageSubjects?.getPageId() ).toBe( otherPageId );
	} );

	it( 'still resolves when reading the page after the write fails', async () => {
		vi.spyOn( console, 'error' ).mockImplementation( () => undefined );
		withSubjectRepository( {
			updateSubject: vi.fn().mockResolvedValue( writeResult( host ) ),
			getPageSubjects: vi.fn().mockRejectedValue( new Error( 'read failed' ) ),
		} );

		await expect( useSubjectStore().updateSubject( host, [ birth ], 'why' ) ).resolves.toBeUndefined();
	} );

	it( 'creates a Subject on a page of its own together with its Dependent Subjects, and reads that page', async () => {
		setupMwMock( { config: { wgNeoWikiSubjectFirst: true } } );
		const repository = {
			createSubjectPage: vi.fn().mockResolvedValue( { ...writeResult( null ), pageTitle: 'Pablo Picasso', pageId: 12 } ),
			getPageSubjects: vi.fn().mockResolvedValue( pageHolding( host, birth ) ),
		};
		withSubjectRepository( repository );

		await useSubjectStore().createSubject( host, pageId, [ birth ], 'why' );

		expect( callsOf( repository, 'createSubjectPage' ) ).toContainEqual( expect.objectContaining( {
			label: 'Pablo Picasso',
			schemaName: 'TestSchema',
			statements: host.getStatements(),
			dependentSubjects: [ birth ],
			comment: 'why',
			pageTitle: undefined,
			id: host.getId(),
		} ) );
		expect( repository.getPageSubjects ).toHaveBeenCalledWith( 12 );
	} );

	it( 'creates a Subject on the page it was given together with its Dependent Subjects', async () => {
		setupMwMock( { config: { wgNeoWikiSubjectFirst: false } } );
		const repository = {
			createOtherSubject: vi.fn().mockResolvedValue( writeResult( host ) ),
			getPageSubjects: vi.fn().mockResolvedValue( pageHolding( host, birth ) ),
		};
		withSubjectRepository( repository );

		await useSubjectStore().createSubject( host, pageId, [ birth ], 'why' );

		expect( callsOf( repository, 'createOtherSubject' )[ 0 ].dependentSubjects ).toEqual( [ birth ] );
		expect( useSubjectStore().findSubject( birth.getId() ) ).toStrictEqual( birth );
	} );

	it( 'creates a page\'s Main Subject together with its Dependent Subjects', async () => {
		const repository = {
			createMainSubject: vi.fn().mockResolvedValue( writeResult( host ) ),
			getPageSubjects: vi.fn().mockResolvedValue( pageHolding( host, birth ) ),
		};
		withSubjectRepository( repository );

		await useSubjectStore().createMainSubject( pageId, 'Pablo Picasso', 'TestSchema', host.getStatements(), [ birth ], 'why', host.getId() );

		expect( callsOf( repository, 'createMainSubject' ) ).toContainEqual( expect.objectContaining( {
			pageId,
			label: 'Pablo Picasso',
			schemaName: 'TestSchema',
			statements: host.getStatements(),
			dependentSubjects: [ birth ],
			comment: 'why',
			id: host.getId(),
		} ) );
	} );

} );
