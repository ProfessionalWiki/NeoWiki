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
			<div
				v-for="section in selectableSections"
				:key="section.heading"
				class="ext-neowiki-schema-import-dialog__section"
			>
				<CdxCheckbox
					class="ext-neowiki-schema-import-dialog__heading"
					:model-value="section.names.every( isChecked )"
					:indeterminate="isPartlyChecked( section.names )"
					:disabled="importing"
					@update:model-value="setChecked( section.names, $event )"
				>
					<strong>{{ $i18n( section.heading, section.names.length ).text() }}</strong>
					<template
						v-if="section.description !== null"
						#description
					>
						{{ $i18n( section.description, section.names.length ).text() }}
					</template>
				</CdxCheckbox>
				<div class="ext-neowiki-schema-import-dialog__items">
					<CdxCheckbox
						v-for="name in section.names"
						:key="name"
						v-model="checkedNames"
						:input-value="name"
						:disabled="importing"
					>
						{{ name }}
					</CdxCheckbox>
				</div>
			</div>

			<div
				v-if="unchangedNames.length > 0"
				class="ext-neowiki-schema-import-dialog__section"
			>
				<p class="ext-neowiki-schema-import-dialog__heading">
					<strong>{{ $i18n( 'neowiki-schemas-import-unchanged', unchangedNames.length ).text() }}</strong>
				</p>
				<p>
					{{ $i18n( 'neowiki-schemas-import-unchanged-names', nameList( unchangedNames ) ).text() }}
				</p>
			</div>
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
	type SchemaImportOutcome,
	type SchemaImportStatus
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

function namesWith( status: SchemaImportStatus ): string[] {
	return props.items.filter( ( item ) => item.status === status ).map( ( item ) => item.schema.getName() );
}

const selectableSections = [
	{ heading: 'neowiki-schemas-import-new', description: null, names: namesWith( 'new' ) },
	{ heading: 'neowiki-schemas-import-changed', description: 'neowiki-schemas-import-replaces', names: namesWith( 'changed' ) }
].filter( ( section ) => section.names.length > 0 );

const unchangedNames = namesWith( 'unchanged' );

const checkedNames = ref<string[]>( namesWith( 'new' ) );
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

function isChecked( name: string ): boolean {
	return checkedNames.value.includes( name );
}

function isPartlyChecked( names: string[] ): boolean {
	return names.some( isChecked ) && !names.every( isChecked );
}

function setChecked( names: string[], checked: boolean ): void {
	const others = checkedNames.value.filter( ( name ) => !names.includes( name ) );
	checkedNames.value = checked ? [ ...others, ...names ] : others;
}

function nameList( names: string[] ): string {
	return names.join( mw.msg( 'comma-separator' ) );
}
</script>

<style lang="less">
@import ( reference ) '@wikimedia/codex-design-tokens/theme-wikimedia-ui.less';

.ext-neowiki-schema-import-dialog {
	&__section + &__section {
		margin-top: @spacing-100;
	}

	&__items {
		padding-inline-start: calc( @min-size-input-binary + @spacing-50 );
	}

	&__outcome {
		display: flex;
		flex-direction: column;
		gap: @spacing-75;
	}
}
</style>
