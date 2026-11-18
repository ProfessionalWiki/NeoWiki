import type { Schema } from '@/domain/Schema';
import type { SchemaSummary } from '@/application/SchemaLookup';
import { PageTitleTakenError } from '@/persistence/PageTitleTakenError';
import { normalizeSchemaName } from '@/stores/SchemaStore';
import type {
	CurrentSubject,
	DataModelRepository,
	InstallRecordRepository,
	LayoutSummary,
	ReadPage,
} from '../../src/application/DataModelRepository';
import type { JsonObject, SubjectEntry } from '../../src/application/ModelBundle';
import type { InstallRecord } from '../../src/application/InstallRecord';
import { sameBundleName } from '../../src/application/bundleName';
import { schemaJson } from './builders';

/**
 * A wiki held in memory, which records the writes made to it in the order they were made, such as
 * `create subject:s1demo1aaaaaaa1` and `delete schema:Person`.
 */
export class InMemoryDataModel implements DataModelRepository, InstallRecordRepository {

	public readonly schemas = new Map<string, JsonObject>();

	public readonly layouts = new Map<string, JsonObject>();

	public readonly mappings = new Map<string, JsonObject>();

	/** Standalone Subjects, their Dependent Subjects inside. */
	public readonly subjects = new Map<string, SubjectEntry>();

	/** Whether the wiki is subject-first, which titles each Subject's own page by its ID, in the Subject namespace. */
	public subjectFirst = false;

	public readonly takenPageTitles = new Set<string>();

	/** Of pages left without Subjects, as deleting a Subject leaves its page. */
	public readonly emptyPageTitles = new Set<string>();

	private readonly pageTitles = new Map<string, string>();

	public readonly referencingSubjects = new Map<string, string[]>();

	public readonly unreadable = new Set<string>();

	/** Writes that fail, by `<type>:<key>`, with the reason they fail with. */
	public readonly failingWrites = new Map<string, string>();

	public records: InstallRecord[] = [];

	public readonly writes: string[] = [];

	/** Of the Schema, Layout and Mapping pages saved since they were set up, which start at revision 1. */
	private readonly revisions = new Map<string, number>();

	public withSchemas( ...schemas: Schema[] ): this {
		schemas.forEach( ( schema ) => this.schemas.set( schema.getName(), schemaJson( schema ) ) );
		return this;
	}

	public withSubject( id: string, entry: SubjectEntry ): this {
		this.subjects.set( id, entry );
		return this;
	}

	public async getSchemaSummaries(): Promise<SchemaSummary[]> {
		return [ ...this.schemas ].map( ( [ name, json ] ) => ( {
			name,
			description: String( json.description ?? '' ),
			propertyCount: 0,
			subjectsAreDependent: json.subjectsAreDependent === true,
		} ) );
	}

	public async getLayoutSummaries(): Promise<LayoutSummary[]> {
		return [ ...this.layouts ].map( ( [ name, json ] ) => ( { name, schema: json.schema as string } ) );
	}

	public async getMappingNames(): Promise<string[]> {
		return [ ...this.mappings.keys() ];
	}

	public async getSchema( name: string ): Promise<ReadPage> {
		return this.readPage( `schema:${ name }`, this.schemas.get( name ) );
	}

	public async getLayout( name: string ): Promise<ReadPage> {
		return this.readPage( `layout:${ name }`, this.layouts.get( name ) );
	}

	public async getMapping( name: string ): Promise<ReadPage> {
		return this.readPage( `mapping:${ name }`, this.mappings.get( name ) );
	}

	private readPage( item: string, json: JsonObject | undefined ): ReadPage {
		this.failIfUnreadable( item );
		return { json: this.found( json, item ), revisionId: this.revisions.get( item ) ?? 1 };
	}

	public async getSubject( id: string ): Promise<CurrentSubject | null> {
		this.failIfUnreadable( `subject:${ id }` );
		const entry = this.subjects.get( id );

		if ( entry !== undefined ) {
			return { entry: { ...entry, pageTitle: null }, hostRootId: null };
		}

		for ( const [ hostId, host ] of this.subjects ) {
			const dependent = host.dependentSubjects.find( ( candidate ) => candidate.id === id );

			if ( dependent !== undefined ) {
				return {
					entry: { schema: dependent.schema, label: null, statements: dependent.statements, dependentSubjects: [], pageTitle: null },
					hostRootId: hostId,
				};
			}
		}

		return null;
	}

	public async getSubjectsOfSchema( schemaName: string ): Promise<Map<string, SubjectEntry>> {
		return new Map( [ ...this.subjects ].filter( ( [ , entry ] ) => entry.schema === schemaName ) );
	}

	public async hasSubjectsOutside( schemaName: string, ids: ReadonlySet<string> ): Promise<boolean> {
		const ofSchema = [ ...this.subjects ].flatMap( ( [ id, entry ] ) => [
			...( normalizeSchemaName( entry.schema ) === normalizeSchemaName( schemaName ) ? [ id ] : [] ),
			...entry.dependentSubjects.filter( ( dependent ) => dependent.schema === schemaName ).map( ( dependent ) => dependent.id ),
		] );

		return ofSchema.some( ( id ) => !ids.has( id ) );
	}

