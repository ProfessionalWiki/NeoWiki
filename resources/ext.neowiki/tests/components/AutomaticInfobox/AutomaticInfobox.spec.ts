import { mount } from '@vue/test-utils';
import { describe, it, expect, vi } from 'vitest';
import AutomaticInfobox from '@/components/AutomaticInfobox/AutomaticInfobox.vue';
import { Subject } from '@neo/domain/Subject';
import { SubjectId } from '@neo/domain/SubjectId';
import { StatementList } from '@neo/domain/StatementList';
import { Statement } from '@neo/domain/Statement';
import { PropertyName } from '@neo/domain/PropertyDefinition';
import { TextFormat } from '@neo/domain/valueFormats/Text';
import { NumberFormat } from '@neo/domain/valueFormats/Number';
import { UrlFormat } from '@neo/domain/valueFormats/Url';
import { newStringValue, newNumberValue } from '@neo/domain/Value';
import { PageIdentifiers } from '@neo/domain/PageIdentifiers';
import { NeoWikiExtension } from '@/NeoWikiExtension';
import { Schema } from '@neo/domain/Schema';
import { PropertyDefinitionList } from '@neo/domain/PropertyDefinitionList';
import { createPropertyDefinitionFromJson } from '@neo/domain/PropertyDefinition';

const $i18n = vi.fn().mockImplementation( ( key ) => ( {
	text: () => key
} ) );

describe( 'AutomaticInfobox', () => {
	const mockSchema = new Schema(
		'TestSchema',
		'A test schema',
		new PropertyDefinitionList( [
			createPropertyDefinitionFromJson( 'name', { type: 'string', format: TextFormat.formatName } ),
			createPropertyDefinitionFromJson( 'age', { type: 'number', format: NumberFormat.formatName } ),
			createPropertyDefinitionFromJson( 'website', { type: 'string', format: UrlFormat.formatName } )
		] )
	);

	const mockSubject = new Subject(
		new SubjectId( 's11111111111111' ),
		'Test Subject',
		'TestSchema',
		new StatementList( [
			new Statement(
				new PropertyName( 'name' ), TextFormat.formatName, newStringValue( 'John Doe', 'Jane Doe' )
			),
			new Statement(
				new PropertyName( 'age' ), NumberFormat.formatName, newNumberValue( 30 )
			),
			new Statement(
				new PropertyName( 'website' ), UrlFormat.formatName, newStringValue( 'https://example.com' )
			)
		] ),
		new PageIdentifiers( 1, 'Test_Subject' )
	);

	it( 'renders the title correctly', () => {
		const wrapper = mount( AutomaticInfobox, {
			props: {
				subject: mockSubject,
				schema: mockSchema,
				valueFormatComponentRegistry: NeoWikiExtension.getInstance().getValueFormatComponentRegistry()
			},
			global: {
				mocks: {
					$i18n
				}
			}
		} );

		expect( wrapper.find( '.infobox-title' ).text() ).toBe( 'Test Subject' );
	} );

	it( 'renders statements correctly', () => {
		const wrapper = mount( AutomaticInfobox, {
			props: {
				subject: mockSubject,
				schema: mockSchema,
				valueFormatComponentRegistry: NeoWikiExtension.getInstance().getValueFormatComponentRegistry()
			},
			global: {
				mocks: {
					$i18n
				}
			}
		} );

		const statementElements = wrapper.findAll( '.infobox-statement' );
		expect( statementElements ).toHaveLength( 4 ); // 3 properties + 1 for schema type

		expect( statementElements[ 0 ].find( '.infobox-statement-property' ).text() ).toBe( 'neowiki-infobox-type' );
		expect( statementElements[ 0 ].find( '.infobox-statement-value' ).text() ).toBe( 'TestSchema' );

		expect( statementElements[ 1 ].find( '.infobox-statement-property' ).text() ).toBe( 'name' );
		expect( statementElements[ 1 ].find( '.infobox-statement-value' ).text() ).toBe( 'John Doe, Jane Doe' );

		expect( statementElements[ 2 ].find( '.infobox-statement-property' ).text() ).toBe( 'age' );
		expect( statementElements[ 2 ].find( '.infobox-statement-value' ).text() ).toBe( '30' );

		expect( statementElements[ 3 ].find( '.infobox-statement-property' ).text() ).toBe( 'website' );
		const linkElement = statementElements[ 3 ].find( '.infobox-statement-value a' );
		expect( linkElement.attributes( 'href' ) ).toBe( 'https://example.com' );
		expect( linkElement.text() ).toBe( 'https://example.com' );
	} );

	it( 'renders without statements when subject has no statements', () => {
		const emptySubject = new Subject(
			new SubjectId( 's11111111111112' ),
			'Empty Subject',
			'TestSchema',
			new StatementList( [] ),
			new PageIdentifiers( 2, 'Empty_Subject' )
		);

		const wrapper = mount( AutomaticInfobox, {
			props: {
				subject: emptySubject,
				schema: mockSchema,
				valueFormatComponentRegistry: NeoWikiExtension.getInstance().getValueFormatComponentRegistry()
			},
			global: {
				mocks: {
					$i18n
				}
			}
		} );

		const statementElements = wrapper.findAll( '.infobox-statement' );
		expect( statementElements ).toHaveLength( 1 ); // Only the schema type statement
		expect( statementElements[ 0 ].find( '.infobox-statement-property' ).text() ).toBe( 'neowiki-infobox-type' );
		expect( statementElements[ 0 ].find( '.infobox-statement-value' ).text() ).toBe( 'TestSchema' );
	} );

	it( 'does not render edit button when canEdit is false', () => {
		const wrapper = mount( AutomaticInfobox, {
			props: {
				subject: mockSubject,
				schema: mockSchema,
				valueFormatComponentRegistry: NeoWikiExtension.getInstance().getValueFormatComponentRegistry(),
				canEdit: false
			},
			global: {
				mocks: {
					$i18n
				}
			}
		} );

		expect( wrapper.find( '.cdx-button' ).exists() ).toBe( false );
	} );

	it( 'renders edit button when canEdit is true', () => {
		const wrapper = mount( AutomaticInfobox, {
			props: {
				subject: mockSubject,
				schema: mockSchema,
				valueFormatComponentRegistry: NeoWikiExtension.getInstance().getValueFormatComponentRegistry(),
				canEdit: true
			},
			global: {
				mocks: {
					$i18n
				}
			}
		} );

		const editButton = wrapper.find( '.cdx-button' );
		expect( editButton.exists() ).toBe( true );
		expect( editButton.text() ).toBe( 'neowiki-infobox-edit-link' );
	} );
} );
