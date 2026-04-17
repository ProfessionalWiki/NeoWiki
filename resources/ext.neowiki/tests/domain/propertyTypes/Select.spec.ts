import { describe, expect, it } from 'vitest';
import { newSelectProperty, resolveSelectLabel, SelectType } from '@/domain/propertyTypes/Select';
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

describe( 'validate', () => {
	const selectType = new SelectType();

	it( 'returns no errors for empty value when optional', () => {
		const property = newSelectProperty( {
			options: [
				{ id: 'a', label: 'A' },
				{ id: 'b', label: 'B' },
				{ id: 'c', label: 'C' },
			],
		} );

		const errors = selectType.validate( newStringValue(), property );

		expect( errors ).toEqual( [] );
	} );

	it( 'returns required error for required empty value', () => {
		const property = newSelectProperty( {
			required: true,
			options: [
				{ id: 'a', label: 'A' },
			],
		} );

		const errors = selectType.validate( newStringValue(), property );

		expect( errors ).toEqual( [ { code: 'required' } ] );
	} );

	it( 'returns required error for required undefined value', () => {
		const property = newSelectProperty( {
			required: true,
			options: [
				{ id: 'a', label: 'A' },
			],
		} );

		const errors = selectType.validate( undefined, property );

		expect( errors ).toEqual( [ { code: 'required' } ] );
	} );

	it( 'accepts a known option ID as valid', () => {
		const property = newSelectProperty( {
			options: [
				{ id: 'opt1', label: 'Draft' },
				{ id: 'opt2', label: 'Review' },
			],
		} );

		expect( selectType.validate( newStringValue( 'opt2' ), property ) ).toEqual( [] );
	} );

	it( 'rejects an option label as invalid since values are matched by ID', () => {
		const property = newSelectProperty( {
			options: [
				{ id: 'opt1', label: 'Draft' },
				{ id: 'opt2', label: 'Review' },
			],
		} );

		expect( selectType.validate( newStringValue( 'Draft' ), property ) ).toEqual( [
			{ code: 'invalid-option', args: [ 'Draft' ], source: 'Draft' },
		] );
	} );

	it( 'returns single-value-only error when multiple values given for single select', () => {
		const property = newSelectProperty( {
			options: [
				{ id: 'a', label: 'A' },
				{ id: 'b', label: 'B' },
			],
			multiple: false,
		} );

		const errors = selectType.validate( newStringValue( [ 'a', 'b' ] ), property );

		expect( errors ).toEqual( [ { code: 'single-value-only' } ] );
	} );

	it( 'returns no errors for multiple values when multiple is true', () => {
		const property = newSelectProperty( {
			options: [
				{ id: 'a', label: 'A' },
				{ id: 'b', label: 'B' },
				{ id: 'c', label: 'C' },
			],
			multiple: true,
		} );

		const errors = selectType.validate( newStringValue( [ 'a', 'c' ] ), property );

		expect( errors ).toEqual( [] );
	} );

	it( 'returns invalid-option errors for each invalid value in multi-select', () => {
		const property = newSelectProperty( {
			options: [
				{ id: 'a', label: 'A' },
				{ id: 'b', label: 'B' },
				{ id: 'c', label: 'C' },
			],
			multiple: true,
		} );

		const errors = selectType.validate( newStringValue( [ 'a', 'X', 'b', 'Y' ] ), property );

		expect( errors ).toEqual( [
			{ code: 'invalid-option', args: [ 'X' ], source: 'X' },
			{ code: 'invalid-option', args: [ 'Y' ], source: 'Y' },
		] );
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
