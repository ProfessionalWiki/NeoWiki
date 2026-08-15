<template>
	<div class="ext-neowiki-subject-editor-container">
		<CdxDialog
			:open="open"
			class="ext-neowiki-ui ext-neowiki-subject-editor-dialog"
			:title="$i18n( 'neowiki-subject-editor-title', props.subject.getLabel() ).text()"
			@update:open="onDialogUpdateOpen"
		>
			<template #header>
				<div class="cdx-dialog__header__title-group">
					<div class="cdx-dialog__header__title ext-neowiki-subject-editor-dialog__title">
						<EditableText
							:model-value="subjectLabel"
							:edit-button-label="$i18n( 'neowiki-subject-editor-rename' ).text()"
							:input-aria-label="$i18n( 'neowiki-subject-editor-label-field' ).text()"
							:placeholder="$i18n( 'neowiki-subject-editor-label-field' ).text()"
							:status="labelViolation ? 'error' : 'default'"
							:required="true"
							@update:model-value="onLabelEdited"
						/>
					</div>

					<p
						v-if="labelViolation"
						class="ext-neowiki-subject-editor-dialog__label-error"
						role="alert"
					>
						{{ formatViolationMessage( labelViolation ) }}
					</p>

					<p class="cdx-dialog__header__subtitle">
						<I18nSlot
							message-key="neowiki-schema-label"
							class="ext-neowiki-subject-editor-dialog-schema"
							text-class="ext-neowiki-subject-editor-dialog-schema__label"
						>
							<a
								v-if="canEditSchema"
								class="ext-neowiki-subject-editor-dialog-schema__link"
								href="#"
								@click.prevent="isSchemaEditorOpen = true"
							>
								{{ props.subject.getSchemaName() }}
							</a>
							<span
								v-else
								class="ext-neowiki-subject-editor-dialog-schema__name"
							>
								{{ props.subject.getSchemaName() }}
							</span>
						</I18nSlot>
					</p>
				</div>

				<CdxButton
					class="cdx-dialog__header__close-button"
					weight="quiet"
					type="button"
					:aria-label="$i18n( 'cdx-dialog-close-button-label' ).text()"
					@click="requestClose"
				>
					<CdxIcon :icon="cdxIconClose" />
				</CdxButton>
			</template>

			<SubjectViolationBanners :violations="anchorlessViolations" />

			<SubjectEditor
				ref="subjectEditorRef"
				:statements="statements"
				:schema="currentSchema"
				:server-violations="serverViolations"
				@change="handleEditorChange"
				@focusout="handleEditorBlur"
				@clear-server-violation="handleClearViolation"
			/>

			<!-- TODO: We should make this into a component-->
			<template #footer>
				<SummaryAction
					help-text=""
					:save-button-label="$i18n( 'neowiki-subject-editor-save' ).text()"
					:save-disabled="!hasChanged"
					@save="handleSave"
				/>
			</template>
		</CdxDialog>

		<SchemaEditorDialog
			:open="isSchemaEditorOpen"
			:initial-schema="currentSchema"
			:on-save="props.onSaveSchema"
			@saved="onSchemaSaved"
			@update:open="isSchemaEditorOpen = $event"
		/>

		<CloseConfirmationDialog
			:open="confirmationOpen"
			@discard="confirmClose"
			@keep-editing="cancelClose"
		/>
	</div>
</template>

