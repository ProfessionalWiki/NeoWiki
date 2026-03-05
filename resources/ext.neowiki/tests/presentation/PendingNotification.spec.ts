import { describe, it, expect, vi, beforeEach } from 'vitest';
import { setPendingNotification, showPendingNotification } from '@/presentation/PendingNotification';

describe( 'PendingNotification', () => {
	beforeEach( () => {
		vi.stubGlobal( 'mw', {
			storage: {
				session: {
					get: vi.fn(),
					set: vi.fn(),
					remove: vi.fn(),
				},
			},
			msg: vi.fn( ( key: string ) => `[${ key }]` ),
			notify: vi.fn(),
		} );
	} );

	describe( 'setPendingNotification', () => {
		it( 'stores the key in session storage', () => {
			setPendingNotification( 'neowiki-some-message' );

			expect( mw.storage.session.set ).toHaveBeenCalledWith( 'neowiki-some-message', '1' );
		} );
	} );

	describe( 'showPendingNotification', () => {
		it( 'shows notification and removes key when present', () => {
			( mw.storage.session.get as ReturnType<typeof vi.fn> ).mockReturnValue( '1' );

			showPendingNotification( 'neowiki-some-message' );

			expect( mw.storage.session.remove ).toHaveBeenCalledWith( 'neowiki-some-message' );
			expect( mw.notify ).toHaveBeenCalledWith( '[neowiki-some-message]', { type: 'success' } );
		} );

		it( 'does nothing when key is not present', () => {
			( mw.storage.session.get as ReturnType<typeof vi.fn> ).mockReturnValue( null );

			showPendingNotification( 'neowiki-some-message' );

			expect( mw.storage.session.remove ).not.toHaveBeenCalled();
			expect( mw.notify ).not.toHaveBeenCalled();
		} );
	} );
} );
