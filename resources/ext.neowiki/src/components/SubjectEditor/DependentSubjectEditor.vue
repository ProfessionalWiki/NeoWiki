<template>
	<div
		ref="root"
		class="ext-neowiki-dependent-subject-editor"
		role="group"
		:aria-label="props.name"
		tabindex="-1"
	>
		<div
			v-if="props.showsSchemaName && props.schema !== undefined"
			class="ext-neowiki-dependent-subject-editor__schema"
		>
			<SchemaNameDisplay
				:schema-name="props.schema.getName()"
				link="none"
			/>
		</div>

		<CdxButton
			class="ext-neowiki-dependent-subject-editor__remove"
			weight="quiet"
			:aria-label="$i18n( 'neowiki-dependent-subject-remove', props.name ).text()"
			:title="$i18n( 'neowiki-dependent-subject-remove', props.name ).text()"
			@click="emit( 'remove' )"
		>
			<CdxIcon :icon="cdxIconClose" />
		</CdxButton>

		<div class="ext-neowiki-dependent-subject-editor__body">
			<CdxMessage
				v-if="props.subject === undefined || props.schema === undefined"
				type="error"
				:inline="true"
			>
				{{ $i18n( 'neowiki-dependent-subject-load-error' ).text() }}
			</CdxMessage>

			<template v-else>
				<SubjectViolationBanners :violations="anchorlessViolations" />

				<SubjectEditor
					ref="subjectEditorRef"
					:statements="statements"
					:schema="props.schema"
					:server-violations="violations"
					@change="onChange( false )"
					@relation-change="onChange( true )"
					@dependent-subject-change="emit( 'change', $event )"
					@focusout="onBlur"
					@clear-server-violation="clearViolation"
					@edit-relation-target="emit( 'edit-relation-target', $event )"
				/>
			</template>
		</div>
	</div>
</template>

<script lang="ts">
import type { Subject } from '@/domain/Subject.ts';
import type { SaveBlocker } from '@/components/common/SaveBlocker.ts';

export interface DependentSubjectEditorExposes {
	/** As its fields hold it; null for one that did not load. */
	buildSubject(): Subject | null;
	/** Those inside it, as their fields hold them, each followed by those inside it. */
	dependentSubjects(): Subject[];
	saveBlocker(): SaveBlocker | null;
	focus(): void;
}
</script>

<script setup lang="ts">
import { computed, inject, ref, watch } from 'vue';
import { CdxButton, CdxIcon, CdxMessage } from '@wikimedia/codex';
import { cdxIconClose } from '@wikimedia/codex-icons';
import SubjectEditor from '@/components/SubjectEditor/SubjectEditor.vue';
import type { SubjectEditorExposes } from '@/components/SubjectEditor/SubjectEditor.vue';
import SubjectViolationBanners from '@/components/common/SubjectViolationBanners.vue';
import SchemaNameDisplay from '@/components/common/SchemaNameDisplay.vue';
import { StatementList } from '@/domain/StatementList.ts';
import type { Schema } from '@/domain/Schema.ts';
import type { SubjectId } from '@/domain/SubjectId.ts';
import { useSubjectStore } from '@/stores/SubjectStore.ts';
import { useSubjectValidation } from '@/composables/useSubjectValidation.ts';
import { NeoWikiExtension } from '@/NeoWikiExtension.ts';
import {
	withoutMissingValueViolations,
	withoutUnsavedTargetViolations,
	type SubjectViolation
} from '@/domain/SubjectViolation.ts';
import { DependentSubjectEditingKey, DependentSubjectViolationsKey } from '@/components/SubjectEditor/DependentSubjectEditing.ts';
import { focusFirstValueControl } from '@/components/SubjectEditor/valueControlFocus.ts';

const props = defineProps<{
	/** Undefined for a Dependent Subject that did not load, which can only be removed. */
	subject: Subject | undefined;
	schema: Schema | undefined;
	/** Whether the wiki does not hold it yet. */
	isNew: boolean;
	/** The group's accessible name, which its remove control uses too. */
	name: string;
	showsSchemaName: boolean;
}>();

const emit = defineEmits<{
	remove: [];
	change: [ relationChanged: boolean ];
	'edit-relation-target': [ SubjectId ];
}>();

const root = ref<HTMLElement | null>( null );
const subjectEditorRef = ref<SubjectEditorExposes | null>( null );
const subjectStore = useSubjectStore();
const editing = inject( DependentSubjectEditingKey, undefined );

// Read once: a group is keyed by its Subject's id, and the fields keep what the user typed.
const statements = computed( (): StatementList =>
	props.schema === undefined || props.subject === undefined ?
		new StatementList( [] ) :
		props.schema.statementsFrom( props.subject.getStatements() )
);

