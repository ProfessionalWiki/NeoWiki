import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { markRaw } from 'vue';
import { FrontendRegistrar } from '@/presentation/FrontendRegistrar';
import { TypeSpecificComponentRegistry } from '@/TypeSpecificComponentRegistry';
import { PropertyTypeRegistry } from '@/domain/PropertyType';
import { ViewTypeRegistry } from '@/ViewTypeRegistry';
import type { PropertyTypeRegistration } from '@/domain/PropertyTypeRegistration';
import { ValueType, newStringValue } from '@/domain/Value';

function registration( typeName: string ): PropertyTypeRegistration {
	const stub = markRaw( { render: (): null => null } );
	return {
		typeName,
		valueType: ValueType.String,
		displayAttributeNames: [],
		createPropertyDefinitionFromJson: ( base ) => base,
		getExampleValue: () => newStringValue( 'x' ),
		displayComponent: stub,
		inputComponent: stub,
		attributesEditor: stub,
		label: `label-${ typeName }`,
		icon: 'icon' as any,
	};
}

describe( 'FrontendRegistrar', () => {
	it( 'inserts a registered type into the property type registry', () => {
		const componentRegistry = new TypeSpecificComponentRegistry();
		const typeRegistry = new PropertyTypeRegistry();
		const registrar = new FrontendRegistrar( componentRegistry, typeRegistry, new ViewTypeRegistry() );

		registrar.registerPropertyType( registration( 'foo' ) );

		expect( typeRegistry.getTypeNames() ).toContain( 'foo' );
		expect( typeRegistry.getType( 'foo' ).getTypeName() ).toBe( 'foo' );
	} );

	it( 'inserts components, label and icon into the component registry', () => {
		const componentRegistry = new TypeSpecificComponentRegistry();
		const typeRegistry = new PropertyTypeRegistry();
		const registrar = new FrontendRegistrar( componentRegistry, typeRegistry, new ViewTypeRegistry() );

		const reg = registration( 'bar' );
		registrar.registerPropertyType( reg );

		expect( componentRegistry.getPropertyTypes() ).toContain( 'bar' );
		expect( componentRegistry.getValueDisplayComponent( 'bar' ) ).toBe( reg.displayComponent );
		expect( componentRegistry.getValueEditingComponent( 'bar' ) ).toBe( reg.inputComponent );
		expect( componentRegistry.getAttributesEditor( 'bar' ) ).toBe( reg.attributesEditor );
		expect( componentRegistry.getLabel( 'bar' ) ).toBe( 'label-bar' );
		expect( componentRegistry.getIcon( 'bar' ) ).toBe( reg.icon );
	} );

	it( 'inserts a registered view type into the view type registry', () => {
		const viewTypeRegistry = new ViewTypeRegistry();
		const registrar = new FrontendRegistrar(
			new TypeSpecificComponentRegistry(),
			new PropertyTypeRegistry(),
			viewTypeRegistry,
		);

		const component = markRaw( { render: (): null => null } );
		registrar.registerViewType( { typeName: 'card', component } );

		expect( viewTypeRegistry.getComponent( 'card' ) ).toBe( component );
	} );
} );

describe( 'FrontendRegistrar after the UI has started', () => {
	const warn = vi.fn();

	function newRegistrar( typeRegistry = new PropertyTypeRegistry() ): FrontendRegistrar {
		return new FrontendRegistrar( new TypeSpecificComponentRegistry(), typeRegistry, new ViewTypeRegistry() );
	}

	beforeEach( () => {
		warn.mockClear();
		vi.stubGlobal( 'mw', { log: { warn } } );
	} );

	afterEach( () => {
		vi.unstubAllGlobals();
	} );

	it( 'warns about a Property Type registered late, by name', () => {
		const registrar = newRegistrar();
		registrar.markUiStarted();

		registrar.registerPropertyType( registration( 'late' ) );

		expect( warn ).toHaveBeenCalledWith( expect.stringContaining( '"late"' ) );
	} );

	it( 'does not warn about a Property Type registered before the UI starts', () => {
		const registrar = newRegistrar();

		registrar.registerPropertyType( registration( 'early' ) );
		registrar.markUiStarted();

		expect( warn ).not.toHaveBeenCalled();
	} );

	it( 'still registers a Property Type registered late', () => {
		const typeRegistry = new PropertyTypeRegistry();
		const registrar = newRegistrar( typeRegistry );
		registrar.markUiStarted();

		registrar.registerPropertyType( registration( 'late' ) );

		expect( typeRegistry.getTypeNames() ).toContain( 'late' );
	} );

	it( 'does not warn about a View Type registered late, which a mounted View picks up', () => {
		const registrar = newRegistrar();
		registrar.markUiStarted();

		registrar.registerViewType( { typeName: 'card', component: markRaw( { render: (): null => null } ) } );

		expect( warn ).not.toHaveBeenCalled();
	} );
} );
