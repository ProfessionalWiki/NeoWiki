import { DOMWrapper, enableAutoUnmount, flushPromises, mount, VueWrapper } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi, beforeEach } from 'vitest';
import PropertyList from '@/components/SchemaEditor/PropertyList.vue';
import { LABEL_ROW, propertyRow, SCHEMA_ROW, type PropertyListRow } from '@/components/SchemaEditor/PropertyListRow.ts';
import { useSortable, type UseSortableOptions } from '@/composables/useSortable.ts';
import { PropertyDefinitionList } from '@/domain/PropertyDefinitionList.ts';
import { createPropertyDefinitionFromJson, PropertyName } from '@/domain/PropertyDefinition.ts';
import { TextType } from '@/domain/propertyTypes/Text.ts';
import { createI18nMock, setupMwMock } from '../../VueTestHelpers.ts';

vi.mock( '@/composables/useSortable.ts', () => ( {
	useSortable: vi.fn(),
} ) );

vi.mock( '@/NeoWikiServices.ts', () => {
	class MockNeoWikiServices {
		public static getComponentRegistry(): Record<string, unknown> {
			return {
				getIcon: () => undefined,
				getLabel: () => 'neowiki-property-type-text',
			};
		}
	}

	return { NeoWikiServices: MockNeoWikiServices };
} );

enableAutoUnmount( afterEach );

function createWrapper(
	properties: PropertyDefinitionList,
	selection: PropertyListRow,
	attachTo?: HTMLElement,
	showsLabelRow: boolean = true,
	listeners: Record<string, ( ...args: never[] ) => unknown> = {},
): VueWrapper {
	return mount( PropertyList, {
		props: {
			properties,
			selection,
			showsLabelRow,
			...listeners,
		},
		attachTo,
		global: {
			mocks: {
				$i18n: createI18nMock(),
			},
		},
	} );
}

// The Schema editor's part: it makes each selection and reordering the list asks for, unless it refuses a selection.
function selecting( wrapper: () => VueWrapper ): Record<string, ( ...args: never[] ) => unknown> {
	return {
		onSelect: ( row: PropertyListRow ) => wrapper().setProps( { selection: row } ),
		onPropertyReordered: ( names: PropertyName[] ) => wrapper().setProps( {
			properties: ( wrapper().props() as { properties: PropertyDefinitionList } ).properties.reordered( names ),
		} ),
	};
}

const SCHEMA_ROW_TITLE = 'neowiki-schema-editor-schema';
const LABEL_ROW_TITLE = 'neowiki-schema-editor-subject-label';
const DELETE_BUTTON = '[aria-label="neowiki-schema-editor-delete-property"]';

function rowTitle( row: DOMWrapper<Element> ): string {
	return row.find( '.ext-neowiki-property-list__item__text__label' ).text();
}

function rowDescription( row: DOMWrapper<Element> ): string {
	return row.find( '.ext-neowiki-property-list__item__text__description' ).text();
}

function rowTitles( wrapper: VueWrapper ): string[] {
	return wrapper.findAll( '[role="option"]' ).map( rowTitle );
}

function selectedRowTitles( wrapper: VueWrapper ): string[] {
	return wrapper.findAll( '[role="option"][aria-selected="true"]' ).map( rowTitle );
}

function focusableRowTitles( wrapper: VueWrapper ): string[] {
	return wrapper.findAll( '[role="option"][tabindex="0"]' ).map( rowTitle );
}

function highlightedRowTitles( wrapper: VueWrapper ): string[] {
	return wrapper.findAll( '.ext-neowiki-property-list__item--selected' ).map( rowTitle );
}

function findRow( wrapper: VueWrapper, title: string ): DOMWrapper<Element> {
	return wrapper.findAll( '[role="option"]' ).find( ( row ) => rowTitle( row ) === title )!;
}

function findAddButton( wrapper: VueWrapper ): DOMWrapper<Element> {
	return wrapper.findAll( 'button' ).find( ( button ) => button.text() === 'neowiki-schema-editor-new-property' )!;
}