<script setup lang="ts">
import { ref, shallowRef, nextTick, computed, watch } from 'vue';
import SubjectEditor from '@/components/SubjectEditor/SubjectEditor.vue';
import type { SubjectEditorExposes } from '@/components/SubjectEditor/SubjectEditor.vue';
import SubjectViolationBanners from '@/components/common/SubjectViolationBanners.vue';
import SummaryAction from '@/components/common/SummaryAction.vue';
import I18nSlot from '@/components/common/I18nSlot.vue';
import { CdxButton, CdxDialog, CdxIcon } from '@wikimedia/codex';
import EditableText from '@/components/common/EditableText.vue';
import { cdxIconClose } from '@wikimedia/codex-icons';
import { StatementList } from '@/domain/StatementList.ts';
import { Subject } from '@/domain/Subject.ts';
import { useSubjectStore } from '@/stores/SubjectStore.ts';
import { Schema } from '@/domain/Schema.ts';
import SchemaEditorDialog from '@/components/SchemaEditor/SchemaEditorDialog.vue';
import type { SchemaSaveHandler } from '@/components/SchemaEditor/SchemaEditorDialog.vue';
import CloseConfirmationDialog from '@/components/common/CloseConfirmationDialog.vue';
import { useSchemaPermissions } from '@/composables/useSchemaPermissions.ts';
import { useChangeDetection } from '@/composables/useChangeDetection.ts';
import { useCloseConfirmation } from '@/composables/useCloseConfirmation.ts';
import { useSubjectValidation } from '@/composables/useSubjectValidation.ts';
import { NeoWikiExtension } from '@/NeoWikiExtension.ts';
import { ValidationFailedError } from '@/persistence/ValidationFailedError';
import type { SubjectViolation } from '@/domain/SubjectViolation';

type SubjectSaveHandler = ( subject: Subject, comment: string ) => Promise<void>;

const props = defineProps<{
	subject: Subject;
	schema: Schema;
	onSave: SubjectSaveHandler;
	onSaveSchema: SchemaSaveHandler;
	open: boolean;
}>();

const emit = defineEmits( [ 'update:open' ] );

const subjectStore = useSubjectStore();

const isSchemaEditorOpen = ref( false );
const subjectEditorRef = ref<SubjectEditorExposes | null>( null );
// Initialized by the immediate props.subject watch below.
const subjectLabel = ref( '' );
// Mirrors the prop so a schema saved through the nested SchemaEditorDialog takes effect here
// without waiting for the host to pass a new one down.
const currentSchema = shallowRef<Schema>( props.schema );
const { canEditSchema, checkEditPermission } = useSchemaPermissions();
const { hasChanged, markChanged, resetChanged } = useChangeDetection();

const { violations: serverViolations, revalidate, flush, reset } = useSubjectValidation(
	async () => {
		if ( !subjectEditorRef.value ) {
			return [];
		}
		const statements = [ ...subjectEditorRef.value.getSubjectData() ].filter( ( s ) => s.hasValue() );
		try {
			// Unlike subject creation, editing an existing subject surfaces
			// 'required' live: an empty required field here is a real gap, not a
			// field the user is still on their way to filling in.
			return await subjectStore.validateSubjectUpdate(
				props.subject.getId(),
				subjectLabel.value.trim(),
				new StatementList( statements )
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

// EditableText commits once per edit, so a commit is both the change and the
// end of the interaction: validate immediately rather than waiting for a blur.
function onLabelEdited( value: string ): void {
	subjectLabel.value = value;
	handleEditorChange();
	handleEditorBlur();
}

const labelViolation = computed<SubjectViolation | null>( () =>
	serverViolations.value.find( ( v ) => v.code === 'label-required' ) ?? null
);

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
		// label-required renders at the label itself, in the dialog header.
		if ( v.code === 'label-required' ) {
			return false;
		}
		if ( v.propertyName === null ) {
			return true;
		}
		return !renderedPropertyNames.has( v.propertyName );
	} );
} );

// The label violation renders in the header rather than in a banner, so it is
// formatted here. It is always an error, so it carries no severity styling.
function formatViolationMessage( v: SubjectViolation ): string {
	return mw.message( `neowiki-field-${ v.code }`, ...( v.args as string[] ) ).text();
}

function handleClearViolation( payload: { propertyName: string; valuePartIndex: number | null } ): void {
	serverViolations.value = serverViolations.value.filter(
		( v ) => !( v.propertyName === payload.propertyName && v.valuePartIndex === payload.valuePartIndex )
	);
}

function close(): void {
	emit( 'update:open', false );
}

const { confirmationOpen, requestClose, confirmClose, cancelClose } = useCloseConfirmation( hasChanged, close );

function onDialogUpdateOpen( value: boolean ): void {
	if ( !value ) {
		requestClose();
	}
}

