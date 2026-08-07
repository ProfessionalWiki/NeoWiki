import { describe, it, expect, vi, beforeEach, type Mock } from 'vitest';
import { ref, shallowRef, type Ref } from 'vue';
import { useServerViolations, violationStatus } from '@/composables/useServerViolations.ts';
import { SubjectViolation } from '@/domain/SubjectViolation.ts';
import { PropertyDefinition, PropertyName } from '@/domain/PropertyDefinition.ts';

vi.stubGlobal( 'mw', {
	message: vi.fn( ( key: string, ...params: string[] ) => ( {
		text: () => [ key, ...params ].join( '|' ),
	} ) ),
} );

const PROPERTY_NAME = 'Status';

function newProperty(): PropertyDefinition {
	return {
		name: new PropertyName( PROPERTY_NAME ),
		type: 'text',
		description: '',
		required: false,
	};
}

function violation( overrides: Partial<SubjectViolation> = {} ): SubjectViolation {
	return {
		propertyName: PROPERTY_NAME,
		code: 'invalid-option',
		args: [],
		severity: 'error',
		valuePartIndex: null,
		...overrides,
	};
}

function setup( violations: SubjectViolation[], formatArg?: ( arg: string ) => string ): {
	composable: ReturnType<typeof useServerViolations>;
	serverViolations: Ref<readonly SubjectViolation[] | undefined>;
	emit: Mock;
} {
	const serverViolations = ref<readonly SubjectViolation[] | undefined>( violations );
	const emit = vi.fn();
	const composable = useServerViolations( shallowRef( newProperty() ), serverViolations, emit, formatArg );
	return { composable, serverViolations, emit };
}

