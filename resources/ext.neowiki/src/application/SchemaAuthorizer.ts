export interface SchemaAuthorizer {

	canEditSchema( schemaName: string ): Promise<boolean>;

}
