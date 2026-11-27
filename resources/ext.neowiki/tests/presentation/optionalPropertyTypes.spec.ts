import { describe, expect, it } from 'vitest';
import { markRaw } from 'vue';
import { OPTIONAL_PROPERTY_TYPES, registerEnabledPropertyTypes } from '@/presentation/optionalPropertyTypes';
import { FrontendRegistrar } from '@/presentation/FrontendRegistrar';
import { TypeSpecificComponentRegistry } from '@/TypeSpecificComponentRegistry';
import { PropertyTypeRegistry } from '@/domain/PropertyType';
import { ViewTypeRegistry } from '@/ViewTypeRegistry';
import type { PropertyTypeRegistration } from '@/domain/PropertyTypeRegistration';
import { ValueType, newStringValue } from '@/domain/Value';
import { setupMwMock } from '../VueTestHelpers';

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

function typeNamesRegisteredFrom( optionalTypes: readonly PropertyTypeRegistration[] ): string[] {
	const typeRegistry = new PropertyTypeRegistry();

	registerEnabledPropertyTypes(
		new FrontendRegistrar( new TypeSpecificComponentRegistry(), typeRegistry, new ViewTypeRegistry() ),
		optionalTypes,
	);

	return typeRegistry.getTypeNames();
}

describe( 'registerEnabledPropertyTypes', () => {
	it( 'registers an optional Property Type the wiki turns on', () => {
		setupMwMock( { config: { wgNeoWikiEnabledPropertyTypes: [ 'color' ] } } );

		expect( typeNamesRegisteredFrom( [ registration( 'shape' ), registration( 'color' ) ] ) ).toEqual( [ 'color' ] );
	} );

	it( 'registers EDTF date where the wiki turns it on', () => {
		setupMwMock( { config: { wgNeoWikiEnabledPropertyTypes: [ 'edtf' ] } } );

		expect( typeNamesRegisteredFrom( OPTIONAL_PROPERTY_TYPES ) ).toContain( 'edtf' );
	} );

	it( 'registers no optional Property Type on a page that does not say which are on', () => {
		setupMwMock();

		expect( typeNamesRegisteredFrom( [ registration( 'color' ) ] ) ).toEqual( [] );
	} );
} );
