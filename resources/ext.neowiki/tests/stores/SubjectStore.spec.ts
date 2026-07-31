import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
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
} );

function withSubjectRepository( repository: Record<string, unknown> ): void {
	vi.spyOn( NeoWikiExtension, 'getInstance' ).mockReturnValue(
		{ getSubjectRepository: () => repository } as unknown as NeoWikiExtension,
	);
}

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
			updateSubject: vi.fn().mockResolvedValue( { subject: updated, schema: null } ),
		} );
		const store = useSubjectStore();

		const request = store.getOrFetchSubject( new SubjectId( 's11111111111111' ) );
		await store.updateSubject( updated );
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
		const fromServer = newSubject( { id: 's11111111111111', label: 'from-server' } );
		const updated = newSubject( { id: 's11111111111111', label: 'updated' } );
		let resolveLoad!: ( value: unknown ) => void;
		const pending = new Promise( ( resolve ) => {
			resolveLoad = resolve;
		} );
		withSubjectRepository( {
			getPageSubjects: () => pending,
			updateSubject: vi.fn().mockResolvedValue( { subject: updated, schema: null } ),
		} );
		const store = useSubjectStore();

		const request = store.loadPageSubjects( 7 );
		await store.updateSubject( updated );
		resolveLoad( {
			pageSubjects: new PageSubjects( 7, null, [ fromServer ] ),
			referencedSubjects: [],
			schemas: [],
		} );
		await request;

		expect( store.pageSubjects ).toBeNull();
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
	} );

	afterEach( () => {
		vi.restoreAllMocks();
	} );

	it( 'records the Subject the update returned, not the one it was given', async () => {
		// The caller's copy is a plain Subject built by the editor; only the server's carries the
		// page context and whatever normalisation the write applied.
		const sent = newSubject( { id: id.text, label: 'as typed' } );
		const canonical = newSubject( { id: id.text, label: 'as persisted' } );
		withSubjectRepository( {
			updateSubject: vi.fn().mockResolvedValue( { subject: canonical, schema: null } ),
		} );
		const store = useSubjectStore();

		await store.updateSubject( sent );

		expect( store.getSubject( id ) ).toStrictEqual( canonical );
	} );

	it( 'records the Schema the update bundled', async () => {
		const schema = newSchema( { title: 'Person', description: 'as persisted' } );
		withSubjectRepository( {
			updateSubject: vi.fn().mockResolvedValue( {
				subject: newSubject( { id: id.text } ),
				schema,
			} ),
		} );
		const subjectStore = useSubjectStore();

		await subjectStore.updateSubject( newSubject( { id: id.text } ) );

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
			} ),
			getSchemaRepository: () => ( { saveSchema: vi.fn().mockResolvedValue( undefined ) } ),
		};
		vi.spyOn( NeoWikiExtension, 'getInstance' ).mockReturnValue( repositories as unknown as NeoWikiExtension );
		const subjectStore = useSubjectStore();
		const schemaStore = useSchemaStore();

		const request = subjectStore.updateSubject( newSubject( { id: id.text } ) );
		await schemaStore.saveSchema( savedElsewhere );
		resolveUpdate( { subject: newSubject( { id: id.text } ), schema: bundled } );
		await request;

		expect( schemaStore.getSchema( 'Person' ) ).toStrictEqual( savedElsewhere );
	} );

	it( 'records the Subject the creation returned', async () => {
		const created = newSubject( { id: id.text, label: 'as persisted' } );
		withSubjectRepository( {
			createMainSubject: vi.fn().mockResolvedValue( { subject: created, schema: null } ),
		} );
		const store = useSubjectStore();

		const returnedId = await store.createMainSubject( 7, 'as typed', 'Person', new StatementList( [] ) );

		expect( returnedId ).toStrictEqual( id );
		expect( store.getSubject( id ) ).toStrictEqual( created );
	} );

	it( 'records the Subject a child creation returned', async () => {
		const created = newSubject( { id: id.text, label: 'as persisted' } );
		withSubjectRepository( {
			createChildSubject: vi.fn().mockResolvedValue( { subject: created, schema: null } ),
		} );
		const store = useSubjectStore();

		await store.createChildSubject( 7, 'as typed', 'Person', new StatementList( [] ) );

		expect( store.getSubject( id ) ).toStrictEqual( created );
	} );

} );
