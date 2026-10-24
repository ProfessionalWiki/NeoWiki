import type { PropertyNameProblem } from '@/domain/propertyNameRules.ts';

/**
 * Worded as the wiki words the problem when it refuses to save the name, in PHP's PropertyNameProblemMessage.
 */
export function propertyNameProblemMessage( problem: PropertyNameProblem ): string {
	const { name, details } = problem;

	switch ( problem.rule ) {
		case 'canonical-form':
			return canonicalFormMessage( name, details );
		case 'empty':
			return mw.message( 'neowiki-property-name-empty' ).text();
		case 'characters':
			return mw.message( 'neowiki-property-name-characters', name, mw.language.listToText( [ ...details ] ) ).text();
		case 'integer':
			return mw.message( 'neowiki-property-name-integer', name ).text();
		case 'dot-segment':
			return mw.message( 'neowiki-property-name-dot-segment' ).text();
		case 'length':
			return mw.message( 'neowiki-property-name-too-long', name ).text();
		case 'reserved':
			return mw.message( 'neowiki-property-name-reserved', name ).text();
		case 'near-duplicate':
			return mw.message( 'neowiki-property-name-near-duplicate', name, details[ 0 ] ).text();
	}
}

function canonicalFormMessage( name: string, details: readonly string[] ): string {
	const [ canonicalForm, ...hiddenCharacters ] = details;

	if ( hiddenCharacters.length === 0 ) {
		return mw.message( 'neowiki-property-name-not-canonical', name, canonicalForm ).text();
	}

	return mw.message(
		'neowiki-property-name-not-canonical-invisible',
		name,
		canonicalForm,
		mw.language.listToText( hiddenCharacters ),
	).text();
}
