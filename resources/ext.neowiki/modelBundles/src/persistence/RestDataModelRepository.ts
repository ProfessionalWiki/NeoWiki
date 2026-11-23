import {
	LayoutDeserializer,
	LayoutSerializer,
	normalizeSchemaName,
	PageTitleTakenError,
	parseViolations,
	SchemaDeserializer,
	schemaReferenceName,
	SchemaSerializer,
	type HttpClient,
	type PageRevisionSaver,
	type SchemaReference,
	type SchemaRepository,
	type SchemaSummary,
} from 'ext.neowiki';
import { type ActionApi, actionApiCall } from './ActionApi';
import { savedForm } from './savedForm';
import { isJsonObject } from '../application/canonical';
import { mapConcurrently } from '../application/mapConcurrently';
import type { CurrentSubject, DataModelRepository, LayoutSummary, ReadPage } from '../application/DataModelRepository';
import type { DependentSubjectEntry, JsonObject, SubjectEntry } from '../application/ModelBundle';

interface HostJson {
	id: string;
	hostSubject?: HostJson;
}

interface SubjectJson {
	id: string;
	label: string | null;
	schema: SchemaReference;
	statements: JsonObject;
	hostSubject?: HostJson;
	pageId?: number;
}

interface PageSubjectsJson {
	mainSubjectId: string | null;
	subjects: Record<string, SubjectJson & { pageTitle?: string; pageNamespaceId?: number }>;
}

const PAGE_SIZE = 50;
const TITLES_PER_QUERY = 50;
const MAIN_NAMESPACE = 0;

/**
 * Reads are of the latest revision, which a write replaces: Subjects through `?latest=1`, pages through the
 * page endpoint, which the browser is told not to answer from its cache.
 */
export class RestDataModelRepository implements DataModelRepository {

	public constructor(
		private readonly mediaWikiRestApiUrl: string,
		private readonly httpClient: HttpClient,
		private readonly schemaRepository: SchemaRepository,
		private readonly pageSaver: PageRevisionSaver,
		private readonly actionApi: ActionApi,
	) {
	}

	public async getSchemaSummaries(): Promise<SchemaSummary[]> {
		return this.pageThrough( async ( cursor ) => {
			const page = await this.schemaRepository.getSchemaSummaries( cursor, PAGE_SIZE );
			return { items: page.schemas, nextCursor: page.nextCursor };
		} );
	}

	public async getLayoutSummaries(): Promise<LayoutSummary[]> {
		return this.pageThrough( async ( cursor ) => {
			const page = await this.getJson( this.listUrl( 'layouts', cursor ) ) as { layouts: LayoutSummary[]; nextCursor: string | null };
			return { items: page.layouts.map( ( { name, schema } ) => ( { name, schema } ) ), nextCursor: page.nextCursor };
		} );
	}

	public async getMappingNames(): Promise<string[]> {
		return this.pageThrough( async ( cursor ) => {
			const page = await this.getJson( this.listUrl( 'mappings', cursor ) ) as { mappings: { name: string }[]; nextCursor: string | null };
			return { items: page.mappings.map( ( mapping ) => mapping.name ), nextCursor: page.nextCursor };
		} );
	}

	private listUrl( list: string, cursor: string | null ): string {
		const cursorParameter = cursor === null ? '' : `&cursor=${ encodeURIComponent( cursor ) }`;
		return `${ this.mediaWikiRestApiUrl }/neowiki/v0/${ list }?limit=${ PAGE_SIZE }${ cursorParameter }`;
	}

	private async pageThrough<T>( fetchPage: ( cursor: string | null ) => Promise<{ items: T[]; nextCursor: string | null }> ): Promise<T[]> {
		const items: T[] = [];
		let cursor: string | null = null;

		do {
			const page = await fetchPage( cursor );
			items.push( ...page.items );
			cursor = page.nextCursor;
		} while ( cursor !== null );

		return items;
	}

	public async getSchema( name: string ): Promise<ReadPage> {
		const page = await this.getPage( 'Schema', name );
		return { ...page, json: savedForm( 'schema', name, page.json ) };
	}

	public async getLayout( name: string ): Promise<ReadPage> {
		const page = await this.getPage( 'Layout', name );
		return { ...page, json: savedForm( 'layout', name, page.json ) };
	}

	public getMapping( name: string ): Promise<ReadPage> {
		return this.getPage( 'Mapping', name );
	}

