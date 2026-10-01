/**
 * Maps CdxTable's offset-based server pagination onto the cursor-based listing endpoints.
 *
 * CdxTable requests pages as ( offset, limit ) pairs, while the listing endpoints page with an opaque cursor (see
 * docs/api/rest-api.md). The map records each response's nextCursor under the row offset it unlocks. A row offset is
 * limit-independent, so a page-size change, which re-requests the current offset with the new limit, keeps the served
 * rows aligned with the "X–Y of many" label. The consuming page keeps the table's total-rows undefined (indeterminate
 * pagination) until a response carries a null cursor, then reports the now-known count — the indeterminate
 * next-button heuristic (a short page) would miss a listing that ends exactly on a page boundary.
 *
 * CdxTable can still ask for an offset no response led to: Previous after a page-size change moves back by the new
 * size, and a second pager click before a response lands moves past the last recorded offset. `hasCursorFor` tells
 * the consumer, which can start the listing again; `cursorFor` gives such an offset the first page's cursor.
 */
interface CursorPagination {
	hasCursorFor: ( offset: number ) => boolean;
	cursorFor: ( offset: number ) => string | null;
	recordNextCursor: ( offset: number, limit: number, nextCursor: string | null ) => void;
	reset: () => void;
}

export function useCursorPagination(): CursorPagination {
	const cursorByOffset = new Map<number, string | null>( [ [ 0, null ] ] );

	function hasCursorFor( offset: number ): boolean {
		return cursorByOffset.has( offset );
	}

	function cursorFor( offset: number ): string | null {
		return cursorByOffset.get( offset ) ?? null;
	}

	function recordNextCursor( offset: number, limit: number, nextCursor: string | null ): void {
		// A null cursor ends the listing, so it leads to no offset; null in the map is the first page's alone.
		if ( nextCursor !== null ) {
			cursorByOffset.set( offset + limit, nextCursor );
		}
	}

	/** Forgets the recorded cursors, for a listing whose filter or order changed. */
	function reset(): void {
		cursorByOffset.clear();
		cursorByOffset.set( 0, null );
	}

	return { hasCursorFor, cursorFor, recordNextCursor, reset };
}
