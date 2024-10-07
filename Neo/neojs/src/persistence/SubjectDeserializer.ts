import { SubjectId } from '@neo/domain/SubjectId';
import { Subject } from '@neo/domain/Subject';
import { PageIdentifiers } from '@neo/domain/PageIdentifiers';
import { StatementList } from '@neo/domain/StatementList';

export class SubjectDeserializer {

	public async deserialize( data: any ): Promise<Subject> {
		const id = new SubjectId( data.id );
		const label = data.label;
		const schema = data.schema;

		const pageIdentifiers = new PageIdentifiers( data.pageId, data.pageTitle );
		const statementList = await this.buildStatementList( data );

		return new Subject( id, label, schema, statementList, pageIdentifiers );
	}

	private async buildStatementList( data: any ): Promise<StatementList> {
		return StatementList.fromJsonValues( data.statements ); // TODO: use StatementDeserializer and remove this static function
	}

}
