<template>
	<div class="ext-neowiki-layout-display">
		<CdxTable
			:columns="hasDisplayRules ? columns : []"
			:data="displayRuleRows"
			:caption="currentLayout.getName()"
			:use-row-headers="true"
			:hide-caption="true"
		>
			<template #header>
				<LayoutDisplayHeader
					:layout="currentLayout"
					:can-edit-layout="canEditLayout"
					@edit="openEditor"
				/>
			</template>

			<template #item-property="{ item, row }">
				<span class="ext-neowiki-layout-display__property-cell">
					<CdxIcon
						v-if="row.type"
						:icon="getIcon( row.type )"
						:title="getTypeLabel( row.type )"
						class="ext-neowiki-layout-display__type-icon"
					/>
					{{ item }}
				</span>
			</template>

			<template #item-displayAttributes="{ item }">
				<span
					v-if="!item"
					class="ext-neowiki-layout-display__empty-value"
				>-</span>
				<template v-else>
					{{ item }}
				</template>
			</template>

			<template #empty-state>
				{{ $i18n( 'neowiki-layout-display-no-rules' ).text() }}
			</template>
		</CdxTable>

		<LayoutEditorDialog
			v-if="canEditLayout"
			:open="isEditorOpen"
			:initial-layout="currentLayout"
			:on-save="handleSaveLayout"
			@saved="onLayoutSaved"
			@update:open="isEditorOpen = $event"
		/>
	</div>
</template>

<script setup lang="ts">
import { computed, shallowRef, watch } from 'vue';
import { Layout } from '@/domain/Layout.ts';
import { NeoWikiServices } from '@/NeoWikiServices.ts';
import { CdxIcon, CdxTable } from '@wikimedia/codex';
import type { TableColumn } from '@wikimedia/codex';
import type { Icon } from '@wikimedia/codex-icons';
import LayoutDisplayHeader from './LayoutDisplayHeader.vue';
import LayoutEditorDialog from '@/components/LayoutEditor/LayoutEditorDialog.vue';
import { useLayoutPermissions } from '@/composables/useLayoutPermissions.ts';
import { useLayoutStore } from '@/stores/LayoutStore.ts';
import type { PropertyDefinition } from '@/domain/PropertyDefinition.ts';

const props = defineProps( {
	layout: {
		type: Layout,
		required: true
	}
} );

const isEditorOpen = shallowRef( false );
const currentLayout = shallowRef<Layout>( props.layout );
const { canEditLayout, checkEditPermission } = useLayoutPermissions();
const componentRegistry = NeoWikiServices.getComponentRegistry();
const schemaRepo = NeoWikiServices.getSchemaRepository();
const layoutRepo = NeoWikiServices.getLayoutRepository();
const schemaProperties = shallowRef<PropertyDefinition[]>( [] );

watch( () => props.layout, ( newLayout ) => {
	currentLayout.value = newLayout;
	checkEditPermission( newLayout.getName() );
}, { immediate: true } );

watch( () => currentLayout.value.getSchema(), async ( schemaName ) => {
	try {
		const schema = await schemaRepo.getSchema( schemaName );
		schemaProperties.value = [ ...schema.getPropertyDefinitions() ];
	} catch {
		schemaProperties.value = [];
	}
}, { immediate: true } );

const hasDisplayRules = computed( () => currentLayout.value.getDisplayRules().length > 0 );

const columns = computed<TableColumn[]>( () => [
	{
		id: 'property',
		label: mw.msg( 'neowiki-layout-display-rule-property' )
	},
	{
		id: 'displayAttributes',
		label: mw.msg( 'neowiki-layout-display-rule-attributes' )
	}
] );

function getPropertyType( propertyName: string ): string | undefined {
	const prop = schemaProperties.value.find( ( p ) => p.name.toString() === propertyName );
	return prop?.type;
}

const displayRuleRows = computed( () =>
	currentLayout.value.getDisplayRules().map( ( rule ) => {
		const attrs = rule.displayAttributes;
		const hasAttrs = attrs && Object.keys( attrs ).length > 0;
		return {
			property: rule.property.toString(),
			type: getPropertyType( rule.property.toString() ),
			displayAttributes: hasAttrs ? JSON.stringify( attrs ) : undefined
		};
	} )
);

function getIcon( propertyType: string ): Icon {
	return componentRegistry.getIcon( propertyType );
}

function getTypeLabel( propertyType: string ): string {
	return mw.msg( componentRegistry.getLabel( propertyType ) );
}

const layoutStore = useLayoutStore();

async function openEditor(): Promise<void> {
	try {
		currentLayout.value = await layoutRepo.getLayout( currentLayout.value.getName() );
		isEditorOpen.value = true;
	} catch ( error ) {
		mw.notify(
			error instanceof Error ? error.message : String( error ),
			{ type: 'error' }
		);
	}
}

const handleSaveLayout = async ( updatedLayout: Layout, comment: string ): Promise<void> => {
	await layoutStore.saveLayout( updatedLayout, comment );
};

const onLayoutSaved = ( layout: Layout ): void => {
	currentLayout.value = layout;
};
</script>

<style lang="less">
@import ( reference ) '@wikimedia/codex-design-tokens/theme-wikimedia-ui.less';

.ext-neowiki-layout-display {
	max-width: 64rem;

	.cdx-table__header__caption {
		display: none;
	}

	.cdx-table__header__content {
		flex-grow: 1;
	}

	&__property-cell {
		display: inline-flex;
		align-items: center;
		gap: @spacing-50;
	}

	&__type-icon {
		color: @color-subtle;
	}

	&__empty-value {
		color: @color-subtle;
		user-select: none;
	}
}
</style>
