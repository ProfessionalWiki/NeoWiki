import { SubjectId } from '@neo/domain/SubjectId';
import { PageIdentifiers } from '@neo/domain/PageIdentifiers';
import { Schema } from '@neo/domain/Schema';
import { StatementList } from '@neo/domain/StatementList';
import { PropertyDefinitionList } from '@neo/domain/PropertyDefinitionList';
import { SubjectWithContext } from '@neo/domain/SubjectWithContext';

export const DEFAULT_SUBJECT_ID = 's11111111111111';
export const DEFAULT_TEST_SUBJECT_LABEL = 'Test subject';
export const DEFAULT_TEST_SCHEMA_ID = 'TestSchema';

interface NewTestSubjectOptions {
	id?: string|SubjectId;
	label?: string;
	schemaId?: string;
	statements?: StatementList;
	pageIdentifiers?: PageIdentifiers;
}

export function newSubject( {
	id = DEFAULT_SUBJECT_ID,
	label = DEFAULT_TEST_SUBJECT_LABEL,
	schemaId = DEFAULT_TEST_SCHEMA_ID,
	statements = new StatementList( [] ),
	pageIdentifiers = new PageIdentifiers( 0, 'TestSubjectPage' )
}: NewTestSubjectOptions = {} ): SubjectWithContext {
	return new SubjectWithContext(
		id instanceof SubjectId ? id : new SubjectId( id ),
		label,
		schemaId,
		statements,
		pageIdentifiers
	);
}

interface NewTestSchemaOptions {
	title?: string;
	description?: string;
	properties?: PropertyDefinitionList;
}

export function newSchema( {
	title = 'TestSchema',
	description = 'TestSchema description',
	properties
}: NewTestSchemaOptions = {} ): Schema {
	return new Schema(
		title,
		description,
		properties ?? new PropertyDefinitionList( [] )
	);
}
