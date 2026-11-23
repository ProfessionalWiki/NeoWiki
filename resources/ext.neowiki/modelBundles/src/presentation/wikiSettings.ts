import { dataLanguageTagsOf, languageNameOrTag, pageSource, type ConfigValues, type PageRevisionSaver, type PageValues } from 'ext.neowiki';
import { setupConfig } from './setupConfig';

/**
 * As on MediaWiki:NeoWiki: the value the page sets, else the one LocalSettings.php gives.
 */
export function valueInEffect<Key extends keyof ConfigValues>( values: PageValues, key: Key ): ConfigValues[ Key ] {
	return ( values[ key ] ?? setupConfig().settings.localSettingsValues[ key ] ) as ConfigValues[ Key ];
}

/**
 * Saves on MediaWiki:NeoWiki what was changed there, where the wiki lets settings be changed. The reason it could
 * not, or null.
 */
export async function saveSettings( values: PageValues, saver: PageRevisionSaver ): Promise<string | null> {
	const config = setupConfig();

	if ( !config.canEditSettings || pageSource( values ) === pageSource( config.settings.pageValues ) ) {
		return null;
	}

	const status = await saver.saveRevision(
		'MediaWiki:NeoWiki',
		pageSource( values ),
		mw.msg( 'neowiki-bundle-setup-settings-summary' ),
		'json',
		config.settings.revisionId,
	);

	if ( !status.success ) {
		return status.message;
	}

	// What a later save compares with and saves over, and what the frontend reads, until the page reloads.
	mw.config.set( 'wgNeoWikiModelBundlesSetup', {
		...config,
		settings: { ...config.settings, pageValues: { ...values }, revisionId: status.revisionId },
	} );
	mw.config.set( 'wgNeoWikiSubjectFirst', valueInEffect( values, 'subjectFirst' ) );
	mw.config.set( 'wgNeoWikiDataLanguages', valueInEffect( values, 'dataLanguages' ) );
	return null;
}

/**
 * The wiki type and the data languages, as "Wiki type: Subject-first · Data languages: English".
 */
export function settingsLine( values: PageValues ): string {
	return mw.msg(
		'neowiki-bundle-setup-review-settings',
		mw.msg( valueInEffect( values, 'subjectFirst' ) ?
			'neowiki-config-setting-subject-first-subject-first' :
			'neowiki-config-setting-subject-first-page-first' ),
		mw.language.listToText( dataLanguageTagsOf( valueInEffect( values, 'dataLanguages' ) ).map( languageNameOrTag ) ),
	);
}
