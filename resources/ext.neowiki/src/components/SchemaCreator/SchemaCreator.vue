<template>
	<div class="ext-neowiki-schema-creator">
		<div class="ext-neowiki-schema-creator__name-section">
			<CdxField
				:status="nameStatus"
				:messages="nameError ? { error: nameError } : {}"
			>
				<CdxTextInput
					ref="nameInputRef"
					v-model="schemaName"
					:placeholder="$i18n( 'neowiki-schema-creator-name-placeholder' ).text()"
					@input="onNameInput"
				/>
				<template #label>
					{{ $i18n( 'neowiki-schema-creator-name-field' ).text() }}
				</template>
			</CdxField>
		</div>

		<SchemaEditor
			ref="schemaEditorRef"
			:initial-schema="emptySchema"
			@overflow="onOverflow"
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

const emit = defineEmits<{
	overflow: [ hasOverflow: boolean ];
	change: [];
}>();

const schemaStore = useSchemaStore();

const DEBOUNCE_DELAY = 300;

const emptySchema = new Schema( '', '', new PropertyDefinitionList( [] ) );

const schemaName = ref( '' );
const nameError = ref( '' );
const nameStatus = ref<ValidationStatusType>( 'default' );
const nameInputRef = ref<InstanceType<typeof CdxTextInput> | null>( null );
const schemaEditorRef = ref<SchemaEditorExposes | null>( null );
let debounceTimer: ReturnType<typeof setTimeout> | null = null;
let requestSequence = 0;

function onOverflow( hasOverflow: boolean ): void {
	emit( 'overflow', hasOverflow );
}

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
	try {
		await schemaStore.getOrFetchSchema( name );

		if ( expectedSequence !== requestSequence ) {
			return;
		}

		nameError.value = mw.msg( 'neowiki-schema-creator-name-taken' );
		nameStatus.value = 'error';
	} catch {
		// Schema not found — name is available
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

	try {
		await schemaStore.getOrFetchSchema( name );
		nameError.value = mw.msg( 'neowiki-schema-creator-name-taken' );
		nameStatus.value = 'error';
		return false;
	} catch {
		// Schema not found — name is available
		return true;
	}
}

function getSchema(): Schema | null {
	const name = schemaName.value.trim();

	if ( !name ) {
		return null;
	}

	const editorSchema = schemaEditorRef.value?.getSchema();
	const propertyDefinitions = editorSchema?.getPropertyDefinitions() ?? new PropertyDefinitionList( [] );
	const description = editorSchema?.getDescription() ?? '';

	return new Schema( name, description, propertyDefinitions );
}

function reset(): void {
	clearDebounceTimer();
	requestSequence++;
	schemaName.value = '';
	nameError.value = '';
	nameStatus.value = 'default';
}

function focus(): void {
	nameInputRef.value?.focus();
}

export interface SchemaCreatorExposes {
	validate: () => Promise<boolean>;
	getSchema: () => Schema | null;
	reset: () => void;
	focus: () => void;
}

defineExpose( { validate, getSchema, reset, focus } );
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
