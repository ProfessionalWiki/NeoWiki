import { SubjectId } from '@neo/domain/SubjectId';
import { Subject } from '@neo/domain/Subject';
import { PageIdentifiers } from '@neo/domain/PageIdentifiers';
import { StatementList } from '@neo/domain/StatementList';
import { StatementDeserializer } from '@neo/persistence/StatementDeserializer';

export class SubjectDeserializer {

	public constructor(
		private readonly statementDeserializer: StatementDeserializer
	) {
	}

	public deserialize( json: any ): Subject {
		const id = new SubjectId( json.id );
		const label = json.label;
		const schema = json.schema;

		const pageIdentifiers = new PageIdentifiers( json.pageId, json.pageTitle );
		const statementList = this.deserializeStatements( json.statements );

		return new Subject( id, label, schema, statementList, pageIdentifiers );
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
