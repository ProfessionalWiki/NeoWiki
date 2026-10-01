import { describe, it, expect } from 'vitest';
import { useCursorPagination } from '@/composables/useCursorPagination.ts';

describe( 'useCursorPagination', () => {

	it( 'starts the first page without a cursor', () => {
		const { cursorFor } = useCursorPagination();

		expect( cursorFor( 0 ) ).toBeNull();
	} );

	it( 'serves the recorded cursor when the table moves to the next page', () => {
		const { cursorFor, recordNextCursor } = useCursorPagination();

		cursorFor( 0 );
		recordNextCursor( 0, 10, 'cursor-1' );

		expect( cursorFor( 10 ) ).toBe( 'cursor-1' );
	} );

	it( 'serves earlier cursors again when the table moves back', () => {
		const { cursorFor, recordNextCursor } = useCursorPagination();

		cursorFor( 0 );
		recordNextCursor( 0, 10, 'cursor-1' );
		cursorFor( 10 );
		recordNextCursor( 10, 10, 'cursor-2' );

		expect( cursorFor( 0 ) ).toBeNull();
		expect( cursorFor( 10 ) ).toBe( 'cursor-1' );
	} );

	it( 'serves the row-offset cursor across a page-size change', () => {
		const { cursorFor, recordNextCursor } = useCursorPagination();

		cursorFor( 0 );
		recordNextCursor( 0, 10, 'cursor-1' );

		// CdxTable keeps its offset on a page-size change and re-requests ( 10, 20 ). The
		// recorded cursor resumes at row 10 regardless of the limit, so the served rows match
		// the table's "11–30" label.
		expect( cursorFor( 10 ) ).toBe( 'cursor-1' );
		recordNextCursor( 10, 20, 'cursor-2' );

		expect( cursorFor( 30 ) ).toBe( 'cursor-2' );
	} );

	it( 'keeps refetching the current page on the same cursor', () => {
		const { cursorFor, recordNextCursor } = useCursorPagination();

		cursorFor( 0 );
		recordNextCursor( 0, 10, 'cursor-1' );
		cursorFor( 10 );
		recordNextCursor( 10, 10, 'cursor-2' );

		// A refetch after an edit or delete re-requests the same offset.
		expect( cursorFor( 10 ) ).toBe( 'cursor-1' );
	} );

	it( 'knows the cursor of an offset a response led to', () => {
		const { hasCursorFor, recordNextCursor } = useCursorPagination();

		recordNextCursor( 0, 10, 'cursor-1' );

		expect( hasCursorFor( 10 ) ).toBe( true );
	} );

	it( 'knows no cursor for an offset no response led to', () => {
		const { hasCursorFor, recordNextCursor } = useCursorPagination();

		// Previous after a page-size change from 20 to 10 on rows 21–30 asks for row 10.
		recordNextCursor( 0, 20, 'cursor-1' );

		expect( hasCursorFor( 10 ) ).toBe( false );
	} );

	it( 'knows no cursor for the offset past the end of the listing', () => {
		const { hasCursorFor, recordNextCursor } = useCursorPagination();

		recordNextCursor( 0, 10, null );

		expect( hasCursorFor( 10 ) ).toBe( false );
	} );

	it( 'forgets every recorded cursor but the first page\'s when reset', () => {
		const { hasCursorFor, recordNextCursor, reset } = useCursorPagination();

		recordNextCursor( 0, 10, 'cursor-1' );
		reset();

		expect( hasCursorFor( 0 ) ).toBe( true );
		expect( hasCursorFor( 10 ) ).toBe( false );
	} );

} );
