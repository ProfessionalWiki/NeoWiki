/**
 * Hints for showing or hiding affordances. They are advisory, not a security control: Mapping pages
 * are written through MediaWiki, which authorizes the edit.
 */
export interface MappingPermissionHints {

	canCreateMappings(): Promise<boolean>;

	canEditMapping( mappingName: string ): Promise<boolean>;

	canDeleteMapping( mappingName: string ): Promise<boolean>;

}
