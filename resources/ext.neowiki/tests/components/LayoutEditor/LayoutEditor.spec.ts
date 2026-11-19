import { DOMWrapper, enableAutoUnmount, flushPromises, mount, VueWrapper } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import LayoutEditor, { type LayoutEditorExposes } from '@/components/LayoutEditor/LayoutEditor.vue';
import LayoutPreview from '@/components/LayoutEditor/LayoutPreview.vue';
import DisplayRuleList from '@/components/LayoutEditor/DisplayRuleList.vue';
import { InMemorySchemaRepository } from '@/application/SchemaRepository.ts';
import { Layout } from '@/domain/Layout.ts';
import { PropertyDefinitionList } from '@/domain/PropertyDefinitionList.ts';
import { newRelationProperty } from '@/domain/propertyTypes/Relation.ts';
import { newTextProperty } from '@/domain/propertyTypes/Text.ts';
import { NeoWikiExtension } from '@/NeoWikiExtension.ts';
import { Service } from '@/NeoWikiServices.ts';
import { newDisplayRules, newSchema } from '@/TestHelpers.ts';
import { createI18nMock } from '../../VueTestHelpers.ts';

// Attached, because where the focus goes is part of what is asserted.
enableAutoUnmount( afterEach );

const museum = newSchema( {
	title: 'Museum',
	properties: new PropertyDefinitionList( [
		newTextProperty( { name: 'Description' } ),
		newRelationProperty( { name: 'Attendance figures', targetSchemas: [ 'Attendance' ], multiple: true } ),
		newTextProperty( { name: 'Website' } ),
	] ),
} );

const attendance = newSchema( { title: 'Attendance', subjectsAreDependent: true } );

const MUSEUM_OVERVIEW = new Layout( 'Museum overview', 'Museum', 'infobox', '', [], {} );