	private async getPage( namespace: string, name: string ): Promise<ReadPage> {
		const page = await this.getJson(
			`${ this.mediaWikiRestApiUrl }/v1/page/${ namespace }:${ encodeURIComponent( name ) }`,
			{ headers: { 'Cache-Control': 'no-cache' } },
		) as { source: string; latest: { id: number } };
		const json = JSON.parse( page.source );

		if ( !isJsonObject( json ) ) {
			throw new Error( `${ namespace }:${ name } does not hold a JSON object` );
		}

		return { json, revisionId: page.latest.id };
	}

	public async getSubject( id: string ): Promise<CurrentSubject | null> {
		const data = await this.getJson(
			`${ this.mediaWikiRestApiUrl }/neowiki/v0/subject/${ encodeURIComponent( id ) }?latest=1&expand=page`,
		) as { subjects?: Record<string, SubjectJson> };
		const subject = data.subjects?.[ id ];

		if ( subject === undefined ) {
			return null;
		}

		if ( subject.hostSubject !== undefined ) {
			return { entry: entryOf( subject, [], null ), hostRootId: rootOf( subject.hostSubject ) };
		}

		const dependents = hasRelations( subject ) && subject.pageId !== undefined ?
			dependentsOf( id, await this.getPageSubjects( subject.pageId ) ) :
			[];

		return { entry: entryOf( subject, dependents, null ), hostRootId: null };
	}

	private async getPageSubjects( pageId: number ): Promise<PageSubjectsJson> {
		return await this.getJson( `${ this.mediaWikiRestApiUrl }/neowiki/v0/page/${ pageId }/subjects` ) as PageSubjectsJson;
	}

	public async getSubjectsOfSchema( schemaName: string ): Promise<Map<string, SubjectEntry>> {
		const rows = await this.getSubjectRows( schemaName );
		const pages = await mapConcurrently(
			[ ...new Set( rows.map( ( row ) => row.pageId ) ) ],
			( pageId ) => this.getPageSubjects( pageId ),
		);
		const schema = normalizeSchemaName( schemaName );
		const entries = new Map<string, SubjectEntry>();

		for ( const page of pages ) {
			for ( const subject of Object.values( page.subjects ) ) {
				if ( subject.hostSubject !== undefined || normalizeSchemaName( schemaReferenceName( subject.schema ) ) !== schema ) {
					continue;
				}

				const titled = page.mainSubjectId === subject.id && subject.pageNamespaceId === MAIN_NAMESPACE;
				entries.set( subject.id, entryOf( subject, dependentsOf( subject.id, page ), titled ? subject.pageTitle ?? null : null ) );
			}
		}

		return entries;
	}

	public async hasSubjectsOutside( schemaName: string, ids: ReadonlySet<string> ): Promise<boolean> {
		let cursor: string | null = null;

		do {
			const page = await this.getSubjectRowPage( schemaName, cursor );

			if ( page.subjects.some( ( row ) => !ids.has( row.id ) ) ) {
				return true;
			}

			cursor = page.nextCursor;
		} while ( cursor !== null );

		return false;
	}

	private getSubjectRows( schemaName: string ): Promise<{ id: string; pageId: number }[]> {
		return this.pageThrough( async ( cursor ) => {
			const page = await this.getSubjectRowPage( schemaName, cursor );
			return { items: page.subjects, nextCursor: page.nextCursor };
		} );
	}

	private async getSubjectRowPage(
		schemaName: string,
		cursor: string | null,
	): Promise<{ subjects: { id: string; pageId: number }[]; nextCursor: string | null }> {
		const parameters = new URLSearchParams( { schema: schemaName, limit: String( PAGE_SIZE ) } );

		if ( cursor !== null ) {
			parameters.set( 'cursor', cursor );
		}

		return await this.getJson( `${ this.mediaWikiRestApiUrl }/neowiki/v0/subjects?${ parameters.toString() }` ) as
			{ subjects: { id: string; pageId: number }[]; nextCursor: string | null };
	}

	public async getReferencingSubjectIds( id: string ): Promise<{ ids: string[]; truncated: boolean }> {
		const data = await this.getJson(
			`${ this.mediaWikiRestApiUrl }/neowiki/v0/subject/${ encodeURIComponent( id ) }/referencingSubjects?limit=50`,
		) as { referencingSubjects: { subject: { id: string } }[]; truncated: boolean };

		return { ids: data.referencingSubjects.map( ( entry ) => entry.subject.id ), truncated: data.truncated };
	}

