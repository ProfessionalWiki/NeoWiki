import './../neowiki-test-env.ts';
import { mount } from '@vue/test-utils';
import { beforeAll, beforeEach, describe, expect, it } from 'vitest';
import { NeoWikiExtension } from '@/NeoWikiExtension.ts';
import { PropertyType } from '@/domain/PropertyType.ts';
import { newStringValue, ValueType } from '@/domain/Value.ts';
import { TypeSpecificComponentRegistry } from '@/TypeSpecificComponentRegistry.ts';
import { ViewTypeRegistry } from '@/ViewTypeRegistry.ts';
import { setupMwMock } from '../VueTestHelpers.ts';
import { CARD_VIEW_TYPE_NAME, COLOR_TYPE_NAME, loadRedHerbFrontend, newColorProperty } from './RedHerbRegistration.ts';

describe( 'RedHerb registration', () => {
	let colorType: PropertyType;
	let componentRegistry: TypeSpecificComponentRegistry;
	let viewTypeRegistry: ViewTypeRegistry;

	beforeAll( async () => {
		await loadRedHerbFrontend();

		const extension = NeoWikiExtension.getInstance();
		colorType = extension.getPropertyTypeRegistry().getType( COLOR_TYPE_NAME );
		componentRegistry = extension.getTypeSpecificComponentRegistry();
		viewTypeRegistry = extension.getViewTypeRegistry();
	} );

	beforeEach( () => {
		setupMwMock();
	} );

	it( 'registers the color property type as string-valued', () => {
		expect( colorType.getValueType() ).toBe( ValueType.String );
	} );

	it( 'registers a display component that renders a color', () => {
		const wrapper = mount( componentRegistry.getValueDisplayComponent( COLOR_TYPE_NAME ), {
			props: {
				value: newStringValue( '#ff5733' ),
				property: newColorProperty(),
			},
		} );

		expect( wrapper.text() ).toContain( '#ff5733' );
	} );

	it( 'registers an input component for the color type', () => {
		expect( componentRegistry.getValueEditingComponent( COLOR_TYPE_NAME ) ).toBeDefined();
	} );

	it( 'registers an attributes editor for the color type', () => {
		expect( componentRegistry.getAttributesEditor( COLOR_TYPE_NAME ) ).toBeDefined();
	} );

	it( 'registers a label and icon for the color type', () => {
		expect( componentRegistry.getLabel( COLOR_TYPE_NAME ) ).toBe( 'redherb-property-type-color' );
		expect( componentRegistry.getIcon( COLOR_TYPE_NAME ) ).toBeDefined();
	} );

	it( 'registers the card view type', () => {
		expect( viewTypeRegistry.hasType( CARD_VIEW_TYPE_NAME ) ).toBe( true );
	} );

	it( 'offers an example value the display component renders as a color', () => {
		const example = colorType.getExampleValue( newColorProperty() );

		expect( example ).toEqual( newStringValue( '#ff5733' ) );
	} );

	it( 'keeps the palette from the property JSON', () => {
		const property = newColorProperty( { allowedColors: [ '#ff5733', '#000000' ] } );

		expect( property ).toHaveProperty( 'allowedColors', [ '#ff5733', '#000000' ] );
	} );

	it( 'falls back to an empty palette when the property JSON has no usable one', () => {
		const property = newColorProperty( { allowedColors: 'not a list' } );

		expect( property ).toHaveProperty( 'allowedColors', [] );
	} );

	it( 'keeps the shared property attributes alongside the palette', () => {
		const property = newColorProperty( { description: 'Pick a color', required: true } );

		expect( property.description ).toBe( 'Pick a color' );
		expect( property.required ).toBe( true );
	} );
} );
