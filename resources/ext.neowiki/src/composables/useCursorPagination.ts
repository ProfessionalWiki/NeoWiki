/**
 * Maps CdxTable's offset-based server pagination onto the cursor-based listing endpoints.
 *
 * CdxTable requests pages as ( offset, limit ) pairs, while the Schema/Layout/Mapping listing
 * endpoints page with an opaque cursor (see docs/api/rest-api.md). The map records, under each row
 * offset, the cursor that resumes the listing there. A row offset is limit-independent, and
 * CdxTable only ever requests offsets it has already walked to: navigation moves by the current
 * page size or back to zero, and a page-size change re-requests the current offset with the new
 * limit (so the recorded cursor keeps the served rows aligned with the "X–Y of many" label).
 * Recording each response's nextCursor under the offset it unlocks therefore covers every
 * reachable request. The consuming page keeps the table's total-rows undefined (indeterminate
 * pagination) until a response carries a null cursor, then reports the now-known count — the
 * indeterminate next-button heuristic (a short page) would miss a listing that ends exactly on a
 * page boundary.
 *
 * Known limit: the pager buttons stay clickable while a fetch is in flight, so a second click
 * before the response lands requests an offset with no recorded cursor and falls back to the
 * first page. Accepted — the follow-up response overwrites the rows, and CdxTable offers no way
 * to rewind its internal offset.
 */
interface CursorPagination {
	cursorFor: ( offset: number ) => string | null;
	recordNextCursor: ( offset: number, limit: number, nextCursor: string | null ) => void;
}

export function useCursorPagination(): CursorPagination {
	const cursorByOffset = new Map<number, string | null>( [ [ 0, null ] ] );

	function cursorFor( offset: number ): string | null {
		return cursorByOffset.get( offset ) ?? null;
	}

	function recordNextCursor( offset: number, limit: number, nextCursor: string | null ): void {
		cursorByOffset.set( offset + limit, nextCursor );
	}

	return { cursorFor, recordNextCursor };
}
