<template>
	<div class="ext-neowiki-schema-display">
		<CdxTable
			class="ext-neowiki-schema-display__properties"
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
					:can-create-subject="canCreateSubjectPage && !currentSchema.subjectsAreDependent()"
					@edit="openEditor"
					@create-subject="subjectStore.openSubjectCreator()"
				/>
			</template>

			<template #item-name="{ item, row }">
				{{ item.toString() }}
				<div
					v-if="labelOf( row ) !== null"
					class="ext-neowiki-schema-display__property-label"
				>
					{{ labelOf( row ) }}
				</div>
			</template>

			<template #item-type="{ item, row }">
				<span class="ext-neowiki-schema-display__type">
					<!-- Keyed by its text: the tooltip directive reads its value only when mounted. -->
					<CdxInfoChip
						v-if="typeDetails( row, validationEnforced ) !== null"
						:key="typeDetails( row, validationEnforced ) ?? ''"
						v-tooltip="typeDetails( row, validationEnforced )"
						class="ext-neowiki-schema-display__explained"
						tabindex="0"
						:icon="getIcon( item )"
					>
						{{ getTypeLabel( item ) }}
					</CdxInfoChip>
					<CdxInfoChip
						v-else
						:icon="getIcon( item )"
					>
						{{ getTypeLabel( item ) }}
					</CdxInfoChip>
					<span
						v-if="requiredExplanation( row, validationEnforced ) !== null"
						:key="requiredExplanation( row, validationEnforced ) ?? ''"
						v-tooltip="requiredExplanation( row, validationEnforced )"
						class="ext-neowiki-schema-display__required ext-neowiki-schema-display__explained"
						tabindex="0"
					>
						{{ $i18n( 'neowiki-schema-display-required' ).text() }}
					</span>
				</span>
			</template>

			<template #item-description="{ item }">
				<span
					v-if="!item"
					class="ext-neowiki-schema-display__empty-value"
				>-</span>
				<template v-else>
					{{ item }}
				</template>
			</template>

			<template #empty-state>
				{{ $i18n( 'neowiki-schema-display-no-properties' ).text() }}
			</template>
		</CdxTable>

		<SubjectsTable
			class="ext-neowiki-schema-display__subjects"
			:fixed-schema="currentSchema.getName()"
		/>

		<SchemaEditorDialog
			v-if="canEditSchema"
			:open="isEditorOpen"
			:initial-schema="currentSchema"
			:on-save="handleSaveSchema"
			@saved="onSchemaSaved"
			@update:open="isEditorOpen = $event"
		/>

		<SubjectCreatorDialog
			v-if="canCreateSubjectPage"
			v-model:open="subjectStore.subjectCreatorOpen"
			:host-page="null"
			:initial-schema-name="currentSchema.getName()"
		/>
	</div>
</template>

<script setup lang="ts">
import { computed, onMounted, shallowRef, watch } from 'vue';
import { Schema } from '@/domain/Schema.ts';
import { NeoWikiServices } from '@/NeoWikiServices.ts';
import { CdxTable, CdxInfoChip } from '@wikimedia/codex';
import type { TableColumn } from '@wikimedia/codex';
import type { Icon } from '@wikimedia/codex-icons';
import type { PropertyDefinition } from '@/domain/PropertyDefinition.ts';
import SchemaDisplayHeader from './SchemaDisplayHeader.vue';
import SchemaEditorDialog from '@/components/SchemaEditor/SchemaEditorDialog.vue';
import SubjectsTable from '@/components/SubjectsTable/SubjectsTable.vue';
import SubjectCreatorDialog from '@/components/SubjectCreator/SubjectCreatorDialog.vue';
import { useSchemaStore } from '@/stores/SchemaStore.ts';
import { useSubjectStore } from '@/stores/SubjectStore.ts';
import { useSchemaPermissions } from '@/composables/useSchemaPermissions.ts';
import { useSubjectPermissions } from '@/composables/useSubjectPermissions.ts';
import { propertyDisplayName } from '@/presentation/propertyDisplayName.ts';
import { requiredExplanation, typeDetails } from '@/presentation/propertyDetails.ts';
import { NeoWikiExtension } from '@/NeoWikiExtension.ts';

const props = defineProps( {
	schema: {
		type: Schema,
		required: true
	}
} );

const schemaStore = useSchemaStore();
const subjectStore = useSubjectStore();
const schemaRepo = NeoWikiServices.getSchemaRepository();
const { canEditSchema, checkEditPermission } = useSchemaPermissions();
const { canCreateSubjectPage, checkCreateSubjectPagePermission } = useSubjectPermissions();

onMounted( () => {
	checkCreateSubjectPagePermission();
} );

const isEditorOpen = shallowRef( false );
const currentSchema = shallowRef<Schema>( props.schema );

watch( () => props.schema, ( newSchema ) => {
	currentSchema.value = newSchema;
	checkEditPermission( newSchema.getName() );
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
		id: 'description',
		label: mw.msg( 'neowiki-schema-display-property-description' )
	}
] );

const validationEnforced = NeoWikiExtension.getInstance().isValidationEnforced();

function labelOf( property: PropertyDefinition ): string | null {
	const displayName = propertyDisplayName( property );

	return displayName === property.name.toString() ? null : displayName;
}

function getIcon( propertyType: string ): Icon {
	return componentRegistry.getIcon( propertyType );
}

function getTypeLabel( propertyType: string ): string {
	return mw.msg( componentRegistry.getLabel( propertyType ) );
}

async function openEditor(): Promise<void> {
	try {
		currentSchema.value = await schemaRepo.getSchema( currentSchema.value.getName() );
		isEditorOpen.value = true;
	} catch ( error ) {
		mw.notify(
			error instanceof Error ? error.message : String( error ),
			{ type: 'error' }
		);
	}
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

	&__properties {
		// Required to align our custom header to the inline-start of the table header
		.cdx-table__header__caption {
			display: none;
		}

		.cdx-table__header__content {
			flex-grow: 1;
		}
	}

	&__subjects {
		margin-top: @spacing-200;
	}

	&__property-label {
		color: @color-subtle;
		font-size: @font-size-small;
		font-weight: @font-weight-normal;
	}

	&__type {
		white-space: nowrap;
	}

	&__explained {
		cursor: help;
	}

	&__required {
		margin-inline-start: @spacing-50;
		color: @color-subtle;
	}

	&__empty-value {
		color: @color-subtle;
		user-select: none;
	}
}
</style>
