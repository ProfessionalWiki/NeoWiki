import type { MonolingualText } from '@/domain/Value.ts';

export interface LanguageOption {
	readonly tag: string;
	readonly name: string;
}

/**
 * The stored form of a language: the BCP 47 tag of a MediaWiki language code, lowercased.
 */
export function toLanguageTag( code: string ): string {
	return mw.language.bcp47( code ).toLowerCase();
}

export function userLanguageTag(): string {
	return toLanguageTag( mw.config.get( 'wgUserLanguage' ) );
}

/**
 * Every language MediaWiki has a name for, named in the user's interface language. Several
 * MediaWiki codes can share one BCP 47 tag; the first name wins, so each tag is listed once.
 */
export function languageOptions(): LanguageOption[] {
	const names = mw.language.getData( mw.config.get( 'wgUserLanguage' ), 'languageNames' ) as
		Record<string, string> | undefined;

	const byTag = new Map<string, LanguageOption>();

	for ( const [ code, name ] of Object.entries( names ?? {} ) ) {
		const tag = toLanguageTag( code );

		if ( !byTag.has( tag ) ) {
			byTag.set( tag, { tag: tag, name: name } );
		}
	}

	return [ ...byTag.values() ];
}

/**
 * The name MediaWiki lists for a language, or undefined when it lists none.
 */
export function languageName( tag: string ): string | undefined {
	return languageOptions().find( ( option ) => option.tag === tag )?.name;
}

/**
 * The languages a reader is taken to read, best first: the one they see the interface in, the ones
 * MediaWiki falls back to for it, and the language the wiki itself is written in.
 */
export function readerLanguageTags(): string[] {
	const codes = [
		mw.config.get( 'wgUserLanguage' ),
		...mw.language.getFallbackLanguageChain(),
		mw.config.get( 'wgContentLanguage' ),
	];

	return [ ...new Set( codes.map( ( code ) => toLanguageTag( code ) ) ) ];
}

/**
 * The parts to show a reader: the ones in the first of their languages that any part is in, or the
 * first part alone when they read none of them.
 */
export function partsForReader( parts: MonolingualText[], readerTags: string[] ): MonolingualText[] {
	const readTag = readerTags.find( ( tag ) => parts.some( ( part ) => part.language === tag ) );

	if ( readTag === undefined ) {
		return parts.slice( 0, 1 );
	}

	return parts.filter( ( part ) => part.language === readTag );
}
