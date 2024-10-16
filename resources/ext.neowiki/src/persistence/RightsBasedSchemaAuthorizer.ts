import type { RightsFetcher } from '@/persistence/UserObjectBasedRightsFetcher';
import type { SchemaAuthorizer } from '@/application/SchemaAuthorizer';

export class RightsBasedSchemaAuthorizer implements SchemaAuthorizer {

	public constructor( private readonly rightsFetcher: RightsFetcher ) {
	}

	public async canCreateSchemas(): Promise<boolean> {
		const rights = await this.rightsFetcher.getRights();
		return rights.includes( 'neowiki-schema-edit' );
	}

	public async canEditSchema( _schemaName: string ): Promise<boolean> {
		const rights = await this.rightsFetcher.getRights();
		return rights.includes( 'neowiki-schema-edit' );
	}
}