const { violations, revalidate, flush } = useSubjectValidation(
	async () => {
		const subject = buildSubject();

		if ( subject === null ) {
			return [];
		}

		try {
			// Like a pane's: a Dependent Subject the wiki does not hold yet is still being filled in.
			const found = props.isNew ?
				withoutMissingValueViolations( await subjectStore.validateSubject(
					null,
					subject.getSchemaName(),
					subject.getStatements()
				) ) :
				await subjectStore.validateSubjectUpdate( subject.getId(), null, subject.getStatements() );

			return withoutUnsavedTargetViolations( found, editing?.unsavedTargetIds() ?? [] );
		} catch ( error ) {
			console.error( 'Dependent subject validation dry-run failed:', error );
			return [];
		}
	},
	{ debounceMs: NeoWikiExtension.getInstance().getValidationDebounceMs() }
);

// A refused save's report on this Dependent Subject replaces what the dry-run last found.
const savedViolations = inject( DependentSubjectViolationsKey, undefined );

if ( savedViolations !== undefined ) {
	watch( savedViolations, ( reported ) => {
		violations.value = reported.filter( ( violation ) => violation.subjectId === props.subject?.getId().text );
	} );
}

// A Dependent Subject the wiki holds is expected to be complete, as a pane's Subject is.
watch( subjectEditorRef, ( editor ) => {
	if ( editor !== null && !props.isNew ) {
		flush();
	}
}, { flush: 'post' } );

let dirtySinceValidation = false;

function onChange( relationChanged: boolean ): void {
	if ( props.subject !== undefined ) {
		editing?.markChanged( props.subject.getId().text );
	}

	dirtySinceValidation = true;
	revalidate();
	emit( 'change', relationChanged );
}

function onBlur(): void {
	if ( dirtySinceValidation ) {
		dirtySinceValidation = false;
		flush();
	}
}

const anchorlessViolations = computed( (): SubjectViolation[] => {
	const renderedNames = new Set( [ ...statements.value ].map( ( statement ) => statement.propertyName.toString() ) );

	return violations.value.filter(
		( violation ) => violation.propertyName === null || !renderedNames.has( violation.propertyName )
	);
} );

function clearViolation( payload: { propertyName: string; valuePartIndex: number | null } ): void {
	violations.value = violations.value.filter(
		( violation ) => !( violation.propertyName === payload.propertyName && violation.valuePartIndex === payload.valuePartIndex )
	);
}

function buildSubject(): Subject | null {
	if ( props.subject === undefined || props.schema === undefined || subjectEditorRef.value === null ) {
		return null;
	}

	return props.subject.withStatements( props.schema.statementsAfterEdit(
		props.subject.getStatements(),
		subjectEditorRef.value.getSubjectData().withNonEmptyValues()
	) );
}

function dependentSubjects(): Subject[] {
	return subjectEditorRef.value?.getDependentSubjects() ?? [];
}

function saveBlocker(): SaveBlocker | null {
	return subjectEditorRef.value?.saveBlocker() ?? null;
}

function focus(): void {
	if ( root.value !== null ) {
		focusFirstValueControl( root.value );
	}
}

defineExpose<DependentSubjectEditorExposes>( { buildSubject, dependentSubjects, saveBlocker, focus } );
</script>

<style lang="less">
@import ( reference ) '@wikimedia/codex-design-tokens/theme-wikimedia-ui.less';

/* The remove control sits beside the fields: a row of its own would push every field down. */
.ext-neowiki-dependent-subject-editor {
	display: grid;
	grid-template-columns: minmax( 0, 1fr ) auto;
	column-gap: @spacing-50;
	padding: @spacing-50 @spacing-50 @spacing-75 @spacing-75;
	border: @border-width-base @border-style-base @border-color-subtle;
	border-radius: @border-radius-base;
	background-color: @background-color-neutral-subtle;

	&__schema {
		grid-column: 1;
		grid-row: 1;
		align-self: center;
		min-width: 0;
	}

	&__remove {
		grid-column: 2;
		grid-row: 1;
		align-self: start;
	}

	&__body {
		grid-column: 1;
		min-width: 0;
		/* Level with the remove control, whose box is taller than a label line. */
		padding-top: @spacing-25;
	}

	/* Codex gives a label inside a fieldset the normal weight of a checkbox option, and a group sits in the
		fieldset of its relation property. */
	.cdx-field > .cdx-label .cdx-label__label__text {
		font-weight: @font-weight-bold;
	}

	/* Codex gives these controls a 256px minimum, which a group, narrower than the form, cannot hold on a phone. */
	.cdx-text-input,
	.cdx-select,
	.cdx-select-vue__handle,
	.cdx-chip-input__chips,
	.cdx-chip-input__separate-input {
		min-width: 0;
	}
}
</style>
