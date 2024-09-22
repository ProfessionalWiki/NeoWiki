import { SubjectId } from '@neo/domain/SubjectId';
import type { SubjectLookup } from '@neo/domain/SubjectLookup';
import { InMemorySubjectLookup } from '@neo/domain/SubjectLookup';
import type { StatementList } from '@neo/domain/StatementList';
import type { SchemaName } from '@neo/domain/Schema';

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
	updateSubject( id: SubjectId, statements: StatementList ): Promise<object>;

	deleteSubject( id: SubjectId ): Promise<boolean>;

}

export class StubSubjectRepository extends InMemorySubjectLookup implements SubjectRepository {

	public createMainSubject( pageId: number, label: string, schemaName: string, statements: StatementList ): Promise<SubjectId> {
		return Promise.resolve( new SubjectId( '00000000-0000-0000-0000-000000000000' ) );
	}

	public createChildSubject( _pageId: number, _label: string, _schemaName: string, _statements: StatementList ): Promise<SubjectId> {
		return Promise.resolve( new SubjectId( '00000000-0000-0000-0000-000000000010' ) );
	}

	public updateSubject( id: SubjectId, statements: StatementList ): Promise<object> {
		return Promise.resolve( {} );
	}

	public deleteSubject( id: SubjectId ): Promise<boolean> {
		return Promise.resolve( this.subjects.delete( id.text ) );
	}

}
