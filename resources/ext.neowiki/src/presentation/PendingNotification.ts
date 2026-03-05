export function setPendingNotification( key: string ): void {
	mw.storage.session.set( key, '1' );
}

export function showPendingNotification( key: string ): void {
	if ( !mw.storage.session.get( key ) ) {
		return;
	}

	mw.storage.session.remove( key );
	mw.notify( mw.msg( key ), { type: 'success' } );
}
