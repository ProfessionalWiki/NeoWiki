import type { MenuButtonItemData } from '@wikimedia/codex';

/**
 * Builds the export menu items for the Data tab: a JSON entry, then Turtle and TriG entries for each
 * RDF projection the viewing user may read (native first, then ontology mappings). Item values are the
 * full endpoint URLs; the menu navigates to them on selection. Kept as pure functions so the URL and
 * label derivation is unit-tested independently of the Vue component.
 */

interface RdfFormatOption {
	format: string;
	messageKey: string;
}

const RDF_FORMATS: readonly RdfFormatOption[] = [
	{ format: 'turtle', messageKey: 'neowiki-managesubjects-export-turtle' },
	{ format: 'trig', messageKey: 'neowiki-managesubjects-export-trig' },
];

const NATIVE_PROJECTION = 'native';

function restApiBase(): string {
	return mw.util.wikiScript( 'rest' );
}

function projectionLabel( projection: string ): string {
	return projection === NATIVE_PROJECTION ?
		mw.msg( 'neowiki-managesubjects-export-native' ) :
		projection;
}

function exportMenuItems(
	jsonUrl: string,
	rdfEndpoint: string,
	projections: readonly string[],
): MenuButtonItemData[] {
	const items: MenuButtonItemData[] = [
		{ value: jsonUrl, label: mw.msg( 'neowiki-managesubjects-export-json' ) },
	];

	for ( const projection of projections ) {
		for ( const { format, messageKey } of RDF_FORMATS ) {
			items.push( {
				value: `${ rdfEndpoint }?projection=${ encodeURIComponent( projection ) }&format=${ format }`,
				label: mw.msg( messageKey, projectionLabel( projection ) ),
			} );
		}
	}

	return items;
}

export function subjectExportMenuItems(
	subjectId: string,
	projections: readonly string[],
): MenuButtonItemData[] {
	const base = `${ restApiBase() }/neowiki/v0/subject/${ encodeURIComponent( subjectId ) }`;
	return exportMenuItems( base, `${ base }/rdf`, projections );
}

export function pageExportMenuItems(
	pageId: number,
	projections: readonly string[],
): MenuButtonItemData[] {
	const base = `${ restApiBase() }/neowiki/v0/page/${ pageId }`;
	return exportMenuItems( `${ base }/subjects`, `${ base }/rdf`, projections );
}
