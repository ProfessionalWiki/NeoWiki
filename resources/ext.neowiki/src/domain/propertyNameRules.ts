/**
 * What a property name must be to be written, as the wiki decides it when it refuses a save. PHP's PropertyNameRules
 * answers tests/vectors/property-names.json alike. Reading never applies these rules.
 */

export type PropertyNameRule = 'canonical-form' | 'empty' | 'characters' | 'integer' | 'dot-segment' | 'length' |
	'reserved' | 'near-duplicate';

export interface PropertyNameProblem {
	readonly name: string;
	readonly rule: PropertyNameRule;

	/**
	 * For canonical-form, the canonical form, followed by the hidden or look-alike characters that set the name apart
	 * from it, as code points, or by nothing when only spaces differ. For characters, each character the name cannot
	 * hold, as a code point when it is invisible. For near-duplicate, the name it is too similar to.
	 */
	readonly details: readonly string[];
}

const MAX_BYTES = 255;

// Unicode's White_Space property. Not \s, which also matches U+FEFF.
const WHITE_SPACE_RUNS = /[\t\n\v\f\r \u0085\u00A0\u1680\u2000-\u200A\u2028\u2029\u202F\u205F\u3000]+/gu;

type CodePointRange = readonly [ number, number ];

// Formatting characters and fillers that no script needs, so a canonical name holds none.
const INVISIBLE_CHARACTERS: readonly CodePointRange[] = [
	[ 0x00AD, 0x00AD ], [ 0x061C, 0x061C ], [ 0x115F, 0x1160 ], [ 0x200B, 0x200B ], [ 0x200E, 0x200F ],
	[ 0x202A, 0x202E ], [ 0x2060, 0x2064 ], [ 0x2066, 0x206F ], [ 0x3164, 0x3164 ], [ 0xFEFF, 0xFEFF ],
	[ 0xFFA0, 0xFFA0 ], [ 0xFFF9, 0xFFFB ], [ 0x1D173, 0x1D17A ], [ 0xE0001, 0xE0001 ],
];

// Unicode's Default_Ignorable_Code_Point property.
const DEFAULT_IGNORABLE_CODE_POINTS: readonly CodePointRange[] = [
	[ 0x00AD, 0x00AD ], [ 0x034F, 0x034F ], [ 0x061C, 0x061C ], [ 0x115F, 0x1160 ], [ 0x17B4, 0x17B5 ],
	[ 0x180B, 0x180F ], [ 0x200B, 0x200F ], [ 0x202A, 0x202E ], [ 0x2060, 0x206F ], [ 0x3164, 0x3164 ],
	[ 0xFE00, 0xFE0F ], [ 0xFEFF, 0xFEFF ], [ 0xFFA0, 0xFFA0 ], [ 0xFFF0, 0xFFF8 ], [ 0x1BCA0, 0x1BCA3 ],
	[ 0x1D173, 0x1D17A ], [ 0xE0000, 0xE0FFF ],
];

const TITLE_CHARACTERS = new Set( [ '#', '<', '>', '[', ']', '|', '{', '}' ] );

const RESERVED_NAMES = new Set( [ 'id', 'name', 'wiki_id', '__proto__' ] );

/**
 * The first rule the name breaks, the near-duplicate rule aside, or null.
 */
export function propertyNameProblem( name: string ): PropertyNameProblem | null {
	// Before normalizing, which takes time quadratic in a long run of combining marks.
	if ( byteLength( name ) > MAX_BYTES ) {
		return { name, rule: 'length', details: [] };
	}

	const canonicalForm = canonicalPropertyName( name );
	const characters = forbiddenCharacters( canonicalForm );

	if ( characters.length > 0 ) {
		return { name, rule: 'characters', details: characters };
	}

	const rule = ruleBrokenBy( canonicalForm );

	if ( rule !== null ) {
		return { name, rule, details: [] };
	}

	if ( canonicalForm !== name ) {
		return { name, rule: 'canonical-form', details: [ canonicalForm, ...hiddenDifference( name, canonicalForm ) ] };
	}

	return null;
}

export function canonicalPropertyName( name: string ): string {
	return collapseWhiteSpace( withoutCodePointsIn( INVISIBLE_CHARACTERS, name ) ).normalize( 'NFC' );
}

function withoutCodePointsIn( ranges: readonly CodePointRange[], text: string ): string {
	return [ ...text ].filter( ( character ) => {
		const codePoint = character.codePointAt( 0 )!;

		return !ranges.some( ( [ first, last ] ) => codePoint >= first && codePoint <= last );
	} ).join( '' );
}

// Not trim(), which removes more than the spaces that collapsing leaves.
function collapseWhiteSpace( text: string ): string {
	return text.replace( WHITE_SPACE_RUNS, ' ' ).replace( /^ | $/g, '' );
}

function forbiddenCharacters( name: string ): string[] {
	const characters = new Set<string>();

	for ( const character of name ) {
		if ( TITLE_CHARACTERS.has( character ) ) {
			characters.add( character );
		} else if ( isControlOrNoncharacter( character.codePointAt( 0 )! ) ) {
			characters.add( codePoint( character ) );
		}
	}

	return [ ...characters ];
}

