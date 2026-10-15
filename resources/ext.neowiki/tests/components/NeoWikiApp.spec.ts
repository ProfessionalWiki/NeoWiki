import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount, VueWrapper } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import NeoWikiApp from '@/components/NeoWikiApp.vue';
import Infobox from '@/components/Views/Infobox.vue';
import { StoreStateLoader } from '@/persistence/StoreStateLoader.ts';
import { StubSubjectRepository } from '@/domain/SubjectRepository.ts';
import { InMemorySchemaRepository } from '@/application/SchemaRepository.ts';
import { InMemoryLayoutLookup } from '@/application/LayoutLookup.ts';
import { NeoWikiExtension } from '@/NeoWikiExtension.ts';
import { Service } from '@/NeoWikiServices.ts';
import { SubjectId } from '@/domain/SubjectId.ts';
import type { Subject } from '@/domain/Subject.ts';
import type { StatementList } from '@/domain/StatementList.ts';
import { RelationType } from '@/domain/propertyTypes/Relation.ts';
import { Neo } from '@/Neo.ts';
import { newDisplayRules, newSchema, newSubject } from '@/TestHelpers.ts';
import type { Schema } from '@/domain/Schema.ts';
import { PropertyDefinitionList } from '@/domain/PropertyDefinitionList.ts';
import { newRelationProperty } from '@/domain/propertyTypes/Relation.ts';
import { newTextProperty, TextType } from '@/domain/propertyTypes/Text.ts';
import { Layout } from '@/domain/Layout.ts';
import SubjectCreatorDialog from '@/components/SubjectCreator/SubjectCreatorDialog.vue';
import { NeoWikiTestServices } from '../NeoWikiTestServices.ts';
import { clickIsFollowed, createI18nMock, setupMwMock } from '../VueTestHelpers.ts';

const loadedId = new SubjectId( 's11111111111111' );
const unloadableId = new SubjectId( 's22222222222222' );

