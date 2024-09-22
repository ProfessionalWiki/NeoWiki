import { Subject } from '@neo/domain/Subject.ts';
import { SubjectId } from '@neo/domain/SubjectId.ts';
import { StatementList } from '@neo/domain/StatementList.ts';
import { Statement } from '@neo/domain/Statement.ts';
import { PropertyName } from '@neo/domain/PropertyDefinition.ts';
import { TextFormat } from '@neo/domain/valueFormats/Text.ts';
import { newNumberValue, newStringValue } from '@neo/domain/Value.ts';
import { NumberFormat } from '@neo/domain/valueFormats/Number.ts';
import { PageIdentifiers } from '@neo/domain/PageIdentifiers.ts';
import { UrlFormat } from '@neo/domain/valueFormats/Url.ts';
import { Schema } from '@neo/domain/Schema.ts';
import { PropertyDefinitionList } from '@neo/domain/PropertyDefinitionList.ts';

export function createExampleSchemas(): Map<string, Schema> {
	const schemas = new Map<string, Schema>();

	schemas.set( 'Person', new Schema(
		'Person',
		'Information about an individual',
		new PropertyDefinitionList( [] )
	) );
	schemas.set( 'Organization', new Schema(
		'Organization',
		'Details about a company or institution',
		new PropertyDefinitionList( [] )
	) );
	schemas.set( 'Place', new Schema(
		'Place',
		'Geographic location or landmark',
		new PropertyDefinitionList( [] )
	) );

	return schemas;
}

export function createExampleSubjects(): Map<string, Subject> {
	const subjects = new Map<string, Subject>();

	subjects.set( '00000000-0000-0000-0000-000000000001', new Subject(
		new SubjectId( '00000000-0000-0000-0000-000000000001' ),
		'Example Person',
		'Person',
		new StatementList( [
			new Statement(
				new PropertyName( 'name' ), TextFormat.formatName, newStringValue( 'John Doe' )
			),
			new Statement(
				new PropertyName( 'occupation' ), TextFormat.formatName, newStringValue( 'Engineer', 'Tester' )
			),
			new Statement(
				new PropertyName( 'age' ), NumberFormat.formatName, newNumberValue( 42 )
			)
		] ),
		new PageIdentifiers( 1, 'John_Doe' )
	) );

	subjects.set( '00000000-0000-0000-0000-000000000002', new Subject(
		new SubjectId( '00000000-0000-0000-0000-000000000002' ),
		'Example Company',
		'Organization',
		new StatementList( [
			new Statement(
				new PropertyName( 'name' ), TextFormat.formatName, newStringValue( 'Acme Corporation' )
			),
			new Statement(
				new PropertyName( 'industry' ), TextFormat.formatName, newStringValue( 'Technology' )
			),
			new Statement(
				new PropertyName( 'websites' ),
				UrlFormat.formatName,
				newStringValue( 'https://example.com', 'https://foo.bar' )
			)
		] ),
		new PageIdentifiers( 2, 'Acme_Corporation' )
	) );

	subjects.set( '00000000-0000-0000-0000-000000000003', new Subject(
		new SubjectId( '00000000-0000-0000-0000-000000000003' ),
		'Example Place',
		'Place',
		new StatementList( [
			new Statement(
				new PropertyName( 'name' ), TextFormat.formatName, newStringValue( 'Central Park' )
			),
			new Statement(
				new PropertyName( 'location' ), TextFormat.formatName, newStringValue( 'New York City' )
			)
		] ),
		new PageIdentifiers( 3, 'Central_Park' )
	) );

	return subjects;
}
