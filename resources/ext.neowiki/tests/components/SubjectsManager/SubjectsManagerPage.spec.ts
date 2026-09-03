import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { ref } from 'vue';
import { createPinia, setActivePinia } from 'pinia';
import { shallowMount, VueWrapper, flushPromises } from '@vue/test-utils';
import { CdxMenuButton } from '@wikimedia/codex';
import SubjectsManagerPage from '@/components/SubjectsManager/SubjectsManagerPage.vue';
import { createI18nMock, setupMwMock } from '../../VueTestHelpers.ts';
import { NeoWikiExtension } from '@/NeoWikiExtension.ts';
import { Subject } from '@/domain/Subject.ts';
import { SubjectId } from '@/domain/SubjectId.ts';
import { StatementList } from '@/domain/StatementList.ts';
import { PageSubjects } from '@/domain/PageSubjects.ts';
import { subjectRowDomId } from '@/presentation/subjectRowAnchor.ts';
import SummaryAction from '@/components/common/SummaryAction.vue';
import SubjectEditorDialog from '@/components/SubjectEditor/SubjectEditorDialog.vue';
import MoveSubjectDialog from '@/components/SubjectsManager/MoveSubjectDialog.vue';
import { Service } from '@/NeoWikiServices.ts';
import { newSchema } from '@/TestHelpers.ts';

// Two subject-id-shaped ids (s + 14 base58 chars), so the deep-link fragment parser accepts them.
const ID_A = 's1aaaaaaaaaaaa1';
const ID_B = 's1bbbbbbbbbbbb1';
const PAGE_ID = 42;

function subject( id: string ): Subject {
	return new Subject( new SubjectId( id ), 'Label ' + id, 'Label ' + id, 'Person', new StatementList( [] ) );
}

function labellessSubject( id: string, displayName: string ): Subject {
	return new Subject( new SubjectId( id ), null, displayName, 'Person', new StatementList( [] ) );
}

const loadPageSubjectsMock = vi.fn().mockResolvedValue( undefined );
const deleteSubjectMock = vi.fn().mockResolvedValue( undefined );
let storeSubjects: Subject[] = [];
let mainSubjectId: SubjectId | null = null;

// Every describe below except 'delete flow' runs against this plain-object stub: fast,
// hand-controlled listing/main-subject state via storeSubjects/mainSubjectId. The delete-flow
// describe instead flips useRealSubjectStore on and drives a real Pinia-backed store through a
// mocked NeoWikiExtension, so it actually exercises SubjectStore's registry semantics (the
// pageSubjects/subjects consistency invariant from ADR 30) rather than a stub that cannot violate
// them — see SubjectStore.spec.ts's own deleteSubject tests for the store-level coverage this
// mirrors at the component level.
let useRealSubjectStore = false;

vi.mock( '@/stores/SubjectStore.ts', async ( importOriginal ) => {
	const actual = await importOriginal<typeof import( '@/stores/SubjectStore.ts' )>();
	return {
		useSubjectStore: () => {
			if ( useRealSubjectStore ) {
				return actual.useSubjectStore();
			}
			return {
				loadPageSubjects: loadPageSubjectsMock,
				deleteSubject: deleteSubjectMock,
				getSubject: ( id: SubjectId ) => storeSubjects.find( ( s ) => s.getId().text === id.text ),
				openSubjectCreator: vi.fn(),
				get pageSubjects() {
					return {
						getSubjects: () => storeSubjects,
						getMainSubjectId: () => mainSubjectId,
					};
				},
			};
		},
	};
} );

vi.mock( '@/stores/SchemaStore.ts', () => ( {
	useSchemaStore: () => ( {
		saveSchema: vi.fn(),
		getSchema: vi.fn(),
	} ),
} ) );

// Module-level refs (rather than ones freshly created per useSubjectPermissions() call) so the
// edit- and delete-flow tests below can flip them on; every other describe leaves them off.
const canDeleteSubjectRef = ref( false );
const canEditSubjectRef = ref( false );

