import { describe, expect, it } from 'vitest';
import { namingPropertyName, newSubjectNaming, templateLabel, withRenamedPlaceholder } from '@/domain/LabelTemplate';
import { newSchema } from '@/TestHelpers';
import type { Schema } from '@/domain/Schema';
import { PropertyDefinitionList } from '@/domain/PropertyDefinitionList';
import { PropertyName } from '@/domain/PropertyDefinition';
import { Statement } from '@/domain/Statement';
import { StatementList } from '@/domain/StatementList';
import { newMonolingualTextValue, newNumberValue, newStringValue, type Value } from '@/domain/Value';
import { newTextProperty, TextType } from '@/domain/propertyTypes/Text';
import { newNumberProperty, NumberType } from '@/domain/propertyTypes/Number';
import { newSelectProperty, SelectType } from '@/domain/propertyTypes/Select';
import { MonolingualTextType, newMonolingualTextProperty } from '@/domain/propertyTypes/MonolingualText';

describe( 'withRenamedPlaceholder', () => {

	it( 'renames every placeholder naming the property', () => {
		expect( withRenamedPlaceholder( '{Title} ({Title})', 'Title', 'Name' ) ).toBe( '{Name} ({Name})' );
	} );

	it( 'leaves placeholders naming other properties and the literal text alone', () => {
		expect( withRenamedPlaceholder( '{Year founded}: attendance {Year}', 'Year', 'Season' ) )
			.toBe( '{Year founded}: attendance {Season}' );
	} );

	it( 'renames a placeholder written with space inside its braces', () => {
		expect( withRenamedPlaceholder( '{ Title }', 'Title', 'Name' ) ).toMatch( /^\{\s*Name\s*\}$/ );
	} );

	it( 'leaves text that merely contains the name outside braces alone', () => {
		expect( withRenamedPlaceholder( 'Title: {Title}', 'Title', 'Name' ) ).toBe( 'Title: {Name}' );
	} );

	// Editing anything else about the property leaves the template as its author wrote it.
	it( 'leaves the template as written when the name does not change', () => {
		expect( withRenamedPlaceholder( '{ Title }', 'Title', 'Title' ) ).toBe( '{ Title }' );
	} );

	// The Schema editor renames as the name is typed, so a brace typed and deleted again must not
	// leave the template broken.
	it( 'leaves the template alone for a name no placeholder can hold', () => {
		expect( withRenamedPlaceholder( '{Size}', 'Size', 'Size}' ) ).toBe( '{Size}' );
	} );

} );

describe( 'templateLabel', () => {

	function labelFrom( template: string | null, ...statements: Statement[] ): string | null {
		return templateLabel(
			newSchema( {
				labelTemplate: template,
				properties: new PropertyDefinitionList( [
					newTextProperty( { name: 'Title' } ),
					newNumberProperty( { name: 'Year' } ),
					newSelectProperty( {
						name: 'Status',
						options: [ { id: 'o1', label: 'On loan' }, { id: 'o2', label: 'In storage' } ],
					} ),
					newMonolingualTextProperty( { name: 'Caption' } ),
				] ),
			} ),
			new StatementList( statements ),
		);
	}

	function statement( property: string, type: string, value: Value | undefined ): Statement {
		return new Statement( new PropertyName( property ), type, value );
	}

	function title( ...parts: string[] ): Statement {
		return statement( 'Title', TextType.typeName, newStringValue( ...parts ) );
	}

	it( 'is the text value the template names', () => {
		expect( labelFrom( '{Title}', title( 'Madonna and Child on a Cloud' ) ) ).toBe( 'Madonna and Child on a Cloud' );
	} );

	it( 'reads the first of several values', () => {
		expect( labelFrom( '{Title}', title( 'Madonna', 'Madonna på molnet' ) ) ).toBe( 'Madonna' );
	} );

	it( 'writes a number out', () => {
		expect( labelFrom( 'Rijksmuseum {Year}', statement( 'Year', NumberType.typeName, newNumberValue( 2024 ) ) ) )
			.toBe( 'Rijksmuseum 2024' );
	} );

	it( 'reads a select value as its option label', () => {
		expect( labelFrom( '{Status}', statement( 'Status', SelectType.typeName, newStringValue( 'o2' ) ) ) )
			.toBe( 'In storage' );
	} );

	it( 'reads a monolingual text as its first text, without its language', () => {
		expect( labelFrom( '{Caption}', statement( 'Caption', MonolingualTextType.typeName, newMonolingualTextValue( [
			{ text: 'Madonna på molnet', language: 'sv' },
			{ text: 'Madonna and Child on a Cloud', language: 'en' },
		] ) ) ) ).toBe( 'Madonna på molnet' );
	} );

	it( 'reads a placeholder without a value as nothing, keeping the text around it', () => {
		expect( labelFrom( '{Title} ({Year})', title( 'Madonna' ) ) ).toBe( 'Madonna ()' );
	} );

	it( 'collapses runs of whitespace and trims the label', () => {
		expect( labelFrom( '  {Title}   ({Year})  ', title( 'Madonna' ) ) ).toBe( 'Madonna ()' );
	} );

	it( 'reads a select value as nothing once its property is no longer a select', () => {
		expect( labelFrom( '{Title}', statement( 'Title', SelectType.typeName, newStringValue( 'o2' ) ) ) ).toBeNull();
	} );

	it( 'is null when no placeholder has a value', () => {
		expect( labelFrom( 'Artwork {Title}', statement( 'Title', TextType.typeName, undefined ) ) ).toBeNull();
	} );

	it( 'is null for a Schema without a template', () => {
		expect( labelFrom( null, title( 'Madonna' ) ) ).toBeNull();
	} );

	it( 'reads a placeholder with space inside its braces', () => {
		expect( labelFrom( '{ Title }', title( 'Madonna' ) ) ).toBe( 'Madonna' );
	} );

	it( 'leaves empty braces as written', () => {
		expect( labelFrom( '{Title} {}', title( 'Madonna' ) ) ).toBe( 'Madonna {}' );
	} );

} );

