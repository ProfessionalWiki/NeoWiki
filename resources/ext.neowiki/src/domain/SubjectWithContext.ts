import { Subject } from '@neo/domain/Subject';
import type { SubjectId } from '@neo/domain/SubjectId';
import type { SchemaName } from '@neo/domain/Schema';
import type { StatementList } from '@neo/domain/StatementList';
import type { PageIdentifiers } from '@neo/domain/PageIdentifiers';

export class SubjectWithContext extends Subject {

	public constructor(
		id: SubjectId,
		label: string,
		schemaName: SchemaName,
		statements: StatementList,
		private readonly pageIdentifiers: PageIdentifiers
	) {
		super( id, label, schemaName, statements );
	}

	public getPageIdentifiers(): PageIdentifiers {
		return this.pageIdentifiers;
	}

}
