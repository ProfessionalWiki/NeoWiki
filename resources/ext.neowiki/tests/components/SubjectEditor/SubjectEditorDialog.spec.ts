import { mount, VueWrapper, flushPromises } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import SubjectEditorDialog from '@/components/SubjectEditor/SubjectEditorDialog.vue';
import { Subject } from '@/domain/Subject.ts';
import { SubjectId } from '@/domain/SubjectId.ts';
import { StatementList } from '@/domain/StatementList.ts';
import { NeoWikiExtension } from '@/NeoWikiExtension.ts';
import { Schema } from '@/domain/Schema.ts';
import { PropertyDefinitionList } from '@/domain/PropertyDefinitionList.ts';
import { createPinia, setActivePinia } from 'pinia';
import { useSchemaStore } from '@/stores/SchemaStore.ts';
import { Service } from '@/NeoWikiServices.ts';
import SchemaEditorDialog from '@/components/SchemaEditor/SchemaEditorDialog.vue';
import { createI18nMock, setupMwMock } from '../../VueTestHelpers.ts';

const $i18n = createI18nMock();

describe( 'SubjectEditorDialog', () => {
	beforeEach( () => {
		setupMwMock( { functions: [ 'message', 'msg', 'notify' ] } );
	} );

	let pinia: ReturnType<typeof createPinia>;
	let schemaStore;
	let schemaAuthorizer: any;

	const mockSchema = new Schema(
		'TestSchema',
		'A test schema',
		new PropertyDefinitionList( [] ),
	);

	const mockSubject = new Subject(
		new SubjectId( 's1demo5sssssss1' ),
		'Test Subject',
		'TestSchema',
		new StatementList( [] ),
	);

	const mountComponent = ( canEditSchema: boolean ): VueWrapper => {
		schemaAuthorizer = {
			canEditSchema: vi.fn().mockResolvedValue( canEditSchema ),
		};

		return mount( SubjectEditorDialog, {
			props: {
				subject: mockSubject,
				onSave: vi.fn(),
				onSaveSchema: vi.fn(),
				open: true,
			},
			global: {
				mocks: {
					$i18n,
				},
				plugins: [ pinia ],
				provide: {
					[ Service.ComponentRegistry ]: NeoWikiExtension.getInstance().getTypeSpecificComponentRegistry(),
					[ Service.SchemaAuthorizer ]: schemaAuthorizer,
					[ Service.PropertyTypeRegistry ]: NeoWikiExtension.getInstance().getPropertyTypeRegistry(),
				},
				stubs: {
					teleport: true,
				},
			},
		} );
	};

	beforeEach( () => {
		pinia = createPinia();
		setActivePinia( pinia );

		schemaStore = useSchemaStore();
		schemaStore.setSchema( 'TestSchema', mockSchema );
	} );

	it( 'renders schema as a link when user has edit permissions', async () => {
		const wrapper = mountComponent( true );
		await flushPromises();

		const schemaLink = wrapper.find( '.ext-neowiki-subject-editor-dialog-schema__link' );
		expect( schemaLink.exists() ).toBe( true );
		expect( schemaLink.text() ).toBe( 'TestSchema' );
	} );

	it( 'renders schema as plain text when user lacks edit permissions', async () => {
		const wrapper = mountComponent( false );
		await flushPromises();

		const schemaLink = wrapper.find( '.ext-neowiki-subject-editor-dialog-schema__link' );
		expect( schemaLink.exists() ).toBe( false );

		const schemaName = wrapper.find( '.ext-neowiki-subject-editor-dialog-schema__name' );
		expect( schemaName.exists() ).toBe( true );
		expect( schemaName.text() ).toBe( 'TestSchema' );
	} );

	it( 'opens SchemaEditorDialog when schema link is clicked', async () => {
		const wrapper = mountComponent( true );
		await flushPromises();

		const schemaLink = wrapper.find( 'a.ext-neowiki-subject-editor-dialog-schema__link' );
		await schemaLink.trigger( 'click' );

		const schemaEditorDialog = wrapper.findComponent( SchemaEditorDialog );
		expect( schemaEditorDialog.exists() ).toBe( true );
		expect( schemaEditorDialog.props( 'open' ) ).toBe( true );
	} );
} );