// openEditor reads through the injected repositories; the edit-flow describe below arms these.
const getSubjectRepoMock = vi.fn();
const getSchemaRepoMock = vi.fn();

vi.mock( '@/composables/useSubjectPermissions.ts', () => ( {
	useSubjectPermissions: () => ( {
		canCreateMainSubject: ref( false ),
		canCreateChildSubject: ref( false ),
		canEditSubject: canEditSubjectRef,
		canDeleteSubject: canDeleteSubjectRef,
		checkPermissions: vi.fn().mockResolvedValue( undefined ),
	} ),
} ) );

// The drag wiring boots SortableJS against real DOM, which is irrelevant here and awkward in jsdom.
vi.mock( '@/composables/useSubjectDrag.ts', () => ( {
	useSubjectDrag: vi.fn(),
} ) );

const HIGHLIGHT_CLASS = 'ext-neowiki-subjects-manager__row--highlighted';
const EXPANDED_CLASS = 'ext-neowiki-subjects-manager__row--expanded';

function rowFor( wrapper: VueWrapper, id: string ): VueWrapper {
	return wrapper.find( '#' + subjectRowDomId( id ) ) as unknown as VueWrapper;
}

// shallowMount's auto-stubs do not render slots by default, but the delete-confirmation flow
// lives in CdxDialog's #footer slot (the SummaryAction that emits 'save'); a template-carrying
// stub keeps that slot content in the tree so the delete-flow tests can reach it.
const CdxDialogStub = {
	template: '<div v-if="open" class="cdx-dialog-stub"><slot /><slot name="footer" /></div>',
	props: [ 'open', 'title', 'useCloseButton' ],
	emits: [ 'update:open' ],
};

async function mountPage(): Promise<VueWrapper> {
	setupMwMock( {
		functions: [ 'config', 'msg', 'message', 'notify', 'util' ],
		config: {
			wgNeoWikiManageSubjectsPageId: PAGE_ID,
			wgNeoWikiRdfProjections: [],
			wgNeoWikiSubjectIriBase: '',
		},
	} );

	// A fresh Pinia per mount, always installed. Harmless when useRealSubjectStore is off (the
	// stub factory above never touches it), and required when it is on (the delete-flow describe).
	const pinia = createPinia();
	setActivePinia( pinia );

	const wrapper = shallowMount( SubjectsManagerPage, {
		attachTo: document.body,
		global: {
			plugins: [ pinia ],
			mocks: { $i18n: createI18nMock() },
			provide: {
				[ Service.SubjectRepository ]: { getSubject: getSubjectRepoMock },
				[ Service.SchemaRepository ]: { getSchema: getSchemaRepoMock },
			},
			stubs: { CdxIcon: true, CdxDialog: CdxDialogStub },
		},
	} );

	// onMounted awaits checkPermissions + loadSubjects; then applyHash schedules the scroll on nextTick.
	await flushPromises();
	await flushPromises();

	return wrapper;
}

