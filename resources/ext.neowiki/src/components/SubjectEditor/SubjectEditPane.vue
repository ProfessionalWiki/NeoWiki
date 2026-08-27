<template>
	<!-- The root pane goes unnamed: the dialog around it already names that same Subject. -->
	<section
		class="ext-neowiki-subject-edit-pane"
		:aria-label="props.nested ? paneName : undefined"
	>
		<div
			v-if="props.nested"
			class="ext-neowiki-subject-edit-pane__storage"
		>
			<I18nSlot
				v-if="pageName !== null"
				message-key="neowiki-subject-editor-stored-on"
			>
				<!-- A new tab: following the link in this one discards unsaved edits. -->
				<a
					class="ext-neowiki-subject-edit-pane__page"
					:href="pageUrl"
					target="_blank"
					rel="noopener"
				>{{ pageName }}</a>
			</I18nSlot>
		</div>

		<SubjectViolationBanners :violations="anchorlessViolations" />

		<SubjectEditor
			ref="subjectEditorRef"
			:statements="statements"
			:schema="props.schema"
			:server-violations="serverViolations"
			@change="handleEditorChange"
			@relation-change="handleRelationChange"
			@focusout="handleEditorBlur"
			@clear-server-violation="handleClearViolation"
			@edit-relation-target="emit( 'edit-relation-target', $event )"
		/>
	</section>
</template>

<script setup lang="ts">
import { ref, shallowRef, computed, watch, provide } from 'vue';
import SubjectEditor from '@/components/SubjectEditor/SubjectEditor.vue';
import type { SubjectEditorExposes } from '@/components/SubjectEditor/SubjectEditor.vue';
import SubjectViolationBanners from '@/components/common/SubjectViolationBanners.vue';
import I18nSlot from '@/components/common/I18nSlot.vue';
import { StatementList } from '@/domain/StatementList.ts';
import { Subject } from '@/domain/Subject.ts';
import { enteredSubjectLabel } from '@/domain/enteredSubjectLabel.ts';
import { SubjectWithContext } from '@/domain/SubjectWithContext.ts';
import { SubjectId } from '@/domain/SubjectId.ts';
import { Schema } from '@/domain/Schema.ts';
import { useSubjectStore } from '@/stores/SubjectStore.ts';
import { useChangeDetection } from '@/composables/useChangeDetection.ts';
import { useSubjectValidation } from '@/composables/useSubjectValidation.ts';
import { NeoWikiExtension } from '@/NeoWikiExtension.ts';
import { RelationTargetEditingKey } from '@/components/Value/ValueInputContract.ts';
import type { SubjectViolation } from '@/domain/SubjectViolation';
import type { UnparseableInput } from '@/components/common/UnparseableInput.ts';

const props = defineProps<{
	subject: Subject;
	schema: Schema;
	nested?: boolean;
	// The client's own values for this Subject, written by a save still on screen. Outranks
	// props.subject, which is a pre-edit read.
	editedCopy?: Subject;
}>();

const emit = defineEmits<{
	'edit-relation-target': [ SubjectId ];
}>();

provide( RelationTargetEditingKey, true );

const subjectStore = useSubjectStore();

const subjectEditorRef = ref<SubjectEditorExposes | null>( null );
const { hasChanged, markChanged, resetChanged } = useChangeDetection();

const baseSubject = computed( (): Subject => props.editedCopy ?? props.subject );

// Refreshed when a relation field changes and on nothing else: the tree is its only
// consumer and reads only relation statements, and harvesting per keystroke would re-walk
// the tree per character. Its other statements therefore lag; never save or validate from it.
const editedSubject = shallowRef<Subject>( baseSubject.value );

function handleRelationChange(): void {
	editedSubject.value = buildUpdatedSubject() ?? baseSubject.value;
}

const label = ref( baseSubject.value.getLabel() ?? '' );

const storedLabel = computed( (): string | null => enteredSubjectLabel( label.value ) );

const paneName = computed( (): string => storedLabel.value ?? baseSubject.value.getDisplayName() );

const pageName = computed( (): string | null =>
	props.subject instanceof SubjectWithContext ?
		props.subject.getPageIdentifiers().getPageName() :
		null
);

const pageUrl = computed( (): string =>
	pageName.value === null ? '' : mw.util.getUrl( pageName.value )
);

// EditableText commits once per edit, so a commit is both the change and the
// end of the interaction: validate immediately rather than waiting for a blur.
function setLabel( value: string ): void {
	label.value = value;
	handleEditorChange();
	handleEditorBlur();
}

