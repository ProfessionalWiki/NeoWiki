import { DOMWrapper, enableAutoUnmount, flushPromises, mount, VueWrapper } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import { defineComponent, h, type PropType } from 'vue';
import LayoutPreview from '@/components/LayoutEditor/LayoutPreview.vue';
import PreviewSubjectPicker from '@/components/LayoutEditor/PreviewSubjectPicker.vue';
import { Layout } from '@/domain/Layout.ts';
import type { SubjectId } from '@/domain/SubjectId.ts';
import { ViewTypeRegistry } from '@/ViewTypeRegistry.ts';
import { Service } from '@/NeoWikiServices.ts';
import { NeoWikiExtension } from '@/NeoWikiExtension.ts';
import { useSubjectStore } from '@/stores/SubjectStore.ts';
import { useSchemaStore } from '@/stores/SchemaStore.ts';
import type { SubjectSummary, SubjectSummaryLookup } from '@/application/SubjectSummaryLookup.ts';
import type { StoreStateLoader } from '@/persistence/StoreStateLoader.ts';
import { newDisplayRules, newSchema, newSubject } from '@/TestHelpers.ts';
import { clickIsFollowed, createI18nMock, setupMwMock } from '../../VueTestHelpers.ts';

enableAutoUnmount( afterEach );

const LAYOUT = new Layout( 'CompanyOverview', 'Company', 'infobox', '', newDisplayRules( 'Founded at', 'Status' ), {} );

const SUBJECT_NAMES: Record<string, string> = {
	s1demo1aaaaaaa1: 'ACME Inc',
	s1demo1aaaaaaa2: 'Globex',
	s1demo1aaaaaaa3: 'Initech',
};

// What the View's own controls do when clicked, which the preview should leave alone or stop.
const viewHandlers = {
	link: vi.fn(),
	toggle: vi.fn(),
};

// A View Type of the shape every one has: it renders the Subject it is given, with the Layout it is given, and marks
// the element that shows each property. Each property holds a link and a control of the View's own.
const StubView = defineComponent( {
	props: {
		subjectId: { type: Object as PropType<SubjectId>, required: true },
		canEditSubject: { type: Boolean, required: true },
		layout: { type: Object as PropType<Layout>, required: false, default: undefined },
	},
	setup( props ) {
		return () => h( 'div', { class: 'stub-view' }, ( props.layout?.getDisplayRules() ?? [] ).map(
			( rule ) => h( 'div', { 'data-mw-neowiki-property': rule.property.toString() }, [
				h( 'a', { href: '/wiki/Elsewhere', onClick: viewHandlers.link }, rule.property.toString() ),
				h( 'button', { type: 'button', onClick: viewHandlers.toggle }, '+1 language' ),
			] ),
		) );
	},
} );

function summary( id: string ): SubjectSummary {
	return {
		id,
		displayName: SUBJECT_NAMES[ id ],
		displayNameIsGenerated: false,
		hostSubject: null,
		schema: 'Company',
		pageId: 1,
		pageTitle: SUBJECT_NAMES[ id ],
		lastEdited: '2026-11-19T12:00:00Z',
	};
}