describe( 'SubjectsManagerPage deep-link / hash wiring', () => {

	beforeEach( () => {
		storeSubjects = [ subject( ID_A ), subject( ID_B ) ];
		mainSubjectId = null;
		loadPageSubjectsMock.mockClear();
		window.location.hash = '';
		// jsdom implements neither of these; the component calls both while landing on a row.
		Element.prototype.scrollIntoView = vi.fn();
		window.matchMedia = vi.fn().mockReturnValue( { matches: false } ) as unknown as typeof window.matchMedia;
	} );

	afterEach( () => {
		document.body.innerHTML = '';
		window.location.hash = '';
		vi.restoreAllMocks();
	} );

	it( 'expands, highlights, and scrolls to the row named by a subject-id fragment', async () => {
		window.location.hash = '#' + ID_A;

		const wrapper = await mountPage();

		const row = rowFor( wrapper, ID_A );
		expect( row.exists() ).toBe( true );
		expect( row.classes() ).toContain( HIGHLIGHT_CLASS );
		expect( ( row.find( 'details' ).element as HTMLDetailsElement ).open ).toBe( true );
		// The scrolled element is the target row itself. Asserting the context rather than a call count
		// keeps this independent of how many hashchange events jsdom synthesizes for the programmatic
		// location.hash assignment above (a real page load sets the hash once, with no hashchange).
		const scroll = vi.mocked( Element.prototype.scrollIntoView );
		expect( scroll ).toHaveBeenCalled();
		expect( scroll.mock.contexts ).toContain( row.element );

		// The other row is untouched.
		expect( rowFor( wrapper, ID_B ).classes() ).not.toContain( HIGHLIGHT_CLASS );
	} );

	it( 'leaves every row untouched for a fragment that is not a subject id', async () => {
		window.location.hash = '#section-heading';

		const wrapper = await mountPage();

		expect( rowFor( wrapper, ID_A ).classes() ).not.toContain( HIGHLIGHT_CLASS );
		expect( rowFor( wrapper, ID_B ).classes() ).not.toContain( HIGHLIGHT_CLASS );
		expect( Element.prototype.scrollIntoView ).not.toHaveBeenCalled();
	} );

	it( 'writes the bare subject id to the address bar via replaceState when a row is expanded', async () => {
		const replaceState = vi.spyOn( window.history, 'replaceState' );

		const wrapper = await mountPage();
		await rowFor( wrapper, ID_A ).find( 'summary' ).trigger( 'click' );

		expect( replaceState ).toHaveBeenCalledWith( null, '', '#' + ID_A );
		expect( ( rowFor( wrapper, ID_A ).find( 'details' ).element as HTMLDetailsElement ).open ).toBe( true );
		// replaceState fires no hashchange, so applyHash never re-runs and never highlights the row.
		expect( rowFor( wrapper, ID_A ).classes() ).not.toContain( HIGHLIGHT_CLASS );
	} );

	it( 'carries the --expanded modifier class only while the row is open', async () => {
		const wrapper = await mountPage();

		expect( rowFor( wrapper, ID_A ).classes() ).not.toContain( EXPANDED_CLASS );

		await rowFor( wrapper, ID_A ).find( 'summary' ).trigger( 'click' );
		expect( rowFor( wrapper, ID_A ).classes() ).toContain( EXPANDED_CLASS );

		await rowFor( wrapper, ID_A ).find( 'summary' ).trigger( 'click' );
		expect( rowFor( wrapper, ID_A ).classes() ).not.toContain( EXPANDED_CLASS );
	} );

	it( 'dismisses the arrival highlight on the first manual expand of a different row', async () => {
		window.location.hash = '#' + ID_A;

		const wrapper = await mountPage();
		expect( rowFor( wrapper, ID_A ).classes() ).toContain( HIGHLIGHT_CLASS );

		await rowFor( wrapper, ID_B ).find( 'summary' ).trigger( 'click' );

		// The highlight no longer clings to A while the fragment (now #ID_B) has moved on.
		expect( rowFor( wrapper, ID_A ).classes() ).not.toContain( HIGHLIGHT_CLASS );
		expect( rowFor( wrapper, ID_B ).classes() ).not.toContain( HIGHLIGHT_CLASS );
	} );

	it( 'stops responding to hashchange after unmount', async () => {
		const removeListener = vi.spyOn( window, 'removeEventListener' );

		const wrapper = await mountPage();
		wrapper.unmount();

		expect( removeListener ).toHaveBeenCalledWith( 'hashchange', expect.any( Function ) );
	} );

} );

