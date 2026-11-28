import { describe, it, expect } from 'vitest';
import {
	unifiedRows,
	rulesAfterToggle,
	rulesAfterShowingAll,
	rulesAfterReorder,
	rulesInEffect,
	rulesAfterShowingAt,
	rulesAfterSettingDisplayAttributes,
	attributesAfterPatch,
} from '@/components/LayoutEditor/displayRuleEditing.ts';
import type { PropertyDefinition } from '@/domain/PropertyDefinition.ts';
import { PropertyName } from '@/domain/PropertyDefinition.ts';
import type { DisplayRule } from '@/domain/Layout.ts';
import { newTextProperty } from '@/domain/propertyTypes/Text.ts';
import { newDisplayRules } from '@/TestHelpers';

function properties( ...names: string[] ): PropertyDefinition[] {
	return names.map( ( name ) => newTextProperty( { name } ) );
}

function rowState( schemaProperties: PropertyDefinition[], displayRules: DisplayRule[] ): [ string, boolean ][] {
	return unifiedRows( schemaProperties, displayRules ).map(
		( row ) => [ row.property.name.toString(), row.shown ],
	);
}

function ruleNames( displayRules: DisplayRule[] ): string[] {
	return displayRules.map( ( rule ) => rule.property.toString() );
}

describe( 'unifiedRows', () => {

	it( 'lists shown properties in rule order followed by hidden ones in schema order', () => {
		const schemaProperties = properties( 'Alpha', 'Beta', 'Gamma', 'Delta' );

		expect( rowState( schemaProperties, newDisplayRules( 'Gamma', 'Beta' ) ) ).toEqual( [
			[ 'Gamma', true ],
			[ 'Beta', true ],
			[ 'Alpha', false ],
			[ 'Delta', false ],
		] );
	} );

	it( 'skips rules referencing unknown properties', () => {
		const schemaProperties = properties( 'Alpha', 'Beta', 'Gamma' );

		expect( rowState( schemaProperties, newDisplayRules( 'Beta', 'Unknown' ) ) ).toEqual( [
			[ 'Beta', true ],
			[ 'Alpha', false ],
			[ 'Gamma', false ],
		] );
	} );

} );

describe( 'rulesAfterToggle', () => {

	it( 'appends a rule when showing a hidden property in a custom state', () => {
		const schemaProperties = properties( 'Alpha', 'Beta', 'Gamma', 'Delta' );

		const result = rulesAfterToggle( schemaProperties, newDisplayRules( 'Beta' ), 'Gamma' );

		expect( ruleNames( result ) ).toEqual( [ 'Beta', 'Gamma' ] );
	} );

	it( 'removes the rule when hiding a shown property in a custom state', () => {
		const schemaProperties = properties( 'Alpha', 'Beta', 'Gamma', 'Delta' );

		const result = rulesAfterToggle( schemaProperties, newDisplayRules( 'Alpha', 'Beta', 'Gamma' ), 'Beta' );

		expect( ruleNames( result ) ).toEqual( [ 'Alpha', 'Gamma' ] );
	} );

	it( 'preserves display attributes of other rules when hiding one', () => {
		const schemaProperties = properties( 'Alpha', 'Beta', 'Gamma' );
		const displayRules: DisplayRule[] = [
			{ property: new PropertyName( 'Alpha' ), displayAttributes: { precision: 2 } },
			{ property: new PropertyName( 'Beta' ) },
			{ property: new PropertyName( 'Gamma' ) },
		];

		const result = rulesAfterToggle( schemaProperties, displayRules, 'Beta' );

		expect( ruleNames( result ) ).toEqual( [ 'Alpha', 'Gamma' ] );
		expect( result[ 0 ].displayAttributes ).toEqual( { precision: 2 } );
	} );

} );

describe( 'rulesAfterShowingAll', () => {

	it( 'appends hidden properties in schema order while preserving the shown order', () => {
		const schemaProperties = properties( 'Alpha', 'Beta', 'Gamma', 'Delta' );

		const result = rulesAfterShowingAll( schemaProperties, newDisplayRules( 'Gamma', 'Beta' ) );

		expect( ruleNames( result ) ).toEqual( [ 'Gamma', 'Beta', 'Alpha', 'Delta' ] );
	} );

	it( 'leaves the order unchanged when every property is already shown', () => {
		const schemaProperties = properties( 'Alpha', 'Beta', 'Gamma', 'Delta' );

		const result = rulesAfterShowingAll( schemaProperties, newDisplayRules( 'Delta', 'Gamma', 'Beta', 'Alpha' ) );

		expect( ruleNames( result ) ).toEqual( [ 'Delta', 'Gamma', 'Beta', 'Alpha' ] );
	} );

	it( 'preserves display attributes of already-shown rules', () => {
		const schemaProperties = properties( 'Alpha', 'Beta', 'Gamma' );
		const displayRules: DisplayRule[] = [
			{ property: new PropertyName( 'Beta' ), displayAttributes: { precision: 2 } },
		];

		const result = rulesAfterShowingAll( schemaProperties, displayRules );

		expect( ruleNames( result ) ).toEqual( [ 'Beta', 'Alpha', 'Gamma' ] );
		expect( result[ 0 ].displayAttributes ).toEqual( { precision: 2 } );
	} );

} );

