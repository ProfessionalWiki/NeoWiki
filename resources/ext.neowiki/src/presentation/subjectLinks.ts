import type { SubjectWithContext } from '@/domain/SubjectWithContext';
import { subjectPageUrl } from '@/presentation/subjectPageUrl';
import { subjectRowUrl } from '@/presentation/subjectRowUrl';
import { isSubjectFirst } from '@/wikiMode';

/**
 * Where a link to a Subject leads, which is the wiki's mode to answer (ADR 33). A subject-first
 * wiki sends every one of them to the Subject itself; a page-first wiki sends it to a page, and
 * which page that is depends on what the reader is looking at, which is why there are two of these.
 */

/**
 * From a View: the page the Subject is stored on, since that is what a page-first wiki is about.
 */
export function subjectLinkUrl( target: SubjectWithContext ): string {
	return isSubjectFirst() ?
		subjectPageUrl( target.getId().text ) :
		mw.util.getUrl( target.getPageIdentifiers().getPageName() );
}

/**
 * From a row listing Subjects: the target's own row on its page's Data tab, since a reader already
 * browsing Subjects means that one, not whatever else its page is about.
 */
export function subjectLinkUrlFromRow( target: SubjectWithContext ): string {
	return isSubjectFirst() ?
		subjectPageUrl( target.getId().text ) :
		subjectRowUrl( target.getPageIdentifiers().getPageName(), target.getId().text );
}