describe( 'SubjectsManagerPage rows without a stored label', () => {

	beforeEach( () => {
		storeSubjects = [
			labellessSubject( ID_A, 'Host Page' ),
			labellessSubject( ID_B, 'Person' ),
		];
		mainSubjectId = new SubjectId( ID_A );
		loadPageSubjectsMock.mockClear();
		window.location.hash = '';
		Element.prototype.scrollIntoView = vi.fn();
		window.matchMedia = vi.fn().mockReturnValue( { matches: false } ) as unknown as typeof window.matchMedia;
	} );

	afterEach( () => {
		document.body.innerHTML = '';
		window.location.hash = '';
		vi.restoreAllMocks();
	} );

	it( 'names the main row after the page and the child row after its schema', async () => {
		const wrapper = await mountPage();

		const names = wrapper.findAll( '.ext-neowiki-subjects-manager__row-label' ).map( ( el ) => el.text() );
		expect( names ).toEqual( [ 'Host Page', 'Person' ] );
	} );

} );

describe( 'SubjectsManagerPage row copy-link action', () => {
	let writeText: ReturnType<typeof vi.fn>;

	beforeEach( () => {
		storeSubjects = [ subject( ID_A ) ];
		mainSubjectId = null;
		loadPageSubjectsMock.mockClear();
		window.location.hash = '';
		Element.prototype.scrollIntoView = vi.fn();
		window.matchMedia = vi.fn().mockReturnValue( { matches: false } ) as unknown as typeof window.matchMedia;

		writeText = vi.fn().mockResolvedValue( undefined );
		Object.defineProperty( navigator, 'clipboard', {
			value: { writeText },
			configurable: true,
		} );
	} );

	afterEach( () => {
		document.body.innerHTML = '';
		window.location.hash = '';
		vi.restoreAllMocks();
	} );

	it( 'offers copy-link in the overflow menu to a read-only user on both the main and other rows', async () => {
		storeSubjects = [ subject( ID_A ), subject( ID_B ) ];
		mainSubjectId = new SubjectId( ID_A );

		const wrapper = await mountPage();

		const menus = wrapper.findAllComponents( CdxMenuButton );
		expect( menus ).toHaveLength( 2 );
		// The read-only user has neither edit nor delete rights, so copy-link is the whole menu: it is
		// the one row action that is not permission-gated, and it makes the otherwise-empty ⋯ menu useful.
		for ( const menu of menus ) {
			expect( menu.props( 'menuItems' ).map( ( item ) => item.value ) ).toEqual( [ 'copy-link' ] );
		}
	} );

	it( 'copies a URL whose fragment is the subject id and shows the success toast', async () => {
		const replaceState = vi.spyOn( window.history, 'replaceState' );

		const wrapper = await mountPage();
		wrapper.findComponent( CdxMenuButton ).vm.$emit( 'update:selected', 'copy-link' );
		await flushPromises();

		expect( writeText ).toHaveBeenCalledTimes( 1 );
		const copiedUrl = writeText.mock.calls[ 0 ][ 0 ] as string;
		expect( new URL( copiedUrl ).hash ).toBe( '#' + ID_A );

		expect( mw.notify ).toHaveBeenCalledWith( 'neowiki-managesubjects-link-copied', { type: 'success' } );

		// Copying reads the address bar but must not mutate it, expand the row, or highlight it.
		expect( replaceState ).not.toHaveBeenCalled();
		expect( window.location.hash ).toBe( '' );
		expect( ( rowFor( wrapper, ID_A ).find( 'details' ).element as HTMLDetailsElement ).open ).toBe( false );
		expect( rowFor( wrapper, ID_A ).classes() ).not.toContain( HIGHLIGHT_CLASS );
	} );

	it( 'shows the error toast when the clipboard write is rejected', async () => {
		writeText.mockRejectedValue( new Error( 'clipboard denied' ) );
		const consoleError = vi.spyOn( console, 'error' ).mockImplementation( () => undefined );

		const wrapper = await mountPage();
		wrapper.findComponent( CdxMenuButton ).vm.$emit( 'update:selected', 'copy-link' );
		await flushPromises();

		expect( mw.notify ).toHaveBeenCalledWith( 'neowiki-managesubjects-link-copy-error', { type: 'error' } );
		expect( consoleError ).toHaveBeenCalled();
	} );

	it( 'renders an inline copy-link button on both rows for a read-only user', async () => {
		storeSubjects = [ subject( ID_A ), subject( ID_B ) ];
		mainSubjectId = new SubjectId( ID_A );

		const wrapper = await mountPage();

		// One on the main-subject row, one on the single other row. For a read-only user the rest of the
		// inline action cluster (promote/edit/delete/drag) is gated away, so this is its only affordance —
		// and it lives where the ⋯ menu is hidden (desktop widths).
		const buttons = wrapper.findAll( '[aria-label="neowiki-managesubjects-row-copy-link"]' );
		expect( buttons ).toHaveLength( 2 );
	} );

	it( 'copies the deep-link URL and shows the success toast when the inline button is clicked', async () => {
		const wrapper = await mountPage();

		await wrapper.find( '[aria-label="neowiki-managesubjects-row-copy-link"]' ).trigger( 'click' );
		await flushPromises();

		expect( writeText ).toHaveBeenCalledTimes( 1 );
		expect( new URL( writeText.mock.calls[ 0 ][ 0 ] as string ).hash ).toBe( '#' + ID_A );
		expect( mw.notify ).toHaveBeenCalledWith( 'neowiki-managesubjects-link-copied', { type: 'success' } );
	} );

} );

