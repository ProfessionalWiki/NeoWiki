import { mount, VueWrapper } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import DisplayRuleList from '@/components/LayoutEditor/DisplayRuleList.vue';
import { useSortable, type UseSortableOptions } from '@/composables/useSortable.ts';
import { PropertyName, type PropertyDefinition } from '@/domain/PropertyDefinition.ts';
import type { DisplayRule } from '@/domain/Layout.ts';
import { newTextProperty } from '@/domain/propertyTypes/Text.ts';
import { newDisplayRules } from '@/TestHelpers';
import { createI18nMock } from '../../VueTestHelpers.ts';

vi.mock( '@/composables/useSortable.ts', () => ( {
	useSortable: vi.fn(),
} ) );

const HIDDEN_CLASS = 'ext-neowiki-display-rule-list__item--hidden';

function property( name: string ): PropertyDefinition {
	return newTextProperty( { name } );
}

function createWrapper(
	schemaProperties: PropertyDefinition[],
	displayRules: DisplayRule[],
	propertiesTargetingDependentSubjects: PropertyName[],
): VueWrapper {
	return mount( DisplayRuleList, {
		props: {
			schemaProperties,
			displayRules,
			propertiesTargetingDependentSubjects,
		},
		global: {
			mocks: {
				$i18n: createI18nMock(),
			},
		},
	} );
}

// jsdom cannot drag, so a drag is the options the list hands useSortable.
function sortableOptions(): UseSortableOptions {
	const calls = vi.mocked( useSortable ).mock.calls;
	return calls[ calls.length - 1 ][ 1 ];
}

function emittedRuleNames( wrapper: VueWrapper ): string[] {
	const emitted = wrapper.emitted( 'update:display-rules' );
	const rule = emitted![ 0 ][ 0 ] as DisplayRule[];
	return rule.map( ( r ) => r.property.toString() );
}

