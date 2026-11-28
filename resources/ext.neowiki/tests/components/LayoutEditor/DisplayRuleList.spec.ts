import { DOMWrapper, enableAutoUnmount, mount, VueWrapper } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import DisplayRuleList from '@/components/LayoutEditor/DisplayRuleList.vue';
import type { DisplayRuleListExposes, PreviewMarks } from '@/components/LayoutEditor/DisplayRuleList.vue';
import { PropertyName, type PropertyDefinition } from '@/domain/PropertyDefinition.ts';
import type { DisplayRule } from '@/domain/Layout.ts';
import { newTextProperty } from '@/domain/propertyTypes/Text.ts';
import { newDisplayRules } from '@/TestHelpers';
import { chooseReorderMove, createI18nMock, enabledReorderMoves, liveRegionText, REORDER_MESSAGES, setupMwMock, sortableOptionsOf } from '../../VueTestHelpers.ts';
import type { UseSortableOptions } from '@/composables/useSortable.ts';
import type Sortable from 'sortablejs';
import { NeoWikiTestServices } from '../../NeoWikiTestServices.ts';

vi.mock( '@/composables/useSortable.ts', () => ( {
	useSortable: vi.fn(),
} ) );

enableAutoUnmount( afterEach );

const HIDDEN_CLASS = 'ext-neowiki-display-rule-list__item--hidden';

function property( name: string ): PropertyDefinition {
	return newTextProperty( { name } );
}

interface ListProps {
	schemaProperties: PropertyDefinition[];
	displayRules: DisplayRule[];
	propertiesTargetingDependentSubjects: PropertyName[];
	preview: PreviewMarks;
	propertiesWithDisplayAttributes: string[];
	unsavedProperties: string[];
}

const NOTHING_MARKED: PreviewMarks = { hoveredProperty: null, selectedProperty: null };

// Attached to the document only where a test asserts where the focus goes.
function mountList( props: Partial<ListProps>, attachTo?: HTMLElement ): VueWrapper {
	return mount( DisplayRuleList, {
		props: {
			schemaProperties: [],
			displayRules: [],
			propertiesTargetingDependentSubjects: [],
			...props,
		},
		attachTo,
		global: {
			provide: NeoWikiTestServices.getServices(),
			mocks: {
				$i18n: createI18nMock(),
			},
		},
	} );
}

function createWrapper(
	schemaProperties: PropertyDefinition[],
	displayRules: DisplayRule[],
	propertiesTargetingDependentSubjects: PropertyName[],
): VueWrapper {
	return mountList( { schemaProperties, displayRules, propertiesTargetingDependentSubjects } );
}

function rowOf( wrapper: VueWrapper, name: string ): DOMWrapper<Element> {
	const row = wrapper.findAll( '.ext-neowiki-display-rule-list__item' )
		.find( ( item ) => item.find( '.ext-neowiki-display-rule-list__item__name' ).text() === name );
	expect( row ).toBeDefined();
	return row!;
}

function rowName( row: DOMWrapper<Element> ): string {
	return row.find( '.ext-neowiki-display-rule-list__item__name' ).text();
}

// Each group is named for a screen reader by its heading's message.
function group( wrapper: VueWrapper, shownOrHidden: 'shown' | 'hidden' ): DOMWrapper<Element> {
	return wrapper.find( `ul[aria-label="neowiki-layout-editor-${ shownOrHidden }"]` );
}

function sortable( wrapper: VueWrapper, shownOrHidden: 'shown' | 'hidden' ): UseSortableOptions {
	return sortableOptionsOf( group( wrapper, shownOrHidden ).element );
}

function namesInGroup( wrapper: VueWrapper, shownOrHidden: 'shown' | 'hidden' ): string[] {
	return group( wrapper, shownOrHidden ).findAll( '.ext-neowiki-display-rule-list__item' ).map( rowName );
}

function emittedRuleNames( wrapper: VueWrapper ): string[] {
	const emitted = wrapper.emitted( 'update:display-rules' );
	const rule = emitted![ 0 ][ 0 ] as DisplayRule[];
	return rule.map( ( r ) => r.property.toString() );
}

