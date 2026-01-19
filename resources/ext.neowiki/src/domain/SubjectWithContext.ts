import { Subject } from '@/domain/Subject';
import type { SubjectId } from '@/domain/SubjectId';
import type { SchemaName } from '@/domain/Schema';
import type { StatementList } from '@/domain/StatementList';
import type { PageIdentifiers } from '@/domain/PageIdentifiers';

export class SubjectWithContext extends Subject {

	public constructor(
		id: SubjectId,
		label: string,
		schemaName: SchemaName,
		statements: StatementList,
		private readonly pageIdentifiers: PageIdentifiers,
	) {
		super( id, label, schemaName, statements );
	}

	public getPageIdentifiers(): PageIdentifiers {
		return this.pageIdentifiers;
	}

}
