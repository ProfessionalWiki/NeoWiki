import { mount, VueWrapper } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import DisplayRuleList from '@/components/LayoutEditor/DisplayRuleList.vue';
import type { PropertyDefinition } from '@/domain/PropertyDefinition.ts';
import type { DisplayRule } from '@/domain/Layout.ts';
import { newTextProperty } from '@/domain/propertyTypes/Text.ts';
import { newDisplayRules } from '@/TestHelpers';
import { createI18nMock } from '../../VueTestHelpers.ts';

vi.mock( 'sortablejs', () => ( {
	default: {
		create: vi.fn( () => ( { destroy: vi.fn() } ) ),
	},
} ) );

const HIDDEN_CLASS = 'ext-neowiki-display-rule-list__item--hidden';

function property( name: string ): PropertyDefinition {
	return newTextProperty( { name } );
}

function createWrapper( schemaProperties: PropertyDefinition[], displayRules: DisplayRule[] ): VueWrapper {
	return mount( DisplayRuleList, {
		props: {
			schemaProperties,
			displayRules,
		},
		global: {
			mocks: {
				$i18n: createI18nMock(),
			},
		},
	} );
}

function emittedRuleNames( wrapper: VueWrapper ): string[] {
	const emitted = wrapper.emitted( 'update:display-rules' );
	const rule = emitted![ 0 ][ 0 ] as DisplayRule[];
	return rule.map( ( r ) => r.property.toString() );
}

