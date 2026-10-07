<template>
	<CdxDialog
		:open="true"
		class="ext-neowiki-ui ext-neowiki-schema-import-dialog"
		:title="$i18n( 'neowiki-schemas-import-title' ).text()"
		:use-close-button="!importing"
		:primary-action="primaryAction"
		:default-action="defaultAction"
		@primary="importCheckedSchemas"
		@default="emit( 'close' )"
		@update:open="onUpdateOpen"
	>
		<template v-if="outcome === null">
			<CdxCheckbox
				v-for="item in items"
				:key="item.schema.getName()"
				v-model="checkedNames"
				:input-value="item.schema.getName()"
				:disabled="importing"
			>
				{{ item.schema.getName() }}
				<template
					v-if="item.replacesExisting"
					#description
				>
					{{ $i18n( 'neowiki-schemas-import-replaces' ).text() }}
				</template>
			</CdxCheckbox>
		</template>

		<div
			v-else
			ref="outcomeElement"
			class="ext-neowiki-schema-import-dialog__outcome"
			tabindex="-1"
		>
			<CdxMessage
				v-if="outcome.created.length > 0"
				type="success"
			>
				{{ $i18n( 'neowiki-schemas-import-created', outcome.created.length, nameList( outcome.created ) ).text() }}
			</CdxMessage>
			<CdxMessage
				v-if="outcome.replaced.length > 0"
				type="success"
			>
				{{ $i18n( 'neowiki-schemas-import-replaced', outcome.replaced.length, nameList( outcome.replaced ) ).text() }}
			</CdxMessage>
			<CdxMessage
				v-for="failure in outcome.failed"
				:key="failure.name"
				type="error"
			>
				{{ $i18n( 'neowiki-schemas-import-failed', failure.name, failure.reason ).text() }}
			</CdxMessage>
		</div>
	</CdxDialog>
</template>

<script setup lang="ts">
import { computed, nextTick, ref, shallowRef } from 'vue';
import { CdxCheckbox, CdxDialog, CdxMessage } from '@wikimedia/codex';
import type { DialogAction, PrimaryDialogAction } from '@wikimedia/codex';
import {
	importSchemas,
	type SchemaImportItem,
	type SchemaImportOutcome
} from '@/components/SchemasPage/schemaImport.ts';
import { useSchemaStore } from '@/stores/SchemaStore.ts';

const props = defineProps<{
	items: SchemaImportItem[];
}>();

const emit = defineEmits<{
	close: [];
	imported: [];
}>();

const schemaStore = useSchemaStore();

const checkedNames = ref<string[]>(
	props.items.filter( ( item ) => !item.replacesExisting ).map( ( item ) => item.schema.getName() )
);
const importing = ref( false );
const outcome = shallowRef<SchemaImportOutcome | null>( null );
const outcomeElement = ref<HTMLElement | null>( null );

const primaryAction = computed( (): PrimaryDialogAction | undefined => {
	if ( outcome.value !== null ) {
		return undefined;
	}

	return {
		label: mw.msg( 'neowiki-schemas-import-confirm', checkedNames.value.length ),
		actionType: 'progressive',
		disabled: importing.value || checkedNames.value.length === 0
	};
} );

const defaultAction = computed( (): DialogAction => ( {
	label: mw.msg( outcome.value === null ? 'cancel' : 'cdx-dialog-close-button-label' ),
	disabled: importing.value
} ) );

// Closing mid-run would hide which saves went through.
function onUpdateOpen( open: boolean ): void {
	if ( !open && !importing.value ) {
		emit( 'close' );
	}
}

async function importCheckedSchemas(): Promise<void> {
	importing.value = true;

	outcome.value = await importSchemas(
		props.items.filter( ( item ) => checkedNames.value.includes( item.schema.getName() ) ),
		( schema ) => schemaStore.saveSchema( schema, mw.msg( 'neowiki-schemas-import-summary' ) )
	);

	importing.value = false;
	emit( 'imported' );

	// Focus left with the import action. The outcome takes it, so the keyboard can close the dialog.
	await nextTick();
	outcomeElement.value?.focus();
}

function nameList( names: string[] ): string {
	return names.join( mw.msg( 'comma-separator' ) );
}
</script>

<style lang="less">
@import ( reference ) '@wikimedia/codex-design-tokens/theme-wikimedia-ui.less';

.ext-neowiki-schema-import-dialog__outcome {
	display: flex;
	flex-direction: column;
	gap: @spacing-75;
}
</style>
