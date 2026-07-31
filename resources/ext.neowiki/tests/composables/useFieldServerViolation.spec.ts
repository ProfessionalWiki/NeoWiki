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
		severity: 'error',
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

	describe( 'validationMessages and validationStatus', () => {
		it( 'is empty with default status when there are no violations', () => {
			const { validationMessages, validationStatus } = setup( [] );

			expect( validationMessages.value ).toEqual( {} );
			expect( validationStatus.value ).toBe( 'default' );
		} );

		it( 'keys the field-level server violation by its severity', () => {
			const { validationMessages, validationStatus } = setup(
				[ fieldViolation( { code: 'type-mismatch', args: [ 'url', 'number' ] } ) ],
			);

			expect( validationMessages.value ).toEqual( { error: 'neowiki-field-type-mismatch|url|number' } );
			expect( validationStatus.value ).toBe( 'error' );
		} );

		it( 'renders a warning violation with the warning status', () => {
			const { validationMessages, validationStatus } = setup(
				[ fieldViolation( { code: 'max-value', args: [ '100' ], severity: 'warning' } ) ],
			);

			expect( validationMessages.value ).toEqual( { warning: 'neowiki-field-max-value|100' } );
			expect( validationStatus.value ).toBe( 'warning' );
		} );

		it( 'stops surfacing the violation when the parent removes it', () => {
			const { validationMessages, serverViolations } = setup( [ fieldViolation() ] );
			expect( validationMessages.value ).toEqual( { error: 'neowiki-field-required' } );

			serverViolations.value = [];

			expect( validationMessages.value ).toEqual( {} );
		} );

		it( 'ignores violations belonging to a different property', () => {
			const { validationMessages } = setup( [ fieldViolation( { propertyName: 'OtherProperty' } ) ] );

			expect( validationMessages.value ).toEqual( {} );
		} );

		it( 'ignores per-index violations, since single-value inputs have no per-index slot', () => {
			const { validationMessages } = setup( [ fieldViolation( { valuePartIndex: 2 } ) ] );

			expect( validationMessages.value ).toEqual( {} );
		} );

		it( 'selects the field-level violation from among per-index ones for the same property', () => {
			const { validationMessages } = setup( [
				fieldViolation( { code: 'invalid-url', valuePartIndex: 0 } ),
				fieldViolation( { code: 'required', valuePartIndex: null } ),
				fieldViolation( { code: 'invalid-url', valuePartIndex: 1 } ),
			] );

			expect( validationMessages.value ).toEqual( { error: 'neowiki-field-required' } );
		} );

		it( 'passes args through unchanged when no formatter is given', () => {
			const { validationMessages } = setup( [ fieldViolation( { code: 'min-value', args: [ '42' ] } ) ] );

			expect( validationMessages.value ).toEqual( { error: 'neowiki-field-min-value|42' } );
		} );

		it( 'applies the arg formatter to message args', () => {
			const { validationMessages } = useFieldServerViolation(
				shallowRef( newProperty() ),
				ref<readonly SubjectViolation[] | undefined>( [ fieldViolation( { code: 'min-value', args: [ '2025-01-01' ] } ) ] ),
				vi.fn(),
				( arg ) => `formatted(${ arg })`,
			);

			expect( validationMessages.value ).toEqual( { error: 'neowiki-field-min-value|formatted(2025-01-01)' } );
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
