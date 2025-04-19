import { describe, expect, it } from 'vitest';
import { Neo } from '@neo/Neo';
import { SubjectId } from '@neo/domain/SubjectId';
import { StatementList } from '@neo/domain/StatementList';
import { PageIdentifiers } from '@neo/domain/PageIdentifiers';
import { TextFormat } from '@neo/domain/valueFormats/Text';
import { Statement } from '@neo/domain/Statement';
import { PropertyName } from '@neo/domain/PropertyDefinition';
import { newNumberValue, newStringValue } from '@neo/domain/Value';
import { NumberFormat } from '@neo/domain/valueFormats/Number';
import { SubjectWithContext } from '@neo/domain/SubjectWithContext';

describe( 'SubjectDeserializer', () => {

	const deserializer = Neo.getInstance().getSubjectDeserializer();

	it( 'deserializes minimal Subject', () => {
		const json = {
			id: 's13333333333337',
			label: 'SubjectDeserializer',
			schema: 'SDSchema',
			statements: {},
			pageId: 42,
			pageTitle: 'SDPageTitle'
		};

		const subject = deserializer.deserialize( json );

		expect( subject ).toEqual( new SubjectWithContext(
			new SubjectId( 's13333333333337' ),
			'SubjectDeserializer',
			'SDSchema',
			new StatementList( [] ),
			new PageIdentifiers( 42, 'SDPageTitle' )
		) );
	} );

	it( 'deserializes Subject with Statements', () => {
		const json = {
			id: 's13333333333337',
			label: 'SubjectDeserializer',
			schema: 'SDSchema',
			statements: {
				Property1: {
					value: [ 'foo' ],
					format: 'text'
				},
				Property2: {
					value: 1337,
					format: 'number'
				}
			},
			pageId: 42,
			pageTitle: 'SDPageTitle'
		};

		const subject = deserializer.deserialize( json );

		expect( subject ).toEqual( new SubjectWithContext(
			new SubjectId( 's13333333333337' ),
			'SubjectDeserializer',
			'SDSchema',
			new StatementList( [
				new Statement( new PropertyName( 'Property1' ), TextFormat.typeName, newStringValue( 'foo' ) ),
				new Statement( new PropertyName( 'Property2' ), NumberFormat.typeName, newNumberValue( 1337 ) )
			] ),
			new PageIdentifiers( 42, 'SDPageTitle' )
		) );
	} );

} );
