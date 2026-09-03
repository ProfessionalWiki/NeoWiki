import { mount, VueWrapper, flushPromises } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import MoveSubjectDialog from '@/components/SubjectsManager/MoveSubjectDialog.vue';
import PagePicker from '@/components/common/PagePicker.vue';
import SummaryAction from '@/components/common/SummaryAction.vue';
import { useSubjectStore } from '@/stores/SubjectStore.ts';
import { NeoWikiExtension } from '@/NeoWikiExtension.ts';
import { PageSubjects } from '@/domain/PageSubjects.ts';
import { SubjectId } from '@/domain/SubjectId.ts';
import { newSubject } from '@/TestHelpers.ts';
import { createI18nMock } from '../../VueTestHelpers.ts';

const $i18n = createI18nMock();

const SOURCE_PAGE_ID = 7;
const TARGET_PAGE_ID = 12;
const SUBJECT_ID = 's11111111111maa';
const TARGET_MAIN_ID = 's11111111111taa';

// shallowMount's auto-stubs do not render slots, and the Move button lives in CdxDialog's #footer
// slot, so the stub has to keep both slots in the tree.
const CdxDialogStub = {
	template: '<div v-if="open" class="cdx-dialog-stub"><slot /><slot name="footer" /></div>',
	props: [ 'open', 'title', 'useCloseButton' ],
	emits: [ 'update:open' ],
};

const PagePickerStub = {
	template: '<div class="page-picker-stub" />',
	props: [ 'excludedPageId', 'ariaLabel' ],
	emits: [ 'update:selected' ],
};

