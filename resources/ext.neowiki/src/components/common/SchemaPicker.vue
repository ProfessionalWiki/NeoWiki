<template>
	<div class="ext-neowiki-schema-picker">
		<CdxLookup
			v-if="schemasLoaded"
			ref="lookupRef"
			v-model:selected="pickedSchema"
			v-model:input-value="inputText"
			:menu-items="menuItems"
			:placeholder="$i18n( 'neowiki-schema-picker-placeholder' ).text()"
			@input="filterSchemas"
			@update:selected="onSelect"
			@blur="reconcileOnBlur"
		/>
	</div>
</template>

<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue';
import { CdxLookup } from '@wikimedia/codex';
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
const pickedSchema = ref<string | null>( props.selected ?? null );
const inputText = ref<string | number>( props.selected ?? '' );
const summaries = ref<SchemaSummary[]>( [] );
const schemasLoaded = ref( false );
const query = ref<string>( '' );
const lookupRef = ref<InstanceType<typeof CdxLookup> | null>( null );

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

// CdxLookup decides whether focus opens the menu from the menu items it was created
// with, so the field is only rendered once the schemas are in. A picker whose schemas
// failed to load still renders, so the field is never missing.
async function loadSchemas(): Promise<void> {
	try {
		summaries.value = await schemaStore.getAllSchemaSummaries();
	} catch ( error ) {
		console.error( 'Failed to load schemas for the picker:', error );
	}
	schemasLoaded.value = true;
}

const schemasReady = loadSchemas();

watch( () => props.selected, ( value ) => {
	pickedSchema.value = selectableSchema( value ?? null );
	inputText.value = value ?? '';
} );

// CdxLookup takes the field's text for a selection from the matching menu entry and
// empties the field when there is none, so it is only handed a schema it lists. The
// field keeps showing the committed name either way.
function selectableSchema( schemaName: string | null ): string | null {
	return summaries.value.some( ( summary ) => summary.name === schemaName ) ? schemaName : null;
}

function filterSchemas( value: string ): void {
	query.value = value.trim().toLowerCase();
}

// CdxLookup reports a selection only for a menu entry the user picks, and null while
// they type, so a schema name typed out in full is not picked by itself. Resetting the
// filter restores the full menu, so reopening the picker without leaving the field
// browses every schema again rather than the last filter.
function onSelect( schemaName: string | null ): void {
	if ( schemaName === null ) {
		return;
	}

	inputText.value = schemaName;
	query.value = '';
	emit( 'select', schemaName );
}

// On blur, drop typing that was never picked: the field returns to the committed schema
// (empty when none is set) and the menu to the full list.
function reconcileOnBlur(): void {
	pickedSchema.value = selectableSchema( props.selected ?? null );
	inputText.value = props.selected ?? '';
	query.value = '';
	emit( 'blur' );
}

// Waits for the schemas so that focus lands on a field whose menu can open right away.
async function focus(): Promise<void> {
	await schemasReady;
	await nextTick();
	const input = ( lookupRef.value?.$el as HTMLElement )?.querySelector( 'input' );
	input?.focus();
}

defineExpose( { focus } );
</script>

<style lang="less">
.ext-neowiki-schema-picker .cdx-lookup {
	width: 100%;
}
</style>