describe( 'namingPropertyName', () => {

	it( 'is the property the template\'s first placeholder names', () => {
		expect( namingPropertyName( newSchema( { labelTemplate: 'Exhibit: { Title } ({Year})' } ) ) ).toBe( 'Title' );
	} );

	it( 'passes over empty braces', () => {
		expect( namingPropertyName( newSchema( { labelTemplate: '{} {Title}' } ) ) ).toBe( 'Title' );
	} );

	it( 'is null for a Schema without a template', () => {
		expect( namingPropertyName( newSchema( { labelTemplate: null } ) ) ).toBeNull();
	} );

} );

describe( 'newSubjectNaming', () => {

	function schemaNamedBy( template: string | null ): Schema {
		return newSchema( {
			title: 'Artwork',
			labelTemplate: template,
			properties: new PropertyDefinitionList( [
				newTextProperty( { name: 'Title' } ),
				newNumberProperty( { name: 'Year' } ),
			] ),
		} );
	}

	it( 'puts a typed name into the text field the template names rather than the label', () => {
		const naming = newSubjectNaming( schemaNamedBy( 'Exhibit {Title}' ), 'Madonna' );

		expect( naming.label ).toBeNull();
		expect( naming.statements.get( new PropertyName( 'Title' ) ).value ).toEqual( newStringValue( 'Madonna' ) );
	} );

	it( 'shows such a Subject under the label its template reads', () => {
		const naming = newSubjectNaming( schemaNamedBy( 'Exhibit {Title}' ), 'Madonna' );

		expect( [ naming.displayName, naming.displayNameIsGenerated ] ).toEqual( [ 'Exhibit Madonna', false ] );
	} );

	// A typed name is no number, select option or text in a language as it stands.
	it( 'keeps a typed name as the label where the field the template names is not text', () => {
		expect( newSubjectNaming( schemaNamedBy( '{Year} {Title}' ), '2024' ).label ).toBe( '2024' );
	} );

	it( 'keeps a typed name as the label where the template first names a property the Schema lacks', () => {
		expect( newSubjectNaming( schemaNamedBy( '{Material} {Title}' ), 'Madonna' ).label ).toBe( 'Madonna' );
	} );

	it( 'keeps a typed name as the label for a Schema without a template', () => {
		const naming = newSubjectNaming( schemaNamedBy( null ), 'Madonna' );

		expect( [ naming.label, naming.displayName, naming.displayNameIsGenerated ] ).toEqual( [ 'Madonna', 'Madonna', false ] );
	} );

	it( 'leaves a Subject nothing names under its Schema name, as a stand-in', () => {
		const naming = newSubjectNaming( schemaNamedBy( 'Exhibit {Title}' ), null );

		expect( [ naming.label, naming.displayName, naming.displayNameIsGenerated ] ).toEqual( [ null, 'Artwork', true ] );
	} );

} );
