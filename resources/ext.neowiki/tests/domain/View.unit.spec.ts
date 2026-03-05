import { describe, it, expect } from 'vitest';
import { View } from '@/domain/View';
import { PropertyName } from '@/domain/PropertyDefinition';

describe( 'View', () => {

	it( 'exposes all constructor values via getters', () => {
		const view = new View(
			'FinancialOverview',
			'Company',
			'infobox',
			'Key financial data',
			[
				{ property: new PropertyName( 'Revenue' ), displayAttributes: { precision: 0 } },
				{ property: new PropertyName( 'Net Income' ) },
			],
			{ borderColor: '#336699' },
		);

		expect( view.getName() ).toBe( 'FinancialOverview' );
		expect( view.getSchema() ).toBe( 'Company' );
		expect( view.getType() ).toBe( 'infobox' );
		expect( view.getDescription() ).toBe( 'Key financial data' );
		expect( view.getDisplayRules() ).toHaveLength( 2 );
		expect( view.getDisplayRules()[ 0 ].property.toString() ).toBe( 'Revenue' );
		expect( view.getDisplayRules()[ 0 ].displayAttributes ).toEqual( { precision: 0 } );
		expect( view.getDisplayRules()[ 1 ].displayAttributes ).toBeUndefined();
		expect( view.getSettings() ).toEqual( { borderColor: '#336699' } );
	} );

} );
