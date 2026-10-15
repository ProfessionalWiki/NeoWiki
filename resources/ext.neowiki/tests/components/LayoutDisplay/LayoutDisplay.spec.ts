import { mount, VueWrapper, flushPromises } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi, beforeEach } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import { ref } from 'vue';
import LayoutDisplay from '@/components/LayoutDisplay/LayoutDisplay.vue';
import LayoutDisplayHeader from '@/components/LayoutDisplay/LayoutDisplayHeader.vue';
import LayoutEditorDialog from '@/components/LayoutEditor/LayoutEditorDialog.vue';
import { Layout, type DisplayRule } from '@/domain/Layout.ts';
import { NeoWikiExtension } from '@/NeoWikiExtension.ts';
import { Service } from '@/NeoWikiServices.ts';
import { setupMwMock, createI18nMock } from '../../VueTestHelpers.ts';
import { PropertyName } from '@/domain/PropertyDefinition.ts';
import { PropertyDefinitionList } from '@/domain/PropertyDefinitionList.ts';
import { newRelationProperty } from '@/domain/propertyTypes/Relation.ts';
import { InMemorySchemaRepository } from '@/application/SchemaRepository.ts';
import type { Schema } from '@/domain/Schema.ts';
import { newSchema } from '@/TestHelpers.ts';

const checkEditPermissionMock = vi.fn();
const canEditLayoutRef = ref( false );

vi.mock( '@/composables/useLayoutPermissions.ts', () => ( {
	useLayoutPermissions: () => ( {
		canEditLayout: canEditLayoutRef,
		checkEditPermission: checkEditPermissionMock,
	} ),
} ) );

const getLayoutMock = vi.fn();
const getSchemaMock = vi.fn();

// The Schema summaries are read through the extension, the Layout's Schema through the injected services: both
// reach the one repository, as they reach the one REST API on a wiki.
function wikiHolding( ...schemas: Schema[] ): void {
	const repository = new InMemorySchemaRepository( schemas );

	getSchemaMock.mockImplementation( ( name: string ) => repository.getSchema( name ) );
	vi.spyOn( NeoWikiExtension.getInstance(), 'getSchemaRepository' ).mockReturnValue( repository );
}

function newLayout( description: string, displayRules: DisplayRule[] = [] ): Layout {
	return new Layout( 'CompanyInfo', 'Company', 'infobox', description, displayRules, {} );
}

function mountComponent( layout: Layout ): VueWrapper {
	setupMwMock( { functions: [ 'msg', 'notify' ] } );

	return mount( LayoutDisplay, {
		props: { layout },
		global: {
			plugins: [ createPinia() ],
			mocks: { $i18n: createI18nMock() },
			provide: {
				[ Service.ComponentRegistry ]: NeoWikiExtension.getInstance().getTypeSpecificComponentRegistry(),
				[ Service.LayoutRepository ]: { getLayout: getLayoutMock },
				[ Service.SchemaRepository ]: { getSchema: getSchemaMock },
			},
			stubs: {
				CdxIcon: true,
				LayoutDisplayHeader: true,
				LayoutEditorDialog: true,
			},
		},
	} );
}

function emptyStateText( wrapper: VueWrapper ): string {
	return wrapper.find( '.cdx-table__table__empty-state-content' ).text();
}

