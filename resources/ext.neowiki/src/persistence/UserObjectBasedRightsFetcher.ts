import { NeoWikiExtension } from '@/NeoWikiExtension';

export interface RightsFetcher {
	getRights(): Promise<string[]>;
	refreshRights(): Promise<void>;
}

export class UserObjectBasedRightsFetcher implements RightsFetcher {

	protected rights: Promise<string[]>;

	public constructor() {
		this.rights = this.fetchRights();
	}

	public async getRights(): Promise<string[]> {
		return await this.rights;
	}

	public async refreshRights(): Promise<void> {
		this.rights = this.fetchRights();
	}

	private async fetchRights(): Promise<string[]> {
		let rights: string[] = [];
		if ( NeoWikiExtension.getInstance().getMediaWiki()?.user ) {
			rights = await NeoWikiExtension.getInstance().getMediaWiki().user.getRights();
		}
		return rights;
	}
}
