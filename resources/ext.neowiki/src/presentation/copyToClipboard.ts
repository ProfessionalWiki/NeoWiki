/**
 * Puts text on the clipboard and tells the user how it went. The messages are passed in rather than
 * derived, because what was copied is what each caller reports.
 */
export async function copyToClipboard( text: string, copied: string, failed: string ): Promise<void> {
	try {
		await navigator.clipboard.writeText( text );
		mw.notify( copied, { type: 'success' } );
	} catch ( error ) {
		console.error( 'Failed to copy to the clipboard:', error );
		mw.notify( failed, { type: 'error' } );
	}
}