describe( 'LayoutDisplay', () => {
	beforeEach( () => {
		setActivePinia( createPinia() );
		canEditLayoutRef.value = false;
		checkEditPermissionMock.mockClear();
		getLayoutMock.mockReset();
		getSchemaMock.mockReset();
		wikiHolding( newSchema( { title: 'Company' } ) );
	} );

	afterEach( () => {
		vi.restoreAllMocks();
	} );

	it( 'passes layout and canEditLayout to the header component', () => {
		const layout = newLayout( 'Company overview' );

		const header = mountComponent( layout ).findComponent( LayoutDisplayHeader );

		expect( header.props( 'layout' ) ).toStrictEqual( layout );
		expect( header.props( 'canEditLayout' ) ).toBe( false );
	} );

	it( 'renders a row per display rule', () => {
		const wrapper = mountComponent( newLayout( '', [
			{ property: new PropertyName( 'Website' ) },
			{ property: new PropertyName( 'Founded' ), displayAttributes: { precision: 0 } },
		] ) );

		const rows = wrapper.findAll( 'tbody tr' );
		expect( rows ).toHaveLength( 2 );
		expect( rows[ 0 ].text() ).toContain( 'Website' );
		expect( rows[ 1 ].text() ).toContain( 'Founded' );
	} );

	it( 'shows the empty message when the layout has no display rules', async () => {
		const wrapper = mountComponent( newLayout( '' ) );
		await flushPromises();

		expect( emptyStateText( wrapper ) ).toBe( 'neowiki-layout-display-no-rules' );
	} );

	it( 'names the properties targeting Dependent Subjects as left out when the layout has no display rules', async () => {
		wikiHolding(
			newSchema( {
				title: 'Company',
				properties: new PropertyDefinitionList( [
					newRelationProperty( { name: 'Annual reports', targetSchemas: [ 'Annual report' ] } ),
				] ),
			} ),
			newSchema( { title: 'Annual report', subjectsAreDependent: true } ),
		);

		const wrapper = mountComponent( newLayout( '' ) );
		await flushPromises();

		expect( emptyStateText( wrapper ) ).toBe( 'neowiki-layout-display-no-rules-dependentAnnual reports1' );
	} );

	it( 'renders LayoutEditorDialog only with edit permission', () => {
		expect( mountComponent( newLayout( '' ) ).findComponent( LayoutEditorDialog ).exists() ).toBe( false );

		canEditLayoutRef.value = true;

		expect( mountComponent( newLayout( '' ) ).findComponent( LayoutEditorDialog ).exists() ).toBe( true );
	} );

	it( 'opens the editor on the layout fetched from the repository', async () => {
		canEditLayoutRef.value = true;
		// A description the prop does not carry, so the assertion can only pass if the dialog
		// received the repository's layout rather than the one passed in.
		const fetched = newLayout( 'from the repository' );
		getLayoutMock.mockResolvedValue( fetched );

		const wrapper = mountComponent( newLayout( 'stale' ) );
		await wrapper.findComponent( LayoutDisplayHeader ).vm.$emit( 'edit' );
		await flushPromises();

		expect( getLayoutMock ).toHaveBeenCalledWith( 'CompanyInfo' );
		const dialog = wrapper.findComponent( LayoutEditorDialog );
		expect( dialog.props( 'open' ) ).toBe( true );
		expect( dialog.props( 'initialLayout' ) ).toStrictEqual( fetched );
	} );

	it( 'reports a failed layout fetch instead of opening the editor', async () => {
		canEditLayoutRef.value = true;
		getLayoutMock.mockRejectedValue( new Error( 'Unknown layout: CompanyInfo' ) );

		const wrapper = mountComponent( newLayout( '' ) );
		await wrapper.findComponent( LayoutDisplayHeader ).vm.$emit( 'edit' );
		await flushPromises();

		expect( wrapper.findComponent( LayoutEditorDialog ).props( 'open' ) ).toBe( false );
		expect( mw.notify ).toHaveBeenCalledWith( 'Unknown layout: CompanyInfo', { type: 'error' } );
	} );

	it( 'shows the saved layout once the editor reports a save', async () => {
		canEditLayoutRef.value = true;
		const saved = newLayout( 'saved' );

		const wrapper = mountComponent( newLayout( 'before' ) );
		await wrapper.findComponent( LayoutEditorDialog ).vm.$emit( 'saved', saved );
		await flushPromises();

		expect( wrapper.findComponent( LayoutDisplayHeader ).props( 'layout' ) ).toStrictEqual( saved );
	} );
} );
