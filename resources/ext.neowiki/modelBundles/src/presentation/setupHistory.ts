import type { PageValues } from 'ext.neowiki';

const PARAMETER = 'step';
const CHOICES_KEY = 'neowiki-data-model-setup';

/**
 * What the setup keeps through a reload.
 */
export interface SetupChoices {
	/** Undefined while none is chosen. */
	readonly model: string | undefined;
	readonly destinations: readonly string[];
	/** What MediaWiki:NeoWiki will set; undefined in what an older version of the setup kept. */
	readonly settings?: PageValues;
}

/**
 * The step the page URL names, as ?step=…, or null when it names none.
 */
export function stepInUrl(): string | null {
	return new URL( location.href ).searchParams.get( PARAMETER );
}

/**
 * Names the step in the page URL: in a new history entry, so that the browser's Back returns to the step shown
 * before, or in place of the current entry.
 */
export function enterStep( step: string, entry: 'push' | 'replace' ): void {
	const url = new URL( location.href );
	url.searchParams.set( PARAMETER, step );

	if ( entry === 'push' ) {
		history.pushState( { previousStep: stepInUrl() }, '', url );
	} else {
		history.replaceState( history.state, '', url );
	}
}

/**
 * Whether the browser's Back leads to the step, as this history entry was entered from it.
 */
export function cameFrom( step: string ): boolean {
	return history.state?.previousStep === step;
}

export function saveChoices( choices: SetupChoices ): void {
	mw.storage.session.setObject( CHOICES_KEY, choices );
}

/**
 * What saveChoices saved in this browser tab, or null.
 */
export function savedChoices(): SetupChoices | null {
	const saved = mw.storage.session.getObject( CHOICES_KEY );

	return Array.isArray( saved?.destinations ) ? saved : null;
}

export function reloadPage(): void {
	location.reload();
}
