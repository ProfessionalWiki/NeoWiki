import { describe, it, expect } from 'vitest';
import { PropertyTypeAdapter } from '@/domain/PropertyTypeAdapter';
import type { PropertyTypeRegistration } from '@/domain/PropertyTypeRegistration';
import type { PropertyDefinition } from '@/domain/PropertyDefinition';
import { PropertyName } from '@/domain/PropertyDefinition';
import { newStringValue, ValueType } from '@/domain/Value';
import type { StringValue } from '@/domain/Value';
import { defineComponent } from 'vue';

function newTestRegistration( overrides: Partial<PropertyTypeRegistration> = {} ): PropertyTypeRegistration {
	return {
		typeName: 'testType',
		valueType: 'string',
		displayAttributeNames: [ 'foo', 'bar' ],
		createPropertyDefinition: ( base: PropertyDefinition ) => ( { ...base, custom: true } ),
		getExampleValue: () => newStringValue( 'example' ),
		validate: () => [],
		displayComponent: defineComponent( {} ),
		inputComponent: defineComponent( {} ),
		attributesEditor: defineComponent( {} ),
		label: 'test-label',
		icon: 'testIcon',
		...overrides,
	};
}

describe( 'PropertyTypeAdapter', () => {

	it( 'returns the type name from the registration', () => {
		const adapter = new PropertyTypeAdapter( newTestRegistration( { typeName: 'myType' } ) );
		expect( adapter.getTypeName() ).toBe( 'myType' );
	} );

	it( 'returns the value type from the registration', () => {
		const adapter = new PropertyTypeAdapter( newTestRegistration( { valueType: 'number' } ) );
		expect( adapter.getValueType() ).toBe( 'number' );
	} );

	it( 'returns display attribute names from the registration', () => {
		const adapter = new PropertyTypeAdapter( newTestRegistration( {
			displayAttributeNames: [ 'precision' ],
		} ) );
		expect( adapter.getDisplayAttributeNames() ).toEqual( [ 'precision' ] );
	} );

	it( 'delegates createPropertyDefinitionFromJson to registration', () => {
		const base: PropertyDefinition = {
			name: new PropertyName( 'test' ),
			type: 'testType',
			description: '',
			required: false,
		};
		const adapter = new PropertyTypeAdapter( newTestRegistration( {
			createPropertyDefinition: ( b ) => ( { ...b, extra: 'value' } ),
		} ) );
		const result = adapter.createPropertyDefinitionFromJson( base, {} );
		expect( ( result as any ).extra ).toBe( 'value' );
	} );

	it( 'delegates getExampleValue to registration', () => {
		const adapter = new PropertyTypeAdapter( newTestRegistration( {
			getExampleValue: () => newStringValue( 'hello' ),
		} ) );
		const result = adapter.getExampleValue( {} as PropertyDefinition ) as StringValue;
		expect( result.type ).toBe( ValueType.String );
		expect( result.parts ).toEqual( [ 'hello' ] );
	} );

	it( 'delegates validate to registration', () => {
		const error = { code: 'test-error' };
		const adapter = new PropertyTypeAdapter( newTestRegistration( {
			validate: () => [ error ],
		} ) );
		const result = adapter.validate( undefined, {} as PropertyDefinition );
		expect( result ).toEqual( [ error ] );
	} );

} );
