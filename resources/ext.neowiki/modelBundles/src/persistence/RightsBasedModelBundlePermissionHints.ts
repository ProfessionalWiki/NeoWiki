import type { RightsFetcher } from 'ext.neowiki';
import type { ModelBundlePermissionHints } from '../application/ModelBundlePermissionHints';

/**
 * An install record is a JSON page in the MediaWiki namespace, which takes both rights to write.
 */
export class RightsBasedModelBundlePermissionHints implements ModelBundlePermissionHints {

	public constructor( private readonly rightsFetcher: RightsFetcher ) {
	}

	public async canInstallModelBundles(): Promise<boolean> {
		const rights = await this.rightsFetcher.getRights();
		return rights.includes( 'editsitejson' ) && rights.includes( 'editinterface' );
	}

}
