import { DOMWrapper, flushPromises, mount, VueWrapper } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import LayoutCreator from '@/components/LayoutsPage/LayoutCreator.vue';
import type { LayoutCreatorExposes } from '@/components/LayoutsPage/LayoutCreator.vue';
import { InMemorySchemaRepository } from '@/application/SchemaRepository.ts';
import { Layout } from '@/domain/Layout.ts';
import type { Schema } from '@/domain/Schema.ts';
import { PropertyDefinitionList } from '@/domain/PropertyDefinitionList.ts';
import { newTextProperty } from '@/domain/propertyTypes/Text.ts';
import { newRelationProperty } from '@/domain/propertyTypes/Relation.ts';
import { NeoWikiExtension } from '@/NeoWikiExtension.ts';
import { Service } from '@/NeoWikiServices.ts';
import { newSchema } from '@/TestHelpers.ts';
import { NeoWikiTestServices } from '../../NeoWikiTestServices.ts';
import { createI18nMock, setupMwMock } from '../../VueTestHelpers.ts';

const NAME_FIELD = 'neowiki-layout-creator-name-field';
const SCHEMA_FIELD = 'neowiki-layout-creator-schema-field';

/**
 * Refuses to search Schema names for nothing, as the wiki's REST API does.
 */
class RestApiLikeSchemaRepository extends InMemorySchemaRepository {

	public async getSchemaNames( search?: string ): Promise<string[]> {
		if ( !search ) {
			throw new Error( 'Error fetching schemas' );
		}

		return super.getSchemaNames();
	}

}

function schemaWithProperties( title: string, ...propertyNames: string[] ): Schema {
	return newSchema( {
		title,
		properties: new PropertyDefinitionList( propertyNames.map( ( name ) => newTextProperty( { name } ) ) ),
	} );
}

function newSchemaRepository(): RestApiLikeSchemaRepository {
	return new RestApiLikeSchemaRepository( [
		schemaWithProperties( 'Company', 'Founded' ),
		schemaWithProperties( 'Person', 'Name', 'Born' ),
		schemaWithProperties( 'Product', 'Price' ),
	] );
}

