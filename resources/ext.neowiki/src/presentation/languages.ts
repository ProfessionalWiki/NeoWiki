import type { MonolingualText } from '@/domain/Value.ts';

export interface LanguageOption {
	readonly tag: string;
	readonly name: string;
}

/**
 * The languages the search lists: those with an ISO 639-1 code. Any other is entered by its tag.
 */
const LISTED_TAGS: readonly string[] = [
	'aa', 'ab', 'ae', 'af', 'ak', 'am', 'an', 'ar', 'as', 'av', 'ay', 'az', 'ba', 'be', 'bg', 'bi', 'bm', 'bn',
	'bo', 'br', 'bs', 'ca', 'ce', 'ch', 'co', 'cr', 'cs', 'cu', 'cv', 'cy', 'da', 'de', 'dv', 'dz', 'ee', 'el',
	'en', 'eo', 'es', 'et', 'eu', 'fa', 'ff', 'fi', 'fj', 'fo', 'fr', 'fy', 'ga', 'gd', 'gl', 'gn', 'gu', 'gv',
	'ha', 'he', 'hi', 'ho', 'hr', 'ht', 'hu', 'hy', 'hz', 'ia', 'id', 'ie', 'ig', 'ii', 'ik', 'io', 'is', 'it',
	'iu', 'ja', 'jv', 'ka', 'kg', 'ki', 'kj', 'kk', 'kl', 'km', 'kn', 'ko', 'kr', 'ks', 'ku', 'kv', 'kw', 'ky',
	'la', 'lb', 'lg', 'li', 'ln', 'lo', 'lt', 'lu', 'lv', 'mg', 'mh', 'mi', 'mk', 'ml', 'mn', 'mr', 'ms', 'mt',
	'my', 'na', 'nb', 'nd', 'ne', 'ng', 'nl', 'nn', 'no', 'nr', 'nv', 'ny', 'oc', 'oj', 'om', 'or', 'os', 'pa',
	'pi', 'pl', 'ps', 'pt', 'qu', 'rm', 'rn', 'ro', 'ru', 'rw', 'sa', 'sc', 'sd', 'se', 'sg', 'si', 'sk', 'sl',
	'sm', 'sn', 'so', 'sq', 'sr', 'ss', 'st', 'su', 'sv', 'sw', 'ta', 'te', 'tg', 'th', 'ti', 'tk', 'tl', 'tn',
	'to', 'tr', 'ts', 'tt', 'tw', 'ty', 'ug', 'uk', 'ur', 'uz', 've', 'vi', 'vo', 'wa', 'wo', 'xh', 'yi', 'yo',
	'za', 'zh', 'zu',
];

/**
 * The stored form of a MediaWiki language code: its BCP 47 tag, lowercased.
 */
function toLanguageTag( code: string ): string {
	return mw.language.bcp47( code ).toLowerCase();
}

export function userLanguageTag(): string {
	return toLanguageTag( mw.config.get( 'wgUserLanguage' ) );
}

/**
 * The language the wiki is written in, which is the same for every editor and every page they
 * edit from, special pages included.
 */
function contentLanguageTag(): string {
	return toLanguageTag( mw.config.get( 'wgContentLanguage' ) );
}

/**
 * The languages the wiki's data is in, each once, in the order its admins listed them: the content
 * language when they listed none.
 */
export function dataLanguageTags(): string[] {
	const listed = mw.config.get( 'wgNeoWikiDataLanguages' );

	return Array.isArray( listed ) && listed.length > 0 ?
		[ ...new Set( listed.map( ( tag: string ) => toLanguageTag( tag ) ) ) ] :
		[ contentLanguageTag() ];
}

/**
 * The language a new part starts in: the first data language no part is in yet, or the content
 * language once every one is.
 */
export function newPartLanguageTag( partLanguageTags: readonly string[] ): string {
	return dataLanguageTags().find( ( tag ) => !partLanguageTags.includes( tag ) ) ?? contentLanguageTag();
}

let namesIn: { userLanguage: string; names: Intl.DisplayNames | null } | undefined;

function displayNames(): Intl.DisplayNames | null {
	const userLanguage = userLanguageTag();

	if ( namesIn?.userLanguage !== userLanguage ) {
		let names: Intl.DisplayNames | null;

		try {
			names = new Intl.DisplayNames( [ userLanguage ], { type: 'language', fallback: 'none' } );
		} catch {
			names = null;
		}

		namesIn = { userLanguage: userLanguage, names: names };
	}

	return namesIn.names;
}

/**
 * The language's name in the interface language, or undefined where the browser has none. A browser
 * without names in the interface language gives them in English. A tag the browser reads as another
 * language's, such as `sh`, which it would name Serbian, gets no name.
 */
export function languageName( tag: string ): string | undefined {
	try {
		return Intl.getCanonicalLocales( tag )[ 0 ].toLowerCase() === tag ? displayNames()?.of( tag ) : undefined;
	} catch {
		return undefined;
	}
}

let listed: { userLanguage: string; options: readonly LanguageOption[] } | undefined;

/**
 * The listed languages the browser can name, by name, each name once: browsers name `tw` Akan,
 * like `ak`, which comes first.
 */
export function languageOptions(): readonly LanguageOption[] {
	const userLanguage = userLanguageTag();

	if ( listed?.userLanguage !== userLanguage ) {
		const options: LanguageOption[] = [];

		for ( const tag of LISTED_TAGS ) {
			const name = languageName( tag );

			if ( name !== undefined && !options.some( ( option ) => option.name === name ) ) {
				options.push( { tag: tag, name: name } );
			}
		}

		listed = { userLanguage: userLanguage, options: options.sort( ( a, b ) => a.name.localeCompare( b.name, userLanguage ) ) };
	}

	return listed.options;
}

/**
 * The languages the typed text matches, best match first: a language it names or tags exactly, then
 * one whose name or tag it starts, then one whose name or tag it contains. Languages matching equally
 * well keep their order.
 */
export function matchingLanguages( options: readonly LanguageOption[], typed: string ): readonly LanguageOption[] {
	const text = typed.trim().toLowerCase();

	if ( text === '' ) {
		return options;
	}

	const rankOf = ( option: LanguageOption ): number | undefined => {
		const name = option.name.toLowerCase();

		if ( option.tag === text || name === text ) {
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
// its way to a name, and taking it for a tag would store `german`. The backend accepts every tag
// this does. Each repetition has to start with a hyphen, so there is nothing ambiguous to
// backtrack over.
// eslint-disable-next-line security/detect-unsafe-regex
const TYPED_TAG = /^[A-Za-z]{2,3}(-[A-Za-z0-9]{1,8})*$/;

/**
 * The tag the typed text is also offered as, or undefined when it is not offered as one. Text that
 * names or tags a listed language is the user choosing that language, and offering both would put
 * two entries reading `Ewe` in the menu. Text on its way to a name is not that name: `ara` is a
 * language of its own as well as the start of `Aragonese`.
 */
export function typedLanguageTag( options: readonly LanguageOption[], typed: string ): string | undefined {
	if ( !TYPED_TAG.test( typed ) ) {
		return undefined;
	}

	const text = typed.toLowerCase();

	return options.some( ( option ) => option.tag === text || option.name.toLowerCase() === text ) ?
		undefined :
		text;
}

// In script rather than by `text-transform`, which follows the page's language and turns `it`
// into `İT` on a Turkish page.
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