	/**
	 * A title MediaWiki cannot parse is unavailable too; one with a `|` is not asked about, as it would split
	 * the query's list of titles.
	 */
	public async getUnavailablePageTitles( titles: string[] ): Promise<Set<string>> {
		const unavailable = new Set( titles.filter( ( title ) => title.includes( '|' ) ) );
		const asked = titles.filter( ( title ) => !unavailable.has( title ) );

		for ( let start = 0; start < asked.length; start += TITLES_PER_QUERY ) {
			const batch = asked.slice( start, start + TITLES_PER_QUERY );
			const response = await actionApiCall<TitleQueryResponse>( this.actionApi.post( {
				action: 'query',
				titles: batch.join( '|' ),
				formatversion: 2,
			} ) );

			unavailableIn( batch, response ).forEach( ( title ) => unavailable.add( title ) );
		}

		return unavailable;
	}

	public saveSchema( name: string, json: JsonObject, comment: string, baseRevisionId: number | null ): Promise<void> {
		const source = new SchemaSerializer().serializeSchema( new SchemaDeserializer().deserialize( name, json ) );
		return this.savePage( 'Schema', name, source, 'NeoWikiSchema', comment, baseRevisionId );
	}

	public saveLayout( name: string, json: JsonObject, comment: string, baseRevisionId: number | null ): Promise<void> {
		const source = new LayoutSerializer().serializeLayout( new LayoutDeserializer().deserialize( name, json ) );
		return this.savePage( 'Layout', name, source, 'NeoWikiLayout', comment, baseRevisionId );
	}

	public saveMapping( name: string, json: JsonObject, comment: string, baseRevisionId: number | null ): Promise<void> {
		return this.savePage( 'Mapping', name, JSON.stringify( json, null, '\t' ), 'NeoWikiMapping', comment, baseRevisionId );
	}

	private async savePage(
		namespace: string,
		name: string,
		source: string,
		contentModel: string,
		comment: string,
		baseRevisionId: number | null,
	): Promise<void> {
		const status = await this.pageSaver.saveRevision(
			`${ namespace }:${ encodeURIComponent( name ) }`,
			source,
			comment,
			contentModel,
			baseRevisionId,
		);

		if ( !status.success ) {
			throw new Error( status.message );
		}
	}

	public async createSubject( id: string, entry: SubjectEntry, pageTitle: string | null, comment: string ): Promise<void> {
		const response = await this.httpClient.post(
			`${ this.mediaWikiRestApiUrl }/neowiki/v0/subjects`,
			{
				id,
				schema: entry.schema,
				...writeBody( entry ),
				...( pageTitle === null ? {} : { pageTitle } ),
				comment,
			},
		);

		const body = await bodyOf( response );

		if ( response.status === 409 && typeof body.pageTitle === 'string' ) {
			throw new PageTitleTakenError( body.pageTitle );
		}

		failUnlessOk( response, body );
	}

	public async addSubjectToEmptyPage( pageTitle: string, id: string, entry: SubjectEntry, comment: string ): Promise<boolean> {
		const pageId = await this.emptyPageId( pageTitle );

		if ( pageId === null ) {
			return false;
		}

		const response = await this.httpClient.post(
			`${ this.mediaWikiRestApiUrl }/neowiki/v0/page/${ pageId }/mainSubject`,
			{ id, schema: entry.schema, ...writeBody( entry ), comment },
		);

		failUnlessOk( response, await bodyOf( response ) );
		return true;
	}

	private async emptyPageId( pageTitle: string ): Promise<number | null> {
		const response = await actionApiCall<TitleQueryResponse>( this.actionApi.get( {
			action: 'query',
			titles: pageTitle,
			prop: 'info',
			formatversion: 2,
		} ) );
		const page = response.query?.pages?.[ 0 ];

		if ( page?.pageid === undefined || page.redirect === true ) {
			return null;
		}

		const subjects = await this.getPageSubjects( page.pageid );
		return Object.keys( subjects.subjects ).length === 0 ? page.pageid : null;
	}

	public async replaceSubject( id: string, entry: SubjectEntry, comment: string ): Promise<void> {
		const response = await this.httpClient.put(
			`${ this.mediaWikiRestApiUrl }/neowiki/v0/subject/${ encodeURIComponent( id ) }`,
			{ ...writeBody( entry ), comment },
		);

		failUnlessOk( response, await bodyOf( response ) );
	}

	public async deleteSubject( id: string, comment: string ): Promise<void> {
		const response = await this.httpClient.delete(
			`${ this.mediaWikiRestApiUrl }/neowiki/v0/subject/${ encodeURIComponent( id ) }`,
			{ data: { comment } },
		);

		failUnlessOk( response, await bodyOf( response ) );
	}