describe( 'SubjectsManagerPage edit flow', () => {

	beforeEach( () => {
		storeSubjects = [ subject( ID_A ) ];
		mainSubjectId = null;
		canEditSubjectRef.value = true;
		window.location.hash = '';
		Element.prototype.scrollIntoView = vi.fn();
		window.matchMedia = vi.fn().mockReturnValue( { matches: false } ) as unknown as typeof window.matchMedia;

		getSubjectRepoMock.mockReset();
		getSchemaRepoMock.mockReset();
	} );

	afterEach( () => {
		document.body.innerHTML = '';
		window.location.hash = '';
		canEditSubjectRef.value = false;
		vi.restoreAllMocks();
	} );

	it( 'opens the editor on the subject and schema fetched from the repositories', async () => {
		// Values the store stub does not hold, so the assertions can only pass if the dialog was
		// handed the repositories' data rather than a registry read.
		const freshSubject = new Subject(
			new SubjectId( ID_A ),
			'Fetched label',
			'Fetched label',
			'Person',
			new StatementList( [] ),
		);
		const freshSchema = newSchema( { title: 'Person' } );
		getSubjectRepoMock.mockResolvedValue( freshSubject );
		getSchemaRepoMock.mockResolvedValue( freshSchema );

		const wrapper = await mountPage();

		await wrapper.find( '[aria-label="neowiki-managesubjects-row-edit"]' ).trigger( 'click' );
		await flushPromises();

		expect( getSubjectRepoMock ).toHaveBeenCalledWith( expect.objectContaining( { text: ID_A } ) );
		expect( getSchemaRepoMock ).toHaveBeenCalledWith( 'Person' );
		const dialog = wrapper.findComponent( SubjectEditorDialog );
		expect( dialog.props( 'open' ) ).toBe( true );
		expect( dialog.props( 'subject' ) ).toStrictEqual( freshSubject );
		expect( dialog.props( 'schema' ) ).toStrictEqual( freshSchema );
	} );

	it( 'reports a failed fetch instead of opening the editor', async () => {
		getSubjectRepoMock.mockRejectedValue( new Error( 'Unknown subject' ) );
		getSchemaRepoMock.mockResolvedValue( newSchema( { title: 'Person' } ) );

		const wrapper = await mountPage();

		await wrapper.find( '[aria-label="neowiki-managesubjects-row-edit"]' ).trigger( 'click' );
		await flushPromises();

		expect( wrapper.findComponent( SubjectEditorDialog ).exists() ).toBe( false );
		expect( mw.notify ).toHaveBeenCalledWith( 'Unknown subject', { type: 'error' } );
	} );
} );

