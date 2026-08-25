/**
 * The label the subject creator offers as a placeholder. Leaving the field blank stores no
 * label, and the server then displays exactly this name: the first Subject on a page is its
 * Main Subject and represents the page's own topic, so it shows the page name; every further
 * (Child) Subject shows its Schema name, since the page name would give all of them the same
 * misleading name.
 */
export function placeholderSubjectLabel( pageHasMainSubject: boolean, pageName: string, schemaName: string ): string {
	return pageHasMainSubject ? schemaName : pageName;
}