	public async deleteSchema( name: string, comment: string ): Promise<void> {
		const response = await this.httpClient.delete(
			`${ this.mediaWikiRestApiUrl }/neowiki/v0/schema/${ encodeURIComponent( name ) }`,
			{ data: { comment } },
		);

		failUnlessOk( response, await bodyOf( response ) );
	}

	public deleteLayout( name: string, comment: string ): Promise<void> {
		return this.deletePage( `Layout:${ name }`, comment );
	}

	public deleteMapping( name: string, comment: string ): Promise<void> {
		return this.deletePage( `Mapping:${ name }`, comment );
	}

	private async deletePage( title: string, comment: string ): Promise<void> {
		await actionApiCall( this.actionApi.postWithEditToken( { action: 'delete', title, reason: comment } ) );
	}

	private async getJson( url: string, config?: Record<string, unknown> ): Promise<unknown> {
		const response = await this.httpClient.get( url, config );
		const body = await bodyOf( response );

		failUnlessOk( response, body );
		return body;
	}

}

function entryOf( subject: SubjectJson, dependentSubjects: DependentSubjectEntry[], pageTitle: string | null ): SubjectEntry {
	return {
		schema: schemaReferenceName( subject.schema ),
		label: subject.label,
		statements: subject.statements,
		dependentSubjects,
		pageTitle,
	};
}

function rootOf( host: HostJson ): string {
	return host.hostSubject === undefined ? host.id : rootOf( host.hostSubject );
}

function hasRelations( subject: SubjectJson ): boolean {
	return Object.values( subject.statements ).some(
		( statement ) => isJsonObject( statement ) && Array.isArray( statement.value ) &&
			statement.value.some( ( part ) => isJsonObject( part ) && typeof part.target === 'string' ),
	);
}

/**
 * The Dependent Subjects whose chain of Host Subjects starts at the given Subject.
 */
function dependentsOf( id: string, page: PageSubjectsJson ): DependentSubjectEntry[] {
	return Object.values( page.subjects )
		.filter( ( subject ) => subject.hostSubject !== undefined && rootOf( subject.hostSubject ) === id )
		.map( ( subject ) => ( {
			id: subject.id,
			schema: schemaReferenceName( subject.schema ),
			statements: subject.statements,
		} ) );
}

function writeBody( entry: SubjectEntry ): JsonObject {
	return {
		...( entry.label === null ? {} : { label: entry.label } ),
		statements: entry.statements,
		...( entry.dependentSubjects.length === 0 ? {} : { dependentSubjects: entry.dependentSubjects } ),
	};
}

async function bodyOf( response: Response ): Promise<JsonObject> {
	try {
		const body = await response.json();
		return isJsonObject( body ) ? body : {};
	} catch {
		return {};
	}
}

function failUnlessOk( response: Response, body: JsonObject ): void {
	if ( !response.ok ) {
		throw new Error( reasonIn( body ) ?? `HTTP ${ response.status }` );
	}
}

/**
 * A refused Subject write names its violations; another error carries a message, translated or not.
 */
function reasonIn( body: JsonObject ): string | null {
	const violations = parseViolations( body );

	if ( violations !== null && violations.length > 0 ) {
		return mw.msg( 'neowiki-bundle-write-violations', violations
			.map( ( violation ) => violation.propertyName ? `${ violation.code } (${ violation.propertyName })` : violation.code )
			.join( mw.msg( 'comma-separator' ) ) );
	}

	if ( isJsonObject( body.messageTranslations ) ) {
		const translated = Object.values( body.messageTranslations )[ 0 ];

		if ( typeof translated === 'string' && translated !== '' ) {
			return translated;
		}
	}

	return typeof body.message === 'string' && body.message !== '' ? body.message : null;
}

interface TitleQueryResponse {
	query?: {
		normalized?: { from: string; to: string }[];
		pages?: { title: string; pageid?: number; missing?: boolean; invalid?: boolean; redirect?: boolean }[];
	};
}

function unavailableIn( titles: string[], response: TitleQueryResponse ): string[] {
	const normalized = new Map( ( response.query?.normalized ?? [] ).map( ( { from, to } ) => [ from, to ] ) );
	const free = new Set( ( response.query?.pages ?? [] )
		.filter( ( page ) => page.missing === true && page.invalid !== true )
		.map( ( page ) => page.title ) );

	return titles.filter( ( title ) => !free.has( normalized.get( title ) ?? title ) );
}
