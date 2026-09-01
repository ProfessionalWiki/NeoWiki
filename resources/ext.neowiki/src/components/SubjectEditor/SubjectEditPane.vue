<template>
	<!-- The root pane goes unnamed: the dialog around it already names that same Subject. -->
	<section
		class="ext-neowiki-subject-edit-pane"
		:aria-label="props.nested ? paneName : undefined"
	>
		<!-- Only a nested pane carries these: the dialog's own header names the root Subject
			and holds the field that renames it. -->
		<div
			v-if="props.nested"
			class="ext-neowiki-subject-edit-pane__header"
		>
			<h3 class="ext-neowiki-subject-edit-pane__name">
				<EditableText
					:model-value="label"
					:edit-button-label="$i18n( 'neowiki-subject-editor-rename' ).text()"
					:input-aria-label="$i18n( 'neowiki-subject-editor-label-field' ).text()"
					:placeholder="labelPlaceholder"
					@update:model-value="setLabel"
				/>
			</h3>

			<div
				v-if="pageName !== null"
				class="ext-neowiki-subject-edit-pane__storage"
			>
				<I18nSlot message-key="neowiki-subject-editor-stored-on">
					<!-- A new tab: following the link in this one discards unsaved edits. -->
					<a
						class="ext-neowiki-subject-edit-pane__page"
						:href="pageUrl"
						target="_blank"
						rel="noopener"
					>{{ pageName }}</a>
				</I18nSlot>
			</div>
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

<script lang="ts">
export interface SubjectEditPaneExposes {
	hasChanged: boolean;
	label: string;
	// Refreshed on relation changes alone, so its other statements lag: read it for the
	// tree, never to save or validate from.
	editedSubject: Subject;
	setLabel: ( value: string ) => void;
	resetChanged: () => void;
	buildUpdatedSubject: () => Subject | null;
	setServerViolations: ( violations: readonly SubjectViolation[] ) => void;
	unparseableInput: () => UnparseableInput | null;
	flushValidation: () => Promise<void>;
}
</script>

<script setup lang="ts">
import { ref, shallowRef, computed, watch, provide } from 'vue';
import SubjectEditor from '@/components/SubjectEditor/SubjectEditor.vue';
import type { SubjectEditorExposes } from '@/components/SubjectEditor/SubjectEditor.vue';
import SubjectViolationBanners from '@/components/common/SubjectViolationBanners.vue';
import I18nSlot from '@/components/common/I18nSlot.vue';
import EditableText from '@/components/common/EditableText.vue';
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
import { withoutMissingValueViolations, withoutUnsavedTargetViolations, type SubjectViolation } from '@/domain/SubjectViolation';
import type { UnparseableInput } from '@/components/common/UnparseableInput.ts';

const props = defineProps<{
	subject: Subject;
	schema: Schema;
	nested?: boolean;
	// A Subject this editing session invented, which the server has never seen. It is validated
	// as a creation rather than as an update, and the dialog writes it with a create.
	isNew?: boolean;
	// Subjects the session has invented but not yet written. A relation naming one of them is
	// sound here and unresolvable to the server, so its complaint is withheld.
	unsavedTargetIds?: readonly string[];
}>();

const emit = defineEmits<{
	'edit-relation-target': [ SubjectId ];
}>();

provide( RelationTargetEditingKey, true );

const subjectStore = useSubjectStore();

const subjectEditorRef = ref<SubjectEditorExposes | null>( null );
const { hasChanged, markChanged, resetChanged } = useChangeDetection();

// Refreshed when a relation field changes and on nothing else: the tree is its only
// consumer and reads only relation statements, and harvesting per keystroke would re-walk
// the tree per character. Its other statements therefore lag; never save or validate from it.
const editedSubject = shallowRef<Subject>( props.subject );

function handleRelationChange(): void {
	editedSubject.value = buildUpdatedSubject() ?? props.subject;
}

const label = ref( props.subject.getLabel() ?? '' );

const storedLabel = computed( (): string | null => enteredSubjectLabel( label.value ) );

const paneName = computed( (): string => storedLabel.value ?? props.subject.getDisplayName() );

const pageName = computed( (): string | null =>
	props.subject instanceof SubjectWithContext ?
		props.subject.getPageIdentifiers().getPageName() :
		null
);

