/**
 * A setting of the on-wiki configuration, as the server describes it to the form.
 */
export interface ConfigSetting {
	/** The key the setting has on the page. */
	readonly key: string;
	/** The type of its value, which picks the control. */
	readonly type: string;
	readonly label: string;
	readonly help: string | null;
	/** The values the form offers by name, in place of the control for the type. */
	readonly choices: readonly ConfigChoice[];
	/** The value LocalSettings.php gives it, as NeoWiki reads it. */
	readonly localSettingsValue: unknown;
	/** The value the page sets, or null when the page leaves it to LocalSettings.php. */
	readonly pageValue: unknown;
}

export interface ConfigChoice {
	readonly value: string | number | boolean;
	readonly label: string;
	readonly help: string;
}

/**
 * The form's configuration, handed over as the wgNeoWikiConfigEditor JS config var.
 */
export interface ConfigEditorJsConfig {
	/** The revision the form saves over, or null when the page does not exist. */
	readonly revisionId: number | null;
	readonly settings: readonly ConfigSetting[];
}

/**
 * What the page sets, by setting key: null for a setting it leaves to LocalSettings.php.
 */
export type PageValues = Readonly<Record<string, unknown>>;

export function pageValuesOf( settings: readonly ConfigSetting[] ): PageValues {
	return Object.fromEntries( settings.map( ( setting ) => [ setting.key, setting.pageValue ] ) );
}

/**
 * The page's JSON: the settings it sets, in the order of the form, and no others.
 */
export function pageSource( settings: readonly ConfigSetting[], values: PageValues ): string {
	return JSON.stringify(
		Object.fromEntries(
			settings
				.filter( ( setting ) => values[ setting.key ] !== null )
				.map( ( setting ) => [ setting.key, values[ setting.key ] ] ),
		),
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