describe( 'LayoutPreview', () => {
	let pinia: ReturnType<typeof createPinia>;
	let lookup: SubjectSummaryLookup;
	let loadable: Set<string>;
	let registry: ViewTypeRegistry;

	function editedLast( ...ids: string[] ): void {
		vi.mocked( lookup.getSubjectSummaries ).mockResolvedValue( { subjects: ids.map( summary ), nextCursor: null } );
	}

	beforeEach( () => {
		setupMwMock( { functions: [ 'message', 'msg', 'config', 'language', 'notify' ] } );
		pinia = createPinia();
		setActivePinia( pinia );
		viewHandlers.link.mockClear();
		viewHandlers.toggle.mockClear();

		lookup = {
			getSubjectSummaries: vi.fn(),
			getRecentlyEditedSubjects: vi.fn(),
		};
		editedLast( 's1demo1aaaaaaa1', 's1demo1aaaaaaa2', 's1demo1aaaaaaa3' );

		// Loads as the real one does: each Subject with its Schema, which a View renders from. One left out of
		// `loadable` does not load, as one deleted or no longer readable does not.
		loadable = new Set( Object.keys( SUBJECT_NAMES ) );
		vi.spyOn( NeoWikiExtension.getInstance(), 'getLatestStoreStateLoader' ).mockReturnValue( {
			loadSubjectsAndSchemas: vi.fn( async ( ids: Set<string> ) => {
				[ ...ids ].filter( ( id ) => loadable.has( id ) ).forEach( ( id ) => useSubjectStore().setSubject(
					newSubject( { id, label: SUBJECT_NAMES[ id ], schemaName: 'Company' } ),
				) );
				useSchemaStore().setSchema( 'Company', newSchema( { title: 'Company' } ) );
			} ),
		} as unknown as StoreStateLoader );

		registry = new ViewTypeRegistry();
		registry.registerType( 'infobox', StubView );
	} );

	afterEach( () => {
		vi.restoreAllMocks();
	} );

	async function mountPreview(
		props: Partial<{ layout: Layout; hoveredProperty: string | null; selectedProperty: string | null }> = {},
	): Promise<VueWrapper> {
		const wrapper = mount( LayoutPreview, {
			props: { layout: LAYOUT, hoveredProperty: null, selectedProperty: null, ...props },
			attachTo: document.body,
			global: {
				plugins: [ pinia ],
				mocks: { $i18n: createI18nMock() },
				provide: {
					[ Service.SubjectSummaryLookup ]: lookup,
					[ Service.ViewTypeRegistry ]: registry,
				},
			},
		} );

		await flushPromises();
		return wrapper;
	}

	function view( wrapper: VueWrapper ): VueWrapper {
		return wrapper.findComponent( StubView );
	}

	function viewProps( wrapper: VueWrapper ): Record<string, unknown> {
		return view( wrapper ).props() as Record<string, unknown>;
	}

	function previewedSubjectId( wrapper: VueWrapper ): string {
		return ( viewProps( wrapper ).subjectId as SubjectId ).text;
	}

	function propertyElement( wrapper: VueWrapper, name: string ): DOMWrapper<Element> {
		return wrapper.find( `[data-mw-neowiki-property="${ name }"]` );
	}

	function anotherButton( wrapper: VueWrapper ): DOMWrapper<Element> {
		return wrapper.find( '.ext-neowiki-layout-preview__another' );
	}

	function pickerTrigger( wrapper: VueWrapper ): DOMWrapper<Element> {
		return wrapper.find( '.ext-neowiki-preview-subject-picker__trigger' );
	}

	async function pick( wrapper: VueWrapper, subjectId: string ): Promise<void> {
		wrapper.findComponent( PreviewSubjectPicker ).vm.$emit( 'pick', subjectId );
		await flushPromises();
	}

	it( 'previews the Subject of the Layout\'s Schema edited last', async () => {
		const wrapper = await mountPreview();

		expect( lookup.getSubjectSummaries ).toHaveBeenCalledWith( expect.objectContaining( {
			schema: 'Company',
			search: '',
			sort: 'edited',
			direction: 'desc',
		} ) );
		expect( previewedSubjectId( wrapper ) ).toBe( 's1demo1aaaaaaa1' );
	} );

	it( 'names the previewed Subject in its toolbar', async () => {
		const wrapper = await mountPreview();

		expect( pickerTrigger( wrapper ).text() ).toBe( 'ACME Inc' );
	} );

	it( 'renders the View with the Layout it is handed, without the button that edits the Subject', async () => {
		const wrapper = await mountPreview();

		expect( viewProps( wrapper ).layout ).toEqual( LAYOUT );
		expect( viewProps( wrapper ).canEditSubject ).toBe( false );
	} );

	it( 'follows the Layout as it changes', async () => {
		const wrapper = await mountPreview();
		const changed = new Layout( 'CompanyOverview', 'Company', 'infobox', '', newDisplayRules( 'Status' ), {} );

		await wrapper.setProps( { layout: changed } );

		expect( viewProps( wrapper ).layout ).toEqual( changed );
	} );

	it( 'previews the Subject picked in its toolbar', async () => {
		const wrapper = await mountPreview();

		await pick( wrapper, 's1demo1aaaaaaa2' );

		expect( previewedSubjectId( wrapper ) ).toBe( 's1demo1aaaaaaa2' );
	} );

	it( 'previews another of the Subjects edited last, never the one it shows, when asked', async () => {
		vi.spyOn( Math, 'random' ).mockReturnValue( 0.5 );
		const wrapper = await mountPreview();

		await anotherButton( wrapper ).trigger( 'click' );
		await flushPromises();

		expect( previewedSubjectId( wrapper ) ).toBe( 's1demo1aaaaaaa3' );
	} );

	it( 'offers no other Subject when the Schema has one', async () => {
		editedLast( 's1demo1aaaaaaa1' );

		const wrapper = await mountPreview();

		expect( anotherButton( wrapper ).attributes( 'disabled' ) ).toBeDefined();
	} );

	it( 'says so in place of the View, and offers no Subject to pick, when no Subject uses the Schema', async () => {
		editedLast();

		const wrapper = await mountPreview();

		expect( view( wrapper ).exists() ).toBe( false );
		expect( wrapper.find( '.ext-neowiki-layout-preview__no-subjects' ).text() ).toBe( 'neowiki-layout-preview-no-subjects' );
		expect( pickerTrigger( wrapper ).attributes( 'disabled' ) ).toBeDefined();
	} );

	it( 'says so in place of the View when the Subjects cannot be listed', async () => {
		vi.mocked( lookup.getSubjectSummaries ).mockRejectedValue( new Error( 'Network down' ) );

		const wrapper = await mountPreview();

		expect( view( wrapper ).exists() ).toBe( false );
		expect( wrapper.text() ).toContain( 'neowiki-layout-preview-load-error' );
	} );

	it( 'keeps previewing the Subject it shows, and says so, when a picked one does not load', async () => {
		loadable.delete( 's1demo1aaaaaaa2' );
		const wrapper = await mountPreview();

		await pick( wrapper, 's1demo1aaaaaaa2' );

		expect( previewedSubjectId( wrapper ) ).toBe( 's1demo1aaaaaaa1' );
		expect( mw.notify ).toHaveBeenCalledWith( 'neowiki-layout-preview-load-error', { type: 'error' } );
	} );

	it( 'offers no longer a Subject that did not load', async () => {
		editedLast( 's1demo1aaaaaaa1', 's1demo1aaaaaaa2' );
		loadable.delete( 's1demo1aaaaaaa2' );
		const wrapper = await mountPreview();

		await pick( wrapper, 's1demo1aaaaaaa2' );

		expect( anotherButton( wrapper ).attributes( 'disabled' ) ).toBeDefined();
	} );

	it( 'selects the property clicked in the View, without following the link there or letting the View handle it', async () => {
		const wrapper = await mountPreview();

		const followed = clickIsFollowed( propertyElement( wrapper, 'Status' ).find( 'a' ).element );

		expect( followed ).toBe( false );
		expect( viewHandlers.link ).not.toHaveBeenCalled();
		expect( wrapper.emitted( 'select' ) ).toEqual( [ [ 'Status' ] ] );
	} );

	it( 'leaves a control of the View to act, without selecting its property', async () => {
		const wrapper = await mountPreview();

		await propertyElement( wrapper, 'Status' ).find( 'button' ).trigger( 'click' );

		expect( viewHandlers.toggle ).toHaveBeenCalled();
		expect( wrapper.emitted( 'select' ) ).toBeUndefined();
	} );

	it( 'reports the property pointed at in the View, and none once the pointer leaves the View', async () => {
		const wrapper = await mountPreview();

		await propertyElement( wrapper, 'Founded at' ).find( 'a' ).trigger( 'mouseover' );
		await wrapper.find( '.ext-neowiki-layout-preview__stage' ).trigger( 'mouseleave' );

		expect( wrapper.emitted( 'hover' ) ).toEqual( [ [ 'Founded at' ], [ null ] ] );
	} );

	it( 'marks the properties pointed at and selected in the list', async () => {
		const wrapper = await mountPreview( { hoveredProperty: 'Status', selectedProperty: 'Founded at' } );

		expect( propertyElement( wrapper, 'Status' ).classes() ).toContain( 'ext-neowiki-layout-preview__property--hovered' );
		expect( propertyElement( wrapper, 'Founded at' ).classes() ).toContain( 'ext-neowiki-layout-preview__property--selected' );
	} );

	it( 'moves the marks when the list points at another property', async () => {
		const wrapper = await mountPreview( { hoveredProperty: 'Status' } );

		await wrapper.setProps( { hoveredProperty: 'Founded at' } );

		expect( propertyElement( wrapper, 'Status' ).classes() ).not.toContain( 'ext-neowiki-layout-preview__property--hovered' );
		expect( propertyElement( wrapper, 'Founded at' ).classes() ).toContain( 'ext-neowiki-layout-preview__property--hovered' );
	} );

	it( 'marks the selected property again where a changed Layout renders it', async () => {
		const wrapper = await mountPreview( { selectedProperty: 'Status' } );

		await wrapper.setProps( {
			layout: new Layout( 'CompanyOverview', 'Company', 'infobox', '', newDisplayRules( 'Status', 'Founded at' ), {} ),
		} );

		expect( propertyElement( wrapper, 'Status' ).classes() ).toContain( 'ext-neowiki-layout-preview__property--selected' );
		expect( propertyElement( wrapper, 'Founded at' ).classes() ).not.toContain( 'ext-neowiki-layout-preview__property--selected' );
	} );

} );