describe( 'SubjectsManagerPage move action', () => {

	beforeEach( () => {
		storeSubjects = [ subject( ID_A ), subject( ID_B ) ];
		mainSubjectId = new SubjectId( ID_A );
		canEditSubjectRef.value = true;
		canDeleteSubjectRef.value = true;
		window.location.hash = '';
		Element.prototype.scrollIntoView = vi.fn();
		window.matchMedia = vi.fn().mockReturnValue( { matches: false } ) as unknown as typeof window.matchMedia;
	} );

	afterEach( () => {
		document.body.innerHTML = '';
		window.location.hash = '';
		canEditSubjectRef.value = false;
		canDeleteSubjectRef.value = false;
		vi.restoreAllMocks();
	} );

	it( 'offers the move on both the main row and the other rows', async () => {
		const wrapper = await mountPage();

		// One per row: the main subject and the child. Offering it on the main row is deliberate -
		// moving a page's topic elsewhere is allowed, and the dialog warns what the page loses.
		expect( wrapper.findAll( '[aria-label="neowiki-managesubjects-row-move"]' ) ).toHaveLength( 2 );
	} );

	it( 'puts the move before the delete on every row', async () => {
		const wrapper = await mountPage();

		const labels = wrapper.findAll( '.ext-neowiki-subjects-manager__row-actions [aria-label]' )
			.map( ( element ) => element.attributes( 'aria-label' ) )
			.filter( ( label ) => label === 'neowiki-managesubjects-row-move' || label === 'neowiki-managesubjects-row-delete' );

		expect( labels ).toEqual( [
			'neowiki-managesubjects-row-move',
			'neowiki-managesubjects-row-delete',
			'neowiki-managesubjects-row-move',
			'neowiki-managesubjects-row-delete',
		] );
	} );

	it( 'offers the move in the overflow menu too, which is the only surface on mobile', async () => {
		const wrapper = await mountPage();

		const menus = wrapper.findAllComponents( CdxMenuButton );
		expect( menus ).toHaveLength( 2 );
		for ( const menu of menus ) {
			const values = menu.props( 'menuItems' ).map( ( item ) => item.value );
			expect( values ).toContain( 'move' );
			expect( values.indexOf( 'move' ) ).toBeLessThan( values.indexOf( 'delete' ) );
		}
	} );

	it( 'offers no move to a user who cannot edit', async () => {
		canEditSubjectRef.value = false;
		canDeleteSubjectRef.value = false;

		const wrapper = await mountPage();

		expect( wrapper.findAll( '[aria-label="neowiki-managesubjects-row-move"]' ) ).toHaveLength( 0 );
		for ( const menu of wrapper.findAllComponents( CdxMenuButton ) ) {
			expect( menu.props( 'menuItems' ).map( ( item ) => item.value ) ).not.toContain( 'move' );
		}
	} );

	it( 'opens the move dialog on the row that asked for it', async () => {
		const wrapper = await mountPage();

		await wrapper.findAll( '[aria-label="neowiki-managesubjects-row-move"]' )[ 1 ].trigger( 'click' );
		await flushPromises();

		const dialog = wrapper.findComponent( MoveSubjectDialog );
		expect( dialog.props( 'open' ) ).toBe( true );
		expect( dialog.props( 'subjectId' ) ).toBe( ID_B );
		expect( dialog.props( 'currentPageId' ) ).toBe( PAGE_ID );
	} );

	it( 'tells the dialog when the subject being moved is the page\'s main subject', async () => {
		const wrapper = await mountPage();

		await wrapper.findAll( '[aria-label="neowiki-managesubjects-row-move"]' )[ 0 ].trigger( 'click' );
		await flushPromises();

		expect( wrapper.findComponent( MoveSubjectDialog ).props( 'subjectIsMainSubject' ) ).toBe( true );
	} );

	it( 'tells the dialog when a child subject is being moved', async () => {
		const wrapper = await mountPage();

		await wrapper.findAll( '[aria-label="neowiki-managesubjects-row-move"]' )[ 1 ].trigger( 'click' );
		await flushPromises();

		expect( wrapper.findComponent( MoveSubjectDialog ).props( 'subjectIsMainSubject' ) ).toBe( false );
	} );

	it( 'opens the move dialog from the overflow menu as well', async () => {
		const wrapper = await mountPage();

		wrapper.findComponent( CdxMenuButton ).vm.$emit( 'update:selected', 'move' );
		await flushPromises();

		expect( wrapper.findComponent( MoveSubjectDialog ).props( 'open' ) ).toBe( true );
	} );

	it( 'reports the move once the dialog says it landed, naming the subject and its new page', async () => {
		const wrapper = await mountPage();
		await wrapper.findAll( '[aria-label="neowiki-managesubjects-row-move"]' )[ 1 ].trigger( 'click' );
		await flushPromises();
		loadPageSubjectsMock.mockClear();

		wrapper.findComponent( MoveSubjectDialog ).vm.$emit( 'moved', 'Rembrandt van Rijn' );
		await flushPromises();

		const [ message, options ] = ( mw.notify as ReturnType<typeof vi.fn> ).mock.calls[ 0 ];
		expect( message ).toContain( 'neowiki-managesubjects-move-success' );
		expect( message ).toContain( 'Rembrandt van Rijn' );
		expect( options ).toEqual( { type: 'success' } );

		// The listing refresh belongs to the store's move action; refreshing here too would fetch
		// the same page twice for one move.
		expect( loadPageSubjectsMock ).not.toHaveBeenCalled();
	} );

} );

