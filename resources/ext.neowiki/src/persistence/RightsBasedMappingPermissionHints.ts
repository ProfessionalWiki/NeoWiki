import type { RightsFetcher } from '@/persistence/UserObjectBasedRightsFetcher';
import type { MappingPermissionHints } from '@/application/MappingPermissionHints';

export class RightsBasedMappingPermissionHints implements MappingPermissionHints {

	public constructor( private readonly rightsFetcher: RightsFetcher ) {
	}

	public async canCreateMappings(): Promise<boolean> {
		const rights = await this.rightsFetcher.getRights();
		return rights.includes( 'neowiki-mapping-edit' );
	}

	public async canEditMapping( _mappingName: string ): Promise<boolean> {
		const rights = await this.rightsFetcher.getRights();
		return rights.includes( 'neowiki-mapping-edit' );
	}

	// Deleting a Mapping page is a normal MediaWiki page deletion, authorized by the core `delete`
	// right alone (the `neowiki-mapping-edit` namespace protection does not cover deletion). Checked
	// on its own so a delete-capable group without `neowiki-mapping-edit` still sees the button.
	public async canDeleteMapping( _mappingName: string ): Promise<boolean> {
		const rights = await this.rightsFetcher.getRights();
		return rights.includes( 'delete' );
	}

}
