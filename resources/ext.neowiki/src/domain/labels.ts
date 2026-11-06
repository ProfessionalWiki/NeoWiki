/**
 * The labels of a Schema or Property Definition as stored: trimmed, keyed by lowercase language tag, without empty
 * ones. Undefined when none is left.
 */
export function labelsFromJson( json: unknown ): Record<string, string> | undefined {
	const labels: Record<string, string> = {};
	const entries = json !== null && typeof json === 'object' && !Array.isArray( json ) ? Object.entries( json ) : [];

	for ( const [ language, label ] of entries ) {
		if ( typeof label === 'string' && label.trim() !== '' ) {
			labels[ language.toLowerCase() ] = label.trim();
		}
	}

	return Object.keys( labels ).length > 0 ? labels : undefined;
}
