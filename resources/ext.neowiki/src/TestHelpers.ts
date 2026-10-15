import { SubjectId } from '@/domain/SubjectId';
import { PageIdentifiers } from '@/domain/PageIdentifiers';
import { Schema } from '@/domain/Schema';
import { StatementList } from '@/domain/StatementList';
import { PropertyDefinitionList } from '@/domain/PropertyDefinitionList';
import { Subject } from '@/domain/Subject';
import type { HostSubject } from '@/domain/HostSubject';
import { PropertyName } from '@/domain/PropertyDefinition';
import type { DisplayRule } from '@/domain/Layout';
import { Statement } from '@/domain/Statement';
import { RelationType } from '@/domain/propertyTypes/Relation';
import { newRelation, RelationValue } from '@/domain/Value';

export const DEFAULT_SUBJECT_ID = 's11111111111111';
export const DEFAULT_TEST_SUBJECT_LABEL = 'Test subject';
export const DEFAULT_TEST_SCHEMA_NAME = 'TestSchema';

interface NewTestSubjectOptions {
	id?: string|SubjectId;
	label?: string|null;
	displayName?: string;
	displayNameIsGenerated?: boolean;
	schemaName?: string;
	statements?: StatementList;
	pageIdentifiers?: PageIdentifiers;
	hostSubject?: HostSubject | null;
}

export function newSubject( {
	id = DEFAULT_SUBJECT_ID,
	label = DEFAULT_TEST_SUBJECT_LABEL,
	displayName,
	displayNameIsGenerated = false,
	schemaName = DEFAULT_TEST_SCHEMA_NAME,
	statements = new StatementList( [] ),
	pageIdentifiers = new PageIdentifiers( 0, 'TestSubjectPage' ),
	hostSubject = null,
}: NewTestSubjectOptions = {} ): Subject {
	return new Subject(
		id instanceof SubjectId ? id : new SubjectId( id ),
		label,
		// Mirrors what the server derives: the stored label, or the Schema name without one.
		displayName ?? label ?? schemaName,
		displayNameIsGenerated,
		schemaName,
		statements,
		pageIdentifiers,
		hostSubject,
	);
}

export function newRelationStatements( propertyName: string, ...targets: ( string | SubjectId )[] ): StatementList {
	return new StatementList( [
		new Statement(
			new PropertyName( propertyName ),
			RelationType.typeName,
			new RelationValue( targets.map( ( target ) => newRelation( undefined, target ) ) ),
		),
	] );
}

/**
 * What SubjectDeserializer builds from a payload that omits both page fields, as one for a Subject
 * whose page the server could not resolve does.
 */
export function unresolvedPage(): PageIdentifiers {
	return new PageIdentifiers( undefined as unknown as number, undefined as unknown as string );
}

interface NewTestSchemaOptions {
	title?: string;
	description?: string;
	properties?: PropertyDefinitionList;
	subjectsAreDependent?: boolean;
}

export function newSchema( {
	title = 'TestSchema',
	description = 'TestSchema description',
	properties,
	subjectsAreDependent = false,
}: NewTestSchemaOptions = {} ): Schema {
	return new Schema(
		title,
		description,
		properties ?? new PropertyDefinitionList( [] ),
		subjectsAreDependent,
	);
}

export function newDisplayRules( ...names: string[] ): DisplayRule[] {
	return names.map( ( name ) => ( { property: new PropertyName( name ) } ) );
}
