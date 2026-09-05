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
			displayNameIsGenerated: false,
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
			false,
			'SDSchema',
			new StatementList( [] ),
			new PageIdentifiers( 42, 'SDPageTitle' ),
		) );
	} );

	it( 'carries the server\'s generated-name verdict through to the Subject', () => {
		const json = {
			id: 's13333333333337',
			label: null,
			displayName: 'SDSchema',
			displayNameIsGenerated: true,
			schema: 'SDSchema',
			statements: {},
			pageId: 42,
			pageTitle: 'SDPageTitle',
		};

		expect( deserializer.deserialize( json ).hasGeneratedDisplayName() ).toBe( true );
	} );

	// A name the server reports as chosen must not be marked, even where it equals the Schema name:
	// a Main Subject on a page titled after its Schema is the case a client cannot work out alone.
	it( 'does not invent a generated verdict for a name equal to the schema name', () => {
		const json = {
			id: 's13333333333337',
			label: null,
			displayName: 'SDSchema',
			displayNameIsGenerated: false,
			schema: 'SDSchema',
			statements: {},
			pageId: 42,
			pageTitle: 'SDSchema',
		};

		expect( deserializer.deserialize( json ).hasGeneratedDisplayName() ).toBe( false );
	} );

	it( 'deserializes a Subject without a label, keeping the display name the server derived', () => {
		const json = {
			id: 's13333333333337',
			label: null,
			displayName: 'SDPageTitle',
			displayNameIsGenerated: false,
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
			displayNameIsGenerated: false,
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
			false,
			'SDSchema',
			new StatementList( [
				new Statement( new PropertyName( 'Property1' ), TextType.typeName, newStringValue( 'foo' ) ),
				new Statement( new PropertyName( 'Property2' ), NumberType.typeName, newNumberValue( 1337 ) ),
			] ),
			new PageIdentifiers( 42, 'SDPageTitle' ),
		) );
	} );

} );
