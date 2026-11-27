import { describe, expect, it } from 'vitest';
import { activeConstraints, withInitialSeverities } from '@/domain/initialConstraintSeverity';
import type { PropertyDefinition } from '@/domain/PropertyDefinition';
import type { Severity } from '@/domain/Severity';
import { newDateProperty } from '@/domain/propertyTypes/Date';
import { newMonolingualTextProperty } from '@/domain/propertyTypes/MonolingualText';
import { newNumberProperty } from '@/domain/propertyTypes/Number';
import { newSelectProperty } from '@/domain/propertyTypes/Select';
import { newTextProperty } from '@/domain/propertyTypes/Text';

const OPTION = { id: 'o1', label: 'Open' };

describe( 'activeConstraints', () => {
	it.each( [
		[ 'a required value', newNumberProperty( { required: true } ), [ 'required' ] ],
		[ 'number bounds', newNumberProperty( { minimum: 0, maximum: 10 } ), [ 'minimum', 'maximum' ] ],
		[
			'unique values and lengths of text',
			newTextProperty( { multiple: true, uniqueItems: true, minLength: 1, maxLength: 9 } ),
			[ 'uniqueItems', 'minLength', 'maxLength' ],
		],
		[ 'no single value for text, which does not check it', newTextProperty( { multiple: false, uniqueItems: false } ), [] ],
		[ 'a single value and the options of a select', newSelectProperty( { multiple: false, options: [ OPTION ] } ), [ 'multiple', 'options' ] ],
		[ 'nothing for a select that allows multiple values and has no options', newSelectProperty( { multiple: true } ), [] ],
		[
			'one text per language',
			newMonolingualTextProperty( { multiple: true, uniqueLanguages: true } ),
			[ 'uniqueLanguages' ],
		],
		[
			'no one text per language for a property that takes one text',
			newMonolingualTextProperty( { multiple: false, uniqueLanguages: true } ),
			[ 'multiple' ],
		],
		[ 'the full date a date property asks for when it leaves its precision out', newDateProperty(), [ 'minPrecision' ] ],
		[ 'no precision for a date property that takes a year', newDateProperty( { minPrecision: 'year' } ), [] ],
	] )( 'has %s active', ( _, property: PropertyDefinition, constraints: string[] ) => {
		expect( new Set( activeConstraints( property ) ) ).toEqual( new Set( constraints ) );
	} );
} );

describe( 'withInitialSeverities', () => {
	function severityOf( property: PropertyDefinition, constraint: string ): Severity {
		return property.constraintSeverities?.[ constraint ] ?? 'warning';
	}

	function changed<T extends PropertyDefinition>( property: T, change: Partial<T> ): T {
		return withInitialSeverities( { ...property, ...change }, activeConstraints( property ), 'error' );
	}

	it( 'gives a Constraint the change makes active the initial severity', () => {
		const property = withInitialSeverities( newNumberProperty( { minimum: 0 } ), [], 'error' );

		expect( severityOf( property, 'minimum' ) ).toBe( 'error' );
	} );

	it( 'leaves a Constraint that was active at the severity it has', () => {
		const property = withInitialSeverities( newNumberProperty( { minimum: 5 } ), [ 'minimum' ], 'error' );

		expect( severityOf( property, 'minimum' ) ).toBe( 'warning' );
	} );

	it( 'changes nothing on a wiki that gives new Constraints a warning', () => {
		const property = newDateProperty( { minimum: '1990' } );

		expect( withInitialSeverities( property, [], 'warning' ) ).toEqual( property );
	} );

	it( 'brings the single value back at the severity it had once multiple values are allowed and disallowed again', () => {
		const singleAgain = changed( changed( newSelectProperty( { multiple: false } ), { multiple: true } ), { multiple: false } );

		expect( severityOf( singleAgain, 'multiple' ) ).toBe( 'warning' );
	} );

	it( 'brings an emptied bound back at the severity it had once a bound is entered again', () => {
		const boundedAgain = changed( changed( newNumberProperty( { minimum: 5 } ), { minimum: undefined } ), { minimum: 7 } );

		expect( severityOf( boundedAgain, 'minimum' ) ).toBe( 'warning' );
	} );

	it( 'keeps the error of an emptied bound once a bound is entered again', () => {
		const bounded = { ...newNumberProperty( { minimum: 5 } ), constraintSeverities: { minimum: 'error' as const } };
		const boundedAgain = changed( changed( bounded, { minimum: undefined } ), { minimum: 7 } );

		expect( severityOf( boundedAgain, 'minimum' ) ).toBe( 'error' );
	} );

	it( 'gives a Constraint unticked and ticked again the initial severity', () => {
		const ticked = changed( changed( newNumberProperty( { required: true } ), { required: false } ), { required: true } );

		expect( severityOf( ticked, 'required' ) ).toBe( 'error' );
	} );

	it( 'writes out the full date a date property asks for when its precision gets a severity', () => {
		const property = withInitialSeverities( newDateProperty(), [], 'error' );

		expect( property ).toMatchObject( { minPrecision: 'day', constraintSeverities: { minPrecision: 'error' } } );
	} );

	it( 'keeps the minimum detail a date property asks for when its precision gets a severity', () => {
		const property = changed( newDateProperty( { minPrecision: 'year' } ), { minPrecision: 'month' } );

		expect( property ).toMatchObject( { minPrecision: 'month', constraintSeverities: { minPrecision: 'error' } } );
	} );
} );
