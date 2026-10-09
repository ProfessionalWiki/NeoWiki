import type { MenuItemData } from '@wikimedia/codex';

/**
 * What the Subject UI's export controls offer, for a single Subject or for all Subjects on a page: the
 * JSON URL, the projections worth offering RDF in, and the RDF menu's items. Kept as pure functions so
 * this is unit-tested independently of the Vue component.
 */

const NATIVE_PROJECTION = 'native';

export type RdfFormat = 'turtle' | 'trig';

const RDF_FORMATS: readonly { format: RdfFormat; messageKey: string }[] = [
	{ format: 'turtle', messageKey: 'neowiki-managesubjects-export-format-turtle' },
	{ format: 'trig', messageKey: 'neowiki-managesubjects-export-format-trig' },
];

/**
 * A Mapping as the export menus see it: its name, which names its projection, and the Schemas it maps.
 */
export interface MappingSummary {
	readonly name: string;
	readonly schemas: readonly string[];
}

function restApiBase(): string {
	return mw.util.wikiScript( 'rest' );
}

function projectionLabel( projection: string ): string {
	return projection === NATIVE_PROJECTION ?
		mw.msg( 'neowiki-managesubjects-export-native' ) :
		projection;
}

export interface ExportUrls {
	jsonUrl: string;
	rdfUrl( projection: string, format: RdfFormat ): string;
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

/**
 * The projections that hold data for Subjects of the given Schemas: native always, and each Mapping that
 * maps one of the Schemas. A Mapping projects only the Subjects whose Schema it maps, so any other
 * Mapping's export of them would be empty.
 */
export function rdfProjectionsFor( schemaNames: readonly string[], mappings: readonly MappingSummary[] ): string[] {
	return [
		NATIVE_PROJECTION,
		...mappings
			.filter( ( mapping ) => mapping.schemas.some( ( schema ) => schemaNames.includes( schema ) ) )
			.map( ( mapping ) => mapping.name ),
	];
}

/**
 * One item per projection and format, its value the URL it opens. Items name their projection only when
 * there is more than one to choose from.
 */
export function rdfMenuItems(
	projections: readonly string[],
	rdfUrl: ExportUrls['rdfUrl'],
): MenuItemData[] {
	return projections.flatMap( ( projection ) => RDF_FORMATS.map( ( { format, messageKey } ): MenuItemData => ( {
		value: rdfUrl( projection, format ),
		label: projections.length === 1 ?
			mw.msg( messageKey ) :
			mw.msg( 'neowiki-managesubjects-export-projection-format', projectionLabel( projection ), mw.msg( messageKey ) ),
	} ) ) );
}