function isControlOrNoncharacter( codePoint: number ): boolean {
	return codePoint <= 0x1F ||
		( codePoint >= 0x7F && codePoint <= 0x9F ) ||
		( codePoint >= 0xFDD0 && codePoint <= 0xFDEF ) ||
		codePoint % 0x10000 >= 0xFFFE ||
		codePoint === 0xFFFD;
}

function codePoint( character: string ): string {
	return 'U+' + character.codePointAt( 0 )!.toString( 16 ).toUpperCase().padStart( 4, '0' );
}

function ruleBrokenBy( canonicalForm: string ): PropertyNameRule | null {
	if ( canonicalForm === '' ) {
		return 'empty';
	}

	if ( /^-?[0-9]+$/.test( canonicalForm ) ) {
		return 'integer';
	}

	if ( canonicalForm === '.' || canonicalForm === '..' ) {
		return 'dot-segment';
	}

	if ( byteLength( canonicalForm ) > MAX_BYTES ) {
		return 'length';
	}

	if ( RESERVED_NAMES.has( canonicalForm ) ) {
		return 'reserved';
	}

	return null;
}

const UTF8_ENCODER = new TextEncoder();

function byteLength( text: string ): number {
	return UTF8_ENCODER.encode( text ).length;
}

/**
 * Nothing when only spaces differ. Otherwise the characters outside printable ASCII that the canonical form lacks, or
 * all of them when it lacks none, as when combining marks are only reordered.
 */
function hiddenDifference( name: string, canonicalForm: string ): string[] {
	if ( name.replace( / +/g, ' ' ).replace( /^ | $/g, '' ) === canonicalForm ) {
		return [];
	}

	const hiddenCharacters = [ ...name ].filter( ( character ) => !/^[\x20-\x7E]$/.test( character ) );
	const canonicalCharacters = new Set( canonicalForm );
	const lackedCharacters = hiddenCharacters.filter( ( character ) => !canonicalCharacters.has( character ) );

	return [ ...new Set( ( lackedCharacters.length > 0 ? lackedCharacters : hiddenCharacters ).map( codePoint ) ) ];
}

/**
 * Every problem with the property names of one Schema: the first rule each name breaks, or else whether it is a
 * near-duplicate of an earlier name.
 */
export function propertyNameProblemsInSchema( names: readonly string[] ): PropertyNameProblem[] {
	const keys = names.map( nearDuplicateKey );
	const indices = indicesByKey( keys );

	return names
		.map( ( _name, index ) => problemInSchema( names, indices.get( keys[ index ] ) ?? [], index ) )
		.filter( ( problem ): problem is PropertyNameProblem => problem !== null );
}

// The indices of the names with each key, in order. Names too long to compare have none.
function indicesByKey( keys: readonly ( string | null )[] ): Map<string | null, number[]> {
	const indices = new Map<string | null, number[]>();

	keys.forEach( ( key, index ) => {
		if ( key === null ) {
			return;
		}

		const sameKey = indices.get( key );

		if ( sameKey === undefined ) {
			indices.set( key, [ index ] );
		} else {
			sameKey.push( index );
		}
	} );

	return indices;
}

// Null for a name too long to compare, which is never normalized.
function nearDuplicateKey( name: string ): string | null {
	if ( byteLength( name ) > MAX_BYTES ) {
		return null;
	}

	// Lowercasing writes Σ as ς or σ by the letters around it, which PHP and browsers judge by different Unicode
	// versions.
	const key = withoutCodePointsIn(
		DEFAULT_IGNORABLE_CODE_POINTS,
		canonicalPropertyName( name ).normalize( 'NFKC' ).toLowerCase().replace( /ς/g, 'σ' ),
	);

	return collapseWhiteSpace( key.replace( /_/g, ' ' ) ).normalize( 'NFKC' );
}

// A name to respell is a near-duplicate of a later name too, so that no problem advises a spelling another name has.
// sameKeyIndices are the indices of the names with this name's near-duplicate key, in order.
function problemInSchema( names: readonly string[], sameKeyIndices: readonly number[], index: number ): PropertyNameProblem | null {
	const problem = propertyNameProblem( names[ index ] );

	if ( problem !== null && problem.rule !== 'canonical-form' ) {
		return problem;
	}

	const otherIndex = sameKeyIndices.find( ( otherIndex ) =>
		otherIndex < index || ( problem !== null && otherIndex > index ) );

	return otherIndex === undefined ?
		problem :
		{ name: names[ index ], rule: 'near-duplicate', details: [ names[ otherIndex ] ] };
}

/**
 * The first rule the name breaks as the name of a property whose Schema gives its other properties otherNames, or
 * null.
 */
export function propertyNameProblemBeside( name: string, otherNames: readonly string[] ): PropertyNameProblem | null {
	const names = [ ...otherNames, name ];
	const keys = names.map( nearDuplicateKey );

	return problemInSchema( names, indicesByKey( keys ).get( keys[ otherNames.length ] ) ?? [], otherNames.length );
}
