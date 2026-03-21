import { describe, it, expect } from 'vitest';
import { Layout } from '@/domain/Layout';
import { PropertyName } from '@/domain/PropertyDefinition';

describe( 'Layout', () => {

	it( 'exposes all constructor values via getters', () => {
		const layout = new Layout(
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

		expect( layout.getName() ).toBe( 'FinancialOverview' );
		expect( layout.getSchema() ).toBe( 'Company' );
		expect( layout.getType() ).toBe( 'infobox' );
		expect( layout.getDescription() ).toBe( 'Key financial data' );
		expect( layout.getDisplayRules() ).toHaveLength( 2 );
		expect( layout.getDisplayRules()[ 0 ].property.toString() ).toBe( 'Revenue' );
		expect( layout.getDisplayRules()[ 0 ].displayAttributes ).toEqual( { precision: 0 } );
		expect( layout.getDisplayRules()[ 1 ].displayAttributes ).toBeUndefined();
		expect( layout.getSettings() ).toEqual( { borderColor: '#336699' } );
	} );

} );