describe( 'NeoWikiApp', () => {

	let pinia: ReturnType<typeof createPinia>;

	beforeEach( () => {
		setupMwMock( { functions: [ 'config', 'message', 'msg', 'util' ] } );
		document.body.innerHTML = '';
		pinia = createPinia();
		setActivePinia( pinia );
		vi.spyOn( console, 'warn' ).mockImplementation( () => undefined );
	} );

	afterEach( () => {
		vi.restoreAllMocks();
	} );

	/** Only the Company Schema loads. */
	function loadFrom( ...subjects: Subject[] ): void {
		loadWithCompanySchema( newSchema( { title: 'Company' } ), subjects );
	}

	function loadWithCompanySchema( company: Schema, subjects: Subject[] ): void {
		loadWith( [ company ], subjects, [] );
	}

	function loadWith( schemas: Schema[], subjects: Subject[], layouts: Layout[] ): void {
		vi.spyOn( NeoWikiExtension.getInstance(), 'getStoreStateLoader' ).mockReturnValue(
			new StoreStateLoader(
				new StubSubjectRepository( subjects ),
				new InMemorySchemaRepository( schemas ),
				new InMemoryLayoutLookup( layouts ),
			),
		);
	}

	function relationsTo( target: SubjectId ): StatementList {
		return Neo.getInstance().getSubjectDeserializer().deserializeStatements( {
			Products: {
				value: [ { target: target.text } ],
				propertyType: RelationType.typeName,
			},
		} );
	}

	function placeViewFor( subjectId: SubjectId, layoutName?: string ): void {
		const placeholder = document.createElement( 'div' );
		placeholder.className = 'ext-neowiki-view';
		placeholder.dataset.mwNeowikiSubjectId = subjectId.text;
		if ( layoutName !== undefined ) {
			placeholder.dataset.mwNeowikiLayoutName = layoutName;
		}
		document.body.append( placeholder );
	}

	async function mountApp( showSubjectCreator = false ): Promise<VueWrapper> {
		const wrapper = mount( NeoWikiApp, {
			props: { showSubjectCreator, pageHasMainSubject: false },
			global: {
				plugins: [ pinia ],
				mocks: { $i18n: createI18nMock() },
				directives: { tooltip: {} },
				stubs: { SubjectCreatorDialog: true },
				provide: {
					...NeoWikiTestServices.getServices(),
					[ Service.SubjectPermissionHints ]: { canEditSubject: () => Promise.resolve( false ) },
				},
			},
		} );
		await flushPromises();

		return wrapper;
	}

	function mountedSubjectIds( wrapper: VueWrapper ): string[] {
		return wrapper.findAllComponents( Infobox ).map( ( view ) => view.props( 'subjectId' ).text );
	}

	it( 'mounts the Views whose Subject loaded', async () => {
		// The repository holds only one of the two Subjects, which is how a Subject the viewer may
		// not read reaches the loader: the read answers for it as for one that does not exist.
		loadFrom( newSubject( { id: loadedId, schemaName: 'Company' } ) );
		placeViewFor( loadedId );
		placeViewFor( unloadableId );

		const wrapper = await mountApp();

		expect( mountedSubjectIds( wrapper ) ).toEqual( [ loadedId.text ] );
	} );

	// The other Subject reaches the Subject store without its Schema twice over: through its own load,
	// whose Schema read fails, and as the target of the loaded Subject's relations.
	it( 'does not mount a View whose Subject is stored without its Schema', async () => {
		loadFrom(
			newSubject( { id: loadedId, schemaName: 'Company', statements: relationsTo( unloadableId ) } ),
			newSubject( { id: unloadableId, schemaName: 'Product' } ),
		);
		placeViewFor( loadedId );
		placeViewFor( unloadableId );

		const wrapper = await mountApp();

		expect( mountedSubjectIds( wrapper ) ).toEqual( [ loadedId.text ] );
	} );

	describe( 'a relation target no read found', () => {

		const missingId = new SubjectId( 's33333333333333' );

		beforeEach( () => {
			// Where the page's creator is teleported to.
			const content = document.createElement( 'div' );
			content.id = 'mw-content-text';
			document.body.append( content );

			loadWithCompanySchema(
				newSchema( {
					title: 'Company',
					properties: new PropertyDefinitionList( [
						newRelationProperty( { name: 'Products', targetSchemas: [ 'Product' ] } ),
					] ),
				} ),
				[ newSubject( { id: loadedId, schemaName: 'Company', statements: relationsTo( missingId ) } ) ],
			);
			placeViewFor( loadedId );
		} );

		function clickRedLink(): boolean {
			return clickIsFollowed( document.querySelector( '.ext-neowiki-view a.new' )! );
		}

		it( 'opens the page\'s creator on it from its red link', async () => {
			const wrapper = await mountApp( true );

			clickRedLink();
			await flushPromises();

			const creator = wrapper.findComponent( SubjectCreatorDialog );
			expect( creator.props( 'open' ) ).toBe( true );
			expect( creator.props( 'relationTarget' ) ).toEqual( { subjectId: missingId, schemaNames: [ 'Product' ] } );
		} );

		it( 'leaves its red link to be followed where the page offers no creator', async () => {
			await mountApp( false );

			expect( clickRedLink() ).toBe( true );
		} );

	} );

	describe( 'a relation to a Dependent Subject', () => {

		const foundingId = new SubjectId( 's1foundingAAAA1' );
		const productId = new SubjectId( 's1productAAAAA1' );

		beforeEach( () => {
			loadWith(
				[
					newSchema( {
						title: 'Company',
						properties: new PropertyDefinitionList( [
							newRelationProperty( { name: 'Founding', targetSchemas: [ 'Founding' ] } ),
							newRelationProperty( { name: 'Products', targetSchemas: [ 'Product' ] } ),
						] ),
					} ),
					newSchema( {
						title: 'Founding',
						properties: new PropertyDefinitionList( [ newTextProperty( { name: 'Year' } ) ] ),
						subjectsAreDependent: true,
					} ),
				],
				[
					newSubject( {
						id: loadedId,
						label: 'ACME Inc',
						schemaName: 'Company',
						statements: Neo.getInstance().getSubjectDeserializer().deserializeStatements( {
							Founding: { value: [ { target: foundingId.text } ], propertyType: RelationType.typeName },
							Products: { value: [ { target: productId.text } ], propertyType: RelationType.typeName },
						} ),
					} ),
					newSubject( {
						id: foundingId,
						label: null,
						displayNameIsGenerated: true,
						schemaName: 'Founding',
						statements: Neo.getInstance().getSubjectDeserializer().deserializeStatements( {
							Year: { value: [ '1849' ], propertyType: TextType.typeName },
						} ),
						hostSubject: {
							id: loadedId.text,
							schemaName: 'Company',
							displayName: 'ACME Inc',
							displayNameIsGenerated: false,
							hostSubject: null,
						},
					} ),
					newSubject( { id: productId, label: 'Anvil', schemaName: 'Product' } ),
				],
				[ new Layout( 'CompanyFounding', 'Company', 'infobox', '', newDisplayRules( 'Founding' ), {} ) ],
			);
		} );

		function shownPropertyNames(): string[] {
			return [ ...document.querySelectorAll( '.ext-neowiki-view .ext-neowiki-infobox__property' ) ]
				.map( ( property ) => property.textContent!.trim() );
		}

		it( 'is left out of a View without a Layout', async () => {
			placeViewFor( loadedId );

			await mountApp();

			expect( shownPropertyNames() ).toEqual( [ 'Products' ] );
		} );

		it( 'shows the Dependent Subject inside a View whose Layout names its property', async () => {
			placeViewFor( loadedId, 'CompanyFounding' );

			await mountApp();

			expect( document.querySelector( '.ext-neowiki-view .ext-neowiki-dependent-subject-display' )?.textContent )
				.toContain( '1849' );
		} );

	} );

} );
