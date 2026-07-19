import { describe, expect, it } from 'vitest';
import { newSelectProperty, resolveSelectLabel, SelectType } from '@/domain/propertyTypes/Select';
import { PropertyName } from '@/domain/PropertyDefinition';

describe( 'SelectType', () => {

	const type = new SelectType();

	describe( 'getTypeName', () => {

		it( 'returns "select"', () => {
			expect( type.getTypeName() ).toBe( 'select' );
		} );

	} );

	it( 'has no display attributes', () => {
		expect( type.getDisplayAttributeNames() ).toEqual( [] );
	} );

} );

describe( 'newSelectProperty', () => {
	it( 'creates property with default values when no options provided', () => {
		const property = newSelectProperty();

		expect( property.name ).toEqual( new PropertyName( 'Select' ) );
		expect( property.type ).toBe( SelectType.typeName );
		expect( property.description ).toBe( '' );
		expect( property.required ).toBe( false );
		expect( property.default ).toBeUndefined();
		expect( property.options ).toEqual( [] );
		expect( property.multiple ).toBe( false );
	} );

	it( 'creates property with custom name', () => {
		const property = newSelectProperty( {
			name: 'Status',
		} );

		expect( property.name ).toEqual( new PropertyName( 'Status' ) );
	} );

	it( 'accepts PropertyName instance for name', () => {
		const propertyName = new PropertyName( 'Priority' );
		const property = newSelectProperty( {
			name: propertyName,
		} );

		expect( property.name ).toBe( propertyName );
	} );

	it( 'creates property with all fields', () => {
		const property = newSelectProperty( {
			name: 'Status',
			description: 'Document status',
			required: true,
			options: [
				{ id: 'opt1', label: 'Draft' },
				{ id: 'opt2', label: 'Review' },
				{ id: 'opt3', label: 'Approved' },
			],
			multiple: true,
		} );

		expect( property.name ).toEqual( new PropertyName( 'Status' ) );
		expect( property.type ).toBe( SelectType.typeName );
		expect( property.description ).toBe( 'Document status' );
		expect( property.required ).toBe( true );
		expect( property.options ).toEqual( [
			{ id: 'opt1', label: 'Draft' },
			{ id: 'opt2', label: 'Review' },
			{ id: 'opt3', label: 'Approved' },
		] );
		expect( property.multiple ).toBe( true );
	} );
} );

describe( 'resolveSelectLabel', () => {
	it( 'returns the label for a known id', () => {
		const property = newSelectProperty( {
			options: [
				{ id: 'opt1', label: 'Draft' },
				{ id: 'opt2', label: 'Review' },
			],
		} );

		expect( resolveSelectLabel( property, 'opt2' ) ).toBe( 'Review' );
	} );

	it( 'returns undefined for an unknown id', () => {
		const property = newSelectProperty( {
			options: [
				{ id: 'opt1', label: 'Draft' },
			],
		} );

		expect( resolveSelectLabel( property, 'unknown' ) ).toBeUndefined();
	} );
} );
