import { SubjectId } from '@/domain/SubjectId';
import type { SubjectLookup } from '@/domain/SubjectLookup';
import { InMemorySubjectLookup } from '@/domain/SubjectLookup';
import type { StatementList } from '@/domain/StatementList';
import type { Schema, SchemaName } from '@/domain/Schema';
import { PageSubjects } from '@/domain/PageSubjects';
import type { Subject } from '@/domain/Subject';
import { SubjectWithContext } from '@/domain/SubjectWithContext';
import { PageIdentifiers } from '@/domain/PageIdentifiers';
import type { DeserializedPageSubjects } from '@/persistence/PageSubjectsDeserializer';
import type { SubjectViolation } from '@/domain/SubjectViolation';

export interface SubjectWithReferencedSubjects {
	requestedSubject: Subject;
	referencedSubjects: Subject[];
}

/**
 * What a Subject write returns: the Subject as the server persisted it, carrying the context
 * (page identifiers) and normalisation a client-built copy cannot reproduce, plus the Schema it
 * is an instance of, so a display can render the saved values even when the Schema changed
 * out of band. The Schema is null when the server could not resolve it.
 */
export interface SubjectWriteResult {
	subject: SubjectWithContext;
	schema: Schema | null;
}

export interface SubjectRepository extends SubjectLookup {

	/**
	 * Returns the Subject together with the Subjects its relations target,
	 * so relation labels can be shown without fetching each target individually.
	 * Referenced Subjects that cannot be loaded are omitted rather than failing the whole call.
	 */
	getSubjectWithReferencedSubjects( id: SubjectId ): Promise<SubjectWithReferencedSubjects>;

	getPageSubjects( pageId: number ): Promise<DeserializedPageSubjects>;

	setMainSubject( pageId: number, subjectId: SubjectId | null, comment?: string ): Promise<void>;

	setSubjectsOrdering(
		pageId: number,
		mainSubjectId: SubjectId | null,
		childSubjectIds: SubjectId[],
		comment?: string
	): Promise<void>;

	createMainSubject(
		pageId: number,
		label: string,
		schemaName: SchemaName,
		statements: StatementList,
		comment?: string
	): Promise<SubjectWriteResult>;

	createChildSubject(
		pageId: number,
		label: string,
		schemaName: SchemaName,
		statements: StatementList,
		comment?: string
	): Promise<SubjectWriteResult>;

	updateSubject(
		id: SubjectId,
		label: string,
		statements: StatementList,
		comment?: string
	): Promise<SubjectWriteResult>;

	deleteSubject( id: SubjectId, comment?: string ): Promise<boolean>;

	validateSubject( label: string, schemaName: SchemaName, statements: StatementList ): Promise<SubjectViolation[]>;

	validateSubjectUpdate( id: SubjectId, label: string, statements: StatementList ): Promise<SubjectViolation[]>;

}

export class StubSubjectRepository extends InMemorySubjectLookup implements SubjectRepository {

	public async getSubjectWithReferencedSubjects( id: SubjectId ): Promise<SubjectWithReferencedSubjects> {
		const subject = await this.getSubject( id );

		return {
			requestedSubject: subject,
			referencedSubjects: [ ...await subject.getReferencedSubjects( this ) ],
		};
	}

	public getPageSubjects( pageId: number ): Promise<DeserializedPageSubjects> {
		return Promise.resolve( {
			pageSubjects: new PageSubjects( pageId, null, [] ),
			referencedSubjects: [],
			schemas: [],
		} );
	}

	public setMainSubject( _pageId: number, _subjectId: SubjectId | null, _comment?: string ): Promise<void> {
		return Promise.resolve();
	}

	public setSubjectsOrdering(
		_pageId: number,
		_mainSubjectId: SubjectId | null,
		_childSubjectIds: SubjectId[],
		_comment?: string,
	): Promise<void> {
		return Promise.resolve();
	}

	public createMainSubject( pageId: number, label: string, schemaName: string, statements: StatementList, _comment?: string ): Promise<SubjectWriteResult> {
		return Promise.resolve( this.newWriteResult( new SubjectId( 's11111111111111' ), pageId, label, schemaName, statements ) );
	}

	public createChildSubject( pageId: number, label: string, schemaName: string, statements: StatementList, _comment?: string ): Promise<SubjectWriteResult> {
		return Promise.resolve( this.newWriteResult( new SubjectId( 's11111111111112' ), pageId, label, schemaName, statements ) );
	}

	public async updateSubject( id: SubjectId, label: string, statements: StatementList, _comment?: string ): Promise<SubjectWriteResult> {
		const existing = await this.getSubject( id );

		return {
			subject: new SubjectWithContext(
				id,
				label,
				existing.getSchemaName(),
				statements,
				new PageIdentifiers( 0, 'page-title' ),
			),
			schema: null,
		};
	}

	private newWriteResult(
		id: SubjectId,
		pageId: number,
		label: string,
		schemaName: string,
		statements: StatementList,
	): SubjectWriteResult {
		return {
			subject: new SubjectWithContext( id, label, schemaName, statements, new PageIdentifiers( pageId, 'page-title' ) ),
			schema: null,
		};
	}

	public deleteSubject( id: SubjectId, _comment?: string ): Promise<boolean> {
		return Promise.resolve( this.subjects.delete( id.text ) );
	}

	public validateSubject( _label: string, _schemaName: SchemaName, _statements: StatementList ): Promise<SubjectViolation[]> {
		return Promise.resolve( [] );
	}

	public validateSubjectUpdate( _id: SubjectId, _label: string, _statements: StatementList ): Promise<SubjectViolation[]> {
		return Promise.resolve( [] );
	}

}
