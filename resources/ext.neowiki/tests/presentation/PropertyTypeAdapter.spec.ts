import { describe, expect, it } from 'vitest';
import { markRaw } from 'vue';
import { PropertyTypeAdapter } from '@/presentation/PropertyTypeAdapter';
import type { PropertyTypeRegistration } from '@/domain/PropertyTypeRegistration';
import { ValueType, newStringValue } from '@/domain/Value';

function minimalRegistration( overrides: Partial<PropertyTypeRegistration> = {} ): PropertyTypeRegistration {
	const stub = markRaw( { render: () => null } );
	return {
		typeName: 'faketype',
		valueType: ValueType.String,
		displayAttributeNames: [ 'color' ],
		createPropertyDefinitionFromJson: ( base ) => base,
		getExampleValue: () => newStringValue( 'x' ),
		validate: () => [],
		displayComponent: stub,
		inputComponent: stub,
		attributesEditor: stub,
		label: 'faketype-label',
		icon: 'fakeicon' as any,
		...overrides,
	};
}

describe( 'PropertyTypeAdapter', () => {
	it( 'exposes typeName from the registration', () => {
		const adapter = new PropertyTypeAdapter( minimalRegistration( { typeName: 'mytype' } ) );
		expect( adapter.getTypeName() ).toBe( 'mytype' );
	} );

	it( 'exposes valueType from the registration', () => {
		const adapter = new PropertyTypeAdapter( minimalRegistration( { valueType: ValueType.Number } ) );
		expect( adapter.getValueType() ).toBe( ValueType.Number );
	} );

	it( 'delegates getDisplayAttributeNames to the registration', () => {
		const adapter = new PropertyTypeAdapter( minimalRegistration( { displayAttributeNames: [ 'size' ] } ) );
		expect( adapter.getDisplayAttributeNames() ).toEqual( [ 'size' ] );
	} );

	it( 'delegates validate to the registration', () => {
		const validate = (): { code: string }[] => [ { code: 'err' } ];
		const adapter = new PropertyTypeAdapter( minimalRegistration( { validate } ) );
		expect( adapter.validate( undefined, {} as any ) ).toEqual( [ { code: 'err' } ] );
	} );
} );
