import { SubjectId } from '@neo/domain/SubjectId';
import { Subject } from '@neo/domain/Subject';
import { PageIdentifiers } from '@neo/domain/PageIdentifiers';
import { StatementList } from '@neo/domain/StatementList';
import type { SchemaLookup } from '@/application/SchemaLookup';
import type { SubjectJson } from '@/persistence/RestSubjectRepository';
import type { Schema } from '@neo/domain/Schema';

export class SubjectDeserializer {
	public constructor( private readonly schemaLookup?: SchemaLookup ) {}

	public async deserialize( data: SubjectJson, knownSchema?: Schema ): Promise<Subject> {
		const id = new SubjectId( data.id );
		const label = data.label;
		const schema = knownSchema || ( this.schemaLookup ? await this.schemaLookup.getSchema( data.schema ) : null );

		if ( !schema ) {
			throw new Error( 'No schema provided or found through lookup.' );
		}

		const pageIdentifiers = new PageIdentifiers( data.pageId, data.pageTitle );
		const statementList = await this.buildStatementList( data, schema );

		return new Subject( id, label, schema.getName(), statementList, pageIdentifiers );
	}

	private async buildStatementList( data: SubjectJson, knownSchema: Schema ): Promise<StatementList> {
		return StatementList.fromJsonValues( data.statements, knownSchema );
	}
}