describe( 'DisplayRuleList', () => {

	const schemaProperties = [ property( 'Alpha' ), property( 'Beta' ), property( 'Gamma' ), property( 'Delta' ) ];

	it( 'renders every schema property as a row, all shown in the default state', () => {
		const wrapper = createWrapper( schemaProperties, [], [] );
		const rows = wrapper.findAll( '.ext-neowiki-display-rule-list__item' );

		expect( rows.map( ( row ) => row.text() ) ).toEqual( [ 'Alpha', 'Beta', 'Gamma', 'Delta' ] );
		expect( rows.some( ( row ) => row.classes().includes( HIDDEN_CLASS ) ) ).toBe( false );
	} );

	it( 'orders shown rows by rule order then hidden rows by schema order, flagging hidden ones', () => {
		const wrapper = createWrapper( schemaProperties, newDisplayRules( 'Gamma', 'Beta' ), [] );
		const rows = wrapper.findAll( '.ext-neowiki-display-rule-list__item' );

		expect( rows.map( ( row ) => row.text() ) ).toEqual( [ 'Gamma', 'Beta', 'Alpha', 'Delta' ] );
		expect( rows.map( ( row ) => row.classes().includes( HIDDEN_CLASS ) ) ).toEqual(
			[ false, false, true, true ],
		);
	} );

	it( 'materialises the other properties when hiding one from the default state', async () => {
		const wrapper = createWrapper( schemaProperties, [], [] );
		const toggles = wrapper.findAll( '.ext-neowiki-display-rule-list__item__action' );

		await toggles[ 2 ].trigger( 'click' );

		expect( emittedRuleNames( wrapper ) ).toEqual( [ 'Alpha', 'Beta', 'Delta' ] );
	} );

	it( 'appends a rule when showing a hidden property', async () => {
		const wrapper = createWrapper( schemaProperties, newDisplayRules( 'Beta' ), [] );
		const rows = wrapper.findAll( '.ext-neowiki-display-rule-list__item' );
		const gammaRowIndex = rows.findIndex( ( row ) => row.text() === 'Gamma' );
		const toggles = wrapper.findAll( '.ext-neowiki-display-rule-list__item__action' );

		await toggles[ gammaRowIndex ].trigger( 'click' );

		expect( emittedRuleNames( wrapper ) ).toEqual( [ 'Beta', 'Gamma' ] );
	} );

	it( 'removes a rule when hiding a shown property in a custom state', async () => {
		const wrapper = createWrapper( schemaProperties, newDisplayRules( 'Alpha', 'Beta', 'Gamma' ), [] );
		const toggles = wrapper.findAll( '.ext-neowiki-display-rule-list__item__action' );

		await toggles[ 1 ].trigger( 'click' );

		expect( emittedRuleNames( wrapper ) ).toEqual( [ 'Alpha', 'Gamma' ] );
	} );

	it( 'reveals hidden properties in schema order while preserving the shown order when "show all" is used', async () => {
		const wrapper = createWrapper( schemaProperties, newDisplayRules( 'Gamma', 'Beta' ), [] );

		await wrapper.find( '.ext-neowiki-display-rule-list__reset' ).trigger( 'click' );

		expect( emittedRuleNames( wrapper ) ).toEqual( [ 'Gamma', 'Beta', 'Alpha', 'Delta' ] );
	} );

	it( 'shows the "show all" action when a property is hidden', () => {
		const wrapper = createWrapper( schemaProperties, newDisplayRules( 'Beta' ), [] );

		expect( wrapper.find( '.ext-neowiki-display-rule-list__reset' ).exists() ).toBe( true );
	} );

	it( 'hides the "show all" action in the default state', () => {
		const wrapper = createWrapper( schemaProperties, [], [] );

		expect( wrapper.find( '.ext-neowiki-display-rule-list__reset' ).exists() ).toBe( false );
	} );

	it( 'hides the "show all" action when every property is shown in a custom order', () => {
		const wrapper = createWrapper( schemaProperties, newDisplayRules( 'Delta', 'Gamma', 'Beta', 'Alpha' ), [] );

		expect( wrapper.find( '.ext-neowiki-display-rule-list__reset' ).exists() ).toBe( false );
	} );

	it( 'renders a drag handle only on shown rows, not on hidden ones', () => {
		const wrapper = createWrapper( schemaProperties, newDisplayRules( 'Gamma', 'Beta' ), [] );
		const rows = wrapper.findAll( '.ext-neowiki-display-rule-list__item' );

		const hasHandle = rows.map(
			( row ) => row.find( '.ext-neowiki-display-rule-list__item__drag-handle' ).exists(),
		);

		expect( hasHandle ).toEqual( [ true, true, false, false ] );
	} );

	it( 'gives each toggle a tooltip naming the action it performs', () => {
		const wrapper = createWrapper( schemaProperties, newDisplayRules( 'Alpha', 'Beta' ), [] );
		const tooltips = wrapper.findAll( '.ext-neowiki-display-rule-list__item__action-tooltip' );

		expect( tooltips[ 0 ].attributes( 'title' ) ).toBe( 'neowiki-layout-editor-hide-property' );
		expect( tooltips[ 2 ].attributes( 'title' ) ).toBe( 'neowiki-layout-editor-show-property' );
	} );

	it( 'disables the hide toggle of the only shown property', () => {
		const wrapper = createWrapper( schemaProperties, newDisplayRules( 'Beta' ), [] );
		const toggles = wrapper.findAll( '.ext-neowiki-display-rule-list__item__action' );

		expect( toggles[ 0 ].attributes( 'disabled' ) ).toBeDefined();
		expect( toggles[ 1 ].attributes( 'disabled' ) ).toBeUndefined();
	} );

	it( 'keeps hide toggles enabled when more than one property is shown', () => {
		const wrapper = createWrapper( schemaProperties, newDisplayRules( 'Alpha', 'Beta' ), [] );
		const toggles = wrapper.findAll( '.ext-neowiki-display-rule-list__item__action' );

		expect( toggles[ 0 ].attributes( 'disabled' ) ).toBeUndefined();
		expect( toggles[ 1 ].attributes( 'disabled' ) ).toBeUndefined();
	} );

	it( 'puts the keep-one-shown tooltip on a non-disabled wrapper, not the disabled button', () => {
		const wrapper = createWrapper( schemaProperties, newDisplayRules( 'Beta' ), [] );
		const button = wrapper.find( '.ext-neowiki-display-rule-list__item__action' );
		const tooltip = wrapper.find( '.ext-neowiki-display-rule-list__item__action-tooltip' );

		expect( button.attributes( 'disabled' ) ).toBeDefined();
		expect( button.attributes( 'title' ) ).toBeUndefined();
		expect( tooltip.attributes( 'title' ) ).toBe( 'neowiki-layout-editor-keep-one-shown' );
	} );

	it( 'names the properties targeting Dependent Subjects as left out while there are no rules', () => {
		const wrapper = createWrapper(
			[ ...schemaProperties, property( 'Attendance' ) ],
			[],
			[ new PropertyName( 'Attendance' ) ],
		);

		expect( wrapper.find( '.ext-neowiki-display-rule-list__header__note' ).text() )
			.toBe( 'neowiki-layout-display-no-rules-dependentAttendance1' );
	} );

	it( 'lists the properties targeting Dependent Subjects hidden in the default state, after the others', () => {
		const wrapper = createWrapper(
			[ property( 'Alpha' ), property( 'Attendance' ), property( 'Beta' ) ],
			[],
			[ new PropertyName( 'Attendance' ) ],
		);
		const rows = wrapper.findAll( '.ext-neowiki-display-rule-list__item' );

		expect( rows.map( ( row ) => row.text() ) ).toEqual( [ 'Alpha', 'Beta', 'Attendance' ] );
		expect( rows.map( ( row ) => row.classes().includes( HIDDEN_CLASS ) ) ).toEqual( [ false, false, true ] );
	} );

	it( 'lists every property hidden in the default state when each targets Dependent Subjects', () => {
		const wrapper = createWrapper(
			[ property( 'Chapters' ), property( 'Editions' ) ],
			[],
			[ new PropertyName( 'Chapters' ), new PropertyName( 'Editions' ) ],
		);
		const rows = wrapper.findAll( '.ext-neowiki-display-rule-list__item' );

		expect( rows.map( ( row ) => row.classes().includes( HIDDEN_CLASS ) ) ).toEqual( [ true, true ] );
	} );

	it( 'keeps the properties targeting Dependent Subjects hidden when hiding another from the default state', async () => {
		const wrapper = createWrapper(
			[ property( 'Alpha' ), property( 'Attendance' ), property( 'Beta' ), property( 'Gamma' ) ],
			[],
			[ new PropertyName( 'Attendance' ) ],
		);
		const rows = wrapper.findAll( '.ext-neowiki-display-rule-list__item' );
		const betaRowIndex = rows.findIndex( ( row ) => row.text() === 'Beta' );

		await wrapper.findAll( '.ext-neowiki-display-rule-list__item__action' )[ betaRowIndex ].trigger( 'click' );

		expect( emittedRuleNames( wrapper ) ).toEqual( [ 'Alpha', 'Gamma' ] );
	} );

	it( 'names a property targeting Dependent Subjects once it is shown from the default state', async () => {
		const wrapper = createWrapper(
			[ property( 'Alpha' ), property( 'Attendance' ), property( 'Beta' ) ],
			[],
			[ new PropertyName( 'Attendance' ) ],
		);
		const rows = wrapper.findAll( '.ext-neowiki-display-rule-list__item' );
		const attendanceRowIndex = rows.findIndex( ( row ) => row.text() === 'Attendance' );

		await wrapper.findAll( '.ext-neowiki-display-rule-list__item__action' )[ attendanceRowIndex ].trigger( 'click' );

		expect( emittedRuleNames( wrapper ) ).toEqual( [ 'Alpha', 'Beta', 'Attendance' ] );
	} );

	it( 'reorders the properties shown in the default state, keeping the properties targeting Dependent Subjects hidden', () => {
		const wrapper = createWrapper(
			[ property( 'Alpha' ), property( 'Attendance' ), property( 'Beta' ) ],
			[],
			[ new PropertyName( 'Attendance' ) ],
		);

		sortableOptions().onReorder!( 1, 0 );

		expect( emittedRuleNames( wrapper ) ).toEqual( [ 'Beta', 'Alpha' ] );
	} );

	it( 'shows every property from the default state after those shown, keeping their order', async () => {
		const wrapper = createWrapper(
			[ property( 'Alpha' ), property( 'Attendance' ), property( 'Beta' ) ],
			[],
			[ new PropertyName( 'Attendance' ) ],
		);

		await wrapper.find( '.ext-neowiki-display-rule-list__reset' ).trigger( 'click' );

		expect( emittedRuleNames( wrapper ) ).toEqual( [ 'Alpha', 'Beta', 'Attendance' ] );
	} );

} );