describe( 'DisplayRuleList', () => {

	beforeEach( () => {
		setupMwMock( { messages: REORDER_MESSAGES } );
	} );

	const schemaProperties = [ property( 'Alpha' ), property( 'Beta' ), property( 'Gamma' ), property( 'Delta' ) ];

	it( 'renders every schema property as a row, all shown in the default state', () => {
		const wrapper = createWrapper( schemaProperties, [], [] );
		const rows = wrapper.findAll( '.ext-neowiki-display-rule-list__item' );

		expect( rows.map( rowName ) ).toEqual( [ 'Alpha', 'Beta', 'Gamma', 'Delta' ] );
		expect( rows.some( ( row ) => row.classes().includes( HIDDEN_CLASS ) ) ).toBe( false );
	} );

	it( 'orders shown rows by rule order then hidden rows by schema order, flagging hidden ones', () => {
		const wrapper = createWrapper( schemaProperties, newDisplayRules( 'Gamma', 'Beta' ), [] );
		const rows = wrapper.findAll( '.ext-neowiki-display-rule-list__item' );

		expect( rows.map( rowName ) ).toEqual( [ 'Gamma', 'Beta', 'Alpha', 'Delta' ] );
		expect( rows.map( ( row ) => row.classes().includes( HIDDEN_CLASS ) ) ).toEqual(
			[ false, false, true, true ],
		);
	} );

	it( 'lists the shown properties apart from the hidden ones', () => {
		const wrapper = createWrapper( schemaProperties, newDisplayRules( 'Gamma', 'Beta' ), [] );

		expect( namesInGroup( wrapper, 'shown' ) ).toEqual( [ 'Gamma', 'Beta' ] );
		expect( namesInGroup( wrapper, 'hidden' ) ).toEqual( [ 'Alpha', 'Delta' ] );
	} );

	it( 'heads each group with its name', () => {
		const wrapper = createWrapper( schemaProperties, newDisplayRules( 'Gamma' ), [] );

		expect( wrapper.text() ).toMatch( /^neowiki-layout-editor-shown\s*Gamma.*neowiki-layout-editor-hidden\s*neowiki-layout-editor-show-all\s*Alpha/s );
	} );

	it( 'notes a Layout without Display Rules once, over the shown properties', () => {
		const wrapper = createWrapper( schemaProperties, [], [ new PropertyName( 'Delta' ) ] );

		expect( wrapper.findAll( '.ext-neowiki-display-rule-list__header__note' ) ).toHaveLength( 1 );
	} );

	it( 'has no group of hidden properties while every property is shown', () => {
		const wrapper = createWrapper( schemaProperties, [], [] );

		expect( group( wrapper, 'hidden' ).exists() ).toBe( false );
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
		const gammaRowIndex = rows.findIndex( ( row ) => rowName( row ) === 'Gamma' );
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

	it( 'keeps the hidden properties in the Schema\'s order, sorting the shown ones alone', () => {
		const wrapper = createWrapper( schemaProperties, newDisplayRules( 'Gamma', 'Beta' ), [] );

		expect( sortable( wrapper, 'hidden' ).sort ).toBe( false );
		expect( sortable( wrapper, 'shown' ).sort ).not.toBe( false );
	} );

	it( 'drags hidden properties by a handle of their own', () => {
		const wrapper = createWrapper( schemaProperties, newDisplayRules( 'Gamma', 'Beta' ), [] );

		expect( rowOf( wrapper, 'Alpha' ).find( sortable( wrapper, 'hidden' ).handle! ).exists() ).toBe( true );
	} );

	it( 'shows a hidden property dropped among the shown ones, where it is dropped', () => {
		const wrapper = createWrapper( schemaProperties, newDisplayRules( 'Alpha', 'Gamma' ), [] );

		sortable( wrapper, 'shown' ).onDropIn!( rowOf( wrapper, 'Beta' ).element as HTMLElement, 0, 1 );

		expect( emittedRuleNames( wrapper ) ).toEqual( [ 'Alpha', 'Beta', 'Gamma' ] );
	} );

	it( 'hides a shown property dropped among the hidden ones', () => {
		const wrapper = createWrapper( schemaProperties, newDisplayRules( 'Alpha', 'Gamma' ), [] );

		sortable( wrapper, 'hidden' ).onDropIn!( rowOf( wrapper, 'Alpha' ).element as HTMLElement, 0, 0 );

		expect( emittedRuleNames( wrapper ) ).toEqual( [ 'Gamma' ] );
	} );

	it( 'takes the only shown property among the hidden ones no more than its eye hides it', () => {
		const lastShown = createWrapper( schemaProperties, newDisplayRules( 'Alpha' ), [] );
		const oneOfTwo = createWrapper( schemaProperties, newDisplayRules( 'Alpha', 'Gamma' ), [] );

		const takes = ( wrapper: VueWrapper ): unknown =>
			( ( sortable( wrapper, 'hidden' ).group as Sortable.GroupOptions ).put as () => boolean )();

		expect( [ takes( lastShown ), takes( oneOfTwo ) ] ).toEqual( [ false, true ] );
	} );

	it( 'reports the order a hidden property dragged among the shown ones would give', () => {
		const wrapper = createWrapper( schemaProperties, newDisplayRules( 'Alpha', 'Gamma' ), [] );

		sortable( wrapper, 'shown' ).onMoveIn!( rowOf( wrapper, 'Beta' ).element as HTMLElement, 1 );

		const reported = wrapper.emitted( 'reordering' ) as [ { rules: DisplayRule[]; property: string } ][];
		expect( reported[ 0 ][ 0 ].rules.map( ( rule ) => rule.property.toString() ) ).toEqual( [ 'Alpha', 'Beta', 'Gamma' ] );
		expect( reported[ 0 ][ 0 ].property ).toBe( 'Beta' );
	} );

	it( 'reports the order a shown property dragged among the hidden ones would leave', () => {
		const wrapper = createWrapper( schemaProperties, newDisplayRules( 'Alpha', 'Gamma' ), [] );

		sortable( wrapper, 'hidden' ).onMoveIn!( rowOf( wrapper, 'Alpha' ).element as HTMLElement, 0 );

		const reported = wrapper.emitted( 'reordering' ) as [ { rules: DisplayRule[]; property: string } ][];
		expect( reported[ 0 ][ 0 ].rules.map( ( rule ) => rule.property.toString() ) ).toEqual( [ 'Gamma' ] );
	} );

	it( 'renders a drag handle only on shown rows, not on hidden ones', () => {
		const wrapper = createWrapper( schemaProperties, newDisplayRules( 'Gamma', 'Beta' ), [] );
		const rows = wrapper.findAll( '.ext-neowiki-display-rule-list__item' );

		const hasHandle = rows.map(
			( row ) => row.find( '.ext-neowiki-reorder-handle' ).exists(),
		);

		expect( hasHandle ).toEqual( [ true, true, false, false ] );
	} );

	it( 'drags shown properties by their handles alone', () => {
		const wrapper = createWrapper( schemaProperties, newDisplayRules( 'Gamma', 'Beta' ), [] );
		const handle = sortable( wrapper, 'shown' ).handle!;

		expect( wrapper.find( 'button[aria-label="Reorder Beta"]' ).element.closest( handle ) ).not.toBeNull();
		expect( wrapper.find( '.ext-neowiki-display-rule-list__item__action' ).element.closest( handle ) ).toBeNull();
	} );

	it( 'moves a shown property with its handle', async () => {
		const wrapper = createWrapper( schemaProperties, newDisplayRules( 'Gamma', 'Beta', 'Alpha' ), [] );

		await chooseReorderMove( wrapper, 'Reorder Alpha', 'Move to top' );

		expect( emittedRuleNames( wrapper ) ).toEqual( [ 'Alpha', 'Gamma', 'Beta' ] );
	} );

	it( 'moves a shown property no further down than the last one shown', async () => {
		const wrapper = createWrapper( schemaProperties, newDisplayRules( 'Gamma', 'Beta' ), [] );

		await wrapper.find( 'button[aria-label="Reorder Beta"]' ).trigger( 'click' );

		expect( enabledReorderMoves( wrapper ) ).toEqual( [ 'Move to top', 'Move up' ] );
	} );

	it( 'tells a screen reader where it moved a property to', async () => {
		const wrapper = createWrapper( schemaProperties, newDisplayRules( 'Gamma', 'Beta', 'Alpha' ), [] );

		await chooseReorderMove( wrapper, 'Reorder Alpha', 'Move up' );

		expect( liveRegionText( wrapper ) ).toBe( 'Alpha moved to position 2 of 3.' );
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

		expect( rows.map( rowName ) ).toEqual( [ 'Alpha', 'Beta', 'Attendance' ] );
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
		const betaRowIndex = rows.findIndex( ( row ) => rowName( row ) === 'Beta' );

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
		const attendanceRowIndex = rows.findIndex( ( row ) => rowName( row ) === 'Attendance' );

		await wrapper.findAll( '.ext-neowiki-display-rule-list__item__action' )[ attendanceRowIndex ].trigger( 'click' );

		expect( emittedRuleNames( wrapper ) ).toEqual( [ 'Alpha', 'Beta', 'Attendance' ] );
	} );

	it( 'reorders the properties shown in the default state, keeping the properties targeting Dependent Subjects hidden', () => {
		const wrapper = createWrapper(
			[ property( 'Alpha' ), property( 'Attendance' ), property( 'Beta' ) ],
			[],
			[ new PropertyName( 'Attendance' ) ],
		);

		sortable( wrapper, 'shown' ).onReorder!( 1, 0 );

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

	it( 'offers nothing to select where there is no preview beside it', () => {
		const wrapper = createWrapper( schemaProperties, [], [] );

		expect( rowOf( wrapper, 'Beta' ).attributes( 'role' ) ).toBe( 'listitem' );
		expect( wrapper.find( '[role="listbox"]' ).exists() ).toBe( false );
	} );

	it( 'reports the order a dragged row would give while it is dragged, and none once the drag is over', () => {
		const wrapper = createWrapper( schemaProperties, [], [] );

		sortable( wrapper, 'shown' ).onMove!( 0, 2 );
		sortable( wrapper, 'shown' ).onDragEnd!();

		const reported = wrapper.emitted( 'reordering' ) as [ { rules: DisplayRule[]; property: string } | null ][];
		expect( reported[ 0 ][ 0 ]!.rules.map( ( rule ) => rule.property.toString() ) ).toEqual( [ 'Beta', 'Gamma', 'Alpha', 'Delta' ] );
		expect( reported[ 0 ][ 0 ]!.property ).toBe( 'Alpha' );
		expect( reported[ 1 ] ).toEqual( [ null ] );
	} );

	describe( 'opening Display Attributes', () => {

		function mountWithDisplayAttributes( displayRules: DisplayRule[] = [] ): VueWrapper {
			return mountList( {
				schemaProperties,
				displayRules,
				preview: NOTHING_MARKED,
				propertiesWithDisplayAttributes: [ 'Beta' ],
			} );
		}

		function opens( row: DOMWrapper<Element> ): boolean {
			return row.find( '.ext-neowiki-editor-list-item__end' ).exists();
		}

		it( 'opens the Display Attributes of a property whose name is clicked', async () => {
			const wrapper = mountWithDisplayAttributes();

			await rowOf( wrapper, 'Beta' ).find( '.ext-neowiki-display-rule-list__item__name' ).trigger( 'click' );

			expect( wrapper.emitted( 'open' ) ).toEqual( [ [ 'Beta' ] ] );
			expect( wrapper.emitted( 'select' ) ).toBeUndefined();
		} );

		it( 'marks the rows that open Display Attributes', () => {
			const wrapper = mountWithDisplayAttributes();

			expect( [ 'Alpha', 'Beta', 'Gamma' ].map( ( name ) => opens( rowOf( wrapper, name ) ) ) ).toEqual( [ false, true, false ] );
		} );

		it( 'neither opens nor selects a property whose row\'s eye is clicked', async () => {
			const wrapper = mountWithDisplayAttributes();

			await rowOf( wrapper, 'Beta' ).find( '.ext-neowiki-display-rule-list__item__action' ).trigger( 'click' );

			expect( wrapper.emitted( 'open' ) ).toBeUndefined();
			expect( wrapper.emitted( 'select' ) ).toBeUndefined();
		} );

		it( 'opens the Display Attributes of the focused property with Enter', async () => {
			const wrapper = mountWithDisplayAttributes();

			await rowOf( wrapper, 'Beta' ).trigger( 'keydown', { key: 'Enter' } );

			expect( wrapper.emitted( 'open' ) ).toEqual( [ [ 'Beta' ] ] );
		} );

		it( 'opens the Display Attributes of the focused property with Space', async () => {
			const wrapper = mountWithDisplayAttributes();

			await rowOf( wrapper, 'Beta' ).trigger( 'keydown', { key: ' ' } );

			expect( wrapper.emitted( 'open' ) ).toEqual( [ [ 'Beta' ] ] );
		} );

		it( 'leaves Enter on a control in a row to that control', async () => {
			const wrapper = mountWithDisplayAttributes();

			await rowOf( wrapper, 'Beta' ).find( '.ext-neowiki-display-rule-list__item__action' ).trigger( 'keydown', { key: 'Enter' } );

			expect( wrapper.emitted( 'open' ) ).toBeUndefined();
			expect( wrapper.emitted( 'select' ) ).toBeUndefined();
		} );

		it( 'opens nothing for a hidden property, which has no Display Rule to hold Display Attributes', async () => {
			const wrapper = mountWithDisplayAttributes( newDisplayRules( 'Alpha' ) );

			await rowOf( wrapper, 'Beta' ).find( '.ext-neowiki-display-rule-list__item__name' ).trigger( 'click' );

			expect( opens( rowOf( wrapper, 'Beta' ) ) ).toBe( false );
			expect( wrapper.emitted( 'open' ) ).toBeUndefined();
		} );

		it( 'marks the shown properties whose Display Attributes have unsaved changes', () => {
			const wrapper = mountList( {
				schemaProperties,
				displayRules: newDisplayRules( 'Alpha', 'Beta' ),
				preview: NOTHING_MARKED,
				unsavedProperties: [ 'Beta', 'Gamma' ],
			} );

			expect( [ 'Alpha', 'Beta', 'Gamma' ].map( ( name ) => rowOf( wrapper, name ).find( '.ext-neowiki-status-mark' ).exists() ) )
				.toEqual( [ false, true, false ] );
		} );

	} );

	describe( 'linked to the preview', () => {

		it( 'selects the property whose name is clicked', async () => {
			const wrapper = mountList( { schemaProperties, preview: NOTHING_MARKED } );

			await rowOf( wrapper, 'Beta' ).find( '.ext-neowiki-display-rule-list__item__name' ).trigger( 'click' );

			expect( wrapper.emitted( 'select' ) ).toEqual( [ [ 'Beta' ] ] );
		} );

		it( 'marks the selected property\'s row as selected', () => {
			const wrapper = mountList( { schemaProperties, preview: { ...NOTHING_MARKED, selectedProperty: 'Gamma' } } );

			expect( rowsWithClass( wrapper, 'ext-neowiki-editor-list-item--selected' ) ).toEqual( [ 'Gamma' ] );
			expect( rowOf( wrapper, 'Gamma' ).attributes( 'aria-selected' ) ).toBe( 'true' );
		} );

		it( 'reports the property pointed at, and none once the pointer leaves its row', async () => {
			const wrapper = mountList( { schemaProperties, preview: NOTHING_MARKED } );

			await rowOf( wrapper, 'Gamma' ).trigger( 'mouseenter' );
			await rowOf( wrapper, 'Gamma' ).trigger( 'mouseleave' );

			expect( wrapper.emitted( 'hover' ) ).toEqual( [ [ 'Gamma' ], [ null ] ] );
		} );

		it( 'marks the row of the property pointed at in the preview', () => {
			const wrapper = mountList( { schemaProperties, preview: { ...NOTHING_MARKED, hoveredProperty: 'Beta' } } );

			expect( rowsWithClass( wrapper, 'ext-neowiki-display-rule-list__item--hovered' ) ).toEqual( [ 'Beta' ] );
		} );

		it( 'focuses the row of the property it is asked to', () => {
			const wrapper = mountList( { schemaProperties, preview: NOTHING_MARKED }, document.body );

			( wrapper.vm as unknown as DisplayRuleListExposes ).focusProperty( 'Gamma' );

			expect( document.activeElement ).toBe( rowOf( wrapper, 'Gamma' ).element );
		} );

		it( 'makes the selected property\'s row the Tab stop of its group', () => {
			const wrapper = mountList( { schemaProperties, preview: { ...NOTHING_MARKED, selectedProperty: 'Gamma' } } );

			expect( [ 'Alpha', 'Beta', 'Gamma' ].map( ( name ) => rowOf( wrapper, name ).attributes( 'tabindex' ) ) )
				.toEqual( [ '-1', '-1', '0' ] );
		} );

		it( 'makes the first row of a group without the selected property its Tab stop', () => {
			const wrapper = mountList( {
				schemaProperties,
				displayRules: newDisplayRules( 'Alpha', 'Beta' ),
				preview: { ...NOTHING_MARKED, selectedProperty: 'Beta' },
			} );

			expect( [ 'Gamma', 'Delta' ].map( ( name ) => rowOf( wrapper, name ).attributes( 'tabindex' ) ) ).toEqual( [ '0', '-1' ] );
		} );

		it( 'selects the next property of its group with the down arrow, with focus on it', async () => {
			const wrapper = mountList( { schemaProperties, preview: { ...NOTHING_MARKED, selectedProperty: 'Beta' } }, document.body );

			await rowOf( wrapper, 'Beta' ).trigger( 'keydown', { key: 'ArrowDown' } );

			expect( wrapper.emitted( 'select' ) ).toEqual( [ [ 'Gamma' ] ] );
			expect( document.activeElement ).toBe( rowOf( wrapper, 'Gamma' ).element );
		} );

		it( 'selects the previous property of its group with the up arrow', async () => {
			const wrapper = mountList( { schemaProperties, preview: { ...NOTHING_MARKED, selectedProperty: 'Beta' } } );

			await rowOf( wrapper, 'Beta' ).trigger( 'keydown', { key: 'ArrowUp' } );

			expect( wrapper.emitted( 'select' ) ).toEqual( [ [ 'Alpha' ] ] );
		} );

		it( 'moves the focused property down with Alt and the down arrow', async () => {
			const wrapper = mountList( { schemaProperties, preview: { ...NOTHING_MARKED, selectedProperty: 'Beta' } } );

			await rowOf( wrapper, 'Beta' ).trigger( 'keydown', { key: 'ArrowDown', altKey: true } );

			expect( emittedRuleNames( wrapper ) ).toEqual( [ 'Alpha', 'Gamma', 'Beta', 'Delta' ] );
			expect( wrapper.emitted( 'select' ) ).toBeUndefined();
		} );

		it( 'moves the focus to a property shown a moment ago', async () => {
			const wrapper = mountList( {
				schemaProperties,
				displayRules: newDisplayRules( 'Alpha' ),
				preview: { ...NOTHING_MARKED, selectedProperty: 'Alpha' },
			}, document.body );
			await wrapper.setProps( { displayRules: newDisplayRules( 'Alpha', 'Beta' ) } );

			await rowOf( wrapper, 'Alpha' ).trigger( 'keydown', { key: 'ArrowDown' } );

			expect( document.activeElement ).toBe( rowOf( wrapper, 'Beta' ).element );
		} );

		it( 'keeps the arrows within a group', async () => {
			const wrapper = mountList( {
				schemaProperties,
				displayRules: newDisplayRules( 'Alpha', 'Beta' ),
				preview: { ...NOTHING_MARKED, selectedProperty: 'Beta' },
			} );

			await rowOf( wrapper, 'Beta' ).trigger( 'keydown', { key: 'ArrowDown' } );

			expect( wrapper.emitted( 'select' ) ).toBeUndefined();
		} );

	} );

} );

function rowsWithClass( wrapper: VueWrapper, className: string ): string[] {
	return wrapper.findAll( `.${ className }` )
		.map( ( row ) => row.find( '.ext-neowiki-display-rule-list__item__name' ).text() );
}
