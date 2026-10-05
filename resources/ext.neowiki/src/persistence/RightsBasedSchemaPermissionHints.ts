import type { RightsFetcher } from '@/persistence/UserObjectBasedRightsFetcher';
import type { SchemaPermissionHints } from '@/application/SchemaPermissionHints';

export class RightsBasedSchemaPermissionHints implements SchemaPermissionHints {

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

	/**
	 * Deleting a Schema deletes its page, which MediaWiki allows with the core delete right where the
	 * Schema namespace's protection, the right to edit Schemas, also allows it.
	 */
	public async canDeleteSchema( schemaName: string ): Promise<boolean> {
		const rights = await this.rightsFetcher.getRights();
		return rights.includes( 'delete' ) && await this.canEditSchema( schemaName );
	}
}
