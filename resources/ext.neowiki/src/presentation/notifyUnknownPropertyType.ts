const notifiedTypes = new Set<string>();

/**
 * Warns once per page load for each distinct unknown property type, so editors
 * and admins notice that an extension-owned type is unavailable. Only users who
 * can edit the current page are notified — a regular reader cannot act on a
 * missing extension and should not be alarmed. The warning is deliberately
 * gentle: the data is still shown, it just cannot be interpreted.
 */
export function notifyUnknownPropertyType( typeName: string ): void {
	if ( !mw.config.get( 'wgIsProbablyEditable' ) ) {
		return;
	}

	if ( notifiedTypes.has( typeName ) ) {
		return;
	}

	notifiedTypes.add( typeName );
	mw.notify(
		mw.msg( 'neowiki-property-type-unknown-notification', typeName ),
		{ type: 'warn' },
	);
}

/**
 * Clears the per-page deduplication state. Intended for tests.
 */
export function resetUnknownPropertyTypeNotifications(): void {
	notifiedTypes.clear();
}
