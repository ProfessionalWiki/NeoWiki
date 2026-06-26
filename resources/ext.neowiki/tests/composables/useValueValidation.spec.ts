import { describe, it, expect, vi } from 'vitest';
import {
	SERVER_ENFORCED_CODES,
	liveValidationErrors,
	liveValidationMessages,
	validateValue,
} from '@/composables/useValueValidation.ts';
import { PropertyType, ValueValidationError } from '@/domain/PropertyType.ts';
import { Value, newStringValue } from '@/domain/Value.ts';
import { PropertyDefinition } from '@/domain/PropertyDefinition.ts';

vi.stubGlobal( 'mw', {
	message: vi.fn( ( key: string ) => ( {
		text: () => key,
		parse: () => key,
	} ) ),
} );

function propertyTypeReturning( errors: ValueValidationError[] ): PropertyType {
	return { validate: () => errors } as unknown as PropertyType;
}

const anyValue: Value = newStringValue( 'x' );
const anyProperty = {} as PropertyDefinition;

describe( 'useValueValidation', () => {
	it( 'treats required as a server-enforced code', () => {
		expect( SERVER_ENFORCED_CODES ).toContain( 'required' );
	} );

	describe( 'liveValidationErrors', () => {
		it( 'drops server-enforced codes while keeping the surrounding ones', () => {
			const propertyType = propertyTypeReturning( [
				{ code: 'min-value', args: [ 1 ] },
				{ code: 'required' },
				{ code: 'max-value', args: [ 9 ] },
			] );

			const errors = liveValidationErrors( anyValue, propertyType, anyProperty );

			expect( errors ).toEqual( [
				{ code: 'min-value', args: [ 1 ] },
				{ code: 'max-value', args: [ 9 ] },
			] );
		} );

		it( 'returns no errors when only a server-enforced code is present', () => {
			const propertyType = propertyTypeReturning( [ { code: 'required' } ] );

			expect( liveValidationErrors( anyValue, propertyType, anyProperty ) ).toEqual( [] );
		} );
	} );

	describe( 'liveValidationMessages', () => {
		it( 'formats the first live error and skips server-enforced codes', () => {
			const propertyType = propertyTypeReturning( [
				{ code: 'required' },
				{ code: 'min-value' },
			] );

			expect( liveValidationMessages( anyValue, propertyType, anyProperty ) )
				.toEqual( { error: 'neowiki-field-min-value' } );
		} );

		it( 'returns no message when only a server-enforced code is present', () => {
			const propertyType = propertyTypeReturning( [ { code: 'required' } ] );

			expect( liveValidationMessages( anyValue, propertyType, anyProperty ) ).toEqual( {} );
		} );
	} );

	describe( 'validateValue', () => {
		it( 'surfaces server-enforced codes, unlike the live helpers', () => {
			const propertyType = propertyTypeReturning( [ { code: 'required' } ] );

			expect( validateValue( anyValue, propertyType, anyProperty ) )
				.toEqual( { error: 'neowiki-field-required' } );
		} );
	} );
} );
