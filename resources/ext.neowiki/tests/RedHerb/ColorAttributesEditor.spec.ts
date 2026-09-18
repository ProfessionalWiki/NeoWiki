import './../neowiki-test-env.ts';
import { DOMWrapper, VueWrapper } from '@vue/test-utils';
import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest';
import { Ref } from 'vue';
import { CdxTextInput } from '@wikimedia/codex';
import { UseSortableOptions } from '@/composables/useSortable.ts';
import { createTestWrapper, setupMwMock } from '../VueTestHelpers.ts';
import { loadRedHerbFrontend, newColorProperty } from './RedHerbRegistration.ts';

const sortableCalls: UseSortableOptions[] = [];

vi.mock( '@/composables/useSortable.ts', () => ( {
	useSortable: ( _containerRef: Ref<HTMLElement | null>, options: UseSortableOptions ): void => {
		sortableCalls.push( options );
	},
} ) );

const ITEM = '.ext-redherb-color-attributes__item';
const INVALID_SWATCH = '.ext-redherb-color-attributes__swatch--invalid';
const REMOVE_BUTTON = '.ext-redherb-color-attributes__remove';
const ADD_LABEL = 'redherb-color-add-color';

// Imported after vi.mock so the component picks the stubbed composable up.
const ColorAttributesEditor = ( await import( '@redherb/ColorAttributesEditor.vue' ) ).default;

function newWrapper( allowedColors: string[] ): VueWrapper {
	return createTestWrapper( ColorAttributesEditor, {
		property: newColorProperty( { allowedColors: allowedColors } ),
	} );
}

function colorInputs( wrapper: VueWrapper ): string[] {
	return wrapper.findAll( 'input' ).map( ( input ) => ( input.element as HTMLInputElement ).value );
}

function addButton( wrapper: VueWrapper ): DOMWrapper<Element> {
	const button = wrapper.findAll( 'button' ).find( ( candidate ) => candidate.text().includes( ADD_LABEL ) );

	expect( button, 'add-color button' ).toBeDefined();
	return button!;
}

async function typeInto( wrapper: VueWrapper, index: number, color: string ): Promise<void> {
	await colorInputAt( wrapper, index ).vm.$emit( 'update:modelValue', color );
}

function colorInputAt( wrapper: VueWrapper, index: number ): VueWrapper {
	return wrapper.findAllComponents( CdxTextInput )[ index ] as unknown as VueWrapper;
}

function lastEmittedPalette( wrapper: VueWrapper ): string[] {
	const emitted = wrapper.emitted( 'update:property' );
	expect( emitted, 'update:property' ).toBeTruthy();
	return ( emitted![ emitted!.length - 1 ][ 0 ] as { allowedColors: string[] } ).allowedColors;
}

describe( 'ColorAttributesEditor', () => {
	beforeAll( async () => {
		await loadRedHerbFrontend();
	} );

	beforeEach( () => {
		sortableCalls.length = 0;
		setupMwMock();
	} );

	it( 'renders an entry per allowed color', () => {
		const wrapper = newWrapper( [ '#ff5733', '#000000' ] );

		expect( wrapper.findAll( ITEM ) ).toHaveLength( 2 );
		expect( colorInputs( wrapper ) ).toEqual( [ '#ff5733', '#000000' ] );
	} );

	it( 'renders no entries for an empty palette', () => {
		const wrapper = newWrapper( [] );

		expect( wrapper.findAll( ITEM ) ).toHaveLength( 0 );
	} );

	it( 'appends an empty entry when a color is added', async () => {
		const wrapper = newWrapper( [ '#ff5733' ] );

		await addButton( wrapper ).trigger( 'click' );

		expect( colorInputs( wrapper ) ).toEqual( [ '#ff5733', '' ] );
	} );

	it( 'emits the palette with the added entry', async () => {
		const wrapper = newWrapper( [ '#ff5733' ] );

		await addButton( wrapper ).trigger( 'click' );

		expect( lastEmittedPalette( wrapper ) ).toEqual( [ '#ff5733', '' ] );
	} );

	it( 'emits the palette with the edited color', async () => {
		const wrapper = newWrapper( [ '#ff5733', '#000000' ] );

		await typeInto( wrapper, 1, '#ffffff' );

		expect( lastEmittedPalette( wrapper ) ).toEqual( [ '#ff5733', '#ffffff' ] );
	} );

	it( 'emits the palette without the removed color', async () => {
		const wrapper = newWrapper( [ '#ff5733', '#000000', '#ffffff' ] );

		await wrapper.findAll( REMOVE_BUTTON )[ 1 ].trigger( 'click' );

		expect( lastEmittedPalette( wrapper ) ).toEqual( [ '#ff5733', '#ffffff' ] );
	} );

	it( 'keeps the remaining entries in order after a removal', async () => {
		const wrapper = newWrapper( [ '#ff5733', '#000000', '#ffffff' ] );

		await wrapper.findAll( REMOVE_BUTTON )[ 0 ].trigger( 'click' );

		expect( colorInputs( wrapper ) ).toEqual( [ '#000000', '#ffffff' ] );
	} );

	it( 'marks entries that are not a six-digit hex color', () => {
		const wrapper = newWrapper( [ '#ff5733', 'rebeccapurple' ] );

		const items = wrapper.findAll( ITEM );
		expect( items[ 0 ].find( INVALID_SWATCH ).exists() ).toBe( false );
		expect( items[ 1 ].find( INVALID_SWATCH ).exists() ).toBe( true );
	} );

	it( 'emits the reordered palette when an entry is dragged', () => {
		const wrapper = newWrapper( [ '#ff5733', '#000000', '#ffffff' ] );

		sortableCalls[ 0 ].onReorder!( 0, 2 );

		expect( lastEmittedPalette( wrapper ) ).toEqual( [ '#000000', '#ffffff', '#ff5733' ] );
	} );

	it( 'reorders only through the drag handle', () => {
		newWrapper( [ '#ff5733' ] );

		expect( sortableCalls[ 0 ].handle ).toBe( '.ext-redherb-color-attributes__drag-handle' );
	} );

	it( 'shows a palette replaced from outside the editor', async () => {
		const wrapper = newWrapper( [ '#ff5733' ] );

		await wrapper.setProps( { property: newColorProperty( { allowedColors: [ '#000000', '#ffffff' ] } ) } );

		expect( colorInputs( wrapper ) ).toEqual( [ '#000000', '#ffffff' ] );
	} );

	// Rebuilding the entries would give them fresh keys, so Vue would replace the inputs
	// and drop the caret out of the one being typed into.
	it( 'leaves the entry inputs in place when the property echoes the palette back', async () => {
		const wrapper = newWrapper( [ '#ff5733' ] );
		const inputBeforeEcho = wrapper.find( 'input' ).element;

		await wrapper.setProps( { property: newColorProperty( { allowedColors: [ '#ff5733' ] } ) } );

		expect( wrapper.find( 'input' ).element ).toBe( inputBeforeEcho );
	} );
} );
