import type { SchemaSummary } from '@/application/SchemaLookup';
import type { JsonObject, SubjectEntry } from '@/application/modelBundle/ModelBundle';
import type { InstallRecord } from '@/application/modelBundle/InstallRecord';

export interface LayoutSummary {
	readonly name: string;
	readonly schema: string;
}

export interface ReadPage {
	readonly json: JsonObject;
	/** To save over, so that a change made since it was read is not overwritten. */
	readonly revisionId: number;
}

export interface CurrentSubject {
	/** Without a page title, which comparisons leave out. */
	readonly entry: SubjectEntry;
	/** For a Dependent Subject, the standalone Subject its chain of Host Subjects starts at; null otherwise. */
	readonly hostRootId: string | null;
}

/**
 * Reads give the latest revision, which is what a write replaces. Schemas and Layouts are read in the form
 * NeoWiki saves them in.
 */
export interface DataModelRepository {
	getSchemaSummaries(): Promise<SchemaSummary[]>;
	getLayoutSummaries(): Promise<LayoutSummary[]>;
	getMappingNames(): Promise<string[]>;

	getSchema( name: string ): Promise<ReadPage>;
	getLayout( name: string ): Promise<ReadPage>;
	getMapping( name: string ): Promise<ReadPage>;
	/** Null for an ID no Subject has. */
	getSubject( id: string ): Promise<CurrentSubject | null>;

	/**
	 * The Schema's standalone Subjects, with their Dependent Subjects. Each carries the title of its page
	 * when it is the Main Subject of a page in the main namespace, the only titles a new page can take.
	 */
	getSubjectsOfSchema( schemaName: string ): Promise<Map<string, SubjectEntry>>;
	hasSubjectsOutside( schemaName: string, ids: ReadonlySet<string> ): Promise<boolean>;
	/** Truncated when more were left out. */
	getReferencingSubjectIds( id: string ): Promise<{ ids: string[]; truncated: boolean }>;
	/** Those taken, or not valid as the title of a page in the main namespace. */
	getUnavailablePageTitles( titles: string[] ): Promise<Set<string>>;

	/**
	 * Saves the page over the revision given, failing on a change made since that MediaWiki cannot merge into
	 * the save. A null revision creates the page, failing when one was created meanwhile.
	 */
	saveSchema( name: string, json: JsonObject, comment: string, baseRevisionId: number | null ): Promise<void>;
	saveLayout( name: string, json: JsonObject, comment: string, baseRevisionId: number | null ): Promise<void>;
	saveMapping( name: string, json: JsonObject, comment: string, baseRevisionId: number | null ): Promise<void>;
	/**
	 * Creates the Subject with the given ID on a page of its own, titled pageTitle where given.
	 * Throws a PageTitleTakenError when that title is taken.
	 */
	createSubject( id: string, entry: SubjectEntry, pageTitle: string | null, comment: string ): Promise<void>;
	/**
	 * Puts the Subject, keeping its ID, on the page titled by its ID as that page's Main Subject, when the page
	 * holds no Subjects and is no redirect, as deleting the Subject leaves it. False, writing nothing, for any
	 * other page.
	 */
	addSubjectToEmptyIdPage( id: string, entry: SubjectEntry, comment: string ): Promise<boolean>;
	replaceSubject( id: string, entry: SubjectEntry, comment: string ): Promise<void>;
	deleteSchema( name: string, comment: string ): Promise<void>;
	deleteLayout( name: string, comment: string ): Promise<void>;
	deleteMapping( name: string, comment: string ): Promise<void>;
	deleteSubject( id: string, comment: string ): Promise<void>;
}

export interface InstallRecordRepository {
	getInstallRecords(): Promise<InstallRecord[]>;
	saveInstallRecord( record: InstallRecord, comment: string ): Promise<void>;
}
