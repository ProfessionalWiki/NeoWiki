import { DOMWrapper, mount, VueWrapper } from '@vue/test-utils';
import { afterEach, describe, expect, it, beforeEach, vi } from 'vitest';
import { nextTick, ref, type Ref } from 'vue';
import { createPinia, setActivePinia, type Pinia } from 'pinia';
import RelationDisplay from '@/components/Value/RelationDisplay.vue';
import { EnclosingSubjectIdsKey, RelationTargetCreationKey, RelationTargetUrlKey } from '@/components/Value/ValueDisplayContract.ts';
import { newNumberValue, newRelation, newStringValue, RelationValue, type Value } from '@/domain/Value.ts';
import { newNumberProperty, NumberType } from '@/domain/propertyTypes/Number.ts';
import { SubjectId } from '@/domain/SubjectId.ts';
import { newRelationProperty, RelationType } from '@/domain/propertyTypes/Relation.ts';
import type { RelationProperty } from '@/domain/propertyTypes/Relation.ts';
import { useSubjectStore, type SubjectCreatorTarget } from '@/stores/SubjectStore.ts';
import type { Subject } from '@/domain/Subject.ts';
import { PageIdentifiers } from '@/domain/PageIdentifiers.ts';
import { newSchema, newSubject } from '@/TestHelpers.ts';
import { StatementList } from '@/domain/StatementList.ts';
import { Statement } from '@/domain/Statement.ts';
import { PropertyName, type PropertyDefinition } from '@/domain/PropertyDefinition.ts';
import { PropertyDefinitionList } from '@/domain/PropertyDefinitionList.ts';
import { newTextProperty, TextType } from '@/domain/propertyTypes/Text.ts';
import { useSchemaStore } from '@/stores/SchemaStore.ts';
import { NeoWikiTestServices } from '../../NeoWikiTestServices.ts';
import { accessibleName, clickIsFollowed, createI18nMock, setupMwMock } from '../../VueTestHelpers.ts';

const FOUND_ID = 's1111111111111A';
const MISSING_ID = 's1111111111111B';

function storeSubject( id: string, label: string | null, pageName: string, displayName?: string ): void {
	useSubjectStore().setSubject( newSubject( {
		id,
		label,
		displayName: displayName ?? label ?? '',
		pageIdentifiers: new PageIdentifiers( 42, pageName ),
	} ) );
}

function readAsNotFound( ...ids: string[] ): void {
	useSubjectStore().setSubjectsWithTargets( [], [], ids.map( ( id ) => new SubjectId( id ) ) );
}

function relationsTo( ...ids: string[] ): RelationValue {
	return new RelationValue( ids.map( ( id ) => newRelation( 'not-important', id ) ) );
}

