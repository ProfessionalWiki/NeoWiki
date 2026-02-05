<template>
	<div class="ext-neowiki-schema-display">
		<CdxTable
			:columns="hasProperties ? columns : []"
			:data="properties"
			:caption="currentSchema.getName()"
			:use-row-headers="true"
			:hide-caption="true"
		>
			<template #header>
				<SchemaDisplayHeader
					:schema="currentSchema"
					:can-edit-schema="canEditSchema"
					@edit="isEditorOpen = true"
				/>
			</template>

			<template #item-name="{ item }">
				{{ item.toString() }}
			</template>

			<template #item-type="{ item }">
				<CdxInfoChip :icon="getIcon( item )">
					{{ getTypeLabel( item ) }}
				</CdxInfoChip>
			</template>

			<template #item-required="{ item }">
				{{ item ?
					$i18n( 'neowiki-schema-display-required-yes' ).text() :
					$i18n( 'neowiki-schema-display-required-no' ).text()
				}}
			</template>

			<template #item-default="{ item, row }">
				<component
					:is="componentRegistry.getValueDisplayComponent( row.type )"
					v-if="item !== undefined"
					:value="item"
					:property="row"
				/>
			</template>

			<template #empty-state>
				{{ $i18n( 'neowiki-schema-display-no-properties' ).text() }}
			</template>
		</CdxTable>

		<SchemaEditorDialog
			v-if="canEditSchema"
			:open="isEditorOpen"
			:initial-schema="currentSchema"
			:on-save="handleSaveSchema"
			@saved="onSchemaSaved"
			@update:open="isEditorOpen = $event"
		/>
	</div>
</template>

<script setup lang="ts">
import { computed, shallowRef, watch } from 'vue';
import { Schema } from '@/domain/Schema.ts';
import { NeoWikiServices } from '@/NeoWikiServices.ts';
import { CdxTable, CdxInfoChip } from '@wikimedia/codex';
import type { TableColumn } from '@wikimedia/codex';
import type { Icon } from '@wikimedia/codex-icons';
import SchemaDisplayHeader from './SchemaDisplayHeader.vue';
import SchemaEditorDialog from '@/components/SchemaEditor/SchemaEditorDialog.vue';
import { useSchemaStore } from '@/stores/SchemaStore.ts';
import { useSchemaPermissions } from '@/composables/useSchemaPermissions.ts';

const props = defineProps( {
	schema: {
		type: Schema,
		required: true
	}
} );

const schemaStore = useSchemaStore();
const { canEditSchema, checkPermission } = useSchemaPermissions();

const isEditorOpen = shallowRef( false );
const currentSchema = shallowRef<Schema>( props.schema );

watch( () => props.schema, ( newSchema ) => {
	currentSchema.value = newSchema;
	checkPermission( newSchema.getName() );
}, { immediate: true } );

const componentRegistry = NeoWikiServices.getComponentRegistry();

const properties = computed( () => [ ...currentSchema.value.getPropertyDefinitions() ] );
const hasProperties = computed( () => properties.value.length > 0 );

const columns = computed<TableColumn[]>( () => [
	{
		id: 'name',
		label: mw.msg( 'neowiki-schema-display-property-name' )
	},
	{
		id: 'type',
		label: mw.msg( 'neowiki-schema-display-property-type' )
	},
	{
		id: 'required',
		label: mw.msg( 'neowiki-schema-display-property-required' )
	},
	{
		id: 'default',
		label: mw.msg( 'neowiki-schema-display-property-default' )
	},
	{
		id: 'description',
		label: mw.msg( 'neowiki-schema-display-property-description' )
	}
] );

function getIcon( propertyType: string ): Icon {
	return componentRegistry.getIcon( propertyType );
}

function getTypeLabel( propertyType: string ): string {
	return mw.msg( componentRegistry.getLabel( propertyType ) );
}

const handleSaveSchema = async ( updatedSchema: Schema, comment: string ): Promise<void> => {
	await schemaStore.saveSchema( updatedSchema, comment );
};

const onSchemaSaved = ( schema: Schema ): void => {
	currentSchema.value = schema;
};
</script>

<style lang="less">
@import ( reference ) '@wikimedia/codex-design-tokens/theme-wikimedia-ui.less';

.ext-neowiki-schema-display {
	max-width: 64rem;

	// Required to align our custom header to the inline-start of the table header
	.cdx-table__header__caption {
		display: none;
	}

	.cdx-table__header__content {
		flex-grow: 1;
	}
}
</style>
