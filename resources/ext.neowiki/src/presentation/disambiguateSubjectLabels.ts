import type { SubjectLabelResult } from '@/domain/SubjectLabelSearch.ts';

/**
 * A search result together with what it has to show beyond its label to be told apart from its
 * namesakes. Both facts are on the result already; only whether to show them is decided here.
 */
export interface DisambiguatedSubjectLabel extends SubjectLabelResult {
	showPageTitle: boolean;
	showId: boolean;
}

/**
 * What each result of one search has to show beyond its label. Labels are not unique, and
 * duplicates are ordinary modelling: a museum's yearly attendance figures may all be labelled
 * "Attendance".
 *
 * One rule per tier: show it where it carries information — it is there, and does not merely repeat
 * the tier above — and where the row is not yet unique among those shown. A page repeating its
 * label is what the one-step create flow leaves behind.
 *
 * The rows compared are the ones answered rather than the wiki's, so a namesake beyond that window
 * goes unmarked.
 */
export function disambiguateSubjectLabels( results: SubjectLabelResult[] ): DisambiguatedSubjectLabel[] {
	const rows = results.map( ( result ) => ( {
		result,
		label: asRendered( result.label ),
		pageTitle: asRendered( result.pageTitle ),
	} ) );

	const pagesByLabel = new Map<string, string[]>();

	for ( const row of rows ) {
		const pages = pagesByLabel.get( row.label ) ?? [];

		pages.push( row.pageTitle );
		pagesByLabel.set( row.label, pages );
	}

	return rows.map( ( row ) => {
		const pages = pagesByLabel.get( row.label ) ?? [];

		return {
			...row.result,
			showPageTitle: row.pageTitle !== '' && row.pageTitle !== row.label && pages.length > 1,
			showId: pages.filter( ( page ) => page === row.pageTitle ).length > 1,
		};
	} );
}

// Rows are compared by what a reader sees: HTML collapses runs of whitespace and drops them at the
// ends, and a composed accent draws the same glyph as a decomposed one. Case is left alone, being a
// difference a reader can see. Absent text is tolerated although the type promises a string,
// because the value comes from a REST body; such a row then names no page.
function asRendered( text: string | undefined ): string {
	return ( text ?? '' ).normalize( 'NFC' ).replace( /\s+/g, ' ' ).trim();
}
