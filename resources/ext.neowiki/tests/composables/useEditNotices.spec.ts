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

	it( 'shows none of the last request\'s notices while the next one is in flight', async () => {
		const notice = { key: 'neowiki-editnotice-0', html: '<p>Namespace</p>' };
		let call = 0;
		const repository: EditNoticeRepository = {
			getNotices: () => {
				call++;

				return call === 1 ? Promise.resolve( [ notice ] ) : new Promise<EditNotice[]>( () => {
					// Intentionally never settles.
				} );
			},
		};
		const { notices, loadNotices } = useEditNotices( () => repository );
		await loadNotices( PAGE_ID );

		loadNotices( PAGE_ID );

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

	describe( 'loading', () => {
		function repositoryOnRelease(): { repository: EditNoticeRepository; release: () => void } {
			let release!: () => void;
			const answered = new Promise<EditNotice[]>( ( resolve ) => {
				release = () => resolve( [] );
			} );

			return { repository: { getNotices: () => answered }, release };
		}

		it( 'is loading while a request is in flight', () => {
			const { loading, loadNotices } = useEditNotices( () => repositoryOnRelease().repository );

			loadNotices( PAGE_ID );

			expect( loading.value ).toBe( true );
		} );

		it( 'stops loading once the notices arrive', async () => {
			const { loading, loadNotices } = useEditNotices( () => repositoryReturning( [] ) );

			await loadNotices( PAGE_ID );

			expect( loading.value ).toBe( false );
		} );

		it( 'stops loading once a request fails', async () => {
			const failing: EditNoticeRepository = { getNotices: () => Promise.reject( new Error( 'network down' ) ) };
			const { loading, loadNotices } = useEditNotices( () => failing );

			await loadNotices( PAGE_ID );

			expect( loading.value ).toBe( false );
		} );

		it( 'keeps loading while a newer request is in flight after an earlier one settles', async () => {
			const earlier = repositoryOnRelease();
			const newer = repositoryOnRelease();
			const repositories = [ earlier.repository, newer.repository ];
			const { loading, loadNotices } = useEditNotices( () => repositories.shift() as EditNoticeRepository );

			const first = loadNotices( PAGE_ID );
			loadNotices( PAGE_ID, 'Person' );
			earlier.release();
			await first;

			expect( loading.value ).toBe( true );
		} );
	} );

} );
