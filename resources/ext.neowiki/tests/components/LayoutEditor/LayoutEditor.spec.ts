import { flushPromises, mount, VueWrapper } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import LayoutEditor from '@/components/LayoutEditor/LayoutEditor.vue';
import { InMemorySchemaRepository } from '@/application/SchemaRepository.ts';
import { Layout } from '@/domain/Layout.ts';
import { PropertyDefinitionList } from '@/domain/PropertyDefinitionList.ts';
import { newRelationProperty } from '@/domain/propertyTypes/Relation.ts';
import { newTextProperty } from '@/domain/propertyTypes/Text.ts';
import { NeoWikiExtension } from '@/NeoWikiExtension.ts';
import { Service } from '@/NeoWikiServices.ts';
import { newSchema } from '@/TestHelpers.ts';
import { createI18nMock } from '../../VueTestHelpers.ts';

const museum = newSchema( {
	title: 'Museum',
	properties: new PropertyDefinitionList( [
		newTextProperty( { name: 'Description' } ),
		newRelationProperty( { name: 'Attendance figures', targetSchemas: [ 'Attendance' ], multiple: true } ),
		newTextProperty( { name: 'Website' } ),
	] ),
} );

const attendance = newSchema( { title: 'Attendance', subjectsAreDependent: true } );

describe( 'LayoutEditor', () => {
	let pinia: ReturnType<typeof createPinia>;
	let schemaRepository: InMemorySchemaRepository;

	beforeEach( () => {
		pinia = createPinia();
		setActivePinia( pinia );

		// The Schema summaries are read through the extension, the edited Schema through the injected services:
		// both reach the one repository, as they reach the one REST API on a wiki.
		schemaRepository = new InMemorySchemaRepository( [ museum, attendance ] );
		vi.spyOn( NeoWikiExtension.getInstance(), 'getSchemaRepository' ).mockReturnValue( schemaRepository );
	} );

	afterEach( () => {
		vi.restoreAllMocks();
	} );

	async function mountEditor( layout: Layout ): Promise<VueWrapper> {
		const wrapper = mount( LayoutEditor, {
			props: { initialLayout: layout },
			global: {
				plugins: [ pinia ],
				mocks: { $i18n: createI18nMock() },
				provide: {
					[ Service.SchemaRepository ]: schemaRepository,
				},
			},
		} );

		await flushPromises();
		return wrapper;
	}

	function hiddenProperties( wrapper: VueWrapper ): string[] {
		return wrapper.findAll( '.ext-neowiki-display-rule-list__item--hidden' ).map( ( row ) => row.text() );
	}

	it( 'hides the properties targeting Dependent Subjects of a Layout without Display Rules', async () => {
		const wrapper = await mountEditor( new Layout( 'Museum overview', 'Museum', 'infobox', '', [], {} ) );

		expect( hiddenProperties( wrapper ) ).toEqual( [ 'Attendance figures' ] );
	} );

} );