describe( 'SubjectsManagerPage delete flow', () => {
	let reloadMock: ReturnType<typeof vi.fn>;
	let deleteSubjectRepoMock: ReturnType<typeof vi.fn>;
	let getPageSubjectsRepoMock: ReturnType<typeof vi.fn>;

	beforeEach( () => {
		// Real Pinia-backed SubjectStore for this describe (see the useRealSubjectStore comment
		// above): the deletion race this describe exercises lives in the store's own registry
		// semantics, which the plain-object mock the other describes use cannot reproduce.
		useRealSubjectStore = true;
		canDeleteSubjectRef.value = true;
		window.location.hash = '';
		Element.prototype.scrollIntoView = vi.fn();
		window.matchMedia = vi.fn().mockReturnValue( { matches: false } ) as unknown as typeof window.matchMedia;

		deleteSubjectRepoMock = vi.fn().mockResolvedValue( true );
		// Serves the mount's own loadSubjects() call. Tests that need to inspect the post-delete
		// re-sync window queue a one-time override (mockReturnValueOnce) for the second call
		// *after* mounting, so this default only ever serves the first (mount) call.
		getPageSubjectsRepoMock = vi.fn().mockResolvedValue( {
			pageSubjects: new PageSubjects( PAGE_ID, null, [ subject( ID_A ) ] ),
			referencedSubjects: [],
			schemas: [],
		} );

		vi.spyOn( NeoWikiExtension, 'getInstance' ).mockReturnValue( {
			getSubjectRepository: () => ( {
				deleteSubject: deleteSubjectRepoMock,
				getPageSubjects: getPageSubjectsRepoMock,
			} ),
		} as unknown as NeoWikiExtension );

		// Proves executeDelete never falls back to a full-page reload; see the SubjectCreatorDialog
		// spec for the same stubbing idiom against the create flow's (intentionally kept) reload.
		//
		// vi.restoreAllMocks() does not cover vi.stubGlobal, so this stub outlives every test and
		// (unlike vi.mock's `mw` stub, which every mountPage() call re-establishes) nothing
		// re-stubs `location` back to the real window.location afterwards. A tried fix —
		// vi.unstubAllGlobals() in afterEach — makes things worse: it also strips the `mw` stub
		// out from under any earlier test's Vue component that is still reactively subscribed to
		// its Pinia store (this file never calls wrapper.unmount()), causing a `mw is not defined`
		// crash on a later scheduled re-render that corrupts an unrelated, later test. So this
		// describe must stay the LAST one in the file instead: nothing here runs after it that a
		// stubbed `location` (frozen at this describe's first beforeEach) could break.
		reloadMock = vi.fn();
		vi.stubGlobal( 'location', { ...window.location, reload: reloadMock } );
	} );

	afterEach( () => {
		document.body.innerHTML = '';
		window.location.hash = '';
		canDeleteSubjectRef.value = false;
		useRealSubjectStore = false;
		vi.restoreAllMocks();
	} );

	it( 'deletes the subject, notifies success, and never reloads', async () => {
		const wrapper = await mountPage();

		await wrapper.find( '[aria-label="neowiki-managesubjects-row-delete"]' ).trigger( 'click' );
		wrapper.findComponent( SummaryAction ).vm.$emit( 'save', 'cleanup' );
		await flushPromises();

		expect( deleteSubjectRepoMock ).toHaveBeenCalledWith(
			expect.objectContaining( { text: ID_A } ),
			'cleanup',
		);
		expect( mw.notify ).toHaveBeenCalledWith( 'neowiki-managesubjects-delete-success', { type: 'success' } );
		expect( reloadMock ).not.toHaveBeenCalled();
	} );

	it( 'renders the row without the Unknown-subject throw while the post-delete re-sync is in flight, then removes it', async () => {
		const wrapper = await mountPage();

		// This is the live Critical bug (C1): the deleted subject's registry entry must not be
		// dropped until pageSubjects itself stops naming it, or SubjectsManagerPage's `subjects`
		// computed throws mid-render. Queue the deferred for the delete-triggered re-sync call
		// (the mount's own getPageSubjects call already resolved via the beforeEach default above).
		let resolveResync!: ( value: unknown ) => void;
		const resyncPending = new Promise( ( resolve ) => {
			resolveResync = resolve;
		} );
		getPageSubjectsRepoMock.mockReturnValueOnce( resyncPending );

		await wrapper.find( '[aria-label="neowiki-managesubjects-row-delete"]' ).trigger( 'click' );
		wrapper.findComponent( SummaryAction ).vm.$emit( 'save', 'cleanup' );
		await flushPromises();

		// Mid-window: the DELETE is acknowledged server-side but the re-sync has not landed yet.
		// The row must still render — finding it at all is the assertion, since the live bug threw
		// here instead. The success notification (which fires after deleteSubject resolves) has
		// not appeared yet either, confirming this really is the in-flight window.
		expect( rowFor( wrapper, ID_A ).exists() ).toBe( true );
		expect( mw.notify ).not.toHaveBeenCalledWith( 'neowiki-managesubjects-delete-success', { type: 'success' } );

		resolveResync( {
			pageSubjects: new PageSubjects( PAGE_ID, null, [] ),
			referencedSubjects: [],
			schemas: [],
		} );
		await flushPromises();

		expect( rowFor( wrapper, ID_A ).exists() ).toBe( false );
		expect( mw.notify ).toHaveBeenCalledWith( 'neowiki-managesubjects-delete-success', { type: 'success' } );
		expect( reloadMock ).not.toHaveBeenCalled();
	} );

	it( 'shows an error toast and keeps the row when the delete fails', async () => {
		deleteSubjectRepoMock.mockRejectedValue( new Error( 'boom' ) );
		const consoleError = vi.spyOn( console, 'error' ).mockImplementation( () => undefined );

		const wrapper = await mountPage();

		await wrapper.find( '[aria-label="neowiki-managesubjects-row-delete"]' ).trigger( 'click' );
		wrapper.findComponent( SummaryAction ).vm.$emit( 'save', '' );
		await flushPromises();

		expect( mw.notify ).toHaveBeenCalledWith(
			`neowiki-managesubjects-delete-errorLabel ${ ID_A }`,
			{ type: 'error' },
		);
		expect( consoleError ).toHaveBeenCalled();
		expect( reloadMock ).not.toHaveBeenCalled();
		expect( rowFor( wrapper, ID_A ).exists() ).toBe( true );
	} );

} );
