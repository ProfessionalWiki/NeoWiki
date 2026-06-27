<template>
	<div class="ext-neowiki-schema-picker">
		<CdxCombobox
			ref="comboboxRef"
			v-model:selected="selectedSchema"
			:menu-items="menuItems"
			:placeholder="$i18n( 'neowiki-schema-picker-placeholder' ).text()"
			@input="filterSchemas"
			@update:selected="onSelect"
			@blur="reconcileOnBlur"
		/>
	</div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
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
const query = ref<string>( '' );
const comboboxRef = ref<InstanceType<typeof CdxCombobox> | null>( null );

const menuItems = computed<MenuItemData[]>( () => {
	const matches = query.value === '' ?
		summaries.value :
		summaries.value.filter( ( summary ) => summary.name.toLowerCase().includes( query.value ) );
	return matches.map( ( summary ) => ( {
		label: summary.name,
		value: summary.name,
		description: summary.description || undefined
	} ) );
} );

onMounted( async () => {
	try {
		summaries.value = await schemaStore.getAllSchemaSummaries();
	} catch ( error ) {
		console.error( 'Failed to load schemas for the picker:', error );
	}
} );

watch( () => props.selected, ( value ) => {
	selectedSchema.value = value ?? '';
} );

function findSchema( name: string ): SchemaSummary | undefined {
	return summaries.value.find( ( summary ) => summary.name === name.trim() );
}

function filterSchemas( event: Event ): void {
	query.value = ( event.target as HTMLInputElement ).value.trim().toLowerCase();
}

// CdxCombobox's `selected` tracks the typed text, not only menu picks, so only
// commit it when it resolves to an exact existing schema, and emit that schema's
// canonical name rather than the raw input (which may carry surrounding whitespace).
function onSelect( value: string ): void {
	const schema = findSchema( value );
	if ( schema && schema.name !== props.selected ) {
		emit( 'select', schema.name );
	}
}

// On blur, snap `selected` back to the committed schema (empty for a target schema
// that has not been set yet) so unconfirmed typing reverts and the field only ever
// shows an existing schema. Clearing the query restores the full menu so the
// committed label renders.
function reconcileOnBlur(): void {
	selectedSchema.value = props.selected ?? '';
	query.value = '';
	emit( 'blur' );
}

function focus(): void {
	const input = ( comboboxRef.value?.$el as HTMLElement )?.querySelector( 'input' );
	input?.focus();
}

defineExpose( { focus } );
</script>

<style lang="less">
.ext-neowiki-schema-picker .cdx-combobox {
	width: 100%;
}
</style>
