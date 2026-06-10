import { describe, it, expect, vi, beforeEach, type Mock } from 'vitest';
import { ref, type Ref } from 'vue';
import { useFieldServerViolation } from '@/composables/useFieldServerViolation.ts';
import { SubjectViolation } from '@/domain/SubjectViolation.ts';

type SetupResult = ReturnType<typeof useFieldServerViolation> & {
	liveValidationError: Ref<string | null>;
	emitClear: Mock;
};

vi.stubGlobal( 'mw', {
	message: vi.fn( ( key: string, ...params: string[] ) => ( {
		text: () => [ key, ...params ].join( '|' ),
	} ) ),
} );

const PROPERTY_NAME = 'Homepage';

function fieldViolation( overrides: Partial<SubjectViolation> = {} ): SubjectViolation {
	return {
		propertyName: PROPERTY_NAME,
		code: 'required',
		args: [],
		valuePartIndex: null,
		...overrides,
	};
}

function setup( violations: SubjectViolation[], live: string | null = null ): SetupResult {
	const liveValidationError = ref<string | null>( live );
	const emitClear = vi.fn();
	const composable = useFieldServerViolation(
		() => PROPERTY_NAME,
		() => violations,
		liveValidationError,
		emitClear,
	);
	return { ...composable, liveValidationError, emitClear };
}

describe( 'useFieldServerViolation', () => {
	beforeEach( () => {
		vi.clearAllMocks();
	} );

	describe( 'validationError', () => {
		it( 'is null when there are no violations and no live error', () => {
			const { validationError } = setup( [] );

			expect( validationError.value ).toBeNull();
		} );

		it( 'formats the field-level server violation when there is no live error', () => {
			const { validationError } = setup( [ fieldViolation( { code: 'type-mismatch', args: [ 'url', 'number' ] } ) ] );

			expect( validationError.value ).toBe( 'neowiki-field-type-mismatch|url|number' );
		} );

		it( 'prefers the live error over a server violation on the same field', () => {
			const { validationError } = setup( [ fieldViolation() ], 'live error' );

			expect( validationError.value ).toBe( 'live error' );
		} );

		it( 'ignores violations belonging to a different property', () => {
			const { validationError } = setup( [ fieldViolation( { propertyName: 'OtherProperty' } ) ] );

			expect( validationError.value ).toBeNull();
		} );

		it( 'ignores per-index violations, since single-value inputs have no per-index slot', () => {
			const { validationError } = setup( [ fieldViolation( { valuePartIndex: 2 } ) ] );

			expect( validationError.value ).toBeNull();
		} );

		it( 'selects the field-level violation from among per-index ones for the same property', () => {
			const { validationError } = setup( [
				fieldViolation( { code: 'invalid-url', valuePartIndex: 0 } ),
				fieldViolation( { code: 'required', valuePartIndex: null } ),
				fieldViolation( { code: 'invalid-url', valuePartIndex: 1 } ),
			] );

			expect( validationError.value ).toBe( 'neowiki-field-required' );
		} );

		it( 'passes args through unchanged when no formatter is given', () => {
			const { validationError } = setup( [ fieldViolation( { code: 'min-value', args: [ '42' ] } ) ] );

			expect( validationError.value ).toBe( 'neowiki-field-min-value|42' );
		} );

		it( 'applies the arg formatter to message args', () => {
			const { validationError } = useFieldServerViolation(
				() => PROPERTY_NAME,
				() => [ fieldViolation( { code: 'min-value', args: [ '2025-01-01' ] } ) ],
				ref( null ),
				vi.fn(),
				( arg ) => `formatted(${ arg })`,
			);

			expect( validationError.value ).toBe( 'neowiki-field-min-value|formatted(2025-01-01)' );
		} );
	} );

	describe( 'clearServerViolation', () => {
		it( 'emits clear for the field when a matching field-level violation exists', () => {
			const { clearServerViolation, emitClear } = setup( [ fieldViolation() ] );

			clearServerViolation();

			expect( emitClear ).toHaveBeenCalledWith( { propertyName: PROPERTY_NAME, valuePartIndex: null } );
		} );

		it( 'does not emit when there is no violation for the field', () => {
			const { clearServerViolation, emitClear } = setup( [] );

			clearServerViolation();

			expect( emitClear ).not.toHaveBeenCalled();
		} );

		it( 'does not emit when the only violation is per-index', () => {
			const { clearServerViolation, emitClear } = setup( [ fieldViolation( { valuePartIndex: 0 } ) ] );

			clearServerViolation();

			expect( emitClear ).not.toHaveBeenCalled();
		} );
	} );
} );
