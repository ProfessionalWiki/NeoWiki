import { describe, it, expect } from 'vitest';
import { FrontendRegistrar } from '@/FrontendRegistrar';
import { PropertyTypeRegistry } from '@/domain/PropertyType';
import { TypeSpecificComponentRegistry } from '@/TypeSpecificComponentRegistry';
import type { PropertyTypeRegistration } from '@/domain/PropertyTypeRegistration';
import type { PropertyDefinition } from '@/domain/PropertyDefinition';
import { newStringValue } from '@/domain/Value';
import { defineComponent } from 'vue';

const DummyComponent = defineComponent( {} );

function newTestRegistration( overrides: Partial<PropertyTypeRegistration> = {} ): PropertyTypeRegistration {
	return {
		typeName: 'test',
		valueType: 'string',
		displayAttributeNames: [],
		createPropertyDefinition: ( base: PropertyDefinition ) => base,
		getExampleValue: () => newStringValue( 'example' ),
		validate: () => [],
		displayComponent: DummyComponent,
		inputComponent: DummyComponent,
		attributesEditor: DummyComponent,
		label: 'test-label',
		icon: 'testIcon',
		...overrides,
	};
}

describe( 'FrontendRegistrar', () => {

	it( 'registers a type in the PropertyTypeRegistry', () => {
		const typeRegistry = new PropertyTypeRegistry();
		const componentRegistry = new TypeSpecificComponentRegistry();
		const registrar = new FrontendRegistrar( typeRegistry, componentRegistry );

		registrar.registerType( newTestRegistration( { typeName: 'custom' } ) );

		expect( typeRegistry.getType( 'custom' ).getTypeName() ).toBe( 'custom' );
	} );

	it( 'registers components in the TypeSpecificComponentRegistry', () => {
		const typeRegistry = new PropertyTypeRegistry();
		const componentRegistry = new TypeSpecificComponentRegistry();
		const registrar = new FrontendRegistrar( typeRegistry, componentRegistry );

		const display = defineComponent( {} );
		const input = defineComponent( {} );
		const editor = defineComponent( {} );

		registrar.registerType( newTestRegistration( {
			typeName: 'custom',
			displayComponent: display,
			inputComponent: input,
			attributesEditor: editor,
			label: 'my-label',
			icon: 'myIcon',
		} ) );

		expect( componentRegistry.getValueDisplayComponent( 'custom' ) ).toBe( display );
		expect( componentRegistry.getValueEditingComponent( 'custom' ) ).toBe( input );
		expect( componentRegistry.getAttributesEditor( 'custom' ) ).toBe( editor );
		expect( componentRegistry.getLabel( 'custom' ) ).toBe( 'my-label' );
		expect( componentRegistry.getIcon( 'custom' ) ).toBe( 'myIcon' );
	} );

	it( 'registers multiple types', () => {
		const typeRegistry = new PropertyTypeRegistry();
		const componentRegistry = new TypeSpecificComponentRegistry();
		const registrar = new FrontendRegistrar( typeRegistry, componentRegistry );

		registrar.registerType( newTestRegistration( { typeName: 'alpha' } ) );
		registrar.registerType( newTestRegistration( { typeName: 'beta' } ) );

		expect( typeRegistry.getTypeNames() ).toContain( 'alpha' );
		expect( typeRegistry.getTypeNames() ).toContain( 'beta' );
		expect( componentRegistry.getPropertyTypes() ).toContain( 'alpha' );
		expect( componentRegistry.getPropertyTypes() ).toContain( 'beta' );
	} );

	it( 'throws on empty typeName', () => {
		const registrar = new FrontendRegistrar( new PropertyTypeRegistry(), new TypeSpecificComponentRegistry() );

		expect( () => registrar.registerType( newTestRegistration( { typeName: '' } ) ) )
			.toThrow( 'requires a non-empty typeName' );
	} );

	it( 'throws on missing validate function', () => {
		const registrar = new FrontendRegistrar( new PropertyTypeRegistry(), new TypeSpecificComponentRegistry() );

		expect( () => registrar.registerType( newTestRegistration( { validate: 'not a function' as any } ) ) )
			.toThrow( 'requires a validate function' );
	} );

	it( 'throws on duplicate typeName', () => {
		const registrar = new FrontendRegistrar( new PropertyTypeRegistry(), new TypeSpecificComponentRegistry() );

		registrar.registerType( newTestRegistration( { typeName: 'unique' } ) );

		expect( () => registrar.registerType( newTestRegistration( { typeName: 'unique' } ) ) )
			.toThrow( 'already registered' );
	} );

	it( 'registered type is usable via PropertyTypeRegistry for validation', () => {
		const typeRegistry = new PropertyTypeRegistry();
		const componentRegistry = new TypeSpecificComponentRegistry();
		const registrar = new FrontendRegistrar( typeRegistry, componentRegistry );

		registrar.registerType( newTestRegistration( {
			typeName: 'dateTime',
			validate: ( value ) => {
				if ( value === undefined ) {
					return [ { code: 'required' } ];
				}
				return [];
			},
		} ) );

		const type = typeRegistry.getType( 'dateTime' );
		expect( type.validate( undefined, {} as PropertyDefinition ) ).toEqual( [ { code: 'required' } ] );
		expect( type.validate( newStringValue( 'test' ), {} as PropertyDefinition ) ).toEqual( [] );
	} );

} );
