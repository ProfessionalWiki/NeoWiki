import { describe, expect, it } from 'vitest';
import { interpretConstraints } from '@/domain/ConstraintInterpreter';
import { newNumberValue, newRelation, newStringValue, RelationValue } from '@/domain/Value';

describe( 'interpretConstraints', () => {

	describe( 'required', () => {

		it( 'produces required error for empty StringValue', () => {
			expect( interpretConstraints( [ { kind: 'required' } ], newStringValue() ) )
				.toEqual( [ { code: 'required' } ] );
		} );

		it( 'produces required error for undefined value', () => {
			expect( interpretConstraints( [ { kind: 'required' } ], undefined ) )
				.toEqual( [ { code: 'required' } ] );
		} );

		it( 'produces no error for non-empty StringValue', () => {
			expect( interpretConstraints( [ { kind: 'required' } ], newStringValue( 'a' ) ) )
				.toEqual( [] );
		} );

		it( 'produces required error for empty RelationValue', () => {
			expect( interpretConstraints( [ { kind: 'required' } ], new RelationValue( [] ) ) )
				.toEqual( [ { code: 'required' } ] );
		} );

		it( 'produces no error for non-empty RelationValue', () => {
			const value = new RelationValue( [ newRelation( undefined, 's11111111111111' ) ] );
			expect( interpretConstraints( [ { kind: 'required' } ], value ) ).toEqual( [] );
		} );

	} );

	describe( 'minLength', () => {

		it( 'produces error per part below threshold', () => {
			expect( interpretConstraints(
				[ { kind: 'minLength', value: 3 } ],
				newStringValue( 'ab', 'abcd' ),
			) ).toEqual( [ { code: 'min-length', args: [ 3 ], source: 'ab' } ] );
		} );

		it( 'produces no error when all parts meet threshold', () => {
			expect( interpretConstraints(
				[ { kind: 'minLength', value: 3 } ],
				newStringValue( 'abc', 'abcd' ),
			) ).toEqual( [] );
		} );

		it( 'measures trimmed length', () => {
			// Note: newStringValue trims at construction; this confirms the interpreter ALSO trims.
			// Construct a StringValue with an untrimmed part by hand to bypass newStringValue's trim.
			const value = { type: 'string' as const, parts: [ '  ab  ' ] };
			expect( interpretConstraints(
				[ { kind: 'minLength', value: 3 } ],
				value,
			) ).toEqual( [ { code: 'min-length', args: [ 3 ], source: '  ab  ' } ] );
		} );

	} );

	describe( 'maxLength', () => {

		it( 'produces error per part above threshold', () => {
			expect( interpretConstraints(
				[ { kind: 'maxLength', value: 3 } ],
				newStringValue( 'ab', 'abcd' ),
			) ).toEqual( [ { code: 'max-length', args: [ 3 ], source: 'abcd' } ] );
		} );

		it( 'produces no error when all parts within threshold', () => {
			expect( interpretConstraints(
				[ { kind: 'maxLength', value: 5 } ],
				newStringValue( 'ab', 'abcd' ),
			) ).toEqual( [] );
		} );

	} );

	describe( 'uniqueItems', () => {

		it( 'produces unique error when parts contain duplicates', () => {
			expect( interpretConstraints(
				[ { kind: 'uniqueItems' } ],
				newStringValue( 'a', 'b', 'a' ),
			) ).toEqual( [ { code: 'unique' } ] );
		} );

		it( 'produces no error when all parts are unique', () => {
			expect( interpretConstraints(
				[ { kind: 'uniqueItems' } ],
				newStringValue( 'a', 'b', 'c' ),
			) ).toEqual( [] );
		} );

	} );

	describe( 'cardinality', () => {

		it( 'produces single-value-only when parts exceed maxItems', () => {
			expect( interpretConstraints(
				[ { kind: 'cardinality', maxItems: 1 } ],
				newStringValue( 'a', 'b' ),
			) ).toEqual( [ { code: 'single-value-only' } ] );
		} );

		it( 'produces no error when parts at or below maxItems', () => {
			expect( interpretConstraints(
				[ { kind: 'cardinality', maxItems: 1 } ],
				newStringValue( 'a' ),
			) ).toEqual( [] );
		} );

	} );

	describe( 'enum', () => {

		it( 'produces invalid-option per part outside allowedValues', () => {
			expect( interpretConstraints(
				[ { kind: 'enum', allowedValues: [ 'a', 'b' ] } ],
				newStringValue( 'a', 'x', 'b', 'y' ),
			) ).toEqual( [
				{ code: 'invalid-option', args: [ 'x' ], source: 'x' },
				{ code: 'invalid-option', args: [ 'y' ], source: 'y' },
			] );
		} );

		it( 'produces no error when all parts in allowedValues', () => {
			expect( interpretConstraints(
				[ { kind: 'enum', allowedValues: [ 'a', 'b', 'c' ] } ],
				newStringValue( 'a', 'b' ),
			) ).toEqual( [] );
		} );

	} );

	describe( 'value-type guards', () => {

		it( 'skips minLength on NumberValue silently', () => {
			expect( interpretConstraints(
				[ { kind: 'minLength', value: 3 } ],
				newNumberValue( 42 ),
			) ).toEqual( [] );
		} );

		it( 'skips uniqueItems on undefined silently', () => {
			expect( interpretConstraints( [ { kind: 'uniqueItems' } ], undefined ) ).toEqual( [] );
		} );

		it( 'skips enum on RelationValue silently', () => {
			const value = new RelationValue( [ newRelation( undefined, 's11111111111111' ) ] );
			expect( interpretConstraints(
				[ { kind: 'enum', allowedValues: [ 'a' ] } ],
				value,
			) ).toEqual( [] );
		} );

	} );

	describe( 'severity passthrough', () => {

		it( 'copies severity from constraint to error', () => {
			expect( interpretConstraints(
				[ { kind: 'required', severity: 'warning' } ],
				newStringValue(),
			) ).toEqual( [ { code: 'required', severity: 'warning' } ] );
		} );

		it( 'omits severity when not set on constraint', () => {
			const errors = interpretConstraints( [ { kind: 'required' } ], newStringValue() );
			expect( errors[ 0 ] ).not.toHaveProperty( 'severity' );
		} );

	} );

	describe( 'multiple constraints', () => {

		it( 'concatenates errors in input order', () => {
			// minLength then maxLength: each emits independently across parts
			expect( interpretConstraints(
				[ { kind: 'minLength', value: 3 }, { kind: 'maxLength', value: 5 } ],
				newStringValue( 'ab', 'abcdefgh' ),
			) ).toEqual( [
				{ code: 'min-length', args: [ 3 ], source: 'ab' },
				{ code: 'max-length', args: [ 5 ], source: 'abcdefgh' },
			] );
		} );

		it( 'returns empty array when no constraints fail', () => {
			expect( interpretConstraints(
				[ { kind: 'required' }, { kind: 'minLength', value: 1 } ],
				newStringValue( 'ok' ),
			) ).toEqual( [] );
		} );

	} );

} );
