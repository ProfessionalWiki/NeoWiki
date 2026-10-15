<template>
	<div class="ext-neowiki-schema-creator">
		<div class="ext-neowiki-schema-creator__name-section">
			<CdxField
				:status="nameStatus"
				:messages="nameError ? { error: nameError } : {}"
			>
				<CdxTextInput
					ref="nameInput"
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
		</div>

		<SchemaEditor
			ref="schemaEditorRef"
			:initial-schema="baseSchema"
			:subjects-are-dependent="subjectsAreDependent"
			@change="onChange"
		/>
	</div>
</template>

<script setup lang="ts">
import { nextTick, onMounted, ref } from 'vue';
import { CdxButton, CdxCheckbox, CdxField, CdxIcon, CdxTextInput } from '@wikimedia/codex';
import { cdxIconInfo } from '@wikimedia/codex-icons';
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
	/**
	 * Set to decide for the user. Null leaves it to them, starting from what a duplicated Schema declares.
	 */
	subjectsAreDependent?: boolean | null;
}>(), {
	initialName: '',
	duplicateOf: undefined,
	subjectsAreDependent: null
} );

const emit = defineEmits<{
	change: [];
}>();

const schemaStore = useSchemaStore();

const DEBOUNCE_DELAY = 300;

const baseSchema = props.duplicateOf ?? new Schema( '', '', new PropertyDefinitionList( [] ), false );

const schemaName = ref( props.initialName );
const schemaDescription = ref( baseSchema.getDescription() );
const subjectsAreDependent = ref( props.subjectsAreDependent ?? baseSchema.subjectsAreDependent() );
const explanationShown = ref( false );
const nameError = ref( '' );
const nameStatus = ref<ValidationStatusType>( 'default' );
const nameInput = ref<InstanceType<typeof CdxTextInput> | null>( null );
const schemaEditorRef = ref<SchemaEditorExposes | null>( null );
let debounceTimer: ReturnType<typeof setTimeout> | null = null;
let requestSequence = 0;

// The Schema editor focuses a duplicate's first property definition; the name is the field to fill in first. A new
// Schema is left to Codex, which focuses the name itself: focusing it sooner stops Codex returning the focus on close.
onMounted( () => {
	if ( props.duplicateOf !== undefined ) {
		nextTick( () => nameInput.value?.focus() );
	}
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

/* The Schema editor takes what the name fields leave of a dialog taller than both, so the
	rule between its columns reaches the footer. */
.ext-neowiki-schema-creator {
	display: grid;
	grid-template-rows: auto 1fr;

	&__name-section {
		padding: @spacing-100;
		border-block-end: @border-subtle;

		@media ( min-width: @min-width-breakpoint-desktop ) {
			padding: @spacing-150;
		}
	}

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
		margin: @spacing-75 0 0;
		color: @color-subtle;
	}
}
</style>
