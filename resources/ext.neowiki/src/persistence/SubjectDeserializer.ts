import { SubjectId } from '@/domain/SubjectId';
import { PageIdentifiers } from '@/domain/PageIdentifiers';
import { StatementList } from '@/domain/StatementList';
import { StatementDeserializer } from '@/persistence/StatementDeserializer';
import { SubjectWithContext } from '@/domain/SubjectWithContext';

export class SubjectDeserializer {

	public constructor(
		private readonly statementDeserializer: StatementDeserializer
	) {
	}

	public deserialize( json: any ): SubjectWithContext {
		const id = new SubjectId( json.id );
		const label = json.label;
		const schema = json.schema;

		const pageIdentifiers = new PageIdentifiers( json.pageId, json.pageTitle );
		const statementList = this.deserializeStatements( json.statements );

		return new SubjectWithContext( id, label, schema, statementList, pageIdentifiers );
	}

	public deserializeStatements( json: any ): StatementList {
		return new StatementList(
			Object.entries( json )
				.map( ( [ key, statementJson ] ) => this.statementDeserializer.deserialize(
					key,
					statementJson
				) )
		);
	}

}
