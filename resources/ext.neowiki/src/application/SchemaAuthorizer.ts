export interface SchemaAuthorizer {

	canCreateSchemas(): Promise<boolean>;

	canEditSchema( schemaName: string ): Promise<boolean>;

}
