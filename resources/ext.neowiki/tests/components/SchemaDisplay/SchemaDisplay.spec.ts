import { DOMWrapper, mount, VueWrapper, flushPromises } from '@vue/test-utils';
import { describe, expect, it, vi, beforeEach } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import { ref } from 'vue';
import SchemaDisplay from '@/components/SchemaDisplay/SchemaDisplay.vue';
import SchemaDisplayHeader from '@/components/SchemaDisplay/SchemaDisplayHeader.vue';
import SchemaEditorDialog from '@/components/SchemaEditor/SchemaEditorDialog.vue';
import SubjectCreatorDialog from '@/components/SubjectCreator/SubjectCreatorDialog.vue';
import { useSubjectStore } from '@/stores/SubjectStore.ts';
import { Schema } from '@/domain/Schema.ts';
import { PropertyDefinitionList } from '@/domain/PropertyDefinitionList.ts';
import { createPropertyDefinitionFromJson } from '@/domain/PropertyDefinition.ts';
import { TextType } from '@/domain/propertyTypes/Text.ts';
import { NumberType } from '@/domain/propertyTypes/Number.ts';
import { NeoWikiExtension } from '@/NeoWikiExtension.ts';
import { Service } from '@/NeoWikiServices.ts';
import { setupMwMock, createI18nMock } from '../../VueTestHelpers.ts';
import { newSchema } from '@/TestHelpers.ts';

const checkEditPermissionMock = vi.fn();
const canEditSchemaRef = ref( false );

vi.mock( '@/composables/useSchemaPermissions.ts', () => ( {
	useSchemaPermissions: () => ( {
		canEditSchema: canEditSchemaRef,
		checkEditPermission: checkEditPermissionMock,
	} ),
} ) );

let grantedRight = false;
const canCreateSubjectPageRef = ref( false );
const checkCreateSubjectPagePermissionMock = vi.fn( async (): Promise<void> => {
	canCreateSubjectPageRef.value = grantedRight;
} );

vi.mock( '@/composables/useSubjectPermissions.ts', () => ( {
	useSubjectPermissions: () => ( {
		canCreateSubjectPage: canCreateSubjectPageRef,
		checkCreateSubjectPagePermission: checkCreateSubjectPagePermissionMock,
	} ),
} ) );

const getSchemaMock = vi.fn();

let pinia: ReturnType<typeof createPinia>;

function mountComponent( schema: Schema, config: Record<string, unknown> = {} ): VueWrapper {
	setupMwMock( {
		functions: [ 'msg', 'message', 'config', 'language', 'util' ],
		config: { wgUserLanguage: 'de', ...config },
	} );

	return mount( SchemaDisplay, {
		props: { schema },
		global: {
			plugins: [ pinia ],
			mocks: { $i18n: createI18nMock() },
			directives: {
				tooltip: { mounted: ( element: HTMLElement, binding: { value: unknown } ) => {
					element.dataset.tooltip = String( binding.value );
				} },
			},
			provide: {
				[ Service.ComponentRegistry ]: NeoWikiExtension.getInstance().getTypeSpecificComponentRegistry(),
				[ Service.SchemaRepository ]: { getSchema: getSchemaMock },
			},
			stubs: {
				CdxIcon: true,
				CdxInfoChip: { template: '<span><slot /></span>', props: [ 'icon' ] },
				SchemaDisplayHeader: true,
				SchemaEditorDialog: true,
				SubjectCreatorDialog: true,
				SubjectsTable: true,
			},
		},
	} );
}

function mountWithProperty( name: string, json: Record<string, unknown>, config: Record<string, unknown> = {} ): VueWrapper {
	return mountComponent(
		newSchema( { properties: new PropertyDefinitionList( [ createPropertyDefinitionFromJson( name, json ) ] ) } ),
		config,
	);
}

function cellOf( wrapper: VueWrapper, header: string ): DOMWrapper<Element> {
	const column = wrapper.findAll( 'thead th' ).map( ( headerCell ) => headerCell.text() ).indexOf( header );

	return wrapper.find( 'tbody tr' ).findAll( 'th, td' )[ column ];
}

