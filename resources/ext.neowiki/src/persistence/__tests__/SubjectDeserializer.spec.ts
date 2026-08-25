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
			displayName: 'SubjectDeserializer',
			schema: 'SDSchema',
			statements: {},
			pageId: 42,
			pageTitle: 'SDPageTitle',
		};

		const subject = deserializer.deserialize( json );

		expect( subject ).toEqual( new SubjectWithContext(
			new SubjectId( 's13333333333337' ),
			'SubjectDeserializer',
			'SubjectDeserializer',
			'SDSchema',
			new StatementList( [] ),
			new PageIdentifiers( 42, 'SDPageTitle' ),
		) );
	} );

	it( 'deserializes a Subject without a label, keeping the display name the server derived', () => {
		const json = {
			id: 's13333333333337',
			label: null,
			displayName: 'SDPageTitle',
			schema: 'SDSchema',
			statements: {},
			pageId: 42,
			pageTitle: 'SDPageTitle',
		};

		const subject = deserializer.deserialize( json );

		expect( subject.getLabel() ).toBeNull();
		expect( subject.getDisplayName() ).toBe( 'SDPageTitle' );
	} );

	it( 'deserializes Subject with Statements', () => {
		const json = {
			id: 's13333333333337',
			label: 'SubjectDeserializer',
			displayName: 'SubjectDeserializer',
			schema: 'SDSchema',
			statements: {
				Property1: {
					value: [ 'foo' ],
					propertyType: 'text',
				},
				Property2: {
					value: 1337,
					propertyType: 'number',
				},
			},
			pageId: 42,
			pageTitle: 'SDPageTitle',
		};

		const subject = deserializer.deserialize( json );

		expect( subject ).toEqual( new SubjectWithContext(
			new SubjectId( 's13333333333337' ),
			'SubjectDeserializer',
			'SubjectDeserializer',
			'SDSchema',
			new StatementList( [
				new Statement( new PropertyName( 'Property1' ), TextType.typeName, newStringValue( 'foo' ) ),
				new Statement( new PropertyName( 'Property2' ), NumberType.typeName, newNumberValue( 1337 ) ),
			] ),
			new PageIdentifiers( 42, 'SDPageTitle' ),
		) );
	} );

} );
