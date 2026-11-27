import { DOMWrapper, enableAutoUnmount, flushPromises, mount, VueWrapper } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import BundleExporter from '../../src/components/BundleExporter.vue';
import { Service } from '@/NeoWikiServices.ts';
import { ModelBundleService } from '../../src/ModelBundleServices.ts';
import { SchemaDeserializer } from '@/persistence/SchemaDeserializer.ts';
import { newSchema } from '@/TestHelpers.ts';
import { createI18nMock, setupMwMock } from '../../../tests/VueTestHelpers.ts';
import { InMemoryDataModel } from '../application/InMemoryDataModel.ts';

enableAutoUnmount( afterEach );

let wiki: InMemoryDataModel;

async function mountExporter(): Promise<VueWrapper> {
	const wrapper = mount( BundleExporter, {
		global: {
			mocks: { $i18n: createI18nMock() },
			provide: {
				[ ModelBundleService.DataModelRepository ]: wiki,
				[ Service.SubjectCountLookup ]: { getSubjectCounts: async () => new Map( [ [ 'Person', 2 ], [ 'Museum', 0 ] ] ) },
			},
			stubs: { CdxIcon: true },
		},
	} );
	await flushPromises();

	return wrapper;
}

function schemaRow( wrapper: VueWrapper, name: string ): DOMWrapper<Element> {
	const row = wrapper.findAll( '.ext-neowiki-bundle-export__schema' ).find( ( candidate ) => candidate.find( '.cdx-checkbox' ).text() === name );

	expect( row ).toBeDefined();
	return row!;
}

describe( 'BundleExporter', () => {

	beforeEach( () => {
		setupMwMock( { functions: [ 'msg', 'config', 'language', 'util' ], config: { wgNeoWikiSubjectCountsAvailable: true } } );
		wiki = new InMemoryDataModel().withSchemas(
			new SchemaDeserializer().deserialize( 'Person', {
				propertyDefinitions: { Birth: { type: 'relation', targetSchemas: [ 'Birth' ] }, Home: { type: 'relation', targetSchemas: [ 'City' ] } },
			} ),
			newSchema( { title: 'Birth', subjectsAreDependent: true } ),
			newSchema( { title: 'City' } ),
			newSchema( { title: 'Museum' } ),
		);
	} );

	it( 'says that a Schema has no Subjects, rather than offering to include them', async () => {
		const row = schemaRow( await mountExporter(), 'Museum' );

		expect( [ row.find( '.cdx-toggle-switch' ).exists(), row.text().includes( 'neowiki-bundle-export-no-subjects' ) ] ).toEqual( [ false, true ] );
	} );

	it( 'tells which Schema including a Schema\'s Subjects adds for their Dependent Subjects', async () => {
		const wrapper = await mountExporter();
		await schemaRow( wrapper, 'Birth' ).find( '.cdx-checkbox input' ).setValue( false );

		await schemaRow( wrapper, 'Person' ).find( '.cdx-toggle-switch input' ).setValue( true );
		await flushPromises();

		expect( schemaRow( wrapper, 'Person' ).text() ).toContain( 'neowiki-bundle-export-adds-schemasBirth1' );
	} );

} );
