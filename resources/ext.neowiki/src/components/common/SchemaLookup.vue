<template>
	<div class="ext-neowiki-schema-lookup">
		<CdxLookup
			ref="lookupRef"
			v-model:selected="selectedSchema"
			v-model:input-value="inputText"
			:menu-items="menuItems"
			:start-icon="cdxIconSearch"
			:placeholder="$i18n( 'neowiki-subject-creator-schema-search-placeholder' ).text()"
			@input="onLookupInput"
			@update:selected="onSchemaSelected"
			@blur="reconcileOnBlur"
		/>
	</div>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue';
import { CdxLookup } from '@wikimedia/codex';
import { cdxIconSearch } from '@wikimedia/codex-icons';
import type { MenuItemData } from '@wikimedia/codex';
import { useSchemaStore } from '@/stores/SchemaStore.ts';

const props = defineProps<{
	selected?: string | null;
}>();

const emit = defineEmits<{
	'select': [ schemaName: string ];
}>();

const schemaStore = useSchemaStore();
const selectedSchema = ref<string | null>( props.selected ?? null );
const inputText = ref<string>( props.selected ?? '' );
const menuItems = ref<MenuItemData[]>( [] );
const lookupRef = ref<InstanceType<typeof CdxLookup> | null>( null );
let requestSequence = 0;

function syncFromSelected(): void {
	selectedSchema.value = props.selected ?? null;
	inputText.value = props.selected ?? '';
}

watch( () => props.selected, syncFromSelected );

async function onLookupInput( value: string ): Promise<void> {
	if ( !value ) {
		menuItems.value = [];
		return;
	}

	const currentSequence = ++requestSequence;

	try {
		const schemaNames = await schemaStore.searchAndFetchMissingSchemas( value );

		if ( currentSequence !== requestSequence ) {
			return;
		}

		menuItems.value = schemaNames.map( ( name ) => ( {
			label: name,
			value: name,
			description: schemaStore.getSchema( name ).getDescription() || undefined
		} ) );
	} catch ( error ) {
		if ( currentSequence !== requestSequence ) {
			return;
		}

		console.error( 'Error searching schemas:', error );
		menuItems.value = [];
	}
}

function onSchemaSelected( schemaName: string | null ): void {
	if ( schemaName ) {
		emit( 'select', schemaName );
	}
}

// The field may only hold a schema that was picked from the menu. CdxLookup nulls
// its selection while the user types, so on blur reconcile the unconfirmed text:
// clear when emptied, otherwise revert to the last committed value.
function reconcileOnBlur(): void {
	if ( selectedSchema.value !== null ) {
		return;
	}

	if ( inputText.value === '' ) {
		if ( props.selected ) {
			emit( 'select', '' );
		}
		return;
	}

	syncFromSelected();
}

function focus(): void {
	// CdxLookup component does not expose a focus method,
	// so we need to find the input element and focus it directly.
	const input = ( lookupRef.value?.$el as HTMLElement )?.querySelector( 'input' );
	input?.focus();
}

defineExpose( { focus } );
</script>
