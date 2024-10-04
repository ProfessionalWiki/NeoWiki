export interface SchemaAuthorizer {

	canEditSchema(): Promise<boolean>;

}
