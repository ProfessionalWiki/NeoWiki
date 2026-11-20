import { DOMWrapper, enableAutoUnmount, flushPromises, mount, VueWrapper } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import LayoutEditor, { type LayoutEditorExposes } from '@/components/LayoutEditor/LayoutEditor.vue';
import LayoutPreview from '@/components/LayoutEditor/LayoutPreview.vue';
import DisplayRuleList from '@/components/LayoutEditor/DisplayRuleList.vue';
import DisplayRuleEditor from '@/components/LayoutEditor/DisplayRuleEditor.vue';
import { InMemorySchemaRepository } from '@/application/SchemaRepository.ts';
import { Layout } from '@/domain/Layout.ts';
import { PropertyDefinitionList } from '@/domain/PropertyDefinitionList.ts';
import { PropertyName } from '@/domain/PropertyDefinition.ts';
import { newRelationProperty } from '@/domain/propertyTypes/Relation.ts';
import { newTextProperty } from '@/domain/propertyTypes/Text.ts';
import { newNumberProperty } from '@/domain/propertyTypes/Number.ts';
import { NeoWikiExtension } from '@/NeoWikiExtension.ts';
import { Service } from '@/NeoWikiServices.ts';
import { newDisplayRules, newSchema } from '@/TestHelpers.ts';
import { createI18nMock, setupMwMock } from '../../VueTestHelpers.ts';

// Attached, because where the focus goes is part of what is asserted.
enableAutoUnmount( afterEach );

const museum = newSchema( {
	title: 'Museum',
	properties: new PropertyDefinitionList( [
		newTextProperty( { name: 'Description' } ),
		newRelationProperty( { name: 'Attendance figures', targetSchemas: [ 'Attendance' ], multiple: true } ),
		newTextProperty( { name: 'Website' } ),
		newNumberProperty( { name: 'Visitors' } ),
	] ),
} );

const attendance = newSchema( { title: 'Attendance', subjectsAreDependent: true } );

const MUSEUM_OVERVIEW = new Layout( 'Museum overview', 'Museum', 'infobox', '', [], {} );

