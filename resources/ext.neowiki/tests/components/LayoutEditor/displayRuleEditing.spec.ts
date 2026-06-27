import { describe, it, expect } from 'vitest';
import { unifiedRows, rulesAfterToggle, rulesAfterShowingAll, rulesAfterReorder } from '@/components/LayoutEditor/displayRuleEditing.ts';
import type { PropertyDefinition } from '@/domain/PropertyDefinition.ts';
import { PropertyName } from '@/domain/PropertyDefinition.ts';
import type { DisplayRule } from '@/domain/Layout.ts';
import { newTextProperty } from '@/domain/propertyTypes/Text.ts';

function properties( ...names: string[] ): PropertyDefinition[] {
	return names.map( ( name ) => newTextProperty( { name } ) );
}

function rules( ...names: string[] ): DisplayRule[] {
	return names.map( ( name ) => ( { property: new PropertyName( name ) } ) );
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

	it( 'shows every property in schema order in the default state', () => {
		const schemaProperties = properties( 'Alpha', 'Beta', 'Gamma', 'Delta' );

		expect( rowState( schemaProperties, [] ) ).toEqual( [
			[ 'Alpha', true ],
			[ 'Beta', true ],
			[ 'Gamma', true ],
			[ 'Delta', true ],
		] );
	} );

	it( 'lists shown properties in rule order followed by hidden ones in schema order', () => {
		const schemaProperties = properties( 'Alpha', 'Beta', 'Gamma', 'Delta' );

		expect( rowState( schemaProperties, rules( 'Gamma', 'Beta' ) ) ).toEqual( [
			[ 'Gamma', true ],
			[ 'Beta', true ],
			[ 'Alpha', false ],
			[ 'Delta', false ],
		] );
	} );

	it( 'skips rules referencing unknown properties', () => {
		const schemaProperties = properties( 'Alpha', 'Beta', 'Gamma' );

		expect( rowState( schemaProperties, rules( 'Beta', 'Unknown' ) ) ).toEqual( [
			[ 'Beta', true ],
			[ 'Alpha', false ],
			[ 'Gamma', false ],
		] );
	} );

} );

describe( 'rulesAfterToggle', () => {

	it( 'materialises the other properties in schema order when hiding from the default state', () => {
		const schemaProperties = properties( 'Alpha', 'Beta', 'Gamma', 'Delta' );

		const result = rulesAfterToggle( schemaProperties, [], 'Gamma' );

		expect( ruleNames( result ) ).toEqual( [ 'Alpha', 'Beta', 'Delta' ] );
	} );

	it( 'appends a rule when showing a hidden property in a custom state', () => {
		const schemaProperties = properties( 'Alpha', 'Beta', 'Gamma', 'Delta' );

		const result = rulesAfterToggle( schemaProperties, rules( 'Beta' ), 'Gamma' );

		expect( ruleNames( result ) ).toEqual( [ 'Beta', 'Gamma' ] );
	} );

	it( 'removes the rule when hiding a shown property in a custom state', () => {
		const schemaProperties = properties( 'Alpha', 'Beta', 'Gamma', 'Delta' );

		const result = rulesAfterToggle( schemaProperties, rules( 'Alpha', 'Beta', 'Gamma' ), 'Beta' );

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

		const result = rulesAfterShowingAll( schemaProperties, rules( 'Gamma', 'Beta' ) );

		expect( ruleNames( result ) ).toEqual( [ 'Gamma', 'Beta', 'Alpha', 'Delta' ] );
	} );

	it( 'leaves the order unchanged when every property is already shown', () => {
		const schemaProperties = properties( 'Alpha', 'Beta', 'Gamma', 'Delta' );

		const result = rulesAfterShowingAll( schemaProperties, rules( 'Delta', 'Gamma', 'Beta', 'Alpha' ) );

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

	it( 'materialises all properties in the new order when reordering from the default state', () => {
		const schemaProperties = properties( 'Alpha', 'Beta', 'Gamma', 'Delta' );

		const result = rulesAfterReorder( schemaProperties, [], 2, 0 );

		expect( ruleNames( result ) ).toEqual( [ 'Gamma', 'Alpha', 'Beta', 'Delta' ] );
	} );

	it( 'reorders the shown rules in a custom state, leaving hidden properties out', () => {
		const schemaProperties = properties( 'Alpha', 'Beta', 'Gamma', 'Delta' );

		const result = rulesAfterReorder( schemaProperties, rules( 'Alpha', 'Beta', 'Gamma' ), 0, 2 );

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