describe( 'rulesAfterReorder', () => {

	it( 'reorders the shown rules in a custom state, leaving hidden properties out', () => {
		const schemaProperties = properties( 'Alpha', 'Beta', 'Gamma', 'Delta' );

		const result = rulesAfterReorder( schemaProperties, newDisplayRules( 'Alpha', 'Beta', 'Gamma' ), 0, 2 );

		expect( ruleNames( result ) ).toEqual( [ 'Beta', 'Gamma', 'Alpha' ] );
	} );

	it( 'preserves display attributes when reordering', () => {
		const schemaProperties = properties( 'Alpha', 'Beta', 'Gamma' );
		const displayRules: DisplayRule[] = [
			{ property: new PropertyName( 'Alpha' ), displayAttributes: { precision: 2 } },
			{ property: new PropertyName( 'Beta' ) },
			{ property: new PropertyName( 'Gamma' ) },
		];

		const result = rulesAfterReorder( schemaProperties, displayRules, 0, 2 );

		expect( ruleNames( result ) ).toEqual( [ 'Beta', 'Gamma', 'Alpha' ] );
		expect( result[ 2 ].displayAttributes ).toEqual( { precision: 2 } );
	} );

} );

describe( 'rulesInEffect', () => {

	it( 'is the Display Rules a Layout has', () => {
		const displayRules = newDisplayRules( 'Gamma', 'Alpha' );

		expect( rulesInEffect( properties( 'Alpha', 'Beta', 'Gamma' ), displayRules, [] ) ).toEqual( displayRules );
	} );

	it( 'shows every property in Schema order for a Layout without Display Rules, but those left out', () => {
		const result = rulesInEffect( properties( 'Alpha', 'Beta', 'Gamma' ), [], [ new PropertyName( 'Beta' ) ] );

		expect( ruleNames( result ) ).toEqual( [ 'Alpha', 'Gamma' ] );
	} );

} );

describe( 'rulesAfterSettingDisplayAttributes', () => {

	it( 'gives the named property\'s rule the Display Attributes, leaving the other rules as they are', () => {
		const displayRules: DisplayRule[] = [
			{ property: new PropertyName( 'Alpha' ), displayAttributes: { precision: 1 } },
			{ property: new PropertyName( 'Beta' ) },
			{ property: new PropertyName( 'Gamma' ) },
		];

		const result = rulesAfterSettingDisplayAttributes( displayRules, 'Beta', { precision: 3 } );

		expect( result ).toEqual( [
			{ property: new PropertyName( 'Alpha' ), displayAttributes: { precision: 1 } },
			{ property: new PropertyName( 'Beta' ), displayAttributes: { precision: 3 } },
			{ property: new PropertyName( 'Gamma' ) },
		] );
	} );

	it( 'leaves a rule without Display Attributes when none are left to set', () => {
		const displayRules: DisplayRule[] = [ { property: new PropertyName( 'Alpha' ), displayAttributes: { precision: 1 } } ];

		const result = rulesAfterSettingDisplayAttributes( displayRules, 'Alpha', {} );

		expect( result ).toEqual( [ { property: new PropertyName( 'Alpha' ) } ] );
	} );

} );

describe( 'attributesAfterPatch', () => {

	it( 'sets what the patch sets, keeping what it leaves out', () => {
		expect( attributesAfterPatch( { precision: 1, colour: 'red' }, { precision: 3 } ) ).toEqual( { precision: 3, colour: 'red' } );
	} );

	it( 'drops what the patch takes away, so the Schema\'s applies', () => {
		expect( attributesAfterPatch( { precision: 1, colour: 'red' }, { precision: undefined } ) ).toEqual( { colour: 'red' } );
	} );

} );

describe( 'rulesAfterShowingAt', () => {

	it( 'shows a hidden property at the place given among the shown ones', () => {
		const schemaProperties = properties( 'Alpha', 'Beta', 'Gamma', 'Delta' );

		const result = rulesAfterShowingAt( schemaProperties, newDisplayRules( 'Alpha', 'Gamma', 'Delta' ), 'Beta', 2 );

		expect( ruleNames( result ) ).toEqual( [ 'Alpha', 'Gamma', 'Beta', 'Delta' ] );
	} );

} );