describe( 'LayoutEditor', () => {
	let pinia: ReturnType<typeof createPinia>;
	let schemaRepository: InMemorySchemaRepository;

	beforeEach( () => {
		setupMwMock();
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
					[ Service.ComponentRegistry ]: NeoWikiExtension.getInstance().getTypeSpecificComponentRegistry(),
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
		const found = wrapper.findAll( '.ext-neowiki-display-rule-list__item' )
			.find( ( item ) => item.find( '.ext-neowiki-display-rule-list__item__name' ).text() === name );
		expect( found, `row ${ name }` ).toBeDefined();
		return found!;
	}

	function list( wrapper: VueWrapper ): VueWrapper {
		return wrapper.findComponent( DisplayRuleList );
	}

	function listProp( wrapper: VueWrapper, name: string ): unknown {
		return ( list( wrapper ).props() as Record<string, unknown> )[ name ];
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

		expect( previewedRuleNames( wrapper ) ).toEqual( [ 'Description', 'Visitors' ] );
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

		expect( previewedRuleNames( wrapper ) ).toEqual( [ 'Description', 'Visitors' ] );
		expect( previewProp( wrapper, 'hoveredProperty' ) ).toBeNull();
	} );

	it( 'gives the Layout as edited to save', async () => {
		const wrapper = await mountEditor( MUSEUM_OVERVIEW );

		await hide( wrapper, 'Website' );
		await wrapper.find( 'textarea' ).setValue( 'Key facts' );

		const edited = ( wrapper.vm as unknown as LayoutEditorExposes ).getLayout();
		expect( edited.getDescription() ).toBe( 'Key facts' );
		expect( edited.getDisplayRules().map( ( rule ) => rule.property.toString() ) ).toEqual( [ 'Description', 'Visitors' ] );
	} );

	it( 'keeps the focus on a property\'s eye as hiding it moves its row to the hidden ones', async () => {
		const wrapper = await mountEditor( MUSEUM_OVERVIEW );
		const eye = row( wrapper, 'Website' ).find( '.ext-neowiki-display-rule-list__item__action' );
		( eye.element as HTMLElement ).focus();

		await eye.trigger( 'click' );
		await flushPromises();

		expect( hiddenProperties( wrapper ) ).toContain( 'Website' );
		expect( document.activeElement ).toBe( row( wrapper, 'Website' ).find( '.ext-neowiki-display-rule-list__item__action' ).element );
	} );

	describe( 'a property\'s Display Attributes', () => {

		function ruleEditor( wrapper: VueWrapper ): VueWrapper {
			return wrapper.findComponent( DisplayRuleEditor );
		}

		async function openFromList( wrapper: VueWrapper, name: string ): Promise<void> {
			await row( wrapper, name ).trigger( 'click' );
			await flushPromises();
		}

		it( 'opens from its row, with focus in its first field', async () => {
			const wrapper = await mountEditor( MUSEUM_OVERVIEW );

			await openFromList( wrapper, 'Visitors' );

			expect( ( ruleEditor( wrapper ).props() as Record<string, unknown> ).property ).toEqual( expect.objectContaining( { name: new PropertyName( 'Visitors' ) } ) );
			expect( document.activeElement ).toBe( ruleEditor( wrapper ).find( 'input' ).element );
		} );

		it( 'opens holding the Display Attributes the Layout sets', async () => {
			const wrapper = await mountEditor( new Layout( 'Museum overview', 'Museum', 'infobox', '', [
				{ property: new PropertyName( 'Description' ) },
				{ property: new PropertyName( 'Visitors' ), displayAttributes: { precision: 1 } },
			], {} ) );

			await openFromList( wrapper, 'Visitors' );

			expect( ( ruleEditor( wrapper ).find( 'input' ).element as HTMLInputElement ).value ).toBe( '1' );
		} );

		it( 'opens from the preview, with focus in its first field', async () => {
			const wrapper = await mountEditor( MUSEUM_OVERVIEW );

			await preview( wrapper ).vm.$emit( 'select', 'Visitors' );
			await flushPromises();

			expect( document.activeElement ).toBe( ruleEditor( wrapper ).find( 'input' ).element );
		} );

		it( 'marks in the preview no property pointed at in a list it replaced', async () => {
			const wrapper = await mountEditor( MUSEUM_OVERVIEW );
			await row( wrapper, 'Visitors' ).trigger( 'mouseenter' );

			await openFromList( wrapper, 'Visitors' );

			expect( previewProp( wrapper, 'hoveredProperty' ) ).toBeNull();
		} );

		it( 'marks in the preview the property whose Display Attributes are open', async () => {
			const wrapper = await mountEditor( MUSEUM_OVERVIEW );

			await openFromList( wrapper, 'Visitors' );

			expect( previewProp( wrapper, 'selectedProperty' ) ).toBe( 'Visitors' );
		} );

		it( 'previews the Display Attributes set, the other properties shown as before', async () => {
			const wrapper = await mountEditor( MUSEUM_OVERVIEW );
			await openFromList( wrapper, 'Visitors' );

			await ruleEditor( wrapper ).vm.$emit( 'update:displayAttributes', { precision: 2 } );

			expect( ( previewProp( wrapper, 'layout' ) as Layout ).getDisplayRules() ).toEqual( [
				{ property: new PropertyName( 'Description' ) },
				{ property: new PropertyName( 'Website' ) },
				{ property: new PropertyName( 'Visitors' ), displayAttributes: { precision: 2 } },
			] );
		} );

		it( 'marks a property whose Display Attributes have changes not yet saved', async () => {
			const wrapper = await mountEditor( MUSEUM_OVERVIEW );
			await openFromList( wrapper, 'Visitors' );

			await ruleEditor( wrapper ).vm.$emit( 'update:displayAttributes', { precision: 2 } );

			expect( listProp( wrapper, 'unsavedProperties' ) ).toEqual( [ 'Visitors' ] );
		} );

		it( 'marks no property whose Display Attributes are set back to those the Layout was opened with', async () => {
			const wrapper = await mountEditor( new Layout( 'Museum overview', 'Museum', 'infobox', '', [
				{ property: new PropertyName( 'Description' ) },
				{ property: new PropertyName( 'Visitors' ), displayAttributes: { precision: 1 } },
			], {} ) );
			await openFromList( wrapper, 'Visitors' );

			await ruleEditor( wrapper ).vm.$emit( 'update:displayAttributes', { precision: 2 } );
			await ruleEditor( wrapper ).vm.$emit( 'update:displayAttributes', { precision: 1 } );

			expect( listProp( wrapper, 'unsavedProperties' ) ).toEqual( [] );
		} );

		it( 'keeps the Display Attributes an edit leaves out', async () => {
			const wrapper = await mountEditor( new Layout( 'Museum overview', 'Museum', 'infobox', '', [
				{ property: new PropertyName( 'Visitors' ), displayAttributes: { precision: 1, colour: 'red' } },
			], {} ) );
			await openFromList( wrapper, 'Visitors' );

			await ruleEditor( wrapper ).vm.$emit( 'update:displayAttributes', { precision: undefined } );

			expect( ( previewProp( wrapper, 'layout' ) as Layout ).getDisplayRules() ).toEqual( [
				{ property: new PropertyName( 'Visitors' ), displayAttributes: { colour: 'red' } },
			] );
		} );

		it( 'leaves the Layout as it was for an edit that sets nothing new', async () => {
			const wrapper = await mountEditor( MUSEUM_OVERVIEW );
			await openFromList( wrapper, 'Visitors' );

			await ruleEditor( wrapper ).vm.$emit( 'update:displayAttributes', { precision: undefined } );

			expect( wrapper.emitted( 'change' ) ).toBeUndefined();
			expect( ( wrapper.vm as unknown as LayoutEditorExposes ).getLayout().getDisplayRules() ).toEqual( [] );
		} );

		it( 'goes back to the properties with focus on the row it was opened from', async () => {
			const wrapper = await mountEditor( MUSEUM_OVERVIEW );
			await openFromList( wrapper, 'Visitors' );

			await ruleEditor( wrapper ).vm.$emit( 'back' );
			await flushPromises();

			expect( ruleEditor( wrapper ).exists() ).toBe( false );
			expect( document.activeElement ).toBe( row( wrapper, 'Visitors' ).element );
			expect( previewProp( wrapper, 'selectedProperty' ) ).toBeNull();
		} );

		it( 'goes back to the properties to select one without Display Attributes clicked in the preview', async () => {
			const wrapper = await mountEditor( MUSEUM_OVERVIEW );
			await openFromList( wrapper, 'Visitors' );

			await preview( wrapper ).vm.$emit( 'select', 'Website' );
			await flushPromises();

			expect( ruleEditor( wrapper ).exists() ).toBe( false );
			expect( document.activeElement ).toBe( row( wrapper, 'Website' ).element );
		} );

	} );

	it( 'selects in the list, with focus on it, the property selected in the preview', async () => {
		const wrapper = await mountEditor( MUSEUM_OVERVIEW );

		await preview( wrapper ).vm.$emit( 'select', 'Website' );
		await flushPromises();

		expect( row( wrapper, 'Website' ).attributes( 'aria-selected' ) ).toBe( 'true' );
		expect( document.activeElement ).toBe( row( wrapper, 'Website' ).element );
	} );

	it( 'marks in the preview the property selected in the list', async () => {
		const wrapper = await mountEditor( MUSEUM_OVERVIEW );

		await row( wrapper, 'Website' ).trigger( 'click' );

		expect( previewProp( wrapper, 'selectedProperty' ) ).toBe( 'Website' );
	} );

	it( 'lets go of the property selected in the list when it is selected again', async () => {
		const wrapper = await mountEditor( MUSEUM_OVERVIEW );

		await row( wrapper, 'Website' ).trigger( 'click' );
		await row( wrapper, 'Website' ).trigger( 'click' );

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
