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
 * The language the wiki is written in, which is the same for every editor and every page they
 * edit from, special pages included.
 */
export function contentLanguageTag(): string {
	return toLanguageTag( mw.config.get( 'wgContentLanguage' ) );
}

// Kept from one call to the next, since MediaWiki hands out the same names every time and listing
// them takes a bcp47() call for each of several hundred languages.
let listedNames: Record<string, string> | undefined;
let listedOptions: readonly LanguageOption[] = [];

/**
 * Every language MediaWiki has a name for, named in the user's interface language. Several
 * MediaWiki codes can share one BCP 47 tag; the first name wins, so each tag is listed once.
 */
export function languageOptions(): readonly LanguageOption[] {
	const names = mw.language.getData( mw.config.get( 'wgUserLanguage' ), 'languageNames' ) as
		Record<string, string> | undefined;

	if ( names === listedNames ) {
		return listedOptions;
	}

	const byTag = new Map<string, LanguageOption>();

	for ( const [ code, name ] of Object.entries( names ?? {} ) ) {
		const tag = toLanguageTag( code );

		if ( !byTag.has( tag ) ) {
			byTag.set( tag, { tag: tag, name: name } );
		}
	}

	listedNames = names;
	listedOptions = [ ...byTag.values() ];

	return listedOptions;
}

// Ordered once for as long as the names it was ordered from stand, since every row of a monolingual
// text field has a picker of its own and each would otherwise sort several hundred names again.
let orderedFrom: readonly LanguageOption[] | undefined;
let orderedOptions: readonly LanguageOption[] = [];

/**
 * The languages in the order a picker lists them: the ones the reader reads, best first, then the
 * rest by name.
 */
export function languagesByPreference(): readonly LanguageOption[] {
	const options = languageOptions();

	if ( options !== orderedFrom ) {
		orderedFrom = options;
		orderedOptions = inPreferenceOrder( options, readerLanguageTags() );
	}

	return orderedOptions;
}

function inPreferenceOrder( options: readonly LanguageOption[], readerTags: string[] ): LanguageOption[] {
	const readerOptions = readerTags
		.map( ( tag ) => options.find( ( option ) => option.tag === tag ) )
		.filter( ( option ) => option !== undefined );

	const otherOptions = options
		.filter( ( option ) => !readerTags.includes( option.tag ) )
		.sort( ( a, b ) => a.name.localeCompare( b.name ) );

	return [ ...readerOptions, ...otherOptions ];
}

/**
 * The languages the typed text matches, best match first: a language it names or tags exactly, then
 * one whose name or tag it starts, then one whose name or tag it contains. A MediaWiki code counts
 * as the tag it stands for, so `als` finds Alemannic under `gsw`. Languages matching equally well
 * keep their order.
 */
export function matchingLanguages( options: readonly LanguageOption[], typed: string ): readonly LanguageOption[] {
	const text = typed.trim().toLowerCase();

	if ( text === '' ) {
		return options;
	}

	const tag = toLanguageTag( text );

	const rankOf = ( option: LanguageOption ): number | undefined => {
		const name = option.name.toLowerCase();

		if ( option.tag === text || option.tag === tag || name === text ) {
			return 0;
		}

		if ( option.tag.startsWith( text ) || name.startsWith( text ) ) {
			return 1;
		}

		return option.tag.includes( text ) || name.includes( text ) ? 2 : undefined;
	};

	return options
		.map( ( option ) => ( { option: option, rank: rankOf( option ) } ) )
		.filter( ( match ): match is { option: LanguageOption; rank: number } => match.rank !== undefined )
		.sort( ( a, b ) => a.rank - b.rank )
		.map( ( match ) => match.option );
}

// Typed text offered as a tag of its own: a language subtag of two or three letters, the length
// of every one registered for BCP 47, then any further subtags. A longer first subtag is a word on
// its way to a name: without the CLDR extension MediaWiki names languages in their own language,
// so `German` names nothing, and taking it for a tag would store `german`. The backend accepts
// every tag this does. Each repetition has to start with a hyphen, so there is nothing ambiguous to
// backtrack over.
// eslint-disable-next-line security/detect-unsafe-regex
const TYPED_TAG = /^[A-Za-z]{2,3}(-[A-Za-z0-9]{1,8})*$/;

/**
 * The tag the typed text is also offered as, or undefined when it is not offered as one. Text that
 * names a language MediaWiki knows is the user naming that language rather than writing a tag, and
 * offering both would put two entries reading `Ido` in the menu, one of them storing `ido`. Text on
 * its way to such a name is not that name: `ara` is a language of its own as well as the start of
 * `aragonés`.
 */
export function typedLanguageTag( options: readonly LanguageOption[], typed: string ): string | undefined {
	if ( !TYPED_TAG.test( typed ) ) {
		return undefined;
	}

	const tag = toLanguageTag( typed );
	const name = typed.toLowerCase();

	return options.some( ( option ) => option.tag === tag || option.name.toLowerCase() === name ) ?
		undefined :
		tag;
}

/**
 * The name MediaWiki lists for a language, or undefined when it lists none. Without the CLDR
 * extension MediaWiki names a language in that language, so it has no name for many of them.
 */
export function languageName( tag: string ): string | undefined {
	return languageOptions().find( ( option ) => option.tag === tag )?.name;
}

/**
 * A language tag the way it is shown: in capitals, as tags usually are. Written out here rather
 * than by `text-transform`, which follows the page's language and turns `it` into `İT` on a
 * Turkish page.
 */
export function shownLanguageTag( tag: string ): string {
	return tag.toUpperCase();
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
 * The parts to show a reader: the ones in the first of their languages that any part is in, or,
 * when they read none of them, the ones in the language of the first part.
 */
export function partsForReader( parts: MonolingualText[], readerTags: string[] ): MonolingualText[] {
	const shownTag = readerTags.find( ( tag ) => parts.some( ( part ) => part.language === tag ) ) ??
		parts[ 0 ]?.language;

	return parts.filter( ( part ) => part.language === shownTag );
}
