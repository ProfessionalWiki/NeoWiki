import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { ref } from 'vue';
import { shallowMount, VueWrapper, flushPromises } from '@vue/test-utils';
import { CdxMenuButton } from '@wikimedia/codex';
import SubjectsManagerPage from '@/components/SubjectsManager/SubjectsManagerPage.vue';
import { createI18nMock, setupMwMock } from '../../VueTestHelpers.ts';
import { Subject } from '@/domain/Subject.ts';
import { SubjectId } from '@/domain/SubjectId.ts';
import { StatementList } from '@/domain/StatementList.ts';
import { subjectRowDomId } from '@/presentation/subjectRowAnchor.ts';

// Two subject-id-shaped ids (s + 14 base58 chars), so the deep-link fragment parser accepts them.
const ID_A = 's1aaaaaaaaaaaa1';
const ID_B = 's1bbbbbbbbbbbb1';
const PAGE_ID = 42;

function subject( id: string ): Subject {
	return new Subject( new SubjectId( id ), 'Label ' + id, 'Person', new StatementList( [] ) );
}

const loadPageSubjectsMock = vi.fn().mockResolvedValue( undefined );
let storeSubjects: Subject[] = [];
let mainSubjectId: SubjectId | null = null;

vi.mock( '@/stores/SubjectStore.ts', () => ( {
	useSubjectStore: () => ( {
		loadPageSubjects: loadPageSubjectsMock,
		getSubject: ( id: SubjectId ) => storeSubjects.find( ( s ) => s.getId().text === id.text ),
		openSubjectCreator: vi.fn(),
		get pageSubjects() {
			return {
				getSubjects: () => storeSubjects,
				getMainSubjectId: () => mainSubjectId,
			};
		},
	} ),
} ) );

vi.mock( '@/stores/SchemaStore.ts', () => ( {
	useSchemaStore: () => ( {
		fetchSchema: vi.fn(),
		saveSchema: vi.fn(),
		getSchema: vi.fn(),
	} ),
} ) );

vi.mock( '@/composables/useSubjectPermissions.ts', () => ( {
	useSubjectPermissions: () => ( {
		canCreateMainSubject: ref( false ),
		canCreateChildSubject: ref( false ),
		canEditSubject: ref( false ),
		canDeleteSubject: ref( false ),
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

async function mountPage(): Promise<VueWrapper> {
	setupMwMock( {
		functions: [ 'config', 'msg', 'message', 'notify', 'util' ],
		config: {
			wgNeoWikiManageSubjectsPageId: PAGE_ID,
			wgNeoWikiRdfProjections: [],
			wgNeoWikiSubjectIriBase: '',
		},
	} );

	const wrapper = shallowMount( SubjectsManagerPage, {
		attachTo: document.body,
		global: {
			mocks: { $i18n: createI18nMock() },
			stubs: { CdxIcon: true },
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
