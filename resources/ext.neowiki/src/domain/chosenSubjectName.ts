/**
 * The name someone has chosen for a Subject that does not exist yet, or null when nobody has: the
 * first Subject on a page is its Main Subject and represents the page's own topic, so it shows the
 * page name; every further Subject falls through, since the page name would give all of them the
 * same misleading name.
 *
 * A null page name is a page that has yet to be titled, which falls through too: such a page is
 * titled after the Subject when no label titles it, and an id is not a name. So does a page already
 * titled that way, which pageSubjectIds - the ids of the Subjects the page holds - is what
 * recognises. The first letter is set aside, because a wiki that capitalizes page titles, the
 * default, stores such a page under an upper-case S.
 *
 * The Schema tier the fallthrough leads to is composed in presentation/subjectDisplayName.ts, which
 * is where the stand-in is marked as one.
 */
export function chosenSubjectName(
	pageHasMainSubject: boolean,
	pageName: string | null,
	pageSubjectIds: string[],
): string | null {
	if ( pageHasMainSubject || pageName === null || isTitledBySubjectOnIt( pageName, pageSubjectIds ) ) {
		return null;
	}

	return pageName;
}

function isTitledBySubjectOnIt( pageName: string, pageSubjectIds: string[] ): boolean {
	return pageSubjectIds.includes( pageName.charAt( 0 ).toLowerCase() + pageName.slice( 1 ) );
}