describe( 'MoveSubjectDialog', () => {
	let createMock: ReturnType<typeof vi.fn>;
	let getPageSubjectsMock: ReturnType<typeof vi.fn>;
	let subjectStore: ReturnType<typeof useSubjectStore>;
	let pinia: ReturnType<typeof createPinia>;

	function mountDialog( props: Record<string, unknown> = {} ): VueWrapper {
		return mount( MoveSubjectDialog, {
			props: {
				open: true,
				subjectId: SUBJECT_ID,
				subjectName: 'Rembrandt',
				currentPageId: SOURCE_PAGE_ID,
				currentPageTitle: 'Amsterdam Museum',
				subjectIsMainSubject: false,
				...props,
			},
			global: {
				mocks: { $i18n },
				plugins: [ pinia ],
				stubs: { CdxDialog: CdxDialogStub, PagePicker: PagePickerStub },
			},
		} );
	}

	async function pick( wrapper: VueWrapper, choice: unknown ): Promise<void> {
		wrapper.findComponent( PagePicker ).vm.$emit( 'update:selected', choice );
		await flushPromises();
	}

	async function clickMove( wrapper: VueWrapper, summary = '' ): Promise<void> {
		wrapper.findComponent( SummaryAction ).vm.$emit( 'save', summary );
		await flushPromises();
	}

	function lastEmitted( wrapper: VueWrapper, event: string ): unknown {
		const events = wrapper.emitted( event ) ?? [];
		return events[ events.length - 1 ];
	}

	function textOf( wrapper: VueWrapper ): string {
		return wrapper.text();
	}

	beforeEach( () => {
		// One pinia, shared by the test and the mounted component: two instances would leave the
		// component using a store this test never stubbed.
		pinia = createPinia();
		setActivePinia( pinia );

		createMock = vi.fn().mockResolvedValue( { result: 'Success', pageid: 99 } );
		vi.stubGlobal( 'mw', {
			msg: vi.fn( ( key: string, ...params: string[] ) => key + params.join( '' ) ),
			message: vi.fn( ( key: string, ...params: string[] ) => ( {
				text: () => key + params.join( '' ),
				parse: () => key + params.join( '' ),
			} ) ),
			notify: vi.fn(),
			Api: vi.fn( function ( this: { create: typeof createMock } ) {
				this.create = createMock;
			} ),
		} );

		getPageSubjectsMock = vi.fn().mockResolvedValue( {
			pageSubjects: new PageSubjects( TARGET_PAGE_ID, null, [] ),
			referencedSubjects: [],
			schemas: [],
		} );

		vi.spyOn( NeoWikiExtension, 'getInstance' ).mockReturnValue(
			{ getSubjectRepository: () => ( { getPageSubjects: getPageSubjectsMock } ) } as unknown as NeoWikiExtension,
		);

		subjectStore = useSubjectStore();
		subjectStore.moveSubject = vi.fn().mockResolvedValue( undefined );
	} );

	afterEach( () => {
		vi.restoreAllMocks();
		vi.unstubAllGlobals();
	} );

	it( 'leaves the move unavailable until a target page is chosen', () => {
		expect( mountDialog().findComponent( SummaryAction ).props( 'saveDisabled' ) ).toBe( true );
	} );

	it( 'offers the move once a target page is chosen', async () => {
		const wrapper = mountDialog();

		await pick( wrapper, { pageId: TARGET_PAGE_ID, title: 'Rembrandt van Rijn' } );

		expect( wrapper.findComponent( SummaryAction ).props( 'saveDisabled' ) ).toBe( false );
	} );

	it( 'keeps the page the subject is already on out of the picker', () => {
		expect( mountDialog().findComponent( PagePicker ).props( 'excludedPageId' ) ).toBe( SOURCE_PAGE_ID );
	} );

	it( 'moves the subject to the chosen page', async () => {
		const wrapper = mountDialog();
		await pick( wrapper, { pageId: TARGET_PAGE_ID, title: 'Rembrandt van Rijn' } );

		await clickMove( wrapper, 'Filed properly' );

		expect( subjectStore.moveSubject ).toHaveBeenCalledWith(
			new SubjectId( SUBJECT_ID ),
			TARGET_PAGE_ID,
			false,
			'Filed properly',
		);
	} );

	it( 'passes the promotion choice through to the move', async () => {
		const wrapper = mountDialog();
		await pick( wrapper, { pageId: TARGET_PAGE_ID, title: 'Rembrandt van Rijn' } );

		await wrapper.find( 'input[type="checkbox"]' ).setValue( true );
		await clickMove( wrapper );

		expect( subjectStore.moveSubject ).toHaveBeenCalledWith(
			new SubjectId( SUBJECT_ID ),
			TARGET_PAGE_ID,
			true,
			undefined,
		);
	} );

	it( 'leaves the promotion unchecked by default', async () => {
		const wrapper = mountDialog();
		await pick( wrapper, { pageId: TARGET_PAGE_ID, title: 'Rembrandt van Rijn' } );

		expect( ( wrapper.find( 'input[type="checkbox"]' ).element as HTMLInputElement ).checked ).toBe( false );
	} );

	it( 'names the main subject that promoting would demote', async () => {
		getPageSubjectsMock.mockResolvedValue( {
			pageSubjects: new PageSubjects( TARGET_PAGE_ID, new SubjectId( TARGET_MAIN_ID ), [
				newSubject( { id: TARGET_MAIN_ID, label: 'The Night Watch' } ),
			] ),
			referencedSubjects: [],
			schemas: [],
		} );
		const wrapper = mountDialog();
		await pick( wrapper, { pageId: TARGET_PAGE_ID, title: 'Rembrandt van Rijn' } );

		await wrapper.find( 'input[type="checkbox"]' ).setValue( true );

		expect( textOf( wrapper ) ).toContain( 'neowiki-managesubjects-move-demotes' );
		expect( textOf( wrapper ) ).toContain( 'The Night Watch' );
	} );

	it( 'says nothing about demotion while promoting is unchecked', async () => {
		getPageSubjectsMock.mockResolvedValue( {
			pageSubjects: new PageSubjects( TARGET_PAGE_ID, new SubjectId( TARGET_MAIN_ID ), [
				newSubject( { id: TARGET_MAIN_ID, label: 'The Night Watch' } ),
			] ),
			referencedSubjects: [],
			schemas: [],
		} );
		const wrapper = mountDialog();

		await pick( wrapper, { pageId: TARGET_PAGE_ID, title: 'Rembrandt van Rijn' } );

		expect( textOf( wrapper ) ).not.toContain( 'neowiki-managesubjects-move-demotes' );
	} );

	it( 'says nothing about demotion for a target page that has no main subject', async () => {
		const wrapper = mountDialog();
		await pick( wrapper, { pageId: TARGET_PAGE_ID, title: 'Rembrandt van Rijn' } );

		await wrapper.find( 'input[type="checkbox"]' ).setValue( true );

		expect( textOf( wrapper ) ).not.toContain( 'neowiki-managesubjects-move-demotes' );
	} );

	it( 'warns that the source page loses its main subject when that is what is moving', () => {
		expect( textOf( mountDialog( { subjectIsMainSubject: true } ) ) )
			.toContain( 'neowiki-managesubjects-move-source-loses-main' );
	} );

	it( 'carries no such warning when a child subject is moving', () => {
		expect( textOf( mountDialog() ) ).not.toContain( 'neowiki-managesubjects-move-source-loses-main' );
	} );

	it( 'says a target page that does not exist yet will be created', async () => {
		const wrapper = mountDialog();

		await pick( wrapper, { pageId: null, title: 'Rembrandt van Rijn' } );

		expect( textOf( wrapper ) ).toContain( 'neowiki-managesubjects-move-creates-page' );
	} );

	it( 'creates a target page that does not exist yet, then moves onto it', async () => {
		const wrapper = mountDialog();
		await pick( wrapper, { pageId: null, title: 'Rembrandt van Rijn' } );

		await clickMove( wrapper );

		expect( createMock ).toHaveBeenCalledWith(
			'Rembrandt van Rijn',
			{ summary: 'neowiki-managesubjects-move-create-page-summary-default' },
			'',
		);
		expect( subjectStore.moveSubject ).toHaveBeenCalledWith(
			new SubjectId( SUBJECT_ID ),
			99,
			false,
			undefined,
		);
	} );

	it( 'creates no page when the target already exists', async () => {
		const wrapper = mountDialog();
		await pick( wrapper, { pageId: TARGET_PAGE_ID, title: 'Rembrandt van Rijn' } );

		await clickMove( wrapper );

		expect( createMock ).not.toHaveBeenCalled();
	} );

	it( 'reports the move and closes once it lands', async () => {
		const wrapper = mountDialog();
		await pick( wrapper, { pageId: TARGET_PAGE_ID, title: 'Rembrandt van Rijn' } );

		await clickMove( wrapper );

		expect( wrapper.emitted( 'moved' ) ).toEqual( [ [ 'Rembrandt van Rijn' ] ] );
		expect( lastEmitted( wrapper, 'update:open' ) ).toEqual( [ false ] );
	} );

	it( 'shows the server\'s own reason at the field when the move is refused', async () => {
		( subjectStore.moveSubject as ReturnType<typeof vi.fn> )
			.mockRejectedValue( new Error( 'Subject is already on the target page' ) );
		const wrapper = mountDialog();
		await pick( wrapper, { pageId: TARGET_PAGE_ID, title: 'Rembrandt van Rijn' } );

		await clickMove( wrapper );

		expect( textOf( wrapper ) ).toContain( 'Subject is already on the target page' );
		expect( wrapper.emitted( 'moved' ) ).toBeUndefined();
	} );

	it( 'names the clash when the page it was going to create already exists', async () => {
		createMock.mockRejectedValue( { code: 'articleexists' } );
		const wrapper = mountDialog();
		await pick( wrapper, { pageId: null, title: 'Rembrandt van Rijn' } );

		await clickMove( wrapper );

		expect( textOf( wrapper ) ).toContain( 'neowiki-managesubjects-move-page-taken' );
		expect( subjectStore.moveSubject ).not.toHaveBeenCalled();
	} );

	it( 'blames the page, not the move, when creating the page is refused', async () => {
		createMock.mockRejectedValue( { code: 'invalidtitle' } );
		const wrapper = mountDialog();
		await pick( wrapper, { pageId: null, title: 'Rembrandt|van Rijn' } );

		await clickMove( wrapper );

		expect( textOf( wrapper ) ).toContain( 'neowiki-managesubjects-move-create-page-error' );
		expect( textOf( wrapper ) ).not.toContain( 'neowiki-managesubjects-move-error' );
		expect( subjectStore.moveSubject ).not.toHaveBeenCalled();
	} );

	it( 'does not move when the page could not be created', async () => {
		createMock.mockResolvedValue( { result: 'Failure' } );
		const wrapper = mountDialog();
		await pick( wrapper, { pageId: null, title: 'Rembrandt van Rijn' } );

		await clickMove( wrapper );

		expect( subjectStore.moveSubject ).not.toHaveBeenCalled();
		expect( textOf( wrapper ) ).toContain( 'neowiki-managesubjects-move-create-page-error' );
	} );

	it( 'retries onto the page it already created rather than creating it twice', async () => {
		// The dialog stays open on failure, and the created page is real by then: a retry that took
		// the create branch again would only ever hit articleexists.
		( subjectStore.moveSubject as ReturnType<typeof vi.fn> )
			.mockRejectedValueOnce( new Error( 'Transient' ) )
			.mockResolvedValueOnce( undefined );
		const wrapper = mountDialog();
		await pick( wrapper, { pageId: null, title: 'Rembrandt van Rijn' } );

		await clickMove( wrapper );
		await clickMove( wrapper );

		expect( createMock ).toHaveBeenCalledTimes( 1 );
		expect( ( subjectStore.moveSubject as ReturnType<typeof vi.fn> ).mock.calls[ ( subjectStore.moveSubject as ReturnType<typeof vi.fn> ).mock.calls.length - 1 ][ 1 ] ).toBe( 99 );
		expect( wrapper.emitted( 'moved' ) ).toEqual( [ [ 'Rembrandt van Rijn' ] ] );
	} );

	it( 'forgets the previous target when it is reopened for another subject', async () => {
		// Cancelling leaves the component mounted - the host only drops it after a successful move -
		// so without the reset the next subject would inherit this one's target and checkbox.
		const wrapper = mountDialog();
		await pick( wrapper, { pageId: TARGET_PAGE_ID, title: 'Rembrandt van Rijn' } );
		await wrapper.find( 'input[type="checkbox"]' ).setValue( true );

		await wrapper.setProps( { open: false } );
		await wrapper.setProps( { open: true, subjectId: 's11111111111bbb', subjectName: 'Another' } );

		expect( wrapper.findComponent( SummaryAction ).props( 'saveDisabled' ) ).toBe( true );
		expect( ( wrapper.find( 'input[type="checkbox"]' ).element as HTMLInputElement ).checked ).toBe( false );
	} );

	it( 'clears a previous failure when it is reopened', async () => {
		( subjectStore.moveSubject as ReturnType<typeof vi.fn> ).mockRejectedValue( new Error( 'Nope' ) );
		const wrapper = mountDialog();
		await pick( wrapper, { pageId: TARGET_PAGE_ID, title: 'Rembrandt van Rijn' } );
		await clickMove( wrapper );
		expect( textOf( wrapper ) ).toContain( 'Nope' );

		await wrapper.setProps( { open: false } );
		await wrapper.setProps( { open: true } );

		expect( textOf( wrapper ) ).not.toContain( 'Nope' );
	} );

	it( 'keeps the move available when the target page could not be read', async () => {
		getPageSubjectsMock.mockRejectedValue( new Error( 'unreadable' ) );
		vi.spyOn( console, 'error' ).mockImplementation( () => undefined );
		const wrapper = mountDialog();

		await pick( wrapper, { pageId: TARGET_PAGE_ID, title: 'Rembrandt van Rijn' } );

		expect( wrapper.findComponent( SummaryAction ).props( 'saveDisabled' ) ).toBe( false );
	} );

} );
