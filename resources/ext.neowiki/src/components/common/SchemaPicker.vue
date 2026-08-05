<template>
	<div class="ext-neowiki-schema-picker">
		<CdxLookup
			v-if="schemasLoaded"
			ref="lookupRef"
			v-model:selected="selectedSchema"
			v-model:input-value="inputText"
			:menu-items="menuItems"
			:placeholder="$i18n( 'neowiki-schema-picker-placeholder' ).text()"
			@input="recordTypedText"
			@update:selected="onSelect"
			@blur="revertUncommittedTyping"
		/>
	</div>
</template>

<script setup lang="ts">
import { computed, nextTick, ref, triggerRef, watch } from 'vue';
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
const selectedSchema = ref<string | null>( props.selected ?? null );
const inputText = ref<string | number>( props.selected ?? '' );
const summaries = ref<SchemaSummary[]>( [] );
const schemasLoaded = ref( false );
// The field's text as last typed, or null while the field shows the committed schema
// rather than typing.
const typedText = ref<string | null>( null );
const lookupRef = ref<InstanceType<typeof CdxLookup> | null>( null );

const menuItems = computed<MenuItemData[]>( () => {
	const filter = ( typedText.value ?? '' ).trim().toLowerCase();
	const matches = filter === '' ?
		summaries.value :
		summaries.value.filter( ( summary ) => summary.name.toLowerCase().includes( filter ) );
	return matches.map( ( summary ) => ( {
		label: summary.name,
		value: summary.name,
		description: summary.description || undefined
	} ) );
} );

// CdxLookup decides whether focus opens the menu from the menu items it was created
// with, so the field is only rendered once the schemas have arrived. Loading them is
// also marked done when the request fails, so a failure leaves a usable field rather
// than none at all.
async function loadSchemas(): Promise<void> {
	try {
		summaries.value = await schemaStore.getAllSchemaSummaries();
	} catch ( error ) {
		console.error( 'Failed to load schemas for the picker:', error );
	}
	schemasLoaded.value = true;
}

const schemasReady = loadSchemas();

// The filter is reset alongside the field: CdxLookup resolves a selection against the
// menu as it currently stands, and would empty the field for a schema the filter hides.
watch( () => props.selected, ( value ) => {
	selectedSchema.value = selectableSchema( value ?? null );
	inputText.value = value ?? '';
	typedText.value = null;
} );

// CdxLookup takes the field's text for a selection from the matching menu entry and
// empties the field when there is none, so it is only handed a schema it lists. The
// field keeps showing the committed name either way.
function selectableSchema( schemaName: string | null ): string | null {
	return summaries.value.some( ( summary ) => summary.name === schemaName ) ? schemaName : null;
}

// CdxLookup takes a change to its menu items as the answer to the user's keystroke, and
// marks the field as loading until one arrives. Every edit therefore has to produce a new
// list, including an edit that leaves the text exactly as it found it: pasting a name over
// its own selection, or a cancelled composition.
function recordTypedText( value: string ): void {
	typedText.value = value;
	triggerRef( typedText );
}

// CdxLookup reports a selection only for a menu entry the user picks, and null while
// they type, so a schema name typed out in full is not picked by itself.
//
// Setting the field text is load-bearing: left to do it itself, CdxLookup announces the
// name as typed input, which would re-apply the filter cleared on the next line.
// Restoring the full menu means reopening the picker without leaving the field browses
// every schema again rather than the last filter.
function onSelect( schemaName: string | null ): void {
	if ( schemaName === null ) {
		return;
	}

	inputText.value = schemaName;
	typedText.value = null;

	if ( schemaName !== props.selected ) {
		emit( 'select', schemaName );
	}
}

function revertUncommittedTyping(): void {
	selectedSchema.value = selectableSchema( props.selected ?? null );
	inputText.value = props.selected ?? '';
	typedText.value = null;
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