describe( 'RelationDisplay.vue', () => {
	let pinia: Pinia;

	function mountDisplay(
		value: Value,
		provide: Record<symbol, unknown> = {},
		property: RelationProperty = newRelationProperty(),
	): VueWrapper {
		return mount( RelationDisplay, {
			props: {
				value: value,
				property: property,
			},
			global: {
				plugins: [ pinia ],
				provide: provide,
			},
		} );
	}

	function useWikiMode( subjectFirst: boolean ): void {
		setupMwMock( {
			functions: [ 'config', 'message', 'msg', 'util' ],
			config: { wgNeoWikiSubjectFirst: subjectFirst },
		} );
	}

	beforeEach( () => {
		useWikiMode( false );
		pinia = createPinia();
		setActivePinia( pinia );
	} );

	it( 'links a target to the page it is stored on on a page-first wiki', () => {
		storeSubject( FOUND_ID, 'Test Subject 1', 'Page_Name_1' );

		const wrapper = mountDisplay( relationsTo( FOUND_ID ) );

		const link = wrapper.find( 'a' );
		expect( link.text() ).toBe( 'Test Subject 1' );
		expect( link.attributes( 'href' ) ).toBe( '/wiki/Page_Name_1' );
	} );

	// Where a relation leads without a host to say otherwise follows the wiki's mode (ADR 33): the
	// page the target is stored on where the page is the entity, the target itself where it is not.
	it( 'links a relation to the target Subject itself on a subject-first wiki', () => {
		useWikiMode( true );
		storeSubject( FOUND_ID, 'Test Subject 1', 'Page_Name_1' );

		const wrapper = mountDisplay( relationsTo( FOUND_ID ) );

		expect( wrapper.find( 'a' ).attributes( 'href' ) ).toBe( `/wiki/Special:Subject/${ FOUND_ID }` );
	} );

	// A host that says where relations lead outranks the mode: it is showing Subjects, not pages.
	it( 'links a relation where the host says, rather than to the target\'s page', () => {
		useWikiMode( true );
		storeSubject( FOUND_ID, 'Test Subject 1', 'Page_Name_1' );

		const wrapper = mountDisplay(
			relationsTo( FOUND_ID ),
			{ [ RelationTargetUrlKey as symbol ]: ( target: Subject ) => '/elsewhere/' + target.getId().text },
		);

		expect( wrapper.find( 'a' ).attributes( 'href' ) ).toBe( `/elsewhere/${ FOUND_ID }` );
	} );

	it( 'renders the display name of a target that has no label, rather than its id', () => {
		storeSubject( FOUND_ID, null, 'Page_Name_1', 'Page Name 1' );

		const wrapper = mountDisplay( relationsTo( FOUND_ID ) );

		expect( wrapper.find( 'a' ).text() ).toBe( 'Page Name 1' );
	} );

	// A relation link is article content a reader sees, so a target nobody named must say so there
	// too, not only in the editing UI.
	it( 'marks a target whose name the server generated', () => {
		useSubjectStore().setSubject( newSubject( {
			id: FOUND_ID,
			label: null,
			displayNameIsGenerated: true,
			schemaName: 'Attendance',
			pageIdentifiers: new PageIdentifiers( 42, 'Rijksmuseum' ),
		} ) );

		const wrapper = mountDisplay( relationsTo( FOUND_ID ) );

		expect( wrapper.find( 'a' ).text() ).toBe( `(${ FOUND_ID })` );
	} );

	it( 'shows a target that was not found as a red link to its Special:Subject page', () => {
		readAsNotFound( MISSING_ID );

		const wrapper = mountDisplay( relationsTo( MISSING_ID ) );

		const link = wrapper.find( 'a.new' );
		expect( link.text() ).toBe( MISSING_ID );
		expect( link.attributes( 'href' ) ).toBe( `/wiki/Special:Subject/${ MISSING_ID }` );
	} );

	it( 'says on a red link that its target was not found', () => {
		readAsNotFound( MISSING_ID );

		const wrapper = mountDisplay( relationsTo( MISSING_ID ) );

		expect( wrapper.find( 'a.new' ).attributes( 'title' ) )
			.toBe( `neowiki-relation-target-not-found-title${ MISSING_ID }` );
	} );

	// Only a read that found nothing makes a red link. A target that was never read, or whose read
	// failed, may well exist.
	it( 'shows a target not read as its id, marked as an error', () => {
		const wrapper = mountDisplay( relationsTo( MISSING_ID ) );

		expect( wrapper.find( 'a' ).exists() ).toBe( false );
		expect( wrapper.find( 'span.error' ).text() ).toBe( MISSING_ID );
	} );

	// A row whose targets are fetched only when the reader opens it renders before they are in the
	// registry, so resolving once would leave those targets showing their bare ids forever.
	it( 'resolves a target that reaches the registry after the first render', async () => {
		const wrapper = mountDisplay( relationsTo( FOUND_ID ) );
		expect( wrapper.find( 'span' ).classes() ).toContain( 'error' );

		storeSubject( FOUND_ID, 'Test Subject 1', 'Page_Name_1' );
		await wrapper.vm.$nextTick();

		expect( wrapper.find( 'a' ).text() ).toBe( 'Test Subject 1' );
	} );

	// Someone may create the target between the read that missed it and one that finds it.
	it( 'links a target that a later read found', () => {
		readAsNotFound( FOUND_ID );
		storeSubject( FOUND_ID, 'Test Subject 1', 'Page_Name_1' );

		const wrapper = mountDisplay( relationsTo( FOUND_ID ) );

		expect( wrapper.find( 'a' ).text() ).toBe( 'Test Subject 1' );
	} );

	it( 'turns a target into a red link once a read finds nothing for it', async () => {
		const wrapper = mountDisplay( relationsTo( MISSING_ID ) );

		readAsNotFound( MISSING_ID );
		await wrapper.vm.$nextTick();

		expect( wrapper.find( 'a.new' ).text() ).toBe( MISSING_ID );
	} );

	describe( 'a red link whose target can be created', () => {

		const birthPlace = newRelationProperty( {
			name: 'Birth place',
			targetSchemas: [ 'City', { source: 'geo', name: 'Region' }, 'Place' ],
		} );

		function mountRedLinkTo(
			target: string,
			creationAllowed: Ref<boolean> = ref( true ),
			property: RelationProperty = birthPlace,
		): VueWrapper {
			readAsNotFound( target );

			return mountDisplay( relationsTo( target ), { [ RelationTargetCreationKey as symbol ]: creationAllowed }, property );
		}

		function clickRedLink( wrapper: VueWrapper, init: MouseEventInit = {} ): boolean {
			return clickIsFollowed( wrapper.find( 'a.new' ).element, init );
		}

		function openCreatorTarget(): SubjectCreatorTarget | null {
			const store = useSubjectStore();

			return store.subjectCreatorOpen ? store.subjectCreatorTarget : null;
		}

		it( 'opens the creator on the target, with the property\'s target Schemas of this wiki', () => {
			const wrapper = mountRedLinkTo( MISSING_ID );

			const followed = clickRedLink( wrapper );

			expect( followed ).toBe( false );
			expect( openCreatorTarget() ).toEqual( { subjectId: new SubjectId( MISSING_ID ), schemaNames: [ 'City', 'Place' ] } );
		} );

		it( 'is followed where this user may not create the target', () => {
			const wrapper = mountRedLinkTo( MISSING_ID, ref( false ) );

			expect( clickRedLink( wrapper ) ).toBe( true );
		} );

		// The wiki creates Subjects of its own Source only.
		it( 'is followed for a target of another Source', () => {
			const wrapper = mountRedLinkTo( 'geo:Q1492' );

			expect( clickRedLink( wrapper ) ).toBe( true );
			expect( openCreatorTarget() ).toBeNull();
		} );

		it( 'is followed where the property targets no Schema of this wiki', () => {
			const wrapper = mountRedLinkTo(
				MISSING_ID,
				ref( true ),
				newRelationProperty( { targetSchemas: [ { source: 'geo', name: 'Region' } ] } ),
			);

			expect( clickRedLink( wrapper ) ).toBe( true );
			expect( openCreatorTarget() ).toBeNull();
		} );

		// Opened in a new tab or window, the link leads to the target's Special:Subject page instead.
		it.each( [ 'ctrlKey', 'metaKey', 'shiftKey', 'altKey' ] )( 'is followed when clicked with %s', ( modifier ) => {
			const wrapper = mountRedLinkTo( MISSING_ID );

			expect( clickRedLink( wrapper, { [ modifier ]: true } ) ).toBe( true );
			expect( openCreatorTarget() ).toBeNull();
		} );

		// A host learns whether this user may create Subjects after the page has rendered.
		it( 'opens the creator once the host has granted it', () => {
			const granted = ref( false );
			const wrapper = mountRedLinkTo( MISSING_ID, granted );

			granted.value = true;

			expect( clickRedLink( wrapper ) ).toBe( false );
			expect( openCreatorTarget() ).not.toBeNull();
		} );

	} );

	// What a property given the relation type leaves behind in the Statements it already had.
	it( 'renders nothing for a value of another type', () => {
		const wrapper = mountDisplay( newStringValue( 'Málaga' ) );

		expect( wrapper.find( 'div > :is(a, span)' ).exists() ).toBe( false );
	} );

	describe( 'a Dependent Subject', () => {

		const BIRTH_ID = 's1111111111111C';
		const RECORD_ID = 's1111111111111D';
		const PLACE_ID = 's1111111111111E';

		function storeDependentSubject( id: string, schemaName: string, statements: StatementList ): void {
			useSubjectStore().setSubject( newSubject( {
				id,
				label: null,
				displayNameIsGenerated: true,
				schemaName,
				statements,
				pageIdentifiers: new PageIdentifiers( 42, 'Pablo Picasso' ),
				hostSubject: {
					id: 's1111111111111F',
					schemaName: 'Person',
					displayName: 'Pablo Picasso',
					displayNameIsGenerated: false,
					hostSubject: null,
				},
			} ) );
		}

		function storeSchema( name: string, properties: PropertyDefinition[], subjectsAreDependent: boolean ): void {
			useSchemaStore().setSchema(
				name,
				newSchema( { title: name, properties: new PropertyDefinitionList( properties ), subjectsAreDependent } ),
			);
		}

		function text( property: string, value: string ): Statement {
			return new Statement( new PropertyName( property ), TextType.typeName, newStringValue( value ) );
		}

		function relation( property: string, targetId: string ): Statement {
			return new Statement(
				new PropertyName( property ),
				RelationType.typeName,
				new RelationValue( [ newRelation( 'not-important', targetId ) ] ),
			);
		}

		function storeBirth( ...statements: Statement[] ): void {
			storeSchema( 'Birth', [
				newTextProperty( { name: 'Date' } ),
				newRelationProperty( { name: 'Place', targetSchemas: [ 'Place' ] } ),
				newRelationProperty( { name: 'Record', targetSchemas: [ 'Record' ] } ),
			], true );
			storeDependentSubject( BIRTH_ID, 'Birth', new StatementList( statements ) );
		}

		function mountNested(
			value: Value,
			provide: Record<symbol, unknown> = {},
			property: RelationProperty = newRelationProperty( { targetSchemas: [ 'Birth' ] } ),
		): VueWrapper {
			return mount( RelationDisplay, {
				props: { value, property },
				global: {
					plugins: [ pinia ],
					provide: { ...NeoWikiTestServices.getServices(), ...provide },
					mocks: { $i18n: createI18nMock() },
				},
			} );
		}

		it( 'is shown with its statements in a block, in place of a link, where the property holds a single value', () => {
			storeBirth( text( 'Date', '25 October 1881' ) );

			const wrapper = mountNested( relationsTo( BIRTH_ID ) );

			const block = wrapper.find( '.ext-neowiki-dependent-subject-display' );
			expect( block.text() ).toContain( 'Date' );
			expect( block.text() ).toContain( '25 October 1881' );
			expect( wrapper.find( 'a' ).exists() ).toBe( false );
			expect( wrapper.find( 'table' ).exists() ).toBe( false );
		} );

		it( 'shows the statements of a Dependent Subject it holds in turn', () => {
			storeBirth( relation( 'Record', RECORD_ID ) );
			storeSchema( 'Record', [ newTextProperty( { name: 'Archive' } ) ], true );
			storeDependentSubject( RECORD_ID, 'Record', new StatementList( [ text( 'Archive', 'Archivo de Málaga' ) ] ) );

			const wrapper = mountNested( relationsTo( BIRTH_ID ) );

			expect( wrapper.text() ).toContain( 'Archivo de Málaga' );
		} );

		it( 'links a standalone Subject it points at', () => {
			storeBirth( relation( 'Place', PLACE_ID ) );
			storeSubject( PLACE_ID, 'Málaga', 'Málaga' );

			const wrapper = mountNested( relationsTo( BIRTH_ID ) );

			expect( wrapper.find( 'a[href="/wiki/Málaga"]' ).text() ).toBe( 'Málaga' );
		} );

		it( 'is linked while its Schema is not at hand', () => {
			storeDependentSubject( BIRTH_ID, 'Birth', new StatementList( [ text( 'Date', '25 October 1881' ) ] ) );

			const wrapper = mountNested( relationsTo( BIRTH_ID ) );

			expect( wrapper.find( 'a' ).text() ).toBe( 'Birth of Pablo Picasso' );
		} );

		it( 'is linked where it encloses the value already', () => {
			storeBirth( text( 'Date', '25 October 1881' ) );

			const wrapper = mountNested(
				relationsTo( BIRTH_ID ),
				{ [ EnclosingSubjectIdsKey as symbol ]: ref( [ BIRTH_ID ] ) },
			);

			expect( wrapper.find( 'a' ).text() ).toBe( 'Birth of Pablo Picasso' );
		} );

		it( 'carries the name of its Schema where the property holds Subjects of several', () => {
			storeBirth( text( 'Date', '25 October 1881' ) );

			const wrapper = mountNested(
				relationsTo( BIRTH_ID ),
				{},
				newRelationProperty( { targetSchemas: [ 'Birth', 'Baptism' ] } ),
			);

			expect( wrapper.find( '.ext-neowiki-schema-name' ).text() ).toContain( 'Birth' );
		} );

		it( 'carries no Schema name where the property holds Subjects of one', () => {
			storeBirth( text( 'Date', '25 October 1881' ) );

			const wrapper = mountNested( relationsTo( BIRTH_ID ) );

			expect( wrapper.find( '.ext-neowiki-schema-name' ).exists() ).toBe( false );
		} );

		describe( 'of a property that holds several', () => {

			const LOAN_A = 's1111111111111G';
			const LOAN_B = 's1111111111111H';
			const LOAN_C = 's1111111111111J';
			const INSURANCE_ID = 's1111111111111K';
			const NAME_ID = 's1111111111111L';
			const SECOND_NAME_ID = 's1111111111111M';
			const PSEUDONYM_ID = 's1111111111111N';

			const loans = newRelationProperty( { name: 'Loans', targetSchemas: [ 'Loan' ], multiple: true } );

			function storeLoanSchema( ...extraProperties: PropertyDefinition[] ): void {
				storeSchema( 'Loan', [
					newTextProperty( { name: 'Borrower' } ),
					newTextProperty( { name: 'Venue' } ),
					newTextProperty( { name: 'Year' } ),
					...extraProperties,
				], true );
			}

			function storeLoan( id: string, ...statements: Statement[] ): void {
				storeDependentSubject( id, 'Loan', new StatementList( statements ) );
			}

			function tables( wrapper: VueWrapper ): DOMWrapper<Element>[] {
				return wrapper.findAll( 'table' );
			}

			function onlyTable( wrapper: VueWrapper ): DOMWrapper<Element> {
				const found = tables( wrapper );
				expect( found ).toHaveLength( 1 );

				return found[ 0 ];
			}

			function headersOf( table: DOMWrapper<Element> ): string[] {
				return table.findAll( ':scope > thead > tr > th' ).map( ( header ) => header.text() );
			}

			function cellsOf( row: DOMWrapper<Element> ): DOMWrapper<Element>[] {
				return row.findAll( ':scope > th, :scope > td' );
			}

			function rowsOf( table: DOMWrapper<Element> ): string[][] {
				return table.findAll( ':scope > tbody > tr' ).map( ( row ) => cellsOf( row ).map( ( cell ) => cell.text() ) );
			}

			function cellOf( table: DOMWrapper<Element>, row: number, column: number ): DOMWrapper<Element> {
				return cellsOf( table.findAll( ':scope > tbody > tr' )[ row ] )[ column ];
			}

			function regionOf( table: DOMWrapper<Element> ): Element {
				const region = table.element.closest( '[role="region"], section' );
				expect( region ).not.toBeNull();

				return region as Element;
			}

			// jsdom lays nothing out, so how wide a table is, and the place it has, are stated here.
			function laidOut( tableWidth: number, placeWidth: number ): void {
				vi.spyOn( Element.prototype, 'scrollWidth', 'get' ).mockReturnValue( tableWidth );
				vi.spyOn( Element.prototype, 'clientWidth', 'get' ).mockReturnValue( placeWidth );
			}

			// The edges past which more of the table lies, by the classes the stylesheet shades them with.
			function edgesWithMore( table: DOMWrapper<Element> ): string[] {
				const block = table.element.closest( '.ext-neowiki-dependent-subject-table' ) as Element;

				return [ 'before', 'after' ].filter(
					( edge ) => block.classList.contains( `ext-neowiki-dependent-subject-table--more-${ edge }` ),
				);
			}

			it( 'shows them as the rows of a table, in the order of the relation', () => {
				storeLoanSchema();
				storeLoan( LOAN_A, text( 'Borrower', 'Louvre' ) );
				storeLoan( LOAN_B, text( 'Borrower', 'Prado' ) );
				storeLoan( LOAN_C, text( 'Borrower', 'Uffizi' ) );

				const wrapper = mountNested( relationsTo( LOAN_C, LOAN_A, LOAN_B ), {}, loans );

				expect( rowsOf( onlyTable( wrapper ) ) ).toEqual( [ [ 'Uffizi' ], [ 'Louvre' ], [ 'Prado' ] ] );
				expect( wrapper.find( '.ext-neowiki-dependent-subject-display' ).exists() ).toBe( false );
			} );

			it( 'gives the table a column for each property of their Schema, in Schema order', () => {
				storeLoanSchema();
				storeLoan( LOAN_A, text( 'Year', '1990' ), text( 'Venue', 'Paris' ), text( 'Borrower', 'Louvre' ) );
				storeLoan( LOAN_B, text( 'Borrower', 'Prado' ) );

				const wrapper = mountNested( relationsTo( LOAN_A, LOAN_B ), {}, loans );

				expect( headersOf( onlyTable( wrapper ) ) ).toEqual( [ 'Borrower', 'Venue', 'Year' ] );
			} );

			it( 'leaves out the column of a property none of them has a value for', () => {
				storeLoanSchema();
				storeLoan( LOAN_A, text( 'Borrower', 'Louvre' ), text( 'Year', '1990' ) );
				storeLoan( LOAN_B, text( 'Borrower', 'Prado' ) );

				const wrapper = mountNested( relationsTo( LOAN_A, LOAN_B ), {}, loans );

				expect( headersOf( onlyTable( wrapper ) ) ).toEqual( [ 'Borrower', 'Year' ] );
			} );

			it( 'leaves the cell empty where one of them has no value for its column', () => {
				storeLoanSchema();
				storeLoan( LOAN_A, text( 'Borrower', 'Louvre' ), text( 'Year', '1990' ) );
				storeLoan( LOAN_B, text( 'Borrower', 'Prado' ) );

				const wrapper = mountNested( relationsTo( LOAN_A, LOAN_B ), {}, loans );

				expect( rowsOf( onlyTable( wrapper ) )[ 1 ] ).toEqual( [ 'Prado', '' ] );
			} );

			it( 'shows a table while the property holds a single value', () => {
				storeLoanSchema();
				storeLoan( LOAN_A, text( 'Borrower', 'Louvre' ) );

				const wrapper = mountNested( relationsTo( LOAN_A ), {}, loans );

				expect( rowsOf( onlyTable( wrapper ) ) ).toEqual( [ [ 'Louvre' ] ] );
			} );

			it( 'shows a table for each Schema among them, in the order of the property\'s target Schemas', () => {
				const names = newRelationProperty( { name: 'Names', targetSchemas: [ 'Name', 'Pseudonym' ], multiple: true } );
				storeSchema( 'Name', [ newTextProperty( { name: 'Text' } ) ], true );
				storeSchema( 'Pseudonym', [ newTextProperty( { name: 'Text' } ) ], true );
				storeDependentSubject( PSEUDONYM_ID, 'Pseudonym', new StatementList( [ text( 'Text', 'P. Ruiz' ) ] ) );
				storeDependentSubject( NAME_ID, 'Name', new StatementList( [ text( 'Text', 'Pablo' ) ] ) );
				storeDependentSubject( SECOND_NAME_ID, 'Name', new StatementList( [ text( 'Text', 'Picasso' ) ] ) );

				const wrapper = mountNested( relationsTo( PSEUDONYM_ID, NAME_ID, SECOND_NAME_ID ), {}, names );

				expect( tables( wrapper ).map( rowsOf ) ).toEqual( [ [ [ 'Pablo' ], [ 'Picasso' ] ], [ [ 'P. Ruiz' ] ] ] );
			} );

			it( 'names each table after its Schema where the property has several target Schemas', () => {
				const names = newRelationProperty( { name: 'Names', targetSchemas: [ 'Name', 'Pseudonym' ], multiple: true } );
				storeSchema( 'Name', [ newTextProperty( { name: 'Text' } ) ], true );
				storeSchema( 'Pseudonym', [ newTextProperty( { name: 'Text' } ) ], true );
				storeDependentSubject( NAME_ID, 'Name', new StatementList( [ text( 'Text', 'Pablo' ) ] ) );
				storeDependentSubject( PSEUDONYM_ID, 'Pseudonym', new StatementList( [ text( 'Text', 'P. Ruiz' ) ] ) );

				const wrapper = mountNested( relationsTo( NAME_ID, PSEUDONYM_ID ), {}, names );

				expect( tables( wrapper ).map( ( table ) => accessibleName( table.element ) ) )
					.toEqual( [ 'neowiki-schema-labelName', 'neowiki-schema-labelPseudonym' ] );
			} );

			it( 'repeats the name of the property nowhere in a table of its one target Schema', () => {
				storeLoanSchema();
				storeLoan( LOAN_A, text( 'Borrower', 'Louvre' ) );

				const wrapper = mountNested( relationsTo( LOAN_A ), {}, loans );

				expect( onlyTable( wrapper ).text() ).not.toContain( 'Loans' );
			} );

			describe( 'too wide for its place', () => {

				afterEach( () => {
					vi.restoreAllMocks();
				} );

				it( 'is in a region the keyboard can reach and scroll, named after the property', async () => {
					laidOut( 900, 300 );
					storeLoanSchema();
					storeLoan( LOAN_A, text( 'Borrower', 'Louvre' ) );

					const wrapper = mountNested( relationsTo( LOAN_A ), {}, loans );
					await nextTick();

					const region = regionOf( onlyTable( wrapper ) );
					expect( region.getAttribute( 'tabindex' ) ).toBe( '0' );
					expect( accessibleName( region ) ).toBe( 'Loans' );
				} );

				it( 'shows that more of it lies past its end', async () => {
					laidOut( 900, 300 );
					storeLoanSchema();
					storeLoan( LOAN_A, text( 'Borrower', 'Louvre' ) );

					const wrapper = mountNested( relationsTo( LOAN_A ), {}, loans );
					await nextTick();

					expect( edgesWithMore( onlyTable( wrapper ) ) ).toEqual( [ 'after' ] );
				} );

				it( 'shows that more of it lies before its start once scrolled to its end', async () => {
					laidOut( 900, 300 );
					storeLoanSchema();
					storeLoan( LOAN_A, text( 'Borrower', 'Louvre' ) );
					const wrapper = mountNested( relationsTo( LOAN_A ), {}, loans );
					await nextTick();

					const region = regionOf( onlyTable( wrapper ) );
					region.scrollLeft = 600;
					region.dispatchEvent( new Event( 'scroll' ) );
					await nextTick();

					expect( edgesWithMore( onlyTable( wrapper ) ) ).toEqual( [ 'before' ] );
				} );

				it( 'adds no tab stop where it fits', async () => {
					laidOut( 300, 300 );
					storeLoanSchema();
					storeLoan( LOAN_A, text( 'Borrower', 'Louvre' ) );

					const wrapper = mountNested( relationsTo( LOAN_A ), {}, loans );
					await nextTick();

					expect( wrapper.find( '[tabindex]' ).exists() ).toBe( false );
					expect( edgesWithMore( onlyTable( wrapper ) ) ).toEqual( [] );
				} );

			} );

			it( 'aligns numbers to the end of their cells', () => {
				storeLoanSchema( newNumberProperty( { name: 'Visitors' } ) );
				storeLoan( LOAN_A, text( 'Borrower', 'Louvre' ), new Statement( new PropertyName( 'Visitors' ), NumberType.typeName, newNumberValue( 1200 ) ) );

				const wrapper = mountNested( relationsTo( LOAN_A ), {}, loans );

				expect( cellOf( onlyTable( wrapper ), 0, 1 ).classes() ).toContain( 'ext-neowiki-dependent-subject-table__cell--number' );
				expect( cellOf( onlyTable( wrapper ), 0, 0 ).classes() ).not.toContain( 'ext-neowiki-dependent-subject-table__cell--number' );
			} );

			it( 'links a standalone Subject one of them points at, in its cell', () => {
				storeLoanSchema( newRelationProperty( { name: 'Lender', targetSchemas: [ 'Museum' ] } ) );
				storeLoan( LOAN_A, text( 'Borrower', 'Louvre' ), relation( 'Lender', PLACE_ID ) );
				storeSubject( PLACE_ID, 'Rijksmuseum', 'Rijksmuseum' );

				const wrapper = mountNested( relationsTo( LOAN_A ), {}, loans );

				expect( cellOf( onlyTable( wrapper ), 0, 1 ).find( 'a' ).attributes( 'href' ) ).toBe( '/wiki/Rijksmuseum' );
			} );

			it( 'shows a Dependent Subject one of them holds inside its cell', () => {
				storeLoanSchema( newRelationProperty( { name: 'Insurance', targetSchemas: [ 'Insurance' ] } ) );
				storeSchema( 'Insurance', [ newTextProperty( { name: 'Insurer' } ) ], true );
				storeLoan( LOAN_A, text( 'Borrower', 'Louvre' ), relation( 'Insurance', INSURANCE_ID ) );
				storeDependentSubject( INSURANCE_ID, 'Insurance', new StatementList( [ text( 'Insurer', 'Lloyd\'s' ) ] ) );

				const wrapper = mountNested( relationsTo( LOAN_A ), {}, loans );

				const cell = cellOf( onlyTable( wrapper ), 0, 1 );
				expect( cell.text() ).toContain( 'Lloyd\'s' );
				expect( cell.find( 'a' ).exists() ).toBe( false );
			} );

			it( 'links one of them that points back at itself rather than showing it inside itself again', () => {
				storeLoanSchema( newRelationProperty( { name: 'Renewal of', targetSchemas: [ 'Loan' ] } ) );
				storeLoan( LOAN_A, text( 'Borrower', 'Louvre' ), relation( 'Renewal of', LOAN_A ) );

				const wrapper = mountNested( relationsTo( LOAN_A ), {}, loans );

				expect( cellOf( onlyTable( wrapper ), 0, 1 ).text() ).toBe( 'Loan of Pablo Picasso' );
			} );

			it( 'links one of them while its Schema is not at hand', () => {
				storeLoan( LOAN_A, text( 'Borrower', 'Louvre' ) );

				const wrapper = mountNested( relationsTo( LOAN_A ), {}, loans );

				expect( wrapper.find( 'table' ).exists() ).toBe( false );
				expect( wrapper.find( 'a' ).text() ).toBe( 'Loan of Pablo Picasso' );
			} );

		} );

	} );
} );
