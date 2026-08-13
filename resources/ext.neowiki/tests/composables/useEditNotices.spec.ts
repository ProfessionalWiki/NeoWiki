import { describe, expect, it } from 'vitest';
import { useEditNotices } from '@/composables/useEditNotices';
import type { EditNotice } from '@/domain/EditNotice';
import type { EditNoticeRepository } from '@/application/EditNoticeRepository';

const PAGE_ID = 42;

function repositoryReturning( notices: EditNotice[] ): EditNoticeRepository {
	return { getNotices: () => Promise.resolve( notices ) };
}

describe( 'useEditNotices', () => {

	it( 'exposes the notices the repository reports', async () => {
		const notice = { key: 'neowiki-editnotice-0', html: '<p>Namespace</p>' };
		const { notices, loadNotices } = useEditNotices( () => repositoryReturning( [ notice ] ) );

		await loadNotices( PAGE_ID );

		expect( notices.value ).toStrictEqual( [ notice ] );
	} );

	it( 'starts with no notices', () => {
		expect( useEditNotices( () => repositoryReturning( [] ) ).notices.value ).toStrictEqual( [] );
	} );

	it( 'shows no notices rather than propagating a repository failure', async () => {
		const failing: EditNoticeRepository = { getNotices: () => Promise.reject( new Error( 'network down' ) ) };
		const { notices, loadNotices } = useEditNotices( () => failing );

		await expect( loadNotices( PAGE_ID ) ).resolves.toBeUndefined();

		expect( notices.value ).toStrictEqual( [] );
	} );

	it( 'shows no notices rather than propagating a failure to resolve the repository', async () => {
		const { notices, loadNotices } = useEditNotices( () => {
			throw new Error( 'extension not initialised' );
		} );

		await expect( loadNotices( PAGE_ID ) ).resolves.toBeUndefined();

		expect( notices.value ).toStrictEqual( [] );
	} );

	it( 'lets the newest request win when an earlier one resolves later', async () => {
		const slow = { key: 'stale', html: '<p>Stale</p>' };
		const fast = { key: 'fresh', html: '<p>Fresh</p>' };
		let call = 0;
		const repository: EditNoticeRepository = {
			getNotices: async () => {
				call++;

				if ( call === 1 ) {
					await new Promise( ( resolve ) => {
						setTimeout( resolve, 30 );
					} );

					return [ slow ];
				}

				return [ fast ];
			},
		};
		const { notices, loadNotices } = useEditNotices( () => repository );

		const first = loadNotices( PAGE_ID );
		const second = loadNotices( PAGE_ID, 'Person' );
		await Promise.all( [ first, second ] );

		expect( notices.value ).toStrictEqual( [ fast ] );
	} );

} );
