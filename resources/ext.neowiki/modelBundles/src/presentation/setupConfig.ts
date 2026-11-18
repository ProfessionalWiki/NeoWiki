import type { ConfigEditorJsConfig } from 'ext.neowiki';

/**
 * What Special:DataModel tells its setup of the data model, as wgNeoWikiModelBundlesSetup.
 */
export interface SetupConfig {
	readonly wikiHasSchemas: boolean;
	readonly wikiHasSubjects: boolean;
	/** Whether this user may change the settings on MediaWiki:NeoWiki, as the wiki lets settings be set there. */
	readonly canEditSettings: boolean;
	readonly settings: ConfigEditorJsConfig;
}

export function setupConfig(): SetupConfig {
	return mw.config.get( 'wgNeoWikiModelBundlesSetup' ) as SetupConfig;
}

/**
 * A first install on a wiki without Subjects asks how the wiki works first, as the pages of installed Subjects
 * depend on it and changing it later moves none.
 */
export function asksHowTheWikiWorks( installedBundles: number ): boolean {
	return installedBundles === 0 && !setupConfig().wikiHasSubjects;
}
