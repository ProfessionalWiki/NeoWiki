/**
 * Default label for a new Subject. The first Subject on a page is its Main
 * Subject and represents the page's own topic, so it defaults to the page
 * name; every further (Child) Subject defaults to its Schema name, since the
 * page name would give all of them the same misleading label.
 */
export function defaultSubjectLabel( pageHasMainSubject: boolean, pageName: string, schemaName: string ): string {
	return pageHasMainSubject ? schemaName : pageName;
}