const pageUrl = computed( (): string =>
	pageName.value === null ? '' : mw.util.getUrl( pageName.value )
);

// Previews the name a Subject with no label is shown under, which for one created here is its
// Schema's name (ADR 31). A labelled Subject gets the generic hint instead.
const labelPlaceholder = computed( (): string =>
	props.subject.getLabel() === null ?
		props.subject.getDisplayName() :
		mw.msg( 'neowiki-subject-editor-label-field' )
);

// EditableText commits once per edit, so a commit is both the change and the
// end of the interaction: validate immediately rather than waiting for a blur.
function setLabel( value: string ): void {
	label.value = value;
	handleEditorChange();
	handleEditorBlur();
}

// Reads the prop, never editedSubject: feeding the harvested snapshot back would hand every
// ValueInput a fresh Value on each relation pick, and an input resets its text to a Value it
// did not emit.
const statements = computed( (): StatementList =>
	props.schema.statementsFrom( props.subject.getStatements() )
);

const { violations: serverViolations, revalidate, flush } = useSubjectValidation(
	async () => {
		if ( !subjectEditorRef.value ) {
			return [];
		}
		const current = subjectEditorRef.value.getSubjectData().withNonEmptyValues();
		try {
			// A Subject the server has never seen has no update to dry-run against, and its
			// empty required fields are ones the user is still on their way to filling in
			// rather than real gaps — the same reading the subject creator takes.
			if ( props.isNew ) {
				return withoutSessionOnlyViolations( withoutMissingValueViolations(
					await subjectStore.validateSubject(
						storedLabel.value,
						props.subject.getSchemaName(),
						current
					)
				) );
			}

			// Unlike subject creation, editing an existing subject surfaces
			// 'required' live: an empty required field here is a real gap, not a
			// field the user is still on their way to filling in.
			return withoutSessionOnlyViolations( await subjectStore.validateSubjectUpdate(
				props.subject.getId(),
				storedLabel.value,
				current
			) );
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

// Existing subjects are expected to be complete, so validate as soon as the editor
// mounts, and again whenever the Schema it validates against changes: the violations
// are a snapshot of a server response, so a Schema edit invalidates them until the
// next run. flush() rather than revalidate(), which a zero debounce turns into a
// no-op — blur-only wikis need the refresh too. Post-flush, so the validator reads
// the fields the new Schema produced, not the ones it replaced.
watch( [ subjectEditorRef, () => props.schema ], ( [ editor ] ) => {
	if ( editor ) {
		flush();
	}
}, { flush: 'post' } );

// A replaced Subject starts the pane over. Drop the harvest, or a reopened pane seeds the
// tree with relations the form no longer displays.
watch( () => props.subject, ( newSubject ) => {
	label.value = newSubject.getLabel() ?? '';
	editedSubject.value = newSubject;
} );

function buildUpdatedSubject(): Subject | null {
	if ( !subjectEditorRef.value ) {
		return null;
	}
	return props.subject
		.withLabel( storedLabel.value )
		.withStatements( subjectEditorRef.value.getSubjectData().withNonEmptyValues() );
}

function unparseableInput(): UnparseableInput | null {
	return subjectEditorRef.value?.unparseableInput() ?? null;
}

function setServerViolations( violations: readonly SubjectViolation[] ): void {
	serverViolations.value = withoutSessionOnlyViolations( violations );
}

// Complaints that are only true of the wiki as it stands, not of what this session is saving.
function withoutSessionOnlyViolations( violations: readonly SubjectViolation[] ): SubjectViolation[] {
	return withoutUnsavedTargetViolations( violations, props.unsavedTargetIds ?? [] );
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
	/* The name and where it is stored on one line, the name taking whatever the storage line
		leaves. Baseline-aligned rather than centred, because the two differ in size. */
	&__header {
		display: flex;
		align-items: baseline;
		justify-content: space-between;
		gap: @spacing-50;
		margin-bottom: @spacing-75;
	}

	/* Subordinate to the dialog's own title, which names the root Subject: a heading for
		screen readers and outline tools, sized like the body around it. */
	&__name {
		min-width: 0;
		margin: 0;
		font-size: @font-size-medium;
	}

	&__storage {
		flex-shrink: 0;
		color: @color-subtle;
		font-size: @font-size-small;
	}
}
</style>