describe( 'LayoutCreator', () => {
	let pinia: ReturnType<typeof createPinia>;
	let schemaRepository: RestApiLikeSchemaRepository;

	beforeEach( () => {
		setupMwMock( { functions: [ 'msg' ] } );
		pinia = createPinia();
		setActivePinia( pinia );

		// The Schema list is loaded through the extension, the chosen Schema through the injected
		// services: both reach the one repository, as they reach the one REST API on a wiki.
		schemaRepository = newSchemaRepository();
		vi.spyOn( NeoWikiExtension.getInstance(), 'getSchemaRepository' ).mockReturnValue( schemaRepository );
	} );

	afterEach( () => {
		vi.restoreAllMocks();
	} );

	async function mountCreator(): Promise<VueWrapper> {
		const wrapper = mount( LayoutCreator, {
			global: {
				plugins: [ pinia ],
				mocks: { $i18n: createI18nMock() },
				provide: {
					...NeoWikiTestServices.getServices(),
					[ Service.SchemaRepository ]: schemaRepository,
				},
			},
		} );

		await flushPromises();
		return wrapper;
	}

	function field( wrapper: VueWrapper, label: string ): DOMWrapper<Element> {
		const found = wrapper.findAll( '.cdx-field' )
			.find( ( candidate ) => candidate.find( '.cdx-label__label__text' ).text() === label );

		expect( found, `no field labelled ${ label }` ).toBeDefined();
		return found!;
	}

	// A browser focuses the field it clicks, which jsdom leaves to the caller.
	async function openSchemaField( wrapper: VueWrapper ): Promise<void> {
		const control = field( wrapper, SCHEMA_FIELD ).find( '[role="combobox"]' );

		await control.trigger( 'focus' );
		await control.trigger( 'click' );
	}

	// A closed field shows the reader none of its options.
	function offeredSchemas( wrapper: VueWrapper ): string[] {
		const schemaField = field( wrapper, SCHEMA_FIELD );

		if ( schemaField.find( '[role="combobox"]' ).attributes( 'aria-expanded' ) !== 'true' ) {
			return [];
		}

		return schemaField.findAll( '[role="option"] .cdx-menu-item__text__label' ).map( ( label ) => label.text() );
	}

	async function pickSchema( wrapper: VueWrapper, name: string ): Promise<void> {
		await openSchemaField( wrapper );

		const option = field( wrapper, SCHEMA_FIELD ).findAll( '[role="option"]' )
			.find( ( candidate ) => candidate.find( '.cdx-menu-item__text__label' ).text() === name );

		expect( option, `the Schema field offers no ${ name }` ).toBeDefined();
		await option!.trigger( 'click' );
		await flushPromises();
	}

	function schemaInput( wrapper: VueWrapper ): DOMWrapper<HTMLInputElement> {
		return field( wrapper, SCHEMA_FIELD ).find<HTMLInputElement>( 'input' );
	}

	// A browser fires both when focus leaves a field; jsdom fires only the event it is handed.
	async function leaveSchemaField( wrapper: VueWrapper ): Promise<void> {
		await schemaInput( wrapper ).trigger( 'blur' );
		await schemaInput( wrapper ).trigger( 'focusout' );
	}

	async function enterName( wrapper: VueWrapper, name: string ): Promise<void> {
		await field( wrapper, NAME_FIELD ).find( 'input' ).setValue( name );
	}

	function listedProperties( wrapper: VueWrapper ): string[] {
		return wrapper.findAll( '.ext-neowiki-display-rule-list__item' ).map( ( row ) => row.text() );
	}

	function hiddenProperties( wrapper: VueWrapper ): string[] {
		return wrapper.findAll( '.ext-neowiki-display-rule-list__item--hidden' ).map( ( row ) => row.text() );
	}

	// As the dialog's "Create layout" does.
	async function createLayout( wrapper: VueWrapper ): Promise<Layout | null> {
		const creator = wrapper.vm as unknown as LayoutCreatorExposes;

		return await creator.validate() ? creator.getLayout() : null;
	}

	it( 'offers every Schema of the wiki in the Schema field', async () => {
		const wrapper = await mountCreator();

		await openSchemaField( wrapper );

		expect( offeredSchemas( wrapper ) ).toEqual( [ 'Company', 'Person', 'Product' ] );
	} );

	it( 'lists the properties of the picked Schema', async () => {
		const wrapper = await mountCreator();

		await pickSchema( wrapper, 'Person' );

		expect( listedProperties( wrapper ) ).toEqual( [ 'Name', 'Born' ] );
	} );

	it( 'lists the properties of the Schema picked last, though the one picked before it loads later', async () => {
		const wrapper = await mountCreator();
		const readSchema = schemaRepository.getSchema.bind( schemaRepository );
		let releasePerson!: () => void;
		vi.spyOn( schemaRepository, 'getSchema' ).mockImplementation( ( name: string ) => name === 'Person' ?
			new Promise( ( resolve ) => {
				releasePerson = () => resolve( readSchema( name ) );
			} ) :
			readSchema( name ) );

		await pickSchema( wrapper, 'Person' );
		await pickSchema( wrapper, 'Product' );
		releasePerson();
		await flushPromises();

		expect( listedProperties( wrapper ) ).toEqual( [ 'Price' ] );
	} );

	it( 'lists the properties of the Schema picked last, though the read for the one picked before it fails later', async () => {
		vi.spyOn( console, 'error' ).mockImplementation( () => undefined );
		const wrapper = await mountCreator();
		const readSchema = schemaRepository.getSchema.bind( schemaRepository );
		let failPerson!: () => void;
		vi.spyOn( schemaRepository, 'getSchema' ).mockImplementation( ( name: string ) => name === 'Person' ?
			new Promise( ( _resolve, reject ) => {
				failPerson = () => reject( new Error( 'read failed' ) );
			} ) :
			readSchema( name ) );

		await pickSchema( wrapper, 'Person' );
		await pickSchema( wrapper, 'Product' );
		failPerson();
		await flushPromises();

		expect( listedProperties( wrapper ) ).toEqual( [ 'Price' ] );
	} );

	it( 'hides the properties of the picked Schema that target Dependent Subjects', async () => {
		await schemaRepository.saveSchema( newSchema( {
			title: 'Museum',
			properties: new PropertyDefinitionList( [
				newTextProperty( { name: 'Description' } ),
				newRelationProperty( { name: 'Attendance figures', targetSchemas: [ 'Attendance' ], multiple: true } ),
				newTextProperty( { name: 'Website' } ),
			] ),
		} ) );
		await schemaRepository.saveSchema( newSchema( { title: 'Attendance', subjectsAreDependent: true } ) );
		const wrapper = await mountCreator();

		await pickSchema( wrapper, 'Museum' );

		expect( hiddenProperties( wrapper ) ).toEqual( [ 'Attendance figures' ] );
	} );

	it( 'keeps showing the picked Schema in the Schema field once it is left', async () => {
		const wrapper = await mountCreator();

		await pickSchema( wrapper, 'Person' );
		await leaveSchemaField( wrapper );

		expect( schemaInput( wrapper ).element.value ).toBe( 'Person' );
	} );

	it( 'makes an infobox Layout from a name and a Schema', async () => {
		const wrapper = await mountCreator();

		await enterName( wrapper, 'Staff card' );
		await pickSchema( wrapper, 'Person' );

		expect( await createLayout( wrapper ) ).toEqual( new Layout( 'Staff card', 'Person', 'infobox', '', [], {} ) );
	} );
} );
