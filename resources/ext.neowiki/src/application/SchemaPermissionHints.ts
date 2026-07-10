/**
 * Hints for showing or hiding affordances. They are advisory, not a security control: Schema pages
 * are written through MediaWiki, which authorizes the edit.
 */
export interface SchemaPermissionHints {

	canCreateSchemas(): Promise<boolean>;

	canEditSchema( schemaName: string ): Promise<boolean>;

}
