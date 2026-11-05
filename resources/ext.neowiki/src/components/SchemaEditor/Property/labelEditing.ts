import { type MonolingualTextValue, newMonolingualTextValue } from '@/domain/Value';
import type { SubjectViolation } from '@/domain/SubjectViolation';

export function labelsAsValue( labels: Readonly<Record<string, string>> | undefined ): MonolingualTextValue {
	return newMonolingualTextValue(
		Object.entries( labels ?? {} ).map( ( [ language, text ] ) => ( { text: text, language: language } ) ),
	);
}

/**
 * The first label in each language: a later one is held back by repeatedLanguageViolations().
 */
export function labelsOf( value: MonolingualTextValue | undefined ): Record<string, string> | undefined {
	const labels = new Map<string, string>();

	for ( const part of value?.parts ?? [] ) {
		if ( !labels.has( part.language ) ) {
			labels.set( part.language, part.text );
		}
	}

	return labels.size > 0 ? Object.fromEntries( labels ) : undefined;
}

/**
 * One for each label in a language an earlier label is in, in the shape the backend reports a Subject's, so the
 * input shows it under that label.
 */
export function repeatedLanguageViolations( value: MonolingualTextValue | undefined, propertyName: string ): SubjectViolation[] {
	const languages = new Set<string>();
	const violations: SubjectViolation[] = [];

	( value?.parts ?? [] ).forEach( ( part, index ) => {
		if ( languages.has( part.language ) ) {
			violations.push( {
				propertyName: propertyName,
				code: 'single-value-per-language',
				args: [],
				severity: 'error',
				valuePartIndex: index,
			} );
		}

		languages.add( part.language );
	} );

	return violations;
}
