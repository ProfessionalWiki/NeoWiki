<template>
	<div class="ext-neowiki-schema-lookup">
		<CdxLookup
			ref="lookupRef"
			v-model:selected="selectedSchema"
			:menu-items="menuItems"
			:start-icon="cdxIconSearch"
			:placeholder="$i18n( 'neowiki-subject-creator-schema-search-placeholder' ).text()"
			@input="onLookupInput"
			@update:selected="onSchemaSelected"
		/>
	</div>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import { CdxLookup } from '@wikimedia/codex';
import { cdxIconSearch } from '@wikimedia/codex-icons';
import type { MenuItemData } from '@wikimedia/codex';
import { useSchemaStore } from '@/stores/SchemaStore.ts';

const emit = defineEmits<{
	'select': [ schemaName: string ];
}>();

const schemaStore = useSchemaStore();
const selectedSchema = ref<string | null>( null );
const menuItems = ref<MenuItemData[]>( [] );
const lookupRef = ref<InstanceType<typeof CdxLookup> | null>( null );

async function onLookupInput( value: string ): Promise<void> {
	if ( !value ) {
		menuItems.value = [];
		return;
	}

	try {
		const schemaNames = await schemaStore.searchAndFetchMissingSchemas( value );
		menuItems.value = schemaNames.map( ( name ) => ( {
			label: name,
			value: name
		} ) );
	} catch ( error ) {
		console.error( 'Error searching schemas:', error );
		menuItems.value = [];
	}
}

function onSchemaSelected( schemaName: string ): void {
	if ( schemaName ) {
		emit( 'select', schemaName );
	}
}

function focus(): void {
	// CdxLookup component does not expose a focus method,
	// so we need to find the input element and focus it directly.
	const input = ( lookupRef.value?.$el as HTMLElement )?.querySelector( 'input' );
	input?.focus();
}

defineExpose( { focus } );
</script>
