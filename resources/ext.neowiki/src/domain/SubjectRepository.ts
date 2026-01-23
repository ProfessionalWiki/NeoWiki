import { SubjectId } from '@/domain/SubjectId';
import type { SubjectLookup } from '@/domain/SubjectLookup';
import { InMemorySubjectLookup } from '@/domain/SubjectLookup';
import type { StatementList } from '@/domain/StatementList';
import type { SchemaName } from '@/domain/Schema';

export interface SubjectRepository extends SubjectLookup {

	createMainSubject(
		pageId: number,
		label: string,
		schemaName: SchemaName,
		statements: StatementList
	): Promise<SubjectId>;

	createChildSubject(
		pageId: number,
		label: string,
		schemaName: SchemaName,
		statements: StatementList
	): Promise<SubjectId>;

	// TODO: return something to indicate status
	updateSubject( id: SubjectId, label: string, statements: StatementList, comment?: string ): Promise<object>;

	deleteSubject( id: SubjectId ): Promise<boolean>;

}

export class StubSubjectRepository extends InMemorySubjectLookup implements SubjectRepository {

	public createMainSubject( _pageId: number, _label: string, _schemaName: string, _statements: StatementList ): Promise<SubjectId> {
		return Promise.resolve( new SubjectId( 's11111111111111' ) );
	}

	public createChildSubject( _pageId: number, _label: string, _schemaName: string, _statements: StatementList ): Promise<SubjectId> {
		return Promise.resolve( new SubjectId( 's11111111111112' ) );
	}

	public updateSubject( _id: SubjectId, _label: string, _statements: StatementList, _comment?: string ): Promise<object> {
		return Promise.resolve( {} );
	}

	public deleteSubject( id: SubjectId ): Promise<boolean> {
		return Promise.resolve( this.subjects.delete( id.text ) );
	}

}
