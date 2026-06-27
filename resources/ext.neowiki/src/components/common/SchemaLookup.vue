<template>
	<div class="ext-neowiki-schema-lookup">
		<CdxCombobox
			ref="comboboxRef"
			v-model:selected="selectedSchema"
			:menu-items="menuItems"
			:placeholder="$i18n( 'neowiki-schema-lookup-placeholder' ).text()"
			@input="filterSchemas"
			@update:selected="onSelect"
			@blur="reconcileOnBlur"
		/>
	</div>
</template>

<script setup lang="ts">
import { onMounted, ref, watch } from 'vue';
import { CdxCombobox } from '@wikimedia/codex';
import type { MenuItemData } from '@wikimedia/codex';
import { useSchemaStore } from '@/stores/SchemaStore.ts';
import type { SchemaSummary } from '@/application/SchemaLookup.ts';

const props = defineProps<{
	selected?: string | null;
}>();

const emit = defineEmits<{
	'select': [ schemaName: string ];
	'blur': [];
}>();

const schemaStore = useSchemaStore();
const selectedSchema = ref<string>( props.selected ?? '' );
const summaries = ref<SchemaSummary[]>( [] );
const menuItems = ref<MenuItemData[]>( [] );
const comboboxRef = ref<InstanceType<typeof CdxCombobox> | null>( null );

onMounted( async () => {
	summaries.value = await schemaStore.getAllSchemaSummaries();
	showAllSchemas();
} );

watch( () => props.selected, ( value ) => {
	selectedSchema.value = value ?? '';
} );

function toMenuItems( items: SchemaSummary[] ): MenuItemData[] {
	return items.map( ( summary ) => ( {
		label: summary.name,
		value: summary.name,
		description: summary.description || undefined
	} ) );
}

function showAllSchemas(): void {
	menuItems.value = toMenuItems( summaries.value );
}

function findSchema( name: string ): SchemaSummary | undefined {
	return summaries.value.find( ( summary ) => summary.name === name.trim() );
}

function filterSchemas( event: Event ): void {
	const query = ( event.target as HTMLInputElement ).value.trim().toLowerCase();
	const matches = query === '' ?
		summaries.value :
		summaries.value.filter( ( summary ) => summary.name.toLowerCase().includes( query ) );
	menuItems.value = toMenuItems( matches );
}

// CdxCombobox's `selected` tracks the typed text, not only menu picks, so only
// commit it when it is an exact existing schema name.
function onSelect( value: string ): void {
	if ( findSchema( value ) && value !== props.selected ) {
		emit( 'select', value );
	}
}

// On blur, snap `selected` back to the committed schema (empty for a target schema
// that has not been set yet) so unconfirmed typing reverts and the field only ever
// shows an existing schema. Restoring the full menu lets the committed label render.
function reconcileOnBlur(): void {
	selectedSchema.value = props.selected ?? '';
	showAllSchemas();
	emit( 'blur' );
}

function focus(): void {
	const input = ( comboboxRef.value?.$el as HTMLElement )?.querySelector( 'input' );
	input?.focus();
}

defineExpose( { focus } );
</script>

<style lang="less">
.ext-neowiki-schema-lookup .cdx-combobox {
	width: 100%;
}
</style>
