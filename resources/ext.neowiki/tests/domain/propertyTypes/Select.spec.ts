import { describe, expect, it } from 'vitest';
import { newSelectProperty, SelectType } from '@/domain/propertyTypes/Select';
import { PropertyName } from '@/domain/PropertyDefinition';
import { newStringValue } from '@/domain/Value';

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
			options: [ 'Draft', 'Review', 'Approved' ],
			multiple: true,
		} );

		expect( property.name ).toEqual( new PropertyName( 'Status' ) );
		expect( property.type ).toBe( SelectType.typeName );
		expect( property.description ).toBe( 'Document status' );
		expect( property.required ).toBe( true );
		expect( property.options ).toEqual( [ 'Draft', 'Review', 'Approved' ] );
		expect( property.multiple ).toBe( true );
	} );
} );

describe( 'validate', () => {
	const selectType = new SelectType();

	it( 'returns no errors for empty value when optional', () => {
		const property = newSelectProperty( {
			options: [ 'A', 'B', 'C' ],
		} );

		const errors = selectType.validate( newStringValue(), property );

		expect( errors ).toEqual( [] );
	} );

	it( 'returns required error for required empty value', () => {
		const property = newSelectProperty( {
			required: true,
			options: [ 'A', 'B', 'C' ],
		} );

		const errors = selectType.validate( newStringValue(), property );

		expect( errors ).toEqual( [ { code: 'required' } ] );
	} );

	it( 'returns required error for required undefined value', () => {
		const property = newSelectProperty( {
			required: true,
			options: [ 'A', 'B', 'C' ],
		} );

		const errors = selectType.validate( undefined, property );

		expect( errors ).toEqual( [ { code: 'required' } ] );
	} );

	it( 'returns no errors for valid option', () => {
		const property = newSelectProperty( {
			options: [ 'Draft', 'Review', 'Approved' ],
		} );

		const errors = selectType.validate( newStringValue( 'Review' ), property );

		expect( errors ).toEqual( [] );
	} );

	it( 'returns invalid-option error for value not in options', () => {
		const property = newSelectProperty( {
			options: [ 'Draft', 'Review', 'Approved' ],
		} );

		const errors = selectType.validate( newStringValue( 'Rejected' ), property );

		expect( errors ).toEqual( [ {
			code: 'invalid-option',
			args: [ 'Rejected' ],
			source: 'Rejected',
		} ] );
	} );

	it( 'returns single-value-only error when multiple values given for single select', () => {
		const property = newSelectProperty( {
			options: [ 'A', 'B', 'C' ],
			multiple: false,
		} );

		const errors = selectType.validate( newStringValue( [ 'A', 'B' ] ), property );

		expect( errors ).toEqual( [ { code: 'single-value-only' } ] );
	} );

	it( 'returns no errors for multiple values when multiple is true', () => {
		const property = newSelectProperty( {
			options: [ 'A', 'B', 'C' ],
			multiple: true,
		} );

		const errors = selectType.validate( newStringValue( [ 'A', 'C' ] ), property );

		expect( errors ).toEqual( [] );
	} );

	it( 'returns invalid-option errors for each invalid value in multi-select', () => {
		const property = newSelectProperty( {
			options: [ 'A', 'B', 'C' ],
			multiple: true,
		} );

		const errors = selectType.validate( newStringValue( [ 'A', 'X', 'B', 'Y' ] ), property );

		expect( errors ).toEqual( [
			{ code: 'invalid-option', args: [ 'X' ], source: 'X' },
			{ code: 'invalid-option', args: [ 'Y' ], source: 'Y' },
		] );
	} );
} );
