import { beforeEach, describe, expect, it, vi } from 'vitest';
import type { VueWrapper } from '@vue/test-utils';
import EditConflictEditor from '@/components/SubjectEditor/EditConflictEditor.vue';
import EditConflictValueDisplay from '@/components/SubjectEditor/EditConflictValueDisplay.vue';
import { EditConflict, type Choice } from '@/domain/EditConflict';
import { Statement } from '@/domain/Statement';
import { StatementList } from '@/domain/StatementList';
import { PropertyName, type PropertyDefinition } from '@/domain/PropertyDefinition';
import { newStringValue } from '@/domain/Value';
import { newTextProperty, TextType } from '@/domain/propertyTypes/Text';
import { newUrlProperty, UrlType } from '@/domain/propertyTypes/Url';
import { newSubject } from '@/TestHelpers';
import { createTestWrapper, setupMwMock } from '../../VueTestHelpers';

const HOST = 's11111111111111';

function text( propertyName: string, ...parts: string[] ): Statement {
	return new Statement( new PropertyName( propertyName ), TextType.typeName, newStringValue( ...parts ) );
}

function host( ...statements: Statement[] ): ReturnType<typeof newSubject> {
	return newSubject( { id: HOST, label: 'ACME Inc', statements: new StatementList( statements ) } );
}

function conflict(): EditConflict {
	return EditConflict.between(
		[ host( text( 'Websites', 'acme.example' ), text( 'Progress', '42' ), text( 'Status', 'Active' ) ) ],
		[ host( text( 'Websites', 'acme.example', 'shop.acme.example' ), text( 'Progress', '57' ), text( 'Status', 'Active' ) ) ],
		[ host( text( 'Websites', 'acme.example', 'blog.acme.example' ), text( 'Progress', '42' ), text( 'Status', 'Acquired' ) ) ],
	);
}

type PropertyDefinitionOf = ( subjectId: string, propertyName: string ) => PropertyDefinition | undefined;

function mount(
	editConflict: EditConflict,
	propertyDefinitionOf: PropertyDefinitionOf,
	choices: ReadonlyMap<string, Choice> = new Map(),
): VueWrapper {
	return createTestWrapper( EditConflictEditor, {
		conflict: editConflict,
		subjectName: 'ACME Inc',
		choices,
		nameOf: () => 'ACME Inc',
		propertyDefinitionOf,
	} );
}

function mountEditor( severalWebsites: boolean, choices: ReadonlyMap<string, Choice> = new Map() ): VueWrapper {
	return mount(
		conflict(),
		( _subjectId, propertyName ) => newTextProperty( { name: propertyName, multiple: propertyName === 'Websites' && severalWebsites } ),
		choices,
	);
}

function homepage( url: string ): Statement {
	return new Statement( new PropertyName( 'Homepage' ), UrlType.typeName, newStringValue( url ) );
}

function homepageConflict(): EditConflict {
	return EditConflict.between(
		[ host( homepage( 'https://acme.example' ) ) ],
		[ host( homepage( 'https://shop.acme.example' ) ) ],
		[ host( homepage( 'https://blog.acme.example' ) ) ],
	);
}

function websitesKey(): string {
	return conflict().bothChanged[ 0 ].key;
}

function captions( wrapper: VueWrapper ): string[] {
	return wrapper.findAll( '.ext-neowiki-edit-conflict-editor__side' ).map( ( caption ) => caption.text() );
}

describe( 'EditConflictEditor', () => {

	beforeEach( () => {
		setupMwMock();
	} );

	it( 'offers both lists for a property that takes several values', () => {
		expect( captions( mountEditor( true ) ) ).toEqual( [
			'neowiki-subject-editor-edit-conflict-theirs',
			'neowiki-subject-editor-edit-conflict-yours',
			'neowiki-subject-editor-edit-conflict-both-lists',
		] );
	} );

	it( 'offers no both for a property that takes one value', () => {
		expect( captions( mountEditor( false ) ) ).toEqual( [
			'neowiki-subject-editor-edit-conflict-theirs',
			'neowiki-subject-editor-edit-conflict-yours',
		] );
	} );

	it( 'moves focus to the list as it shows', () => {
		const focus = vi.spyOn( HTMLElement.prototype, 'focus' );

		const wrapper = mountEditor( true );

		expect( focus.mock.contexts ).toContain( wrapper.find( '.ext-neowiki-edit-conflict-editor__title' ).element );
		focus.mockRestore();
	} );

	it( 'chooses a value whose link is clicked instead of following the link', () => {
		const wrapper = mount( homepageConflict(), () => newUrlProperty( { name: 'Homepage' } ) );
		const click = new MouseEvent( 'click', { bubbles: true, cancelable: true } );

		wrapper.findAll( '.ext-neowiki-edit-conflict-editor__option' )[ 1 ].find( 'a' ).element.dispatchEvent( click );

		expect( click.defaultPrevented ).toBe( true );
		expect( wrapper.emitted( 'choose' )?.[ 0 ]?.[ 1 ] ).toBe( 'yours' );
	} );

	it( 'shows a value whose property it does not know as the value\'s stored data', () => {
		const theirs = mount( homepageConflict(), () => undefined ).findAllComponents( EditConflictValueDisplay )[ 0 ];

		expect( theirs.text() ).toBe( '["https://shop.acme.example"]' );
	} );

	it( 'reports the choice picked', async () => {
		const wrapper = mountEditor( true );

		await wrapper.findAll( 'input[type="radio"]' )[ 1 ].trigger( 'change' );

		expect( wrapper.emitted( 'choose' ) ).toEqual( [ [ websitesKey(), 'yours' ] ] );
	} );

	it( 'marks the choice made', () => {
		const wrapper = mountEditor( true, new Map( [ [ websitesKey(), 'theirs' ] ] ) );

		expect( wrapper.findAll( '.ext-neowiki-edit-conflict-editor__option--selected' ).map(
			( option ) => option.find( '.ext-neowiki-edit-conflict-editor__side' ).text(),
		) ).toEqual( [ 'neowiki-subject-editor-edit-conflict-theirs' ] );
	} );

	it( 'shows the value before either change', () => {
		const before = mountEditor( true ).find( '.ext-neowiki-edit-conflict-editor__before' );

		expect( before.text() ).toContain( 'acme.example' );
		expect( before.text() ).not.toContain( 'shop.acme.example' );
		expect( before.text() ).not.toContain( 'blog.acme.example' );
	} );

} );
