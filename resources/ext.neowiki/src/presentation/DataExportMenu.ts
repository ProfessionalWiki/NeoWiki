/**
 * Builds the export endpoint URLs for the Data tab, for a single Subject or for all Subjects on a
 * page: a JSON URL, plus a function producing the RDF URL for a given projection and serialization
 * format. Kept as pure functions so URL derivation is unit-tested independently of the Vue component.
 */

const NATIVE_PROJECTION = 'native';

function restApiBase(): string {
	return mw.util.wikiScript( 'rest' );
}

export function projectionLabel( projection: string ): string {
	return projection === NATIVE_PROJECTION ?
		mw.msg( 'neowiki-managesubjects-export-native' ) :
		projection;
}

export interface ExportUrls {
	jsonUrl: string;
	rdfUrl( projection: string, format: 'turtle' | 'trig' ): string;
}

function exportUrls( jsonUrl: string, rdfEndpoint: string ): ExportUrls {
	return {
		jsonUrl,
		rdfUrl: ( projection, format ) =>
			`${ rdfEndpoint }?projection=${ encodeURIComponent( projection ) }&format=${ format }`,
	};
}

export function subjectExportUrls( subjectId: string ): ExportUrls {
	const base = `${ restApiBase() }/neowiki/v0/subject/${ encodeURIComponent( subjectId ) }`;
	return exportUrls( base, `${ base }/rdf` );
}

export function pageExportUrls( pageId: number ): ExportUrls {
	const base = `${ restApiBase() }/neowiki/v0/page/${ pageId }`;
	return exportUrls( `${ base }/subjects`, `${ base }/rdf` );
}