describe( 'DisplayRuleList', () => {

	const schemaProperties = [ property( 'Alpha' ), property( 'Beta' ), property( 'Gamma' ), property( 'Delta' ) ];

	it( 'renders every schema property as a row, all shown in the default state', () => {
		const wrapper = createWrapper( schemaProperties, [] );
		const rows = wrapper.findAll( '.ext-neowiki-display-rule-list__item' );

		expect( rows.map( ( row ) => row.text() ) ).toEqual( [ 'Alpha', 'Beta', 'Gamma', 'Delta' ] );
		expect( rows.some( ( row ) => row.classes().includes( HIDDEN_CLASS ) ) ).toBe( false );
	} );

	it( 'orders shown rows by rule order then hidden rows by schema order, flagging hidden ones', () => {
		const wrapper = createWrapper( schemaProperties, newDisplayRules( 'Gamma', 'Beta' ) );
		const rows = wrapper.findAll( '.ext-neowiki-display-rule-list__item' );

		expect( rows.map( ( row ) => row.text() ) ).toEqual( [ 'Gamma', 'Beta', 'Alpha', 'Delta' ] );
		expect( rows.map( ( row ) => row.classes().includes( HIDDEN_CLASS ) ) ).toEqual(
			[ false, false, true, true ],
		);
	} );

	it( 'materialises the other properties when hiding one from the default state', async () => {
		const wrapper = createWrapper( schemaProperties, [] );
		const toggles = wrapper.findAll( '.ext-neowiki-display-rule-list__item__action' );

		await toggles[ 2 ].trigger( 'click' );

		expect( emittedRuleNames( wrapper ) ).toEqual( [ 'Alpha', 'Beta', 'Delta' ] );
	} );

	it( 'appends a rule when showing a hidden property', async () => {
		const wrapper = createWrapper( schemaProperties, newDisplayRules( 'Beta' ) );
		const rows = wrapper.findAll( '.ext-neowiki-display-rule-list__item' );
		const gammaRowIndex = rows.findIndex( ( row ) => row.text() === 'Gamma' );
		const toggles = wrapper.findAll( '.ext-neowiki-display-rule-list__item__action' );

		await toggles[ gammaRowIndex ].trigger( 'click' );

		expect( emittedRuleNames( wrapper ) ).toEqual( [ 'Beta', 'Gamma' ] );
	} );

	it( 'removes a rule when hiding a shown property in a custom state', async () => {
		const wrapper = createWrapper( schemaProperties, newDisplayRules( 'Alpha', 'Beta', 'Gamma' ) );
		const toggles = wrapper.findAll( '.ext-neowiki-display-rule-list__item__action' );

		await toggles[ 1 ].trigger( 'click' );

		expect( emittedRuleNames( wrapper ) ).toEqual( [ 'Alpha', 'Gamma' ] );
	} );

	it( 'reveals hidden properties in schema order while preserving the shown order when "show all" is used', async () => {
		const wrapper = createWrapper( schemaProperties, newDisplayRules( 'Gamma', 'Beta' ) );

		await wrapper.find( '.ext-neowiki-display-rule-list__reset' ).trigger( 'click' );

		expect( emittedRuleNames( wrapper ) ).toEqual( [ 'Gamma', 'Beta', 'Alpha', 'Delta' ] );
	} );

	it( 'shows the "show all" action when a property is hidden', () => {
		const wrapper = createWrapper( schemaProperties, newDisplayRules( 'Beta' ) );

		expect( wrapper.find( '.ext-neowiki-display-rule-list__reset' ).exists() ).toBe( true );
	} );

	it( 'hides the "show all" action in the default state', () => {
		const wrapper = createWrapper( schemaProperties, [] );

		expect( wrapper.find( '.ext-neowiki-display-rule-list__reset' ).exists() ).toBe( false );
	} );

	it( 'hides the "show all" action when every property is shown in a custom order', () => {
		const wrapper = createWrapper( schemaProperties, newDisplayRules( 'Delta', 'Gamma', 'Beta', 'Alpha' ) );

		expect( wrapper.find( '.ext-neowiki-display-rule-list__reset' ).exists() ).toBe( false );
	} );

	it( 'renders a drag handle only on shown rows, not on hidden ones', () => {
		const wrapper = createWrapper( schemaProperties, newDisplayRules( 'Gamma', 'Beta' ) );
		const rows = wrapper.findAll( '.ext-neowiki-display-rule-list__item' );

		const hasHandle = rows.map(
			( row ) => row.find( '.ext-neowiki-display-rule-list__item__drag-handle' ).exists(),
		);

		expect( hasHandle ).toEqual( [ true, true, false, false ] );
	} );

	it( 'gives each toggle a tooltip naming the action it performs', () => {
		const wrapper = createWrapper( schemaProperties, newDisplayRules( 'Alpha', 'Beta' ) );
		const tooltips = wrapper.findAll( '.ext-neowiki-display-rule-list__item__action-tooltip' );

		expect( tooltips[ 0 ].attributes( 'title' ) ).toBe( 'neowiki-layout-editor-hide-property' );
		expect( tooltips[ 2 ].attributes( 'title' ) ).toBe( 'neowiki-layout-editor-show-property' );
	} );

	it( 'disables the hide toggle of the only shown property', () => {
		const wrapper = createWrapper( schemaProperties, newDisplayRules( 'Beta' ) );
		const toggles = wrapper.findAll( '.ext-neowiki-display-rule-list__item__action' );

		expect( toggles[ 0 ].attributes( 'disabled' ) ).toBeDefined();
		expect( toggles[ 1 ].attributes( 'disabled' ) ).toBeUndefined();
	} );

	it( 'keeps hide toggles enabled when more than one property is shown', () => {
		const wrapper = createWrapper( schemaProperties, newDisplayRules( 'Alpha', 'Beta' ) );
		const toggles = wrapper.findAll( '.ext-neowiki-display-rule-list__item__action' );

		expect( toggles[ 0 ].attributes( 'disabled' ) ).toBeUndefined();
		expect( toggles[ 1 ].attributes( 'disabled' ) ).toBeUndefined();
	} );

	it( 'puts the keep-one-shown tooltip on a non-disabled wrapper, not the disabled button', () => {
		const wrapper = createWrapper( schemaProperties, newDisplayRules( 'Beta' ) );
		const button = wrapper.find( '.ext-neowiki-display-rule-list__item__action' );
		const tooltip = wrapper.find( '.ext-neowiki-display-rule-list__item__action-tooltip' );

		expect( button.attributes( 'disabled' ) ).toBeDefined();
		expect( button.attributes( 'title' ) ).toBeUndefined();
		expect( tooltip.attributes( 'title' ) ).toBe( 'neowiki-layout-editor-keep-one-shown' );
	} );

} );
