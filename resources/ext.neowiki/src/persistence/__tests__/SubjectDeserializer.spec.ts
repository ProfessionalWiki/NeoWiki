import { describe, expect, it } from 'vitest';
import { Neo } from '@/Neo';
import { SubjectId } from '@/domain/SubjectId';
import { StatementList } from '@/domain/StatementList';
import { PageIdentifiers } from '@/domain/PageIdentifiers';
import { TextType } from '@/domain/propertyTypes/Text';
import { Statement } from '@/domain/Statement';
import { PropertyName } from '@/domain/PropertyDefinition';
import { newNumberValue, newStringValue } from '@/domain/Value';
import { NumberType } from '@/domain/propertyTypes/Number';
import { SubjectWithContext } from '@/domain/SubjectWithContext';

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
					type: 'text'
				},
				Property2: {
					value: 1337,
					type: 'number'
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
				new Statement( new PropertyName( 'Property1' ), TextType.typeName, newStringValue( 'foo' ) ),
				new Statement( new PropertyName( 'Property2' ), NumberType.typeName, newNumberValue( 1337 ) )
			] ),
			new PageIdentifiers( 42, 'SDPageTitle' )
		) );
	} );

} );