describe( 'LayoutEditor', () => {
	let pinia: ReturnType<typeof createPinia>;
	let schemaRepository: InMemorySchemaRepository;

	beforeEach( () => {
		pinia = createPinia();
		setActivePinia( pinia );

		// The Schema summaries are read through the extension, the edited Schema through the injected services:
		// both reach the one repository, as they reach the one REST API on a wiki.
		schemaRepository = new InMemorySchemaRepository( [ museum, attendance ] );
		vi.spyOn( NeoWikiExtension.getInstance(), 'getSchemaRepository' ).mockReturnValue( schemaRepository );
	} );

	afterEach( () => {
		vi.restoreAllMocks();
	} );

	async function mountEditor( layout: Layout ): Promise<VueWrapper> {
		const wrapper = mount( LayoutEditor, {
			props: { initialLayout: layout },
			attachTo: document.body,
			global: {
				plugins: [ pinia ],
				mocks: { $i18n: createI18nMock() },
				provide: {
					[ Service.SchemaRepository ]: schemaRepository,
				},
				// The preview reads the wiki's Subjects; what matters here is what the editor hands it and does with
				// what it reports.
				stubs: { LayoutPreview: true },
			},
		} );

		await flushPromises();
		return wrapper;
	}

	function hiddenProperties( wrapper: VueWrapper ): string[] {
		return wrapper.findAll( '.ext-neowiki-display-rule-list__item--hidden' ).map( ( row ) => row.text() );
	}

	function row( wrapper: VueWrapper, name: string ): DOMWrapper<Element> {
		const found = wrapper.findAll( '.ext-neowiki-display-rule-list__item' ).find( ( item ) => item.text() === name );
		expect( found, `row ${ name }` ).toBeDefined();
		return found!;
	}

	function nameButton( wrapper: VueWrapper, name: string ): DOMWrapper<Element> {
		return row( wrapper, name ).find( '.ext-neowiki-display-rule-list__item__name' );
	}

	function list( wrapper: VueWrapper ): VueWrapper {
		return wrapper.findComponent( DisplayRuleList );
	}

	function preview( wrapper: VueWrapper ): VueWrapper {
		return wrapper.findComponent( LayoutPreview );
	}

	function previewProp( wrapper: VueWrapper, name: string ): unknown {
		return ( preview( wrapper ).props() as Record<string, unknown> )[ name ];
	}

	function previewedRuleNames( wrapper: VueWrapper ): string[] {
		return ( previewProp( wrapper, 'layout' ) as Layout ).getDisplayRules().map( ( rule ) => rule.property.toString() );
	}

	async function hide( wrapper: VueWrapper, name: string ): Promise<void> {
		await row( wrapper, name ).find( '.ext-neowiki-display-rule-list__item__action' ).trigger( 'click' );
	}

	it( 'hides the properties targeting Dependent Subjects of a Layout without Display Rules', async () => {
		const wrapper = await mountEditor( MUSEUM_OVERVIEW );

		expect( hiddenProperties( wrapper ) ).toEqual( [ 'Attendance figures' ] );
	} );

	it( 'previews the Layout as edited', async () => {
		const wrapper = await mountEditor( MUSEUM_OVERVIEW );

		await hide( wrapper, 'Website' );

		expect( previewedRuleNames( wrapper ) ).toEqual( [ 'Description' ] );
	} );

	it( 'previews the order a dragged property would give, marking that property', async () => {
		const wrapper = await mountEditor( MUSEUM_OVERVIEW );

		await list( wrapper ).vm.$emit( 'reordering', { rules: newDisplayRules( 'Website', 'Description' ), property: 'Website' } );

		expect( previewedRuleNames( wrapper ) ).toEqual( [ 'Website', 'Description' ] );
		expect( previewProp( wrapper, 'hoveredProperty' ) ).toBe( 'Website' );
	} );

	it( 'previews the Layout as edited again once a drag is over', async () => {
		const wrapper = await mountEditor( MUSEUM_OVERVIEW );
		await hide( wrapper, 'Website' );

		await list( wrapper ).vm.$emit( 'reordering', { rules: newDisplayRules( 'Website', 'Description' ), property: 'Website' } );
		await list( wrapper ).vm.$emit( 'reordering', null );

		expect( previewedRuleNames( wrapper ) ).toEqual( [ 'Description' ] );
		expect( previewProp( wrapper, 'hoveredProperty' ) ).toBeNull();
	} );

	it( 'gives the Layout as edited to save', async () => {
		const wrapper = await mountEditor( MUSEUM_OVERVIEW );

		await hide( wrapper, 'Website' );
		await wrapper.find( 'textarea' ).setValue( 'Key facts' );

		const edited = ( wrapper.vm as unknown as LayoutEditorExposes ).getLayout();
		expect( edited.getDescription() ).toBe( 'Key facts' );
		expect( edited.getDisplayRules().map( ( rule ) => rule.property.toString() ) ).toEqual( [ 'Description' ] );
	} );

	it( 'selects in the list, with focus on it, the property selected in the preview', async () => {
		const wrapper = await mountEditor( MUSEUM_OVERVIEW );

		await preview( wrapper ).vm.$emit( 'select', 'Website' );

		expect( nameButton( wrapper, 'Website' ).attributes( 'aria-pressed' ) ).toBe( 'true' );
		expect( document.activeElement ).toBe( nameButton( wrapper, 'Website' ).element );
	} );

	it( 'marks in the preview the property selected in the list', async () => {
		const wrapper = await mountEditor( MUSEUM_OVERVIEW );

		await nameButton( wrapper, 'Website' ).trigger( 'click' );

		expect( previewProp( wrapper, 'selectedProperty' ) ).toBe( 'Website' );
	} );

	it( 'lets go of the property selected in the list when it is selected again', async () => {
		const wrapper = await mountEditor( MUSEUM_OVERVIEW );

		await nameButton( wrapper, 'Website' ).trigger( 'click' );
		await nameButton( wrapper, 'Website' ).trigger( 'click' );

		expect( previewProp( wrapper, 'selectedProperty' ) ).toBeNull();
	} );

	it( 'marks in the list the property pointed at in the preview', async () => {
		const wrapper = await mountEditor( MUSEUM_OVERVIEW );

		await preview( wrapper ).vm.$emit( 'hover', 'Description' );

		expect( row( wrapper, 'Description' ).classes() ).toContain( 'ext-neowiki-display-rule-list__item--hovered' );
	} );

	it( 'marks in the preview the property pointed at in the list', async () => {
		const wrapper = await mountEditor( MUSEUM_OVERVIEW );

		await row( wrapper, 'Website' ).trigger( 'mouseenter' );

		expect( previewProp( wrapper, 'hoveredProperty' ) ).toBe( 'Website' );
	} );

} );
