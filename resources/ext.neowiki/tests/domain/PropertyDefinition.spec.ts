import { describe, expect, it } from 'vitest';
import { withConstraintSeverity, withoutSeveritiesOfClearedConstraints } from '@/domain/PropertyDefinition';
import { newNumberProperty } from '@/domain/propertyTypes/Number';
import { newSelectProperty } from '@/domain/propertyTypes/Select';
import { newTextProperty } from '@/domain/propertyTypes/Text';

describe( 'withConstraintSeverity', () => {
	it( 'annotates a Constraint with error', () => {
		const property = newNumberProperty( { minimum: 0 } );

		expect( withConstraintSeverity( property, 'minimum', 'error' ) ).toEqual( {
			constraintSeverities: { minimum: 'error' },
		} );
	} );

	it( 'keeps the other annotations', () => {
		const property = { ...newNumberProperty( { minimum: 0, maximum: 10 } ), constraintSeverities: { maximum: 'error' as const } };

		expect( withConstraintSeverity( property, 'minimum', 'error' ) ).toEqual( {
			constraintSeverities: { maximum: 'error', minimum: 'error' },
		} );
	} );

	it( 'removes the annotation when the Constraint goes back to warning, the default', () => {
		const property = {
			...newNumberProperty( { minimum: 0, maximum: 10 } ),
			constraintSeverities: { minimum: 'error' as const, maximum: 'error' as const },
		};

		expect( withConstraintSeverity( property, 'minimum', 'warning' ) ).toEqual( {
			constraintSeverities: { maximum: 'error' },
		} );
	} );

	it( 'drops the map entirely when its last annotation goes back to warning', () => {
		const property = { ...newNumberProperty( { minimum: 0 } ), constraintSeverities: { minimum: 'error' as const } };

		// toStrictEqual: the explicit undefined key is what clears the old map when the
		// editor spreads this over the property.
		expect( withConstraintSeverity( property, 'minimum', 'warning' ) ).toStrictEqual( {
			constraintSeverities: undefined,
		} );
	} );

	it( 'does not touch the given property', () => {
		const property = { ...newNumberProperty( { minimum: 0 } ), constraintSeverities: { minimum: 'error' as const } };

		withConstraintSeverity( property, 'minimum', 'warning' );

		expect( property.constraintSeverities ).toEqual( { minimum: 'error' } );
	} );
} );

describe( 'withoutSeveritiesOfClearedConstraints', () => {
	it( 'drops the severity of a boolean Constraint that is being unticked and keeps the others', () => {
		const property = {
			...newTextProperty( { multiple: true, uniqueItems: true, minLength: 2 } ),
			constraintSeverities: { uniqueItems: 'error' as const, minLength: 'error' as const },
		};

		expect( withoutSeveritiesOfClearedConstraints( property, { uniqueItems: false } ) ).toEqual( {
			constraintSeverities: { minLength: 'error' },
		} );
	} );

	it( 'drops the map entirely when the last annotated Constraint is unticked', () => {
		const property = { ...newTextProperty( { multiple: true, uniqueItems: true } ), constraintSeverities: { uniqueItems: 'error' as const } };

		expect( withoutSeveritiesOfClearedConstraints( property, { uniqueItems: false } ) ).toStrictEqual( {
			constraintSeverities: undefined,
		} );
	} );

	it( 'keeps the severity of a bound that is being cleared, since a bound being typed reads as cleared', () => {
		const property = { ...newNumberProperty( { minimum: 0 } ), constraintSeverities: { minimum: 'error' as const } };

		expect( withoutSeveritiesOfClearedConstraints( property, { minimum: undefined } ) ).toEqual( {
			constraintSeverities: { minimum: 'error' },
		} );
	} );

	it( 'drops the severity of a list Constraint that is being emptied', () => {
		const property = { ...newSelectProperty( { options: [ { id: 'a', label: 'A' } ] } ), constraintSeverities: { options: 'error' as const } };

		expect( withoutSeveritiesOfClearedConstraints( property, { options: [] } ) ).toStrictEqual( {
			constraintSeverities: undefined,
		} );
	} );

	it( 'keeps the severity of the single-value rule when multiple values stop being allowed', () => {
		const property = { ...newSelectProperty( { multiple: true } ), constraintSeverities: { multiple: 'error' as const } };

		expect( withoutSeveritiesOfClearedConstraints( property, { multiple: false } ) ).toEqual( {
			constraintSeverities: { multiple: 'error' },
		} );
	} );

	it( 'keeps the severity of a Constraint that is being set to a value', () => {
		const property = { ...newNumberProperty( { minimum: 0 } ), constraintSeverities: { minimum: 'error' as const } };

		expect( withoutSeveritiesOfClearedConstraints( property, { minimum: 5 } ) ).toEqual( {
			constraintSeverities: { minimum: 'error' },
		} );
	} );

	it( 'leaves the map alone when a non-Constraint field changes', () => {
		const property = { ...newNumberProperty( { minimum: 0 } ), constraintSeverities: { minimum: 'error' as const } };

		expect( withoutSeveritiesOfClearedConstraints( property, { description: '' } ) ).toEqual( {
			constraintSeverities: { minimum: 'error' },
		} );
	} );
} );
