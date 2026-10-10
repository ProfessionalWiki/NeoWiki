import { DOMWrapper, flushPromises, mount, VueWrapper } from '@vue/test-utils';
import { describe, expect, it, vi, beforeEach } from 'vitest';
import Sortable from 'sortablejs';
import PropertyList from '@/components/SchemaEditor/PropertyList.vue';
import { PropertyDefinitionList } from '@/domain/PropertyDefinitionList.ts';
import { createPropertyDefinitionFromJson, PropertyName } from '@/domain/PropertyDefinition.ts';
import { TextType } from '@/domain/propertyTypes/Text.ts';
import { createI18nMock } from '../../VueTestHelpers.ts';

vi.mock( 'sortablejs', () => ( {
	default: {
		create: vi.fn( () => ( { destroy: vi.fn() } ) ),
	},
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

function createWrapper( properties: PropertyDefinitionList, selectedPropertyName?: string ): VueWrapper {
	return mount( PropertyList, {
		props: {
			properties,
			selectedPropertyName,
		},
		global: {
			mocks: {
				$i18n: createI18nMock(),
			},
		},
	} );
}

const LABEL_ROW_TITLE = 'neowiki-schema-editor-label';

function rowTitle( row: DOMWrapper<Element> ): string {
	return row.find( '.ext-neowiki-property-list__item__text__label' ).text();
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

function findRow( wrapper: VueWrapper, title: string ): DOMWrapper<Element> {
	return wrapper.findAll( '[role="option"]' ).find( ( row ) => rowTitle( row ) === title )!;
}

function reorderedNames( wrapper: VueWrapper ): string[] {
	const emitted = wrapper.emitted( 'propertyReordered' ) as PropertyName[][][];
	return emitted[ 0 ][ 0 ].map( ( name ) => name.toString() );
}

function sortableOptions(): Sortable.Options {
	const calls = vi.mocked( Sortable.create ).mock.calls;
	return calls[ calls.length - 1 ][ 1 ]!;
}

describe( 'PropertyList', () => {

	beforeEach( () => {
		vi.stubGlobal( 'mw', {
			msg: vi.fn( ( key ) => key ),
		} );
	} );

	const property1 = createPropertyDefinitionFromJson( 'Alpha', { type: TextType.typeName } );
	const property2 = createPropertyDefinitionFromJson( 'Beta', { type: TextType.typeName } );
	const property3 = createPropertyDefinitionFromJson( 'Gamma', { type: TextType.typeName } );
	const properties = new PropertyDefinitionList( [ property1, property2, property3 ] );

	it( 'lists the Label row before the properties', () => {
		const wrapper = createWrapper( properties );

		expect( rowTitles( wrapper ) ).toEqual( [ LABEL_ROW_TITLE, 'Alpha', 'Beta', 'Gamma' ] );
	} );

	it( 'marks the selected property with aria-selected', () => {
		const wrapper = createWrapper( properties, 'Beta' );

		expect( selectedRowTitles( wrapper ) ).toEqual( [ 'Beta' ] );
	} );

	it( 'sets tabindex 0 on selected item and -1 on others', () => {
		const wrapper = createWrapper( properties, 'Beta' );

		expect( focusableRowTitles( wrapper ) ).toEqual( [ 'Beta' ] );
		expect( findRow( wrapper, 'Alpha' ).attributes( 'tabindex' ) ).toBe( '-1' );
	} );

	it( 'selects the Label row while no property is selected', () => {
		const wrapper = createWrapper( properties );

		expect( selectedRowTitles( wrapper ) ).toEqual( [ LABEL_ROW_TITLE ] );
		expect( focusableRowTitles( wrapper ) ).toEqual( [ LABEL_ROW_TITLE ] );
	} );

	it( 'selects a property named Label as that property, not as the Label row', () => {
		const wrapper = createWrapper(
			new PropertyDefinitionList( [ property1, createPropertyDefinitionFromJson( 'Label', { type: TextType.typeName } ) ] ),
			'Label',
		);

		expect( selectedRowTitles( wrapper ) ).toEqual( [ 'Label' ] );
	} );

	it( 'emits labelSelected when the Label row is clicked', async () => {
		const wrapper = createWrapper( properties, 'Alpha' );

		await findRow( wrapper, LABEL_ROW_TITLE ).trigger( 'click' );

		expect( wrapper.emitted( 'labelSelected' ) ).toHaveLength( 1 );
		expect( wrapper.emitted( 'propertySelected' ) ).toBeUndefined();
	} );

	it( 'emits propertySelected when an item is clicked', async () => {
		const wrapper = createWrapper( properties, 'Alpha' );

		await findRow( wrapper, 'Beta' ).trigger( 'click' );

		const emitted = wrapper.emitted( 'propertySelected' ) as PropertyName[][];
		expect( emitted ).toHaveLength( 1 );
		expect( emitted[ 0 ][ 0 ].toString() ).toBe( 'Beta' );
	} );

	it( 'offers no delete button on the Label row', () => {
		const wrapper = createWrapper( properties );

		expect( findRow( wrapper, LABEL_ROW_TITLE ).find( '[aria-label="neowiki-schema-editor-delete-property"]' ).exists() ).toBe( false );
		expect( findRow( wrapper, 'Alpha' ).find( '[aria-label="neowiki-schema-editor-delete-property"]' ).exists() ).toBe( true );
	} );

	it( 'emits propertyDeleted when delete button is clicked', async () => {
		const wrapper = createWrapper( properties, 'Alpha' );
		const deleteButtons = wrapper.findAll( '.ext-neowiki-property-list__item__actions__delete' );

		await deleteButtons[ 1 ].trigger( 'click' );

		const emitted = wrapper.emitted( 'propertyDeleted' ) as PropertyName[][];
		expect( emitted ).toHaveLength( 1 );
		expect( emitted[ 0 ][ 0 ].toString() ).toBe( 'Beta' );
	} );

	it( 'does not emit propertySelected when delete button is clicked', async () => {
		const wrapper = createWrapper( properties, 'Alpha' );
		const deleteButtons = wrapper.findAll( '.ext-neowiki-property-list__item__actions__delete' );

		await deleteButtons[ 1 ].trigger( 'click' );

		expect( wrapper.emitted( 'propertySelected' ) ).toBeUndefined();
	} );

	it( 'emits addProperty when the add button is clicked', async () => {
		const wrapper = createWrapper( properties, 'Alpha' );

		await wrapper.find( '.ext-neowiki-property-list__add-item' ).trigger( 'click' );

		expect( wrapper.emitted( 'addProperty' ) ).toHaveLength( 1 );
	} );

	describe( 'keyboard navigation', () => {

		it( 'selects next item on ArrowDown', async () => {
			const wrapper = createWrapper( properties, 'Alpha' );
			const list = wrapper.find( '[role="listbox"]' );

			await list.trigger( 'keydown', { key: 'ArrowDown' } );

			const emitted = wrapper.emitted( 'propertySelected' ) as PropertyName[][];
			expect( emitted ).toHaveLength( 1 );
			expect( emitted[ 0 ][ 0 ].toString() ).toBe( 'Beta' );
		} );

		it( 'selects previous item on ArrowUp', async () => {
			const wrapper = createWrapper( properties, 'Beta' );
			const list = wrapper.find( '[role="listbox"]' );

			await list.trigger( 'keydown', { key: 'ArrowUp' } );

			const emitted = wrapper.emitted( 'propertySelected' ) as PropertyName[][];
			expect( emitted ).toHaveLength( 1 );
			expect( emitted[ 0 ][ 0 ].toString() ).toBe( 'Alpha' );
		} );

		it( 'selects the first property on ArrowDown from the Label row', async () => {
			const wrapper = createWrapper( properties );
			const list = wrapper.find( '[role="listbox"]' );

			await list.trigger( 'keydown', { key: 'ArrowDown' } );

			const emitted = wrapper.emitted( 'propertySelected' ) as PropertyName[][];
			expect( emitted ).toHaveLength( 1 );
			expect( emitted[ 0 ][ 0 ].toString() ).toBe( 'Alpha' );
		} );

		it( 'selects the Label row on ArrowUp from the first property', async () => {
			const wrapper = createWrapper( properties, 'Alpha' );
			const list = wrapper.find( '[role="listbox"]' );

			await list.trigger( 'keydown', { key: 'ArrowUp' } );

			expect( wrapper.emitted( 'labelSelected' ) ).toHaveLength( 1 );
			expect( wrapper.emitted( 'propertySelected' ) ).toBeUndefined();
		} );

		it( 'does not move past the last item on ArrowDown', async () => {
			const wrapper = createWrapper( properties, 'Gamma' );
			const list = wrapper.find( '[role="listbox"]' );

			await list.trigger( 'keydown', { key: 'ArrowDown' } );

			expect( wrapper.emitted( 'propertySelected' ) ).toBeUndefined();
		} );

		it( 'does not move past the Label row on ArrowUp', async () => {
			const wrapper = createWrapper( properties );
			const list = wrapper.find( '[role="listbox"]' );

			await list.trigger( 'keydown', { key: 'ArrowUp' } );

			expect( wrapper.emitted( 'labelSelected' ) ).toBeUndefined();
			expect( wrapper.emitted( 'propertySelected' ) ).toBeUndefined();
		} );

		it( 'emits propertyReordered on Alt+ArrowDown', async () => {
			const wrapper = createWrapper( properties, 'Alpha' );
			const list = wrapper.find( '[role="listbox"]' );

			await list.trigger( 'keydown', { key: 'ArrowDown', altKey: true } );

			expect( reorderedNames( wrapper ) ).toEqual( [ 'Beta', 'Alpha', 'Gamma' ] );
		} );

		it( 'emits propertyReordered on Alt+ArrowUp', async () => {
			const wrapper = createWrapper( properties, 'Gamma' );
			const list = wrapper.find( '[role="listbox"]' );

			await list.trigger( 'keydown', { key: 'ArrowUp', altKey: true } );

			expect( reorderedNames( wrapper ) ).toEqual( [ 'Alpha', 'Gamma', 'Beta' ] );
		} );

		it( 'does not reorder past the last item on Alt+ArrowDown', async () => {
			const wrapper = createWrapper( properties, 'Gamma' );
			const list = wrapper.find( '[role="listbox"]' );

			await list.trigger( 'keydown', { key: 'ArrowDown', altKey: true } );

			expect( wrapper.emitted( 'propertyReordered' ) ).toBeUndefined();
		} );

		it( 'does not move the first property above the Label row on Alt+ArrowUp', async () => {
			const wrapper = createWrapper( properties, 'Alpha' );
			const list = wrapper.find( '[role="listbox"]' );

			await list.trigger( 'keydown', { key: 'ArrowUp', altKey: true } );

			expect( wrapper.emitted( 'propertyReordered' ) ).toBeUndefined();
		} );

		it( 'does not move the Label row on Alt+ArrowDown', async () => {
			const wrapper = createWrapper( properties );
			const list = wrapper.find( '[role="listbox"]' );

			await list.trigger( 'keydown', { key: 'ArrowDown', altKey: true } );

			expect( wrapper.emitted( 'propertyReordered' ) ).toBeUndefined();
		} );

	} );

	// jsdom cannot drag, so these drive SortableJS through the options the list hands it.
	describe( 'drag and drop', () => {

		it( 'lets SortableJS drag the property rows but not the Label row', async () => {
			const wrapper = createWrapper( properties );
			await flushPromises();
			const draggable = sortableOptions().draggable!;

			expect( findRow( wrapper, LABEL_ROW_TITLE ).element.matches( draggable ) ).toBe( false );
			expect( findRow( wrapper, 'Alpha' ).element.matches( draggable ) ).toBe( true );
		} );

		it( 'moves a dropped property to its place among the properties', async () => {
			const wrapper = createWrapper( properties, 'Alpha' );
			await flushPromises();
			const list = wrapper.find( '[role="listbox"]' ).element;
			const gamma = findRow( wrapper, 'Gamma' ).element;

			// SortableJS moves the row, then reports indexes that count every row of the list.
			list.insertBefore( gamma, list.children[ 1 ] );
			sortableOptions().onEnd!( { item: gamma, from: list, to: list, oldIndex: 3, newIndex: 1 } as unknown as Sortable.SortableEvent );

			expect( reorderedNames( wrapper ) ).toEqual( [ 'Gamma', 'Alpha', 'Beta' ] );
		} );

	} );

} );
