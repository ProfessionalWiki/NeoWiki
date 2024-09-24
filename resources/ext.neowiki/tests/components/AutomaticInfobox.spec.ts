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
import { Neo } from '@neo/Neo';

const $i18n = vi.fn().mockImplementation( ( key ) => ( {
	text: () => key
} ) );

describe( 'AutomaticInfobox', () => {
	const mockSubject = new Subject(
		new SubjectId( '00000000-0000-0000-0000-000000000001' ),
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
				valueFormatRegistry: Neo.getInstance().getValueFormatRegistry() // TODO: test instance
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
				valueFormatRegistry: Neo.getInstance().getValueFormatRegistry() // TODO: test instance
			},
			global: {
				mocks: {
					$i18n
				}
			}
		} );

		const statementElements = wrapper.findAll( '.infobox-statement' );
		expect( statementElements ).toHaveLength( 3 );

		expect( statementElements[ 0 ].find( '.infobox-statement-property' ).text() ).toBe( 'name' );
		expect( statementElements[ 0 ].find( '.infobox-statement-value' ).text() ).toBe( 'John Doe, Jane Doe' );

		expect( statementElements[ 1 ].find( '.infobox-statement-property' ).text() ).toBe( 'age' );
		expect( statementElements[ 1 ].find( '.infobox-statement-value' ).text() ).toBe( '30' );

		expect( statementElements[ 2 ].find( '.infobox-statement-property' ).text() ).toBe( 'website' );
		const linkElement = statementElements[ 2 ].find( '.infobox-statement-value a' );
		expect( linkElement.attributes( 'href' ) ).toBe( 'https://example.com' );
		expect( linkElement.text() ).toBe( 'https://example.com' );
	} );

	it( 'renders without statements when subject has no statements', () => {
		const emptySubject = new Subject(
			new SubjectId( '00000000-0000-0000-0000-000000000002' ),
			'Empty Subject',
			'TestSchema',
			new StatementList( [] ),
			new PageIdentifiers( 2, 'Empty_Subject' )
		);

		const wrapper = mount( AutomaticInfobox, {
			props: {
				subject: emptySubject,
				valueFormatRegistry: Neo.getInstance().getValueFormatRegistry() // TODO: test instance
			},
			global: {
				mocks: {
					$i18n
				}
			}
		} );

		expect( wrapper.findAll( '.infobox-statement' ) ).toHaveLength( 0 );
	} );
} );