watch( () => props.open, ( isOpen ) => {
	if ( isOpen ) {
		subjectLabel.value = props.subject.getLabel();
		resetChanged();
		reset();
	}
} );

// Existing subjects are expected to be complete, so validate as soon as the
// editor mounts for an open dialog: pre-existing violations (e.g. a now-empty
// required field) surface immediately, without the user having to touch a field
// first. (Subject creation deliberately does not do this — see the creator.)
watch( subjectEditorRef, ( editor ) => {
	if ( editor && props.open ) {
		flush();
	}
} );

watch( () => props.subject, ( newSubject ) => {
	subjectLabel.value = newSubject.getLabel();
	checkEditPermission( newSubject.getSchemaName() );
}, { immediate: true } );

watch( () => props.schema, ( newSchema ) => {
	currentSchema.value = newSchema;
} );

const statements = computed( (): StatementList =>
	currentSchema.value.statementsFrom( props.subject.getStatements() )
);

const handleSave = async ( summary: string ): Promise<void> => {
	await nextTick();

	const label = subjectLabel.value.trim();

	if ( !label ) {
		mw.notify( mw.msg( 'neowiki-field-label-required' ), { type: 'error' } );
		return;
	}

	if ( !subjectEditorRef.value ) {
		return;
	}

	// Saving now would silently drop the unparseable text the user can still see.
	if ( subjectEditorRef.value.hasUnparseableInput() ) {
		mw.notify( mw.msg( 'neowiki-field-invalid-number' ), { type: 'error' } );
		return;
	}

	await flush();

	const updatedStatements = subjectEditorRef.value.getSubjectData();
	// Filter out statements that don't have a value set.
	const statementsToSave = [ ...updatedStatements ].filter( ( statement ) => statement.hasValue() );
	const updatedSubject = props.subject
		.withLabel( label )
		.withStatements( new StatementList( statementsToSave ) );
	const subjectName = updatedSubject.getLabel();
	const editSummary = summary || mw.msg( 'neowiki-subject-editor-summary-default' );

	try {
		await props.onSave( updatedSubject, editSummary );
		mw.notify( mw.msg( 'neowiki-subject-editor-success', subjectName ), { type: 'success' } );
		close();
	} catch ( error ) {
		if ( error instanceof ValidationFailedError ) {
			serverViolations.value = [ ...error.violations ];
			mw.notify(
				mw.msg( 'neowiki-subject-editor-validation-failed', subjectName ),
				{ type: 'error' }
			);
			return;
		}
		mw.notify(
			error instanceof Error ? error.message : String( error ),
			{
				title: mw.msg( 'neowiki-subject-editor-error', subjectName ),
				type: 'error'
			}
		);
	}
};

const onSchemaSaved = ( schema: Schema ): void => {
	currentSchema.value = schema;
};

defineExpose( { hasChanged } );

</script>

<style lang="less">
@import ( reference ) '@wikimedia/codex-design-tokens/theme-wikimedia-ui.less';
@import ( reference ) '@wikimedia/codex/mixins/link.less';

.ext-neowiki-subject-editor-dialog {
	&-schema {
		&__link {
			.cdx-mixin-link-base();
		}

		&__name {
			color: @color-base;
		}
	}

	/* Replicate the Codex default dialog header styles */
	.cdx-dialog__header {
		display: flex;
		align-items: baseline;
		justify-content: flex-end;
		box-sizing: @box-sizing-base;
		width: @size-full;

		/* Secondary to the title, like the statement-field labels below.
			Nested under the header to out-rank Codex's runtime-injected
			two-class subtitle rule. */
		.cdx-dialog__header__subtitle {
			font-size: @font-size-small;
		}
	}

	/* The title row's edit button already provides vertical air; Codex's
		default gap on top of it reads too wide. */
	.cdx-dialog__header__title-group {
		gap: 0;
	}

	&__title {
		display: flex;
		min-width: 0;
	}

	&__label-error {
		color: @color-error;
		font-size: @font-size-small;
		margin: @spacing-25 0 0;
	}
}
</style>
