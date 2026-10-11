/**
 * The settings on MediaWiki:NeoWiki, by their key on the page.
 */
export interface ConfigValues {
	subjectFirst: boolean;
	/** BCP 47 tags, in order. */
	dataLanguages: string[];
	enforceValidation: boolean;
	autoRenderMainSubject: boolean;
}

/**
 * What the page sets: null for a setting it leaves to LocalSettings.php.
 */
export type PageValues = { [ Key in keyof ConfigValues ]: ConfigValues[ Key ] | null };

/**
 * The form's configuration, handed over as the wgNeoWikiConfigEditor JS config var.
 */
export interface ConfigEditorJsConfig {
	/** The revision the form saves over, or null when the page does not exist. */
	readonly revisionId: number | null;
	readonly pageValues: PageValues;
	/** The values LocalSettings.php gives the settings, as NeoWiki reads them. */
	readonly localSettingsValues: ConfigValues;
}

/**
 * The page's JSON: the settings it sets, and no others.
 */
export function pageSource( values: PageValues ): string {
	return JSON.stringify(
		Object.fromEntries( Object.entries( values ).filter( ( [ , value ] ) => value !== null ) ),
		null,
		'\t',
	);
}

/**
 * Whether two page sources hold the same JSON, however each is formatted.
 */
export function sameJson( source: string, otherSource: string ): boolean {
	return JSON.stringify( JSON.parse( source ) ) === JSON.stringify( JSON.parse( otherSource ) );
}