function reorderedNames( wrapper: VueWrapper ): string[] {
	const emitted = wrapper.emitted( 'propertyReordered' ) as PropertyName[][][];
	expect( emitted ).toHaveLength( 1 );
	return emitted[ 0 ][ 0 ].map( ( name ) => name.toString() );
}

function sortableOptions(): UseSortableOptions {
	const calls = vi.mocked( useSortable ).mock.calls;
	return calls[ calls.length - 1 ][ 1 ];
}

function selections( wrapper: VueWrapper ): PropertyListRow[] {
	return ( wrapper.emitted( 'select' ) ?? [] ).map( ( [ row ] ) => row as PropertyListRow );
}

async function pressOnList( wrapper: VueWrapper, key: string, altKey: boolean = false ): Promise<void> {
	await wrapper.find( '[role="listbox"]' ).trigger( 'keydown', { key, altKey } );
}

describe( 'PropertyList', () => {

	beforeEach( () => {
		setupMwMock();
	} );

	const property1 = createPropertyDefinitionFromJson( 'Alpha', { type: TextType.typeName } );
	const property2 = createPropertyDefinitionFromJson( 'Beta', { type: TextType.typeName } );
	const property3 = createPropertyDefinitionFromJson( 'Gamma', { type: TextType.typeName } );
	const properties = new PropertyDefinitionList( [ property1, property2, property3 ] );

	it( 'lists the Schema\'s row first, then the Label row, then the properties', () => {
		const wrapper = createWrapper( properties, SCHEMA_ROW );

		expect( rowTitles( wrapper ) ).toEqual( [ SCHEMA_ROW_TITLE, LABEL_ROW_TITLE, 'Alpha', 'Beta', 'Gamma' ] );
	} );

	it( 'describes an optional property by its type, marked optional', () => {
		const wrapper = createWrapper( properties, SCHEMA_ROW );

		expect( rowDescription( findRow( wrapper, 'Alpha' ) ) )
			.toBe( 'neowiki-schema-editor-optional-property-typeneowiki-property-type-text' );
	} );

	it( 'describes a required property by its type alone', () => {
		const wrapper = createWrapper( new PropertyDefinitionList( [
			createPropertyDefinitionFromJson( 'Alpha', { type: TextType.typeName, required: true } ),
		] ), SCHEMA_ROW );

		expect( rowDescription( findRow( wrapper, 'Alpha' ) ) ).toBe( 'neowiki-property-type-text' );
	} );

	describe.each( [
		[ 'the Schema\'s row', SCHEMA_ROW, SCHEMA_ROW_TITLE ],
		[ 'the Label row', LABEL_ROW, LABEL_ROW_TITLE ],
		[ 'a property\'s row', propertyRow( 'Beta' ), 'Beta' ],
	] )( 'while %s is selected', ( _name, selection, title ) => {
		it( 'marks it alone with aria-selected', () => {
			const wrapper = createWrapper( properties, selection );

			expect( selectedRowTitles( wrapper ) ).toEqual( [ title ] );
		} );

		it( 'highlights it alone', () => {
			const wrapper = createWrapper( properties, selection );

			expect( highlightedRowTitles( wrapper ) ).toEqual( [ title ] );
		} );

		it( 'makes it the list\'s only Tab stop', () => {
			const wrapper = createWrapper( properties, selection );

			expect( focusableRowTitles( wrapper ) ).toEqual( [ title ] );
		} );
	} );

	it( 'keeps the list in the tab order while the Schema\'s row and the Label row are its only rows', () => {
		const wrapper = createWrapper( new PropertyDefinitionList( [] ), SCHEMA_ROW );

		expect( focusableRowTitles( wrapper ) ).toEqual( [ SCHEMA_ROW_TITLE ] );
	} );

	it( 'leaves the Schema\'s row out of the tab order while it is the only row', () => {
		const wrapper = createWrapper( new PropertyDefinitionList( [] ), SCHEMA_ROW, undefined, false );

		expect( focusableRowTitles( wrapper ) ).toEqual( [] );
	} );

	it( 'moves the selection to the row the editor selects', async () => {
		const wrapper = createWrapper( properties, propertyRow( 'Alpha' ) );

		await wrapper.setProps( { selection: LABEL_ROW } );

		expect( selectedRowTitles( wrapper ) ).toEqual( [ LABEL_ROW_TITLE ] );
	} );

	it( 'selects a property named Label as that property, not as the Label row', () => {
		const wrapper = createWrapper(
			new PropertyDefinitionList( [ property1, createPropertyDefinitionFromJson( 'Label', { type: TextType.typeName } ) ] ),
			propertyRow( 'Label' ),
		);

		expect( selectedRowTitles( wrapper ) ).toEqual( [ 'Label' ] );
	} );

	it( 'selects the Schema\'s row when it is clicked', async () => {
		const wrapper = createWrapper( properties, propertyRow( 'Alpha' ) );

		await findRow( wrapper, SCHEMA_ROW_TITLE ).trigger( 'click' );

		expect( selections( wrapper ) ).toEqual( [ SCHEMA_ROW ] );
	} );

	it( 'selects the Label row when it is clicked', async () => {
		const wrapper = createWrapper( properties, propertyRow( 'Alpha' ) );

		await findRow( wrapper, LABEL_ROW_TITLE ).trigger( 'click' );

		expect( selections( wrapper ) ).toEqual( [ LABEL_ROW ] );
	} );

	it( 'selects a property when its row is clicked', async () => {
		const wrapper = createWrapper( properties, propertyRow( 'Alpha' ) );

		await findRow( wrapper, 'Beta' ).trigger( 'click' );

		expect( selections( wrapper ) ).toEqual( [ propertyRow( 'Beta' ) ] );
	} );

	it( 'offers no delete button on the Schema\'s row or the Label row', () => {
		const wrapper = createWrapper( properties, SCHEMA_ROW );

		expect( findRow( wrapper, SCHEMA_ROW_TITLE ).find( DELETE_BUTTON ).exists() ).toBe( false );
		expect( findRow( wrapper, LABEL_ROW_TITLE ).find( DELETE_BUTTON ).exists() ).toBe( false );
		expect( findRow( wrapper, 'Alpha' ).find( DELETE_BUTTON ).exists() ).toBe( true );
	} );

	it( 'emits propertyDeleted when delete button is clicked', async () => {
		const wrapper = createWrapper( properties, propertyRow( 'Alpha' ) );

		await findRow( wrapper, 'Beta' ).find( DELETE_BUTTON ).trigger( 'click' );

		const emitted = wrapper.emitted( 'propertyDeleted' ) as PropertyName[][];
		expect( emitted ).toHaveLength( 1 );
		expect( emitted[ 0 ][ 0 ].toString() ).toBe( 'Beta' );
	} );

	it( 'selects nothing when a delete button is clicked', async () => {
		const wrapper = createWrapper( properties, propertyRow( 'Alpha' ) );

		await findRow( wrapper, 'Beta' ).find( DELETE_BUTTON ).trigger( 'click' );

		expect( selections( wrapper ) ).toEqual( [] );
	} );

	it( 'emits addProperty when the add button is clicked', async () => {
		const wrapper = createWrapper( properties, propertyRow( 'Alpha' ) );

		await findAddButton( wrapper ).trigger( 'click' );

		expect( wrapper.emitted( 'addProperty' ) ).toHaveLength( 1 );
	} );

	describe( 'keyboard navigation', () => {

		it( 'selects next item on ArrowDown', async () => {
			const wrapper = createWrapper( properties, propertyRow( 'Alpha' ) );

			await pressOnList( wrapper, 'ArrowDown' );

			expect( selections( wrapper ) ).toEqual( [ propertyRow( 'Beta' ) ] );
		} );

		it( 'selects previous item on ArrowUp', async () => {
			const wrapper = createWrapper( properties, propertyRow( 'Beta' ) );

			await pressOnList( wrapper, 'ArrowUp' );

			expect( selections( wrapper ) ).toEqual( [ propertyRow( 'Alpha' ) ] );
		} );

		it( 'selects the Label row on ArrowDown from the Schema\'s row', async () => {
			const wrapper = createWrapper( properties, SCHEMA_ROW );

			await pressOnList( wrapper, 'ArrowDown' );

			expect( selections( wrapper ) ).toEqual( [ LABEL_ROW ] );
		} );

		it( 'selects the first property on ArrowDown from the Label row', async () => {
			const wrapper = createWrapper( properties, LABEL_ROW );

			await pressOnList( wrapper, 'ArrowDown' );

			expect( selections( wrapper ) ).toEqual( [ propertyRow( 'Alpha' ) ] );
		} );

		it( 'selects the Label row on ArrowUp from the first property', async () => {
			const wrapper = createWrapper( properties, propertyRow( 'Alpha' ) );

			await pressOnList( wrapper, 'ArrowUp' );

			expect( selections( wrapper ) ).toEqual( [ LABEL_ROW ] );
		} );

		it( 'selects the Schema\'s row on ArrowUp from the Label row', async () => {
			const wrapper = createWrapper( properties, LABEL_ROW );

			await pressOnList( wrapper, 'ArrowUp' );

			expect( selections( wrapper ) ).toEqual( [ SCHEMA_ROW ] );
		} );

		it( 'selects nothing above the Schema\'s row on ArrowUp', async () => {
			const wrapper = createWrapper( properties, SCHEMA_ROW );

			await pressOnList( wrapper, 'ArrowUp' );

			expect( selections( wrapper ) ).toEqual( [] );
		} );

		it( 'moves the focus to the Schema\'s row on ArrowUp from the Label row', async () => {
			const wrapper: VueWrapper = createWrapper( properties, LABEL_ROW, document.body, true, selecting( () => wrapper ) );

			await pressOnList( wrapper, 'ArrowUp' );
			await flushPromises();

			expect( document.activeElement ).toBe( findRow( wrapper, SCHEMA_ROW_TITLE ).element );
		} );

		it( 'emits propertyReordered on Alt+ArrowDown', async () => {
			const wrapper = createWrapper( properties, propertyRow( 'Alpha' ) );

			await pressOnList( wrapper, 'ArrowDown', true );

			expect( reorderedNames( wrapper ) ).toEqual( [ 'Beta', 'Alpha', 'Gamma' ] );
		} );

		it( 'keeps the property it moves selected on Alt+ArrowDown', async () => {
			const wrapper: VueWrapper = createWrapper( properties, propertyRow( 'Alpha' ), document.body, true, selecting( () => wrapper ) );

			await pressOnList( wrapper, 'ArrowDown', true );
			await flushPromises();

			expect( selectedRowTitles( wrapper ) ).toEqual( [ 'Alpha' ] );
		} );

		it( 'emits propertyReordered on Alt+ArrowUp', async () => {
			const wrapper = createWrapper( properties, propertyRow( 'Gamma' ) );

			await pressOnList( wrapper, 'ArrowUp', true );

			expect( reorderedNames( wrapper ) ).toEqual( [ 'Alpha', 'Gamma', 'Beta' ] );
		} );

		it( 'does not reorder past the last item on Alt+ArrowDown', async () => {
			const wrapper = createWrapper( properties, propertyRow( 'Gamma' ) );

			await pressOnList( wrapper, 'ArrowDown', true );

			expect( wrapper.emitted( 'propertyReordered' ) ).toBeUndefined();
		} );

		it( 'does not move the first property above the Label row on Alt+ArrowUp', async () => {
			const wrapper = createWrapper( properties, propertyRow( 'Alpha' ) );

			await pressOnList( wrapper, 'ArrowUp', true );

			expect( wrapper.emitted( 'propertyReordered' ) ).toBeUndefined();
			expect( selections( wrapper ) ).toEqual( [] );
		} );

		it( 'does not move the Label row on Alt+ArrowDown', async () => {
			const wrapper = createWrapper( properties, LABEL_ROW );

			await pressOnList( wrapper, 'ArrowDown', true );

			expect( wrapper.emitted( 'propertyReordered' ) ).toBeUndefined();
			expect( selections( wrapper ) ).toEqual( [] );
		} );

	} );

	// jsdom cannot drag, so these drive the options the list hands useSortable.
	describe( 'drag and drop', () => {

		it( 'lets SortableJS drag the property rows but neither the Schema\'s row nor the Label row', () => {
			const wrapper = createWrapper( properties, SCHEMA_ROW );
			const draggable = sortableOptions().draggable!;

			expect( findRow( wrapper, SCHEMA_ROW_TITLE ).element.matches( draggable ) ).toBe( false );
			expect( findRow( wrapper, LABEL_ROW_TITLE ).element.matches( draggable ) ).toBe( false );
			expect( findRow( wrapper, 'Alpha' ).element.matches( draggable ) ).toBe( true );
		} );

		it( 'moves a dropped property to its place among the properties', () => {
			const wrapper = createWrapper( properties, propertyRow( 'Alpha' ) );

			// SortableJS reports indexes that count every row of the list.
			sortableOptions().onReorder!( 4, 2 );

			expect( reorderedNames( wrapper ) ).toEqual( [ 'Gamma', 'Alpha', 'Beta' ] );
		} );

	} );

	describe( 'without a Label row', () => {

		function createWithoutLabelRow( selection: PropertyListRow, attachTo?: HTMLElement ): VueWrapper {
			return createWrapper( properties, selection, attachTo, false );
		}

		it( 'lists the Schema\'s row and the properties', () => {
			const wrapper = createWithoutLabelRow( propertyRow( 'Alpha' ) );

			expect( rowTitles( wrapper ) ).toEqual( [ SCHEMA_ROW_TITLE, 'Alpha', 'Beta', 'Gamma' ] );
		} );

		it( 'moves the focus with the selection to the next property', async () => {
			const wrapper: VueWrapper = createWrapper( properties, propertyRow( 'Alpha' ), document.body, false, selecting( () => wrapper ) );

			await pressOnList( wrapper, 'ArrowDown' );
			await flushPromises();

			expect( document.activeElement ).toBe( findRow( wrapper, 'Beta' ).element );
		} );

		it( 'keeps the focus on the selected property when a click on another is refused', async () => {
			const wrapper = createWithoutLabelRow( propertyRow( 'Alpha' ), document.body );

			await findRow( wrapper, 'Beta' ).trigger( 'click' );
			await flushPromises();

			expect( document.activeElement ).toBe( findRow( wrapper, 'Alpha' ).element );
		} );

		it( 'keeps the focus on the selected property when the selection is refused', async () => {
			const wrapper = createWithoutLabelRow( propertyRow( 'Alpha' ), document.body );

			await pressOnList( wrapper, 'ArrowDown' );
			await flushPromises();

			expect( document.activeElement ).toBe( findRow( wrapper, 'Alpha' ).element );
		} );

		it( 'selects the first property on ArrowDown from the Schema\'s row', async () => {
			const wrapper = createWithoutLabelRow( SCHEMA_ROW );

			await pressOnList( wrapper, 'ArrowDown' );

			expect( selections( wrapper ) ).toEqual( [ propertyRow( 'Alpha' ) ] );
		} );

		// With a property right below it, where the Label row would otherwise stop the move by itself.
		it( 'does not move the Schema\'s row on Alt+ArrowDown', async () => {
			const wrapper = createWithoutLabelRow( SCHEMA_ROW );

			await pressOnList( wrapper, 'ArrowDown', true );

			expect( wrapper.emitted( 'propertyReordered' ) ).toBeUndefined();
		} );

		it( 'selects the Schema\'s row on ArrowUp from the first property', async () => {
			const wrapper = createWithoutLabelRow( propertyRow( 'Alpha' ) );

			await pressOnList( wrapper, 'ArrowUp' );

			expect( selections( wrapper ) ).toEqual( [ SCHEMA_ROW ] );
		} );

		it( 'does not move the first property above the Schema\'s row on Alt+ArrowUp', async () => {
			const wrapper = createWithoutLabelRow( propertyRow( 'Alpha' ) );

			await pressOnList( wrapper, 'ArrowUp', true );

			expect( wrapper.emitted( 'propertyReordered' ) ).toBeUndefined();
		} );

		it( 'moves a dropped property to its place, with no Label row among the rows SortableJS counts', () => {
			const wrapper = createWithoutLabelRow( propertyRow( 'Alpha' ) );

			sortableOptions().onReorder!( 3, 1 );

			expect( reorderedNames( wrapper ) ).toEqual( [ 'Gamma', 'Alpha', 'Beta' ] );
		} );

	} );

} );
