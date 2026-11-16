<template>
	<SchemaEditor
		ref="schemaEditorRef"
		class="ext-neowiki-schema-creator"
		:initial-schema="baseSchema"
		:subjects-are-dependent="subjectsAreDependent"
		:opens-on-schema="true"
		@change="onChange"
	>
		<template #schema>
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
				<CdxTextArea
					v-model="schemaDescription"
					:placeholder="$i18n( 'neowiki-schema-editor-description-placeholder' ).text()"
					@update:model-value="onChange"
				/>
				<template #label>
					{{ $i18n( 'neowiki-schema-editor-description' ).text() }}
				</template>
			</CdxField>

			<!-- Decided for the user, only dependent Subjects are worth stating: standalone is the default. -->
			<CdxField
				v-if="props.subjectsAreDependent === null"
				class="ext-neowiki-schema-creator__dependence"
				:hide-label="true"
			>
				<div class="ext-neowiki-schema-creator__dependence-option">
					<CdxCheckbox
						v-model="subjectsAreDependent"
						@update:model-value="onChange"
					>
						{{ $i18n( 'neowiki-schema-creator-dependent' ).text() }}
						<template #description>
							{{ $i18n( 'neowiki-schema-creator-dependent-help' ).text() }}
						</template>
					</CdxCheckbox>
					<!-- Outside the checkbox's label, which would take its name into the checkbox's own. A toggle rather
						than a tooltip: a tap shows it too, and Escape is left to the dialog. -->
					<CdxButton
						weight="quiet"
						:aria-label="$i18n( 'neowiki-schema-creator-dependent-info-label' ).text()"
						:title="$i18n( 'neowiki-schema-creator-dependent-info-label' ).text()"
						:aria-expanded="explanationShown"
						@click="explanationShown = !explanationShown"
					>
						<CdxIcon :icon="cdxIconInfo" />
					</CdxButton>
				</div>
				<p
					v-if="explanationShown"
					class="ext-neowiki-schema-creator__dependence-explanation"
				>
					{{ $i18n( 'neowiki-schema-creator-dependent-info' ).text() }}
				</p>
			</CdxField>
			<p
				v-else-if="subjectsAreDependent"
				class="ext-neowiki-schema-creator__dependence-decided"
			>
				{{ $i18n( 'neowiki-schema-subjects-are-dependent' ).text() }}
			</p>
		</template>
	</SchemaEditor>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { CdxButton, CdxCheckbox, CdxField, CdxIcon, CdxTextArea, CdxTextInput } from '@wikimedia/codex';
import { cdxIconInfo } from '@wikimedia/codex-icons';
import type { ValidationStatusType } from '@wikimedia/codex';
import SchemaEditor from '@/components/SchemaEditor/SchemaEditor.vue';
import type { SchemaEditorExposes } from '@/components/SchemaEditor/SchemaEditor.vue';
import { Schema } from '@/domain/Schema.ts';
import { PropertyDefinitionList } from '@/domain/PropertyDefinitionList.ts';
import { useSchemaStore } from '@/stores/SchemaStore.ts';
import type { SaveBlocker } from '@/components/common/SaveBlocker.ts';

const props = withDefaults( defineProps<{
	/** The Schema to duplicate: the new one starts with all of it but the name. */
	duplicateOf?: Schema;
	/**
	 * Set to decide for the user. Null leaves it to them, starting from what a duplicated Schema declares.
	 */
	subjectsAreDependent?: boolean | null;
}>(), {
	duplicateOf: undefined,
	subjectsAreDependent: null
} );

const emit = defineEmits<{
	change: [];
}>();

const schemaStore = useSchemaStore();

const DEBOUNCE_DELAY = 300;

const baseSchema = props.duplicateOf ?? new Schema( '', '', new PropertyDefinitionList( [] ), false );

const schemaName = defineModel<string>( 'name', { default: '' } );
const schemaDescription = ref( baseSchema.getDescription() );
const subjectsAreDependent = ref( props.subjectsAreDependent ?? baseSchema.subjectsAreDependent() );
const explanationShown = ref( false );
const nameError = ref( '' );
const nameStatus = ref<ValidationStatusType>( 'default' );
const schemaEditorRef = ref<SchemaEditorExposes | null>( null );
let debounceTimer: ReturnType<typeof setTimeout> | null = null;
let requestSequence = 0;

// The name, the first of the fields the editor opens on, is the field to fill in first. A task later: Codex focuses
// the dialog's first focusable element, the Schema's row, one or two ticks after it opens, and would otherwise win.
// Focusing it sooner would also stop Codex returning the focus on close.
onMounted( () => {
	setTimeout( () => schemaEditorRef.value?.showSchema() );
} );

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
		return refuseName( mw.msg( 'neowiki-schema-creator-name-required' ) );
	}

	if ( await schemaStore.schemaNameExists( name ) ) {
		return refuseName( mw.msg( 'neowiki-schema-creator-name-taken' ) );
	}

	return true;
}

function refuseName( error: string ): false {
	nameError.value = error;
	nameStatus.value = 'error';
	schemaEditorRef.value?.showSchema();
	return false;
}

function getSchema(): Schema | null {
	const name = schemaName.value.trim();

	if ( !name ) {
		return null;
	}

	return ( schemaEditorRef.value?.getSchema() ?? baseSchema )
		.withName( name )
		.withDescription( schemaDescription.value.trim() )
		.withSubjectsAreDependent( subjectsAreDependent.value );
}

function saveBlocker(): SaveBlocker | null {
	return schemaEditorRef.value?.saveBlocker() ?? null;
}

function revealErrors(): void {
	schemaEditorRef.value?.revealErrors();
}

export interface SchemaCreatorExposes {
	validate: () => Promise<boolean>;
	getSchema: () => Schema | null;
	saveBlocker: () => SaveBlocker | null;
	revealErrors: () => void;
}

defineExpose<SchemaCreatorExposes>( { validate, getSchema, saveBlocker, revealErrors } );
</script>

<style lang="less">
@import ( reference ) '@wikimedia/codex-design-tokens/theme-wikimedia-ui.less';

.ext-neowiki-schema-creator {
	/* The button ends the line saying that the choice is final, and the explanation opens below that line. On the
		baseline rather than at the end: the checkbox's margin and padding reach below its last line. */
	&__dependence-option {
		display: flex;
		align-items: last baseline;
		gap: @spacing-25;
	}

	/* In line with the checkbox's own text, past its box. */
	&__dependence-explanation {
		margin: @spacing-25 0 0;
		padding-inline-start: calc( @size-125 + @spacing-50 );
		color: @color-subtle;
	}

	&__dependence-decided {
		margin: @spacing-100 0 0;
		color: @color-subtle;
	}
}
</style>
