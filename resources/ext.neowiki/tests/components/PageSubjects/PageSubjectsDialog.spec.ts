import { mount, VueWrapper, flushPromises } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import PageSubjectsDialog from '@/components/PageSubjects/PageSubjectsDialog.vue';
import { useSubjectStore } from '@/stores/SubjectStore.ts';
import { NeoWikiExtension } from '@/NeoWikiExtension.ts';
import { CdxDialog } from '@wikimedia/codex';
import { setupMwMock } from '../../VueTestHelpers.ts';

const PAGE_ID = 42;

interface PageSubjectSummary {
	id: string;
	label: string;
	schema: string;
	isMain: boolean;
}

function stubExtensionWithResponse( body: { subjects: PageSubjectSummary[]; totalRows: number } ): ReturnType<typeof vi.fn> {
	const getMock = vi.fn().mockResolvedValue( {
		ok: true,
		json: async () => body,
	} );

	vi.spyOn( NeoWikiExtension, 'getInstance' ).mockReturnValue( {
		newHttpClient: () => ( { get: getMock } ),
		getMediaWiki: () => ( {
			util: { wikiScript: () => '/rest.php' },
		} ),
	} as unknown as NeoWikiExtension );

	return getMock;
}

function createI18nMockWithParams(): ReturnType<typeof vi.fn> {
	return vi.fn().mockImplementation( ( key: string, ...params: string[] ) => ( {
		text: () => ( params.length === 0 ? key : `${ key }: ${ params.join( ', ' ) }` ),
	} ) );
}

function mountComponent(): VueWrapper {
	return mount( PageSubjectsDialog, {
		global: {
			plugins: [ createPinia() ],
			mocks: { $i18n: createI18nMockWithParams() },
			stubs: {
				CdxDialog: {
					template: '<div class="cdx-dialog-stub"><slot /></div>',
					props: [ 'open', 'title', 'useCloseButton' ],
					emits: [ 'update:open' ],
				},
				CdxInfoChip: {
					template: '<span class="cdx-info-chip-stub"><slot /></span>',
				},
				teleport: true,
			},
		},
	} );
}

describe( 'PageSubjectsDialog', () => {
	beforeEach( () => {
		setActivePinia( createPinia() );
		setupMwMock( {
			functions: [ 'msg', 'config', 'util', 'notify' ],
			config: { wgArticleId: PAGE_ID, wgTitle: 'Acme Corp' },
		} );
	} );

	it( 'fetches and renders subjects when the dialog is opened', async () => {
		stubExtensionWithResponse( {
			subjects: [
				{ id: 's1demo1sssssss1', label: 'Acme Corp', schema: 'Company', isMain: true },
				{ id: 's1demo2sssssss2', label: 'Bob Example', schema: 'Person', isMain: false },
			],
			totalRows: 2,
		} );

		const wrapper = mountComponent();
		const store = useSubjectStore();

		store.openPageSubjects();
		await flushPromises();

		const rows = wrapper.findAll( 'tbody tr' );
		expect( rows ).toHaveLength( 2 );

		expect( rows[ 0 ].text() ).toContain( 'Acme Corp' );
		expect( rows[ 0 ].text() ).toContain( 's1demo1sssssss1' );
		expect( rows[ 0 ].find( '.cdx-info-chip-stub' ).exists() ).toBe( true );
		expect( rows[ 0 ].find( '.cdx-info-chip-stub' ).text() ).toBe( 'neowiki-page-subjects-main-badge' );

		expect( rows[ 1 ].text() ).toContain( 'Bob Example' );
		expect( rows[ 1 ].find( '.cdx-info-chip-stub' ).exists() ).toBe( false );
	} );

	it( 'includes the page name in the dialog title', async () => {
		stubExtensionWithResponse( { subjects: [], totalRows: 0 } );

		const wrapper = mountComponent();

		const dialog = wrapper.findComponent( CdxDialog );
		expect( dialog.props( 'title' ) ).toContain( 'Acme Corp' );
	} );

	it( 'renders schema names as links to the Schema page', async () => {
		stubExtensionWithResponse( {
			subjects: [
				{ id: 's1demo1sssssss1', label: 'Acme Corp', schema: 'Company', isMain: true },
			],
			totalRows: 1,
		} );

		const wrapper = mountComponent();
		const store = useSubjectStore();

		store.openPageSubjects();
		await flushPromises();

		const link = wrapper.find( 'tbody tr a' );
		expect( link.exists() ).toBe( true );
		expect( link.text() ).toBe( 'Company' );
		expect( link.attributes( 'href' ) ).toBe( '/wiki/Schema:Company' );
	} );

	it( 'renders the empty-state message when the page has no subjects', async () => {
		stubExtensionWithResponse( { subjects: [], totalRows: 0 } );

		const wrapper = mountComponent();
		const store = useSubjectStore();

		store.openPageSubjects();
		await flushPromises();

		expect( wrapper.text() ).toContain( 'neowiki-page-subjects-empty' );
	} );

	it( 'notifies the user when the fetch fails', async () => {
		const getMock = vi.fn().mockResolvedValue( {
			ok: false,
			status: 500,
		} );

		vi.spyOn( NeoWikiExtension, 'getInstance' ).mockReturnValue( {
			newHttpClient: () => ( { get: getMock } ),
			getMediaWiki: () => ( {
				util: { wikiScript: () => '/rest.php' },
			} ),
		} as unknown as NeoWikiExtension );

		mountComponent();
		const store = useSubjectStore();

		store.openPageSubjects();
		await flushPromises();

		expect( mw.notify ).toHaveBeenCalledWith(
			expect.anything(),
			expect.objectContaining( { type: 'error' } ),
		);
	} );

	it( 'closes the store flag when the dialog close is emitted', async () => {
		stubExtensionWithResponse( { subjects: [], totalRows: 0 } );

		const wrapper = mountComponent();
		const store = useSubjectStore();

		store.openPageSubjects();
		await flushPromises();
		expect( store.pageSubjectsOpen ).toBe( true );

		wrapper.findComponent( CdxDialog ).vm.$emit( 'update:open', false );
		await flushPromises();

		expect( store.pageSubjectsOpen ).toBe( false );
	} );
} );
