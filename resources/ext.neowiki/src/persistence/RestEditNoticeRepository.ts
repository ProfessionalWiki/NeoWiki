import type { EditNotice } from '@/domain/EditNotice';
import type { EditNoticeRepository } from '@/application/EditNoticeRepository';
import type { HttpClient } from '@/infrastructure/HttpClient/HttpClient';

export class RestEditNoticeRepository implements EditNoticeRepository {

	public constructor(
		private readonly mediaWikiRestApiUrl: string,
		private readonly httpClient: HttpClient,
	) {
	}

	/**
	 * Notices are advisory, so every failure degrades to showing none. Refusing to open an editor
	 * because a notice could not be fetched would trade a missing hint for a blocked edit.
	 */
	public async getNotices( pageId: number, schemaName?: string ): Promise<EditNotice[]> {
		try {
			const response = await this.httpClient.get( this.noticesUrl( pageId, schemaName ) );

			if ( !response.ok ) {
				return [];
			}

			const data = await response.json();

			return Array.isArray( data?.notices ) ? data.notices : [];
		} catch {
			return [];
		}
	}

	private noticesUrl( pageId: number, schemaName?: string ): string {
		const url = `${ this.mediaWikiRestApiUrl }/neowiki/v0/page/${ pageId }/editNotices`;

		if ( schemaName === undefined ) {
			return url;
		}

		return `${ url }?${ new URLSearchParams( { schema: schemaName } ).toString() }`;
	}

}