describe( 'useServerViolations', () => {
	beforeEach( () => {
		vi.clearAllMocks();
	} );

	describe( 'relevant', () => {
		it( 'returns only the violations for this property, preserving order', () => {
			const { composable } = setup( [
				violation( { valuePartIndex: 0 } ),
				violation( { propertyName: 'Other', valuePartIndex: 1 } ),
				violation( { valuePartIndex: 2 } ),
			] );

			expect( composable.relevant().map( ( v ) => v.valuePartIndex ) ).toEqual( [ 0, 2 ] );
		} );

		it( 'is empty when serverViolations is undefined', () => {
			const serverViolations = ref<readonly SubjectViolation[] | undefined>( undefined );
			const composable = useServerViolations( shallowRef( newProperty() ), serverViolations, vi.fn() );

			expect( composable.relevant() ).toEqual( [] );
		} );
	} );

	describe( 'format', () => {
		it( 'builds the neowiki-field message with args', () => {
			const { composable } = setup( [] );

			expect( composable.format( violation( { code: 'type-mismatch', args: [ 'url', 'number' ] } ) ) )
				.toBe( 'neowiki-field-type-mismatch|url|number' );
		} );

		it( 'applies the arg formatter', () => {
			const { composable } = setup( [], ( arg ) => `f(${ arg })` );

			expect( composable.format( violation( { code: 'min-value', args: [ '42' ] } ) ) )
				.toBe( 'neowiki-field-min-value|f(42)' );
		} );
	} );

	describe( 'firstMessages', () => {
		it( 'keys the first relevant violation by its severity, even when part-indexed', () => {
			const { composable } = setup( [ violation( { code: 'invalid-option', args: [ 'x' ], valuePartIndex: 1 } ) ] );

			expect( composable.firstMessages.value ).toEqual( { error: 'neowiki-field-invalid-option|x' } );
		} );

		it( 'keys a warning violation under warning', () => {
			const { composable } = setup( [ violation( { severity: 'warning' } ) ] );

			expect( composable.firstMessages.value ).toEqual( { warning: 'neowiki-field-invalid-option' } );
		} );

		it( 'is empty when there are no relevant violations', () => {
			const { composable } = setup( [ violation( { propertyName: 'Other' } ) ] );

			expect( composable.firstMessages.value ).toEqual( {} );
		} );

		it( 'prefers an error over an earlier warning, so a blocker is never masked', () => {
			const { composable } = setup( [
				violation( { code: 'max-value', args: [ '100' ], severity: 'warning' } ),
				violation( { code: 'required', severity: 'error' } ),
			] );

			expect( composable.firstMessages.value ).toEqual( { error: 'neowiki-field-required' } );
		} );
	} );

	describe( 'fieldLevelMessages', () => {
		it( 'is the null-index violation keyed by its severity, ignoring part-indexed ones', () => {
			const { composable } = setup( [
				violation( { code: 'invalid-option', valuePartIndex: 0 } ),
				violation( { code: 'required', severity: 'warning', valuePartIndex: null } ),
			] );

			expect( composable.fieldLevelMessages.value ).toEqual( { warning: 'neowiki-field-required' } );
		} );

		it( 'is empty when only part-indexed violations exist', () => {
			const { composable } = setup( [ violation( { valuePartIndex: 2 } ) ] );

			expect( composable.fieldLevelMessages.value ).toEqual( {} );
		} );

		it( 'prefers a field-level error over an earlier field-level warning', () => {
			const { composable } = setup( [
				violation( { code: 'max-value', args: [ '100' ], severity: 'warning', valuePartIndex: null } ),
				violation( { code: 'required', severity: 'error', valuePartIndex: null } ),
			] );

			expect( composable.fieldLevelMessages.value ).toEqual( { error: 'neowiki-field-required' } );
		} );
	} );

	describe( 'violationStatus', () => {
		it( 'is the key of the single message', () => {
			expect( violationStatus( { error: 'x' } ) ).toBe( 'error' );
			expect( violationStatus( { warning: 'x' } ) ).toBe( 'warning' );
		} );

		it( 'is default when there are no messages', () => {
			expect( violationStatus( {} ) ).toBe( 'default' );
		} );

		it( 'prefers error when both severities carry a message', () => {
			expect( violationStatus( { error: 'x', warning: 'y' } ) ).toBe( 'error' );
		} );
	} );

	describe( 'emitClears( "all" )', () => {
		it( 'clears every held violation by its own index, null and numeric alike', () => {
			const { composable, emit } = setup( [
				violation( { valuePartIndex: 0 } ),
				violation( { valuePartIndex: 2 } ),
				violation( { code: 'required', valuePartIndex: null } ),
			] );

			composable.emitClears( 'all' );

			expect( emit.mock.calls ).toEqual( [
				[ 'clear-server-violation', { propertyName: PROPERTY_NAME, valuePartIndex: 0 } ],
				[ 'clear-server-violation', { propertyName: PROPERTY_NAME, valuePartIndex: 2 } ],
				[ 'clear-server-violation', { propertyName: PROPERTY_NAME, valuePartIndex: null } ],
			] );
		} );

		it( 'does not emit when there is no violation for the property', () => {
			const { composable, emit } = setup( [ violation( { propertyName: 'Other' } ) ] );

			composable.emitClears( 'all' );

			expect( emit ).not.toHaveBeenCalled();
		} );
	} );

	describe( 'emitClears( indices )', () => {
		it( 'clears only the touched indices that carry a violation', () => {
			const { composable, emit } = setup( [
				violation( { valuePartIndex: 1 } ),
				violation( { valuePartIndex: 3 } ),
			] );

			composable.emitClears( [ 0, 1 ] );

			expect( emit.mock.calls ).toEqual( [
				[ 'clear-server-violation', { propertyName: PROPERTY_NAME, valuePartIndex: 1 } ],
			] );
		} );

		it( 'also clears the shared field-level slot when a null-index violation is held', () => {
			const { composable, emit } = setup( [
				violation( { code: 'invalid-url', valuePartIndex: 1 } ),
				violation( { code: 'required', valuePartIndex: null } ),
			] );

			composable.emitClears( [ 1 ] );

			expect( emit.mock.calls ).toEqual( [
				[ 'clear-server-violation', { propertyName: PROPERTY_NAME, valuePartIndex: 1 } ],
				[ 'clear-server-violation', { propertyName: PROPERTY_NAME, valuePartIndex: null } ],
			] );
		} );

		it( 'clears the field-level slot on any edit even when no numeric index was touched (item 4)', () => {
			const { composable, emit } = setup( [ violation( { code: 'required', valuePartIndex: null } ) ] );

			composable.emitClears( [] );

			expect( emit.mock.calls ).toEqual( [
				[ 'clear-server-violation', { propertyName: PROPERTY_NAME, valuePartIndex: null } ],
			] );
		} );

		it( 'does not clear the field-level slot when only part-indexed violations are held', () => {
			const { composable, emit } = setup( [ violation( { valuePartIndex: 1 } ) ] );

			composable.emitClears( [] );

			expect( emit ).not.toHaveBeenCalled();
		} );

		it( 'leaves an untouched part-indexed violation in place when clearing the field-level slot', () => {
			const { composable, emit } = setup( [
				violation( { code: 'invalid-url', valuePartIndex: 1 } ),
				violation( { code: 'unique', valuePartIndex: null } ),
			] );

			composable.emitClears( [ 0 ] );

			// Editing part 0 clears the shared field-level slot but must NOT sweep the
			// still-invalid part 1: exactly one emit, the null clear, never index 1.
			expect( emit.mock.calls ).toEqual( [
				[ 'clear-server-violation', { propertyName: PROPERTY_NAME, valuePartIndex: null } ],
			] );
		} );
	} );

	describe( 'reactivity', () => {
		it( 'recomputes the display messages when serverViolations changes after creation', () => {
			const { composable, serverViolations } = setup( [] );
			expect( composable.firstMessages.value ).toEqual( {} );
			expect( composable.fieldLevelMessages.value ).toEqual( {} );

			// A failed save sets serverViolations on an already-mounted input; the display
			// computeds must recompute from the live ref, not a snapshot taken at creation.
			serverViolations.value = [ violation( { code: 'required', valuePartIndex: null } ) ];

			expect( composable.firstMessages.value ).toEqual( { error: 'neowiki-field-required' } );
			expect( composable.fieldLevelMessages.value ).toEqual( { error: 'neowiki-field-required' } );
		} );
	} );
} );
