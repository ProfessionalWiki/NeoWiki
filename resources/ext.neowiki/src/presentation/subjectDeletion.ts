import type { PageIdentifiers } from '@/domain/PageIdentifiers';
import { isSubjectFirst } from '@/presentation/wikiMode';

/**
 * How many Subjects a page holds, answered however cheaply the caller can: a surface listing them
 * knows already, one showing a single Subject has to read them. Asked only where the answer decides
 * something, so a page-first wiki never pays for it.
 */
export type PageSubjectCounter = ( pageId: number ) => Promise<number>;

/**
 * MediaWiki's delete form for the page a Subject is stored on, where deleting that Subject means
 * deleting the page too: on a subject-first wiki the page exists to hold the Subject (ADR 33), so
 * one down to its last Subject goes with it. Null everywhere else, which means delete the Subject
 * and leave the page standing.
 *
 * That form is the confirmation and the permission boundary both — a user without `delete` is
 * refused there, in MediaWiki's own words — which is why nothing here asks about the right.
 *
 * A null page is a Subject whose page the read did not resolve, which this cannot answer for.
 */
export async function pageDeleteFormUrl(
	page: PageIdentifiers | null,
	countSubjectsOnPage: PageSubjectCounter,
): Promise<string | null> {
	if ( !isSubjectFirst() || page === null || page.getPageName() === '' ) {
		return null;
	}

	try {
		if ( await countSubjectsOnPage( page.getPageId() ) !== 1 ) {
			return null;
		}
	} catch ( error ) {
		// A page whose Subjects cannot be read is one this cannot answer for. The Subject's own
		// delete still works, and leaves the page behind.
		console.error( 'Failed to read the page of the Subject being deleted:', error );
		return null;
	}

	return mw.util.getUrl( page.getPageName(), { action: 'delete' } );
}
