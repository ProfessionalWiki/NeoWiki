import type { PageIdentifiers } from '@/domain/PageIdentifiers';
import type { SubjectRepository } from '@/domain/SubjectRepository';
import { isSubjectFirst } from '@/wikiMode';

/**
 * MediaWiki's delete form for the page a Subject is stored on, where deleting that Subject means
 * deleting the page too: on a subject-first wiki the page exists to hold the Subject (ADR 33), so
 * one down to its last Subject goes with it. Null everywhere else, which means delete the Subject
 * and leave the page standing.
 *
 * That form is the confirmation and the permission boundary both — a user without `delete` is
 * refused there, in MediaWiki's own words — which is why nothing here asks about the right.
 *
 * The page is read again rather than counted from whatever the surface has: a listing drawn minutes
 * ago can be missing a Subject somebody else added since, and a page that is no longer down to its
 * last one must not be offered for deletion. A null page is a Subject whose page the read did not
 * resolve, which this cannot answer for.
 */
export async function pageDeleteFormUrl(
	page: PageIdentifiers | null,
	subjectLookup: Pick<SubjectRepository, 'getPageSubjects'>,
): Promise<string | null> {
	if ( !isSubjectFirst() || page === null || page.getPageName() === '' ) {
		return null;
	}

	try {
		const { pageSubjects } = await subjectLookup.getPageSubjects( page.getPageId() );

		if ( pageSubjects.getSubjects().length !== 1 ) {
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
