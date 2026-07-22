import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { ref } from 'vue';
import { shallowMount, VueWrapper, flushPromises } from '@vue/test-utils';
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