// Reads baseSubject, never editedSubject: feeding the harvested snapshot back would re-key
// SubjectEditor's v-for on every relation pick, remounting every ValueInput and discarding
// whatever the user was typing elsewhere.
const statements = computed( (): StatementList =>
	props.schema.statementsFrom( baseSubject.value.getStatements() )
);

const { violations: serverViolations, revalidate, flush } = useSubjectValidation(
	async () => {
		if ( !subjectEditorRef.value ) {
			return [];
		}
		const current = [ ...subjectEditorRef.value.getSubjectData() ].filter( ( s ) => s.hasValue() );
		try {
			// Unlike subject creation, editing an existing subject surfaces
			// 'required' live: an empty required field here is a real gap, not a
			// field the user is still on their way to filling in.
			return await subjectStore.validateSubjectUpdate(
				props.subject.getId(),
				storedLabel.value,
				new StatementList( current )
			);
		} catch ( error ) {
			// The dry-run runs alongside the live validators and must never
			// break editing or saving; the authoritative result is the save's
			// own 422 response.
			console.error( 'Subject validation dry-run failed:', error );
			return [];
		}
	},
	{ debounceMs: NeoWikiExtension.getInstance().getValidationDebounceMs() }
);

let dirtySinceValidation = false;

function handleEditorChange(): void {
	markChanged();
	dirtySinceValidation = true;
	revalidate();
}

function handleEditorBlur(): void {
	// focusout bubbles on every field-to-field move; only flush when something
	// actually changed since the last validation, to avoid redundant requests.
	if ( dirtySinceValidation ) {
		dirtySinceValidation = false;
		flush();
	}
}

const anchorlessViolations = computed<SubjectViolation[]>( () => {
	// SubjectEditor renders one field per entry in `statements`, which the
	// schema materialises from its property definitions (so empty/missing
	// properties still get a field). Anchor against THAT list, not the
	// raw subject — otherwise a violation on a missing-but-rendered field
	// would be wrongly banner-routed even though the field is on screen.
	const renderedPropertyNames = new Set(
		[ ...statements.value ].map( ( s ) => s.propertyName.toString() )
	);
	return serverViolations.value.filter( ( v ) => {
		if ( v.propertyName === null ) {
			return true;
		}
		return !renderedPropertyNames.has( v.propertyName );
	} );
} );

function handleClearViolation( payload: { propertyName: string; valuePartIndex: number | null } ): void {
	serverViolations.value = serverViolations.value.filter(
		( v ) => !( v.propertyName === payload.propertyName && v.valuePartIndex === payload.valuePartIndex )
	);
}

// Existing subjects are expected to be complete, so validate as soon as the
// editor mounts: pre-existing violations (e.g. a now-empty required field)
// surface immediately, without the user having to touch a field first.
watch( subjectEditorRef, ( editor ) => {
	if ( editor ) {
		flush();
	}
} );

watch( baseSubject, ( newBaseSubject ) => {
	label.value = newBaseSubject.getLabel() ?? '';
	// Drop the harvest, or a reopened pane seeds the tree with relations the form no
	// longer displays.
	editedSubject.value = newBaseSubject;
} );

function buildUpdatedSubject(): Subject | null {
	if ( !subjectEditorRef.value ) {
		return null;
	}
	const updated = [ ...subjectEditorRef.value.getSubjectData() ].filter( ( s ) => s.hasValue() );
	return props.subject
		.withLabel( storedLabel.value )
		.withStatements( new StatementList( updated ) );
}

function unparseableInput(): UnparseableInput | null {
	return subjectEditorRef.value?.unparseableInput() ?? null;
}

function setServerViolations( violations: readonly SubjectViolation[] ): void {
	serverViolations.value = [ ...violations ];
}

defineExpose( {
	hasChanged,
	label,
	editedSubject,
	setLabel,
	resetChanged,
	buildUpdatedSubject,
	setServerViolations,
	unparseableInput,
	flushValidation: flush
} );
</script>

<style lang="less">
@import ( reference ) '@wikimedia/codex-design-tokens/theme-wikimedia-ui.less';

.ext-neowiki-subject-edit-pane {
	&__storage {
		display: flex;
		justify-content: flex-end;
		margin-bottom: @spacing-75;
		color: @color-subtle;
		font-size: @font-size-small;
	}
}
</style>
