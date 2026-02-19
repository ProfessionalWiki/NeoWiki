import { mount, VueWrapper } from '@vue/test-utils';
import { describe, expect, it, vi, beforeEach } from 'vitest';
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

	it( 'renders all property items', () => {
		const wrapper = createWrapper( properties );
		const items = wrapper.findAll( '[role="option"]' );

		expect( items ).toHaveLength( 3 );
		expect( items[ 0 ].text() ).toContain( 'Alpha' );
		expect( items[ 1 ].text() ).toContain( 'Beta' );
		expect( items[ 2 ].text() ).toContain( 'Gamma' );
	} );

	it( 'marks the selected property with aria-selected', () => {
		const wrapper = createWrapper( properties, 'Beta' );
		const items = wrapper.findAll( '[role="option"]' );

		expect( items[ 0 ].attributes( 'aria-selected' ) ).toBe( 'false' );
		expect( items[ 1 ].attributes( 'aria-selected' ) ).toBe( 'true' );
		expect( items[ 2 ].attributes( 'aria-selected' ) ).toBe( 'false' );
	} );

	it( 'sets tabindex 0 on selected item and -1 on others', () => {
		const wrapper = createWrapper( properties, 'Beta' );
		const items = wrapper.findAll( '[role="option"]' );

		expect( items[ 0 ].attributes( 'tabindex' ) ).toBe( '-1' );
		expect( items[ 1 ].attributes( 'tabindex' ) ).toBe( '0' );
		expect( items[ 2 ].attributes( 'tabindex' ) ).toBe( '-1' );
	} );

	it( 'emits propertySelected when an item is clicked', async () => {
		const wrapper = createWrapper( properties, 'Alpha' );
		const items = wrapper.findAll( '[role="option"]' );

		await items[ 1 ].trigger( 'click' );

		const emitted = wrapper.emitted( 'propertySelected' ) as PropertyName[][];
		expect( emitted ).toHaveLength( 1 );
		expect( emitted[ 0 ][ 0 ].toString() ).toBe( 'Beta' );
	} );

	it( 'emits propertyDeleted when delete button is clicked', async () => {
		const wrapper = createWrapper( properties, 'Alpha' );
		const deleteButtons = wrapper.findAll( '.ext-neowiki-property-list__item__delete' );

		await deleteButtons[ 1 ].trigger( 'click' );

		const emitted = wrapper.emitted( 'propertyDeleted' ) as PropertyName[][];
		expect( emitted ).toHaveLength( 1 );
		expect( emitted[ 0 ][ 0 ].toString() ).toBe( 'Beta' );
	} );

	it( 'does not emit propertySelected when delete button is clicked', async () => {
		const wrapper = createWrapper( properties, 'Alpha' );
		const deleteButtons = wrapper.findAll( '.ext-neowiki-property-list__item__delete' );

		await deleteButtons[ 1 ].trigger( 'click' );

		expect( wrapper.emitted( 'propertySelected' ) ).toBeUndefined();
	} );

	it( 'emits propertyCreated and propertySelected when add button is clicked', async () => {
		const wrapper = createWrapper( properties, 'Alpha' );
		const addButton = wrapper.find( '.ext-neowiki-property-list__add-item' );

		await addButton.trigger( 'click' );

		expect( wrapper.emitted( 'propertyCreated' ) ).toHaveLength( 1 );
		expect( wrapper.emitted( 'propertySelected' ) ).toHaveLength( 1 );
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

		it( 'does not move past the last item on ArrowDown', async () => {
			const wrapper = createWrapper( properties, 'Gamma' );
			const list = wrapper.find( '[role="listbox"]' );

			await list.trigger( 'keydown', { key: 'ArrowDown' } );

			expect( wrapper.emitted( 'propertySelected' ) ).toBeUndefined();
		} );

		it( 'does not move past the first item on ArrowUp', async () => {
			const wrapper = createWrapper( properties, 'Alpha' );
			const list = wrapper.find( '[role="listbox"]' );

			await list.trigger( 'keydown', { key: 'ArrowUp' } );

			expect( wrapper.emitted( 'propertySelected' ) ).toBeUndefined();
		} );

		it( 'emits propertyReordered on Alt+ArrowDown', async () => {
			const wrapper = createWrapper( properties, 'Alpha' );
			const list = wrapper.find( '[role="listbox"]' );

			await list.trigger( 'keydown', { key: 'ArrowDown', altKey: true } );

			const emitted = wrapper.emitted( 'propertyReordered' );
			expect( emitted ).toHaveLength( 1 );

			const names = emitted![ 0 ][ 0 ] as PropertyName[];
			expect( names.map( ( n: PropertyName ) => n.toString() ) ).toEqual( [ 'Beta', 'Alpha', 'Gamma' ] );
		} );

		it( 'emits propertyReordered on Alt+ArrowUp', async () => {
			const wrapper = createWrapper( properties, 'Gamma' );
			const list = wrapper.find( '[role="listbox"]' );

			await list.trigger( 'keydown', { key: 'ArrowUp', altKey: true } );

			const emitted = wrapper.emitted( 'propertyReordered' );
			expect( emitted ).toHaveLength( 1 );

			const names = emitted![ 0 ][ 0 ] as PropertyName[];
			expect( names.map( ( n: PropertyName ) => n.toString() ) ).toEqual( [ 'Alpha', 'Gamma', 'Beta' ] );
		} );

		it( 'does not reorder past the last item on Alt+ArrowDown', async () => {
			const wrapper = createWrapper( properties, 'Gamma' );
			const list = wrapper.find( '[role="listbox"]' );

			await list.trigger( 'keydown', { key: 'ArrowDown', altKey: true } );

			expect( wrapper.emitted( 'propertyReordered' ) ).toBeUndefined();
		} );

		it( 'does not reorder past the first item on Alt+ArrowUp', async () => {
			const wrapper = createWrapper( properties, 'Alpha' );
			const list = wrapper.find( '[role="listbox"]' );

			await list.trigger( 'keydown', { key: 'ArrowUp', altKey: true } );

			expect( wrapper.emitted( 'propertyReordered' ) ).toBeUndefined();
		} );

	} );

} );