describe( 'SchemaDisplay', () => {
	beforeEach( () => {
		pinia = createPinia();
		setActivePinia( pinia );
		canEditSchemaRef.value = false;
		checkEditPermissionMock.mockClear();
		grantedRight = false;
		canCreateSubjectPageRef.value = false;
		checkCreateSubjectPagePermissionMock.mockClear();
		getSchemaMock.mockReset();
	} );

	it( 'lists the Schema\'s Subjects', () => {
		const wrapper = mountComponent( newSchema( { title: 'Computer' } ) );

		const table = wrapper.findComponent( { name: 'SubjectsTable' } );
		expect( table.exists() ).toBe( true );
		expect( table.props( 'fixedSchema' ) ).toBe( 'Computer' );
	} );

	it( 'passes schema and canEditSchema to header component', () => {
		const schema = newSchema( { title: 'Test schema' } );

		const wrapper = mountComponent( schema );
		const header = wrapper.findComponent( SchemaDisplayHeader );

		expect( header.props( 'schema' ) ).toStrictEqual( schema );
		expect( header.props( 'canEditSchema' ) ).toBe( false );
	} );

	it( 'tells the header the user may create a subject page', async () => {
		grantedRight = true;

		const wrapper = mountComponent( newSchema() );
		await flushPromises();

		expect( wrapper.findComponent( SchemaDisplayHeader ).props( 'canCreateSubject' ) ).toBe( true );
	} );

	it( 'tells the header to offer no Subject creation for a Schema whose Subjects are dependent', async () => {
		grantedRight = true;

		const wrapper = mountComponent( newSchema( { subjectsAreDependent: true } ) );
		await flushPromises();

		expect( wrapper.findComponent( SchemaDisplayHeader ).props( 'canCreateSubject' ) ).toBe( false );
	} );

	it( 'opens the creator on this schema, page and all, when the header asks for it', async () => {
		grantedRight = true;
		const wrapper = mountComponent( newSchema( { title: 'Person' } ) );
		await flushPromises();

		await wrapper.findComponent( SchemaDisplayHeader ).vm.$emit( 'create-subject' );

		const dialog = wrapper.findComponent( SubjectCreatorDialog );
		expect( dialog.props( 'hostPage' ) ).toBeNull();
		expect( dialog.props( 'initialSchemaName' ) ).toBe( 'Person' );
		expect( useSubjectStore( pinia ).subjectCreatorOpen ).toBe( true );
	} );

	it( 'keeps the creator closed until it is asked for', async () => {
		grantedRight = true;

		mountComponent( newSchema() );
		await flushPromises();

		expect( useSubjectStore( pinia ).subjectCreatorOpen ).toBe( false );
	} );

	it( 'renders no creator for a user who may not create subject pages', async () => {
		const wrapper = mountComponent( newSchema() );
		await flushPromises();

		expect( wrapper.findComponent( SubjectCreatorDialog ).exists() ).toBe( false );
	} );

	it( 'renders property names and types', () => {
		const schema = newSchema( {
			properties: new PropertyDefinitionList( [
				createPropertyDefinitionFromJson( 'Website', { type: TextType.typeName } ),
				createPropertyDefinitionFromJson( 'Age', { type: NumberType.typeName } ),
			] ),
		} );

		const rows = mountComponent( schema ).findAll( 'tbody tr' );

		expect( rows ).toHaveLength( 2 );
		expect( rows[ 0 ].text() ).toContain( 'Website' );
		expect( rows[ 0 ].text() ).toContain( 'neowiki-property-type-text' );
		expect( rows[ 1 ].text() ).toContain( 'Age' );
		expect( rows[ 1 ].text() ).toContain( 'neowiki-property-type-number' );
	} );

	describe( 'a property with a label in the reader\'s language', () => {
		function birthDateNameCell(): DOMWrapper<Element> {
			return cellOf(
				mountWithProperty( 'Birth date', { type: TextType.typeName, labels: { de: 'Geburtsdatum' } } ),
				'neowiki-schema-display-property-name',
			);
		}

		it( 'is shown by its name', () => {
			expect( birthDateNameCell().text() ).toMatch( /^Birth date/ );
		} );

		it( 'has its label beneath the name', () => {
			expect( birthDateNameCell().find( '.ext-neowiki-schema-display__property-label' ).text() ).toBe( 'Geburtsdatum' );
		} );
	} );

	it( 'shows a property without a label in the reader\'s language by its name alone', () => {
		const wrapper = mountWithProperty( 'Birth date', { type: TextType.typeName, labels: { fr: 'Date de naissance' } } );

		expect( cellOf( wrapper, 'neowiki-schema-display-property-name' ).text() ).toBe( 'Birth date' );
	} );

	describe( 'the Type column', () => {
		function typeCellOf( wrapper: VueWrapper ): DOMWrapper<Element> {
			return cellOf( wrapper, 'neowiki-schema-display-property-type' );
		}

		it( 'names what a property takes beyond its type in a tooltip on the type', () => {
			const wrapper = mountWithProperty( 'Products', { type: 'relation', targetSchemas: [ 'Product' ] } );

			expect( typeCellOf( wrapper ).find( '[data-tooltip]' ).attributes( 'data-tooltip' ) ).toContain( 'Product' );
		} );

		it( 'gives a type with nothing to add no tooltip', () => {
			const wrapper = mountWithProperty( 'Website', { type: TextType.typeName } );

			expect( typeCellOf( wrapper ).find( '[data-tooltip]' ).exists() ).toBe( false );
		} );

		it( 'marks a required property', () => {
			const wrapper = mountWithProperty( 'Website', { type: TextType.typeName, required: true } );

			expect( typeCellOf( wrapper ).text() ).toContain( 'neowiki-schema-display-required' );
		} );

		it( 'says in a tooltip what happens without a required value', () => {
			const wrapper = mountWithProperty( 'Website', { type: TextType.typeName, required: true } );

			expect( typeCellOf( wrapper ).find( '[data-tooltip]' ).attributes( 'data-tooltip' ) )
				.toBe( 'neowiki-schema-display-required-warning' );
		} );

		it( 'leaves an optional property unmarked', () => {
			const wrapper = mountWithProperty( 'Website', { type: TextType.typeName } );

			expect( typeCellOf( wrapper ).text() ).not.toContain( 'neowiki-schema-display-required' );
		} );
	} );

	it( 'shows an empty value indicator for a missing description', () => {
		const wrapper = mountWithProperty( 'Website', { type: TextType.typeName } );

		expect( cellOf( wrapper, 'neowiki-schema-display-property-description' ).find( '.ext-neowiki-schema-display__empty-value' ).text() )
			.toBe( '-' );
	} );

	it( 'does not show empty value indicator when description is present', () => {
		const schema = newSchema( {
			properties: new PropertyDefinitionList( [
				createPropertyDefinitionFromJson( 'Website', {
					type: TextType.typeName,
					description: 'The main website URL',
					default: [ 'https://example.com' ],
				} ),
			] ),
		} );

		const wrapper = mountComponent( schema );

		const descriptionCell = cellOf( wrapper, 'neowiki-schema-display-property-description' );

		expect( descriptionCell.find( '.ext-neowiki-schema-display__empty-value' ).exists() ).toBe( false );
		expect( descriptionCell.text() ).toBe( 'The main website URL' );
	} );

	it( 'shows empty message when schema has no properties', () => {
		const wrapper = mountComponent( newSchema( { properties: new PropertyDefinitionList( [] ) } ) );

		expect( wrapper.text() ).toContain( 'neowiki-schema-display-no-properties' );
		expect( wrapper.find( '.cdx-table__table__empty-state' ).exists() ).toBe( true );
	} );

	it( 'renders SchemaEditorDialog when user has edit permission', () => {
		canEditSchemaRef.value = true;

		const wrapper = mountComponent( newSchema() );

		expect( wrapper.findComponent( SchemaEditorDialog ).exists() ).toBe( true );
	} );

	it( 'does not render SchemaEditorDialog when user lacks permission', () => {
		canEditSchemaRef.value = false;

		const wrapper = mountComponent( newSchema() );

		expect( wrapper.findComponent( SchemaEditorDialog ).exists() ).toBe( false );
	} );

	it( 'opens the dialog on the schema fetched from the repository when the header emits edit', async () => {
		canEditSchemaRef.value = true;
		setupMwMock( { functions: [ 'msg', 'notify' ] } );

		const schema = newSchema( { title: 'Person', description: 'stale' } );
		const fetched = newSchema( { title: 'Person', description: 'from the repository' } );
		getSchemaMock.mockResolvedValue( fetched );

		const wrapper = mountComponent( schema );
		expect( wrapper.findComponent( SchemaEditorDialog ).props( 'open' ) ).toBe( false );

		await wrapper.findComponent( SchemaDisplayHeader ).vm.$emit( 'edit' );
		await flushPromises();

		expect( getSchemaMock ).toHaveBeenCalledWith( 'Person' );
		const dialog = wrapper.findComponent( SchemaEditorDialog );
		expect( dialog.props( 'open' ) ).toBe( true );
		expect( dialog.props( 'initialSchema' ) ).toStrictEqual( fetched );
	} );

	it( 'shows the creator open once it is asked for', async () => {
		grantedRight = true;
		const wrapper = mountComponent( newSchema() );
		await flushPromises();

		await wrapper.findComponent( SchemaDisplayHeader ).vm.$emit( 'create-subject' );

		expect( wrapper.findComponent( SubjectCreatorDialog ).props( 'open' ) ).toBe( true );
	} );

	it( 'closes the creator in the store when the dialog closes', async () => {
		grantedRight = true;
		const wrapper = mountComponent( newSchema() );
		await flushPromises();
		useSubjectStore( pinia ).openSubjectCreator();

		wrapper.findComponent( SubjectCreatorDialog ).vm.$emit( 'update:open', false );

		expect( useSubjectStore( pinia ).subjectCreatorOpen ).toBe( false );
	} );
} );
