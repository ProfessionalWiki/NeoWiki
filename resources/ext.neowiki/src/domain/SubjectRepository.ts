import { SubjectId } from '@/domain/SubjectId';
import type { SubjectLookup } from '@/domain/SubjectLookup';
import { InMemorySubjectLookup } from '@/domain/SubjectLookup';
import type { StatementList } from '@/domain/StatementList';
import type { SchemaName } from '@/domain/Schema';
import { PageSubjects } from '@/domain/PageSubjects';
import type { DeserializedPageSubjects } from '@/persistence/PageSubjectsDeserializer';

export interface SubjectRepository extends SubjectLookup {

	getPageSubjects( pageId: number ): Promise<DeserializedPageSubjects>;

	setMainSubject( pageId: number, subjectId: SubjectId | null, comment?: string ): Promise<void>;

	createMainSubject(
		pageId: number,
		label: string,
		schemaName: SchemaName,
		statements: StatementList,
		comment?: string
	): Promise<SubjectId>;

	createChildSubject(
		pageId: number,
		label: string,
		schemaName: SchemaName,
		statements: StatementList,
		comment?: string
	): Promise<SubjectId>;

	// TODO: return something to indicate status
	updateSubject( id: SubjectId, label: string, statements: StatementList, comment?: string ): Promise<object>;

	deleteSubject( id: SubjectId, comment?: string ): Promise<boolean>;

}

export class StubSubjectRepository extends InMemorySubjectLookup implements SubjectRepository {

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

	public createMainSubject( _pageId: number, _label: string, _schemaName: string, _statements: StatementList, _comment?: string ): Promise<SubjectId> {
		return Promise.resolve( new SubjectId( 's11111111111111' ) );
	}

	public createChildSubject( _pageId: number, _label: string, _schemaName: string, _statements: StatementList, _comment?: string ): Promise<SubjectId> {
		return Promise.resolve( new SubjectId( 's11111111111112' ) );
	}

	public updateSubject( _id: SubjectId, _label: string, _statements: StatementList, _comment?: string ): Promise<object> {
		return Promise.resolve( {} );
	}

	public deleteSubject( id: SubjectId, _comment?: string ): Promise<boolean> {
		return Promise.resolve( this.subjects.delete( id.text ) );
	}

}