	public async getReferencingSubjectIds( id: string ): Promise<{ ids: string[]; truncated: boolean }> {
		return { ids: this.referencingSubjects.get( id ) ?? [], truncated: false };
	}

	public async getUnavailablePageTitles( titles: string[] ): Promise<Set<string>> {
		return new Set( titles.filter( ( title ) => this.takenPageTitles.has( title ) ) );
	}

	public async saveSchema( name: string, json: JsonObject, _comment: string, baseRevisionId: number | null ): Promise<void> {
		this.savePage( `schema:${ name }`, this.schemas.has( name ), baseRevisionId );
		this.schemas.set( name, json );
	}

	public async saveLayout( name: string, json: JsonObject, _comment: string, baseRevisionId: number | null ): Promise<void> {
		this.savePage( `layout:${ name }`, this.layouts.has( name ), baseRevisionId );
		this.layouts.set( name, json );
	}

	public async saveMapping( name: string, json: JsonObject, _comment: string, baseRevisionId: number | null ): Promise<void> {
		this.savePage( `mapping:${ name }`, this.mappings.has( name ), baseRevisionId );
		this.mappings.set( name, json );
	}

	/**
	 * Fails on a change made since the revision given, or on a page created since it was found missing.
	 */
	private savePage( item: string, exists: boolean, baseRevisionId: number | null ): void {
		const current = exists ? this.revisions.get( item ) ?? 1 : null;

		if ( current !== baseRevisionId ) {
			throw new Error( 'Edit conflict' );
		}

		this.write( 'save', item );
		this.revisions.set( item, ( current ?? 0 ) + 1 );
	}

	public async createSubject( id: string, entry: SubjectEntry, pageTitle: string | null ): Promise<void> {
		const title = this.subjectFirst ? `Subject:${ id }` : pageTitle ?? entry.label ?? id;

		if ( this.takenPageTitles.has( title ) ) {
			throw new PageTitleTakenError( title );
		}

		this.write( 'create', `subject:${ id }`, pageTitle === null ? '' : ` on ${ pageTitle }` );
		this.takenPageTitles.add( title );
		this.pageTitles.set( id, title );
		this.subjects.set( id, { ...entry, pageTitle: null } );
	}

	public async addSubjectToEmptyPage( pageTitle: string, id: string, entry: SubjectEntry ): Promise<boolean> {
		if ( !this.emptyPageTitles.has( pageTitle ) ) {
			return false;
		}

		this.write( 'create', `subject:${ id }`, ` on ${ pageTitle }` );
		this.emptyPageTitles.delete( pageTitle );
		this.pageTitles.set( id, pageTitle );
		this.subjects.set( id, { ...entry, pageTitle: null } );
		return true;
	}

	public async replaceSubject( id: string, entry: SubjectEntry ): Promise<void> {
		this.write( 'replace', `subject:${ id }` );
		this.subjects.set( id, { ...entry, schema: this.subjects.get( id )?.schema ?? entry.schema, pageTitle: null } );
	}

	public async deleteSchema( name: string ): Promise<void> {
		this.write( 'delete', `schema:${ name }` );
		this.schemas.delete( name );
	}

	public async deleteLayout( name: string ): Promise<void> {
		this.write( 'delete', `layout:${ name }` );
		this.layouts.delete( name );
	}

	public async deleteMapping( name: string ): Promise<void> {
		this.write( 'delete', `mapping:${ name }` );
		this.mappings.delete( name );
	}

	public async deleteSubject( id: string ): Promise<void> {
		this.write( 'delete', `subject:${ id }` );
		this.subjects.delete( id );

		const pageTitle = this.pageTitles.get( id );

		if ( pageTitle !== undefined ) {
			this.emptyPageTitles.add( pageTitle );
		}
	}

	public async getInstallRecords(): Promise<InstallRecord[]> {
		return this.records;
	}

	/**
	 * Saves over the revision the record was read from, as the wiki does, failing on a change made since.
	 */
	public async saveInstallRecord( record: InstallRecord ): Promise<void> {
		this.failIfWriteFails( 'record' );
		const current = this.recordOf( record.name );

		if ( ( current?.revisionId ?? null ) !== record.revisionId ) {
			throw new Error( 'Edit conflict' );
		}

		this.records = [
			...this.records.filter( ( other ) => other !== current ),
			{ ...record, revisionId: ( current?.revisionId ?? 0 ) + 1 },
		];
	}

	public recordOf( name: string ): InstallRecord | undefined {
		return this.records.find( ( record ) => sameBundleName( record.name, name ) );
	}

	private write( operation: string, item: string, detail = '' ): void {
		this.failIfWriteFails( item );
		this.writes.push( `${ operation } ${ item }${ detail }` );
	}

	private failIfWriteFails( item: string ): void {
		const reason = this.failingWrites.get( item );

		if ( reason !== undefined ) {
			throw new Error( reason );
		}
	}

	private failIfUnreadable( item: string ): void {
		if ( this.unreadable.has( item ) ) {
			throw new Error( `Cannot read ${ item }` );
		}
	}

	private found<T>( value: T | undefined, name: string ): T {
		if ( value === undefined ) {
			throw new Error( `No ${ name }` );
		}

		return value;
	}

}
