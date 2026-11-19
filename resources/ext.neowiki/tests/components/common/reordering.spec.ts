import { beforeEach, describe, expect, it } from 'vitest';
import { positionAfter, reorderMenuItems, withItemMoved } from '@/components/common/reordering.ts';
import { REORDER_MESSAGES, setupMwMock } from '../../VueTestHelpers.ts';

describe( 'reordering', () => {

	beforeEach( () => {
		setupMwMock( { messages: REORDER_MESSAGES } );
	} );

	describe( 'positionAfter', () => {

		it( 'takes an item to the top', () => {
			expect( positionAfter( 'move-to-top', 2, 5 ) ).toBe( 0 );
		} );

		it( 'takes an item one place up', () => {
			expect( positionAfter( 'move-up', 2, 5 ) ).toBe( 1 );
		} );

		it( 'takes an item one place down', () => {
			expect( positionAfter( 'move-down', 2, 5 ) ).toBe( 3 );
		} );

		it( 'takes an item to the bottom', () => {
			expect( positionAfter( 'move-to-bottom', 2, 5 ) ).toBe( 4 );
		} );

		it( 'leaves the first item where it is when it moves up', () => {
			expect( positionAfter( 'move-up', 0, 5 ) ).toBe( 0 );
		} );

		it( 'leaves the last item where it is when it moves down', () => {
			expect( positionAfter( 'move-down', 4, 5 ) ).toBe( 4 );
		} );

	} );

	describe( 'reorderMenuItems', () => {

		function enabledLabels( atTop: boolean, atBottom: boolean ): string[] {
			return reorderMenuItems( atTop, atBottom )
				.filter( ( item ) => item.disabled !== true )
				.map( ( item ) => item.label! );
		}

		it( 'offers every move to an item in the middle', () => {
			expect( enabledLabels( false, false ) ).toEqual( [ 'Move to top', 'Move up', 'Move down', 'Move to bottom' ] );
		} );

		it( 'offers the first item only the moves down', () => {
			expect( enabledLabels( true, false ) ).toEqual( [ 'Move down', 'Move to bottom' ] );
		} );

		it( 'offers the last item only the moves up', () => {
			expect( enabledLabels( false, true ) ).toEqual( [ 'Move to top', 'Move up' ] );
		} );

		it( 'lists the moves disabled rather than leaving them out, so they keep their places', () => {
			expect( reorderMenuItems( true, false ).map( ( item ) => item.label ) )
				.toEqual( [ 'Move to top', 'Move up', 'Move down', 'Move to bottom' ] );
		} );

	} );

	describe( 'withItemMoved', () => {

		it( 'moves an item up, shifting the ones it passes down', () => {
			expect( withItemMoved( [ 'a', 'b', 'c', 'd', 'e' ], 3, 1 ) ).toEqual( [ 'a', 'd', 'b', 'c', 'e' ] );
		} );

		it( 'moves an item down, shifting the ones it passes up', () => {
			expect( withItemMoved( [ 'a', 'b', 'c', 'd', 'e' ], 1, 3 ) ).toEqual( [ 'a', 'c', 'd', 'b', 'e' ] );
		} );

		it( 'leaves the list it was given as it was', () => {
			const items = [ 'a', 'b', 'c' ];

			withItemMoved( items, 0, 2 );

			expect( items ).toEqual( [ 'a', 'b', 'c' ] );
		} );

	} );

} );
