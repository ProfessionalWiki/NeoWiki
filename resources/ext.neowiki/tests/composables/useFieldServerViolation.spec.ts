import { describe, it, expect, vi, beforeEach, type Mock } from 'vitest';
import { ref, shallowRef, type Ref } from 'vue';
import { useFieldServerViolation } from '@/composables/useFieldServerViolation.ts';
import { SubjectViolation } from '@/domain/SubjectViolation.ts';
import { PropertyDefinition, PropertyName } from '@/domain/PropertyDefinition.ts';

type SetupResult = ReturnType<typeof useFieldServerViolation> & {
	serverViolations: Ref<readonly SubjectViolation[] | undefined>;
	emit: Mock;
};

vi.stubGlobal( 'mw', {
	message: vi.fn( ( key: string, ...params: string[] ) => ( {
		text: () => [ key, ...params ].join( '|' ),
	} ) ),
} );

const PROPERTY_NAME = 'Homepage';

function newProperty(): PropertyDefinition {
	return {
		name: new PropertyName( PROPERTY_NAME ),
		type: 'text',
		description: '',
		required: false,
	};
}

function fieldViolation( overrides: Partial<SubjectViolation> = {} ): SubjectViolation {
	return {
		propertyName: PROPERTY_NAME,
		code: 'required',
		args: [],
		valuePartIndex: null,
		...overrides,
	};
}

function setup( violations: SubjectViolation[] ): SetupResult {
	const serverViolations = ref<readonly SubjectViolation[] | undefined>( violations );
	const emit = vi.fn();
	const composable = useFieldServerViolation(
		shallowRef( newProperty() ),
		serverViolations,
		emit,
	);
	return { ...composable, serverViolations, emit };
}

describe( 'useFieldServerViolation', () => {
	beforeEach( () => {
		vi.clearAllMocks();
	} );

	describe( 'validationError', () => {
		it( 'is null when there are no violations', () => {
			const { validationError } = setup( [] );

			expect( validationError.value ).toBeNull();
		} );

		it( 'formats the field-level server violation', () => {
			const { validationError } = setup( [ fieldViolation( { code: 'type-mismatch', args: [ 'url', 'number' ] } ) ] );

			expect( validationError.value ).toBe( 'neowiki-field-type-mismatch|url|number' );
		} );

		it( 'stops surfacing the violation when the parent removes it', () => {
			const { validationError, serverViolations } = setup( [ fieldViolation() ] );
			expect( validationError.value ).toBe( 'neowiki-field-required' );

			serverViolations.value = [];

			expect( validationError.value ).toBeNull();
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
				shallowRef( newProperty() ),
				ref<readonly SubjectViolation[] | undefined>( [ fieldViolation( { code: 'min-value', args: [ '2025-01-01' ] } ) ] ),
				vi.fn(),
				( arg ) => `formatted(${ arg })`,
			);

			expect( validationError.value ).toBe( 'neowiki-field-min-value|formatted(2025-01-01)' );
		} );
	} );

	describe( 'clearServerViolation', () => {
		it( 'emits clear for the field when a matching field-level violation exists', () => {
			const { clearServerViolation, emit } = setup( [ fieldViolation() ] );

			clearServerViolation();

			expect( emit ).toHaveBeenCalledWith(
				'clear-server-violation',
				{ propertyName: PROPERTY_NAME, valuePartIndex: null },
			);
		} );

		it( 'does not emit when there is no violation for the field', () => {
			const { clearServerViolation, emit } = setup( [] );

			clearServerViolation();

			expect( emit ).not.toHaveBeenCalled();
		} );

		it( 'does not emit when the only violation is for a different property', () => {
			const { clearServerViolation, emit } = setup( [ fieldViolation( { propertyName: 'OtherProperty' } ) ] );

			clearServerViolation();

			expect( emit ).not.toHaveBeenCalled();
		} );

		it( 'does not emit when the only violation is per-index', () => {
			const { clearServerViolation, emit } = setup( [ fieldViolation( { valuePartIndex: 0 } ) ] );

			clearServerViolation();

			expect( emit ).not.toHaveBeenCalled();
		} );
	} );
} );
