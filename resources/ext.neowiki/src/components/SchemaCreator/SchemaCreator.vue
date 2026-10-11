<template>
	<div class="ext-neowiki-schema-creator">
		<div class="ext-neowiki-schema-creator__name-section">
			<CdxField
				:status="nameStatus"
				:messages="nameError ? { error: nameError } : {}"
			>
				<CdxTextInput
					v-model="schemaName"
					:placeholder="$i18n( 'neowiki-schema-creator-name-placeholder' ).text()"
					@input="onNameInput"
				/>
				<template #label>
					{{ $i18n( 'neowiki-schema-creator-name-field' ).text() }}
				</template>
			</CdxField>

			<CdxField :optional="true">
				<CdxTextInput
					v-model="schemaDescription"
					:placeholder="$i18n( 'neowiki-schema-editor-description-placeholder' ).text()"
					@input="onChange"
				/>
				<template #label>
					{{ $i18n( 'neowiki-schema-editor-description' ).text() }}
				</template>
			</CdxField>
		</div>

		<SchemaEditor
			ref="schemaEditorRef"
			:initial-schema="baseSchema"
			@change="onChange"
		/>
	</div>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import { CdxField, CdxTextInput } from '@wikimedia/codex';
import type { ValidationStatusType } from '@wikimedia/codex';
import SchemaEditor from '@/components/SchemaEditor/SchemaEditor.vue';
import type { SchemaEditorExposes } from '@/components/SchemaEditor/SchemaEditor.vue';
import { Schema } from '@/domain/Schema.ts';
import { PropertyDefinitionList } from '@/domain/PropertyDefinitionList.ts';
import { useSchemaStore } from '@/stores/SchemaStore.ts';
import type { SaveBlocker } from '@/components/common/SaveBlocker.ts';

const props = withDefaults( defineProps<{
	initialName?: string;
	/** The Schema to duplicate: the new one starts with all of it but the name. */
	duplicateOf?: Schema;
}>(), {
	initialName: '',
	duplicateOf: undefined
} );

const emit = defineEmits<{
	change: [];
}>();

const schemaStore = useSchemaStore();

const DEBOUNCE_DELAY = 300;

const baseSchema = props.duplicateOf ?? new Schema( '', '', new PropertyDefinitionList( [] ) );

const schemaName = ref( props.initialName );
const schemaDescription = ref( baseSchema.getDescription() );
const nameError = ref( '' );
const nameStatus = ref<ValidationStatusType>( 'default' );
const schemaEditorRef = ref<SchemaEditorExposes | null>( null );
let debounceTimer: ReturnType<typeof setTimeout> | null = null;
let requestSequence = 0;

function onChange(): void {
	emit( 'change' );
}

function onNameInput(): void {
	nameError.value = '';
	nameStatus.value = 'default';
	emit( 'change' );
	clearDebounceTimer();
	requestSequence++;

	const name = schemaName.value.trim();

	if ( !name ) {
		nameError.value = mw.msg( 'neowiki-schema-creator-name-required' );
		nameStatus.value = 'error';
		return;
	}

	const expectedSequence = requestSequence;
	debounceTimer = setTimeout( () => checkDuplicateName( name, expectedSequence ), DEBOUNCE_DELAY );
}

async function checkDuplicateName( name: string, expectedSequence: number ): Promise<void> {
	const exists = await schemaStore.schemaNameExists( name );

	if ( expectedSequence !== requestSequence ) {
		return;
	}

	if ( exists ) {
		nameError.value = mw.msg( 'neowiki-schema-creator-name-taken' );
		nameStatus.value = 'error';
	}
}

function clearDebounceTimer(): void {
	if ( debounceTimer !== null ) {
		clearTimeout( debounceTimer );
		debounceTimer = null;
	}
}

async function validate(): Promise<boolean> {
	clearDebounceTimer();
	requestSequence++;

	const name = schemaName.value.trim();

	if ( !name ) {
		nameError.value = mw.msg( 'neowiki-schema-creator-name-required' );
		nameStatus.value = 'error';
		return false;
	}

	if ( await schemaStore.schemaNameExists( name ) ) {
		nameError.value = mw.msg( 'neowiki-schema-creator-name-taken' );
		nameStatus.value = 'error';
		return false;
	}

	return true;
}

function getSchema(): Schema | null {
	const name = schemaName.value.trim();

	if ( !name ) {
		return null;
	}

	return ( schemaEditorRef.value?.getSchema() ?? baseSchema )
		.withName( name )
		.withDescription( schemaDescription.value.trim() );
}

function saveBlocker(): SaveBlocker | null {
	return schemaEditorRef.value?.saveBlocker() ?? null;
}

export interface SchemaCreatorExposes {
	validate: () => Promise<boolean>;
	getSchema: () => Schema | null;
	saveBlocker: () => SaveBlocker | null;
}

defineExpose<SchemaCreatorExposes>( { validate, getSchema, saveBlocker } );
</script>

<style lang="less">
@import ( reference ) '@wikimedia/codex-design-tokens/theme-wikimedia-ui.less';

.ext-neowiki-schema-creator {
	&__name-section {
		padding: @spacing-100;
		border-block-end: @border-subtle;

		@media ( min-width: @min-width-breakpoint-desktop ) {
			padding: @spacing-150;
		}
	}
}
</style>
