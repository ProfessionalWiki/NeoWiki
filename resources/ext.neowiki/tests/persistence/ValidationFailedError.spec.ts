import { describe, expect, it } from 'vitest';
import { ValidationFailedError } from '@/persistence/ValidationFailedError';
import type { SubjectViolation } from '@/domain/SubjectViolation';

describe( 'ValidationFailedError', () => {

	const oneViolation: SubjectViolation = {
		propertyName: 'Status',
		code: 'required',
		args: [],
		valuePartIndex: null,
	};

	it( 'is instanceof Error and ValidationFailedError', () => {
		const error = new ValidationFailedError( [ oneViolation ] );

		expect( error ).toBeInstanceOf( Error );
		expect( error ).toBeInstanceOf( ValidationFailedError );
	} );

	it( 'carries the violations array', () => {
		const error = new ValidationFailedError( [ oneViolation ] );

		expect( error.violations ).toEqual( [ oneViolation ] );
	} );

	it( 'has a stable default message of "Validation failed"', () => {
		const error = new ValidationFailedError( [ oneViolation ] );

		expect( error.message ).toEqual( 'Validation failed' );
	} );

	it( 'has name set to "ValidationFailedError"', () => {
		const error = new ValidationFailedError( [ oneViolation ] );

		expect( error.name ).toEqual( 'ValidationFailedError' );
	} );

} );
