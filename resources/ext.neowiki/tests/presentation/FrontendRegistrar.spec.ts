import { describe, expect, it } from 'vitest';
import { markRaw } from 'vue';
import { FrontendRegistrar } from '@/presentation/FrontendRegistrar';
import { TypeSpecificComponentRegistry } from '@/TypeSpecificComponentRegistry';
import { PropertyTypeRegistry } from '@/domain/PropertyType';
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
		validate: () => [],
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
		const registrar = new FrontendRegistrar( componentRegistry, typeRegistry );

		registrar.registerPropertyType( registration( 'foo' ) );

		expect( typeRegistry.getTypeNames() ).toContain( 'foo' );
		expect( typeRegistry.getType( 'foo' ).getTypeName() ).toBe( 'foo' );
	} );

	it( 'inserts components, label and icon into the component registry', () => {
		const componentRegistry = new TypeSpecificComponentRegistry();
		const typeRegistry = new PropertyTypeRegistry();
		const registrar = new FrontendRegistrar( componentRegistry, typeRegistry );

		const reg = registration( 'bar' );
		registrar.registerPropertyType( reg );

		expect( componentRegistry.getPropertyTypes() ).toContain( 'bar' );
		expect( componentRegistry.getValueDisplayComponent( 'bar' ) ).toBe( reg.displayComponent );
		expect( componentRegistry.getValueEditingComponent( 'bar' ) ).toBe( reg.inputComponent );
		expect( componentRegistry.getAttributesEditor( 'bar' ) ).toBe( reg.attributesEditor );
		expect( componentRegistry.getLabel( 'bar' ) ).toBe( 'label-bar' );
		expect( componentRegistry.getIcon( 'bar' ) ).toBe( reg.icon );
	} );
} );
