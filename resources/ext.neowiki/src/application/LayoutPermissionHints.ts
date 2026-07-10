/**
 * Hints for showing or hiding affordances. They are advisory, not a security control: Layout pages
 * are written through MediaWiki, which authorizes the edit.
 */
export interface LayoutPermissionHints {

	canCreateLayouts(): Promise<boolean>;

	canEditLayout( layoutName: string ): Promise<boolean>;

}
