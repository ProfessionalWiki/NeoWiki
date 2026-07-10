import type { RightsFetcher } from '@/persistence/UserObjectBasedRightsFetcher';
import type { LayoutPermissionHints } from '@/application/LayoutPermissionHints';

export class RightsBasedLayoutPermissionHints implements LayoutPermissionHints {

	public constructor( private readonly rightsFetcher: RightsFetcher ) {
	}

	public async canCreateLayouts(): Promise<boolean> {
		const rights = await this.rightsFetcher.getRights();
		return rights.includes( 'neowiki-layout-edit' );
	}

	public async canEditLayout( _layoutName: string ): Promise<boolean> {
		const rights = await this.rightsFetcher.getRights();
		return rights.includes( 'neowiki-layout-edit' );
	}

}
