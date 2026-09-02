<!-- eslint-disable vue/no-multiple-template-root -->
<template>
	<CdxDialog
		:open="subjectStore.subjectCreatorOpen"
		class="ext-neowiki-ui ext-neowiki-subject-creator-dialog cdx-dialog--dividers"
		:class="{ 'ext-neowiki-subject-creator-dialog--wide': selectedSchemaOption === 'new' && !selectedSchemaName }"
		:title="$i18n( 'neowiki-subject-creator-title' ).text()"
		@update:open="onDialogUpdateOpen"
	>
		<template #header>
			<div class="ext-neowiki-subject-creator-dialog__header">
				<CdxButton
					v-if="selectedSchemaName"
					class="ext-neowiki-subject-creator-back-button"
					weight="quiet"
					type="button"
					:aria-label="$i18n( 'neowiki-subject-creator-back' ).text()"
					@click="goBack"
				>
					<CdxIcon :icon="cdxIconArrowPrevious" />
				</CdxButton>

				<div class="ext-neowiki-subject-creator-dialog__header__title-group">
					<h2 class="cdx-dialog__header__title">
						{{ $i18n( 'neowiki-subject-creator-title' ).text() }}
					</h2>

					<p
						v-if="headerSubtitle"
						class="cdx-dialog__header__subtitle"
					>
						{{ headerSubtitle }}
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
			</div>
		</template>

		<EditNoticeList :notices="notices" />

		<template v-if="!selectedSchemaName">
			<p>
				{{ $i18n( 'neowiki-subject-creator-schema-title' ).text() }}
			</p>

			<CdxToggleButtonGroup
				v-if="canCreateSchemas"
				v-model="selectedSchemaOption"
				class="ext-neowiki-subject-creator-schema-options"
				:buttons="toggleButtons"
			/>

			<div
				v-if="selectedSchemaOption === 'existing'"
				class="ext-neowiki-subject-creator-existing"
			>
				<SchemaPicker
					ref="schemaLookupRef"
					@select="onSchemaSelected"
				/>
			</div>

			<div
				v-if="selectedSchemaOption === 'new'"
				class="ext-neowiki-subject-creator-new"
			>
				<SchemaCreator
					ref="schemaCreatorRef"
					:initial-schema="draftSchema ?? undefined"
					@change="markChanged"
				/>
			</div>
		</template>

		<template v-if="selectedSchemaName">
			<CdxField class="ext-neowiki-subject-creator-label-field" :optional="true">
				<CdxTextInput
					v-model="subjectLabel"
					:placeholder="placeholderLabel"
					@input="handleEditorChange"
					@blur="handleEditorBlur"
				/>
				<template #label>
					{{ $i18n( 'neowiki-subject-creator-label-field' ).text() }}
				</template>
			</CdxField>

			<SubjectViolationBanners :violations="anchorlessViolations" />

			<SubjectEditor
				v-if="statements"
				ref="subjectEditorRef"
				:statements="statements"
				:schema="loadedSchema as Schema"
				:server-violations="serverViolations"
				@change="handleEditorChange"
				@focusout="handleEditorBlur"
				@clear-server-violation="handleClearViolation"
			/>
		</template>

		<template
			v-if="selectedSchemaOption === 'new' && !selectedSchemaName"
			#footer
		>
			<div class="ext-neowiki-subject-creator-continue">
				<CdxButton
					action="progressive"
					weight="primary"
					:disabled="!hasChanged"
					@click="handleCreateSchema"
				>
					{{ $i18n( 'neowiki-subject-creator-continue' ).text() }}
					<CdxIcon :icon="cdxIconArrowNext" />
				</CdxButton>
			</div>
		</template>
		<template
			v-else-if="selectedSchemaName"
			#footer
		>
			<SummaryAction
				help-text=""
				:save-button-label="$i18n( 'neowiki-subject-creator-save' ).text()"
				:save-disabled="!hasChanged"
				@save="handleSave"
			/>
		</template>
	</CdxDialog>

	<CloseConfirmationDialog
		:open="confirmationOpen"
		@discard="confirmClose"
		@keep-editing="cancelClose"
	/>

	<SchemaAbandonmentDialog
		:open="schemaAbandonmentOpen"
		@abandon="abandonAll"
		@save-schema="saveSchemaAndClose"
		@keep-editing="cancelSchemaAbandonment"
	/>
</template>

<script setup lang="ts">
import { ref, shallowRef, computed, watch, nextTick, onMounted } from 'vue';
import { CdxButton, CdxDialog, CdxField, CdxIcon, CdxTextInput, CdxToggleButtonGroup } from '@wikimedia/codex';
import { cdxIconAdd, cdxIconArrowNext, cdxIconArrowPrevious, cdxIconClose, cdxIconSearch } from '@wikimedia/codex-icons';
import type { ButtonGroupItem } from '@wikimedia/codex';
import { useSubjectStore } from '@/stores/SubjectStore.ts';
import { useSchemaStore } from '@/stores/SchemaStore.ts';
import { Schema } from '@/domain/Schema.ts';
import { StatementList } from '@/domain/StatementList.ts';
import { enteredSubjectLabel } from '@/domain/enteredSubjectLabel.ts';
import { placeholderSubjectLabel } from '@/domain/placeholderSubjectLabel.ts';
import { withoutMissingValueViolations, type SubjectViolation } from '@/domain/SubjectViolation';
import { ValidationFailedError } from '@/persistence/ValidationFailedError';
import SubjectEditor from '@/components/SubjectEditor/SubjectEditor.vue';
import type { SubjectEditorExposes } from '@/components/SubjectEditor/SubjectEditor.vue';
import SubjectViolationBanners from '@/components/common/SubjectViolationBanners.vue';
import SchemaCreator from '@/components/SchemaCreator/SchemaCreator.vue';
import type { SchemaCreatorExposes } from '@/components/SchemaCreator/SchemaCreator.vue';
import SummaryAction from '@/components/common/SummaryAction.vue';
import SchemaPicker from '@/components/common/SchemaPicker.vue';
import CloseConfirmationDialog from '@/components/common/CloseConfirmationDialog.vue';
import SchemaAbandonmentDialog from '@/components/SubjectCreator/SchemaAbandonmentDialog.vue';
import { useSchemaPermissions } from '@/composables/useSchemaPermissions.ts';
import { useChangeDetection } from '@/composables/useChangeDetection.ts';
import { useCloseConfirmation } from '@/composables/useCloseConfirmation.ts';
import { useSubjectValidation } from '@/composables/useSubjectValidation.ts';
import { NeoWikiExtension } from '@/NeoWikiExtension.ts';
import { NeoWikiServices } from '@/NeoWikiServices.ts';
import { setPendingNotification } from '@/presentation/PendingNotification.ts';
import EditNoticeList from '@/components/common/EditNoticeList.vue';
import { useEditNotices } from '@/composables/useEditNotices.ts';

const props = defineProps<{
	pageHasMainSubject: boolean;
}>();

const selectedSchemaOption = ref( 'existing' );
const selectedSchemaName = ref<string | null>( null );
const { notices, loadNotices } = useEditNotices( () => NeoWikiExtension.getInstance().getEditNoticeRepository() );

const loadedSchema = ref<Schema | null>( null );
const subjectLabel = ref( '' );
// eslint-disable-next-line @typescript-eslint/no-explicit-any
const schemaLookupRef = ref<any | null>( null );
const schemaCreatorRef = ref<SchemaCreatorExposes | null>( null );

const draftSchema = shallowRef<Schema | null>( null );

// Guards loadedSchema against a stale schema fetch: picking a different schema, and leaving the
// picked one (going back, or the dialog closing), both invalidate an in-flight response.
let requestSequence = 0;

const subjectStore = useSubjectStore();
// Reloaded when the Schema is chosen too, since Schema-scoped notices cannot apply before there
// is a Schema to scope them to.
watch(
	() => [ subjectStore.subjectCreatorOpen, selectedSchemaName.value ],
	() => {
		if ( subjectStore.subjectCreatorOpen ) {
			loadNotices( Number( mw.config.get( 'wgArticleId' ) ), selectedSchemaName.value ?? undefined );
		}
	}
);
const schemaStore = useSchemaStore();
const schemaRepo = NeoWikiServices.getSchemaRepository();
const { canCreateSchemas, checkCreatePermission } = useSchemaPermissions();
const { hasChanged, markChanged, resetChanged } = useChangeDetection();

function close(): void {
	subjectStore.closeSubjectCreator();
}

const hasDraftSchema = computed( () => draftSchema.value !== null );

const {
	confirmationOpen,
	alternateConfirmationOpen: schemaAbandonmentOpen,
	requestClose,
	confirmClose,
	cancelClose,
	confirmAlternateClose: abandonAll,
	cancelAlternateClose: cancelSchemaAbandonment
} = useCloseConfirmation( hasChanged, close, hasDraftSchema );

async function saveSchemaAndClose(): Promise<void> {
	if ( draftSchema.value ) {
		try {
			await schemaStore.saveSchema( draftSchema.value );
			mw.notify( mw.msg( 'neowiki-subject-creator-schema-created' ), { type: 'success' } );
		} catch ( error ) {
			mw.notify(
				error instanceof Error ? error.message : String( error ),
				{
					title: mw.msg( 'neowiki-subject-creator-error' ),
					type: 'error'
				}
			);
			cancelSchemaAbandonment();
			return;
		}
	}
	abandonAll();
}

function onDialogUpdateOpen( value: boolean ): void {
	if ( !value ) {
		requestClose();
	}
}

const subjectEditorRef = ref<SubjectEditorExposes | null>( null );

const { violations: serverViolations, revalidate, flush, reset } = useSubjectValidation(
	async () => {
		// A draft (unsaved) schema does not exist server-side yet, so a dry-run
		// against it would only 404. Skip until the schema is saved.
		if ( !subjectEditorRef.value || !selectedSchemaName.value || hasDraftSchema.value ) {
			return [];
		}
		const statements = [ ...subjectEditorRef.value.getSubjectData() ].filter( ( s ) => s.hasValue() );
		try {
			const violations = await subjectStore.validateSubject(
				enteredLabel(),
				selectedSchemaName.value,
				new StatementList( statements )
			);
			return withoutMissingValueViolations( violations );
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
	// schema materialises from its property definitions. Anchor against that
	// list — a violation referring to a missing-but-rendered field stays on
	// the field, not the banner.
	const renderedPropertyNames = new Set(
		[ ...( statements.value ?? [] ) ].map( ( s ) => s.propertyName.toString() )
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

const headerSubtitle = computed( (): string | null => {
	if ( selectedSchemaOption.value === 'new' && !selectedSchemaName.value ) {
		return mw.msg( 'neowiki-subject-creator-creating-schema' );
	}

	if ( selectedSchemaName.value ) {
		return mw.msg( 'neowiki-schema-label', selectedSchemaName.value );
	}

	return null;
} );

const toggleButtons = [
	{
		value: 'existing',
		label: mw.msg( 'neowiki-subject-creator-existing-schema' ),
		icon: cdxIconSearch
	},
	{
		value: 'new',
		label: mw.msg( 'neowiki-subject-creator-new-schema' ),
		icon: cdxIconAdd
	}
] as ButtonGroupItem[];

onMounted( async () => {
	await checkCreatePermission();
} );

watch( selectedSchemaOption, ( newValue: string ) => {
	focusInitialInput( newValue );
} );

async function focusInitialInput( schemaOption: string ): Promise<void> {
	await nextTick();
	if ( schemaOption === 'existing' && schemaLookupRef.value ) {
		schemaLookupRef.value.focus();
	} else if ( schemaOption === 'new' && schemaCreatorRef.value ) {
		schemaCreatorRef.value.focus();
	}
}

async function onSchemaSelected( schemaName: string ): Promise<void> {
	if ( !schemaName ) {
		return;
	}

	selectedSchemaName.value = schemaName;
	markChanged();

	const currentSequence = ++requestSequence;

	try {
		const schema = await schemaRepo.getSchema( schemaName );

		if ( currentSequence !== requestSequence ) {
			return;
		}

		loadedSchema.value = schema;
	} catch ( error ) {
		if ( currentSequence !== requestSequence ) {
			return;
		}

		console.error( 'Failed to load schema:', error );
		loadedSchema.value = null;
	}
}

async function handleCreateSchema(): Promise<void> {
	if ( !schemaCreatorRef.value ) {
		return;
	}

	const unparseable = schemaCreatorRef.value.unparseableInput();

	// Continuing now would freeze a draft schema with the unparseable text dropped.
	if ( unparseable !== null ) {
		mw.notify( unparseable.message, { title: unparseable.propertyName, type: 'error' } );
		return;
	}

	const valid = await schemaCreatorRef.value.validate();

	if ( !valid ) {
		return;
	}

	const schema = schemaCreatorRef.value.getSchema();

	if ( !schema ) {
		return;
	}

	draftSchema.value = schema;
	selectedSchemaName.value = schema.getName();
	loadedSchema.value = schema;
	markChanged();
}

// The prefixed title, because that is what the server falls back to. wgTitle drops the namespace,
// so it would preview "Onboarding" for a Subject that goes on to display "Handbook:Onboarding".
function pageName(): string {
	return String( mw.config.get( 'wgPageName' ) ?? '' ).replace( /_/g, ' ' );
}

// Shown greyed in the label field and sent as no label at all when the user leaves it be. It
// is what the Subject will display, so the field previews the outcome rather than pre-filling it.
const placeholderLabel = computed( (): string =>
	selectedSchemaName.value === null ?
		'' :
		placeholderSubjectLabel( props.pageHasMainSubject, pageName(), selectedSchemaName.value )
);

function enteredLabel(): string | null {
	return enteredSubjectLabel( subjectLabel.value );
}

const statements = computed( (): StatementList | null =>
	loadedSchema.value?.blankStatements() ?? null
);

watch( () => subjectStore.subjectCreatorOpen, async ( isOpen ) => {
	if ( isOpen ) {
		reset();
		await nextTick();
		focusInitialInput( selectedSchemaOption.value );
	} else {
		resetForm();
	}
} );

function resetForm(): void {
	requestSequence++;
	selectedSchemaName.value = null;
	loadedSchema.value = null;
	draftSchema.value = null;
	subjectLabel.value = '';
	selectedSchemaOption.value = 'existing';
	schemaCreatorRef.value?.reset();
	resetChanged();
}

function goBack(): void {
	requestSequence++;
	selectedSchemaName.value = null;
	loadedSchema.value = null;
	subjectLabel.value = '';

	if ( draftSchema.value ) {
		selectedSchemaOption.value = 'new';
	} else {
		resetChanged();
	}
}

const handleSave = async ( summary: string ): Promise<void> => {
	await nextTick();

	if ( !subjectEditorRef.value || !selectedSchemaName.value ) {
		return;
	}

	const label = enteredLabel();

	await flush();

	const unparseable = subjectEditorRef.value.unparseableInput();

	// Saving now would silently drop the text the user can still see. Held after
	// the dry-run so the field's own complaint and the server's findings on the
	// other fields surface in one pass rather than one round at a time, and above
	// the writes below so no draft schema is created for a subject that is not saved.
	if ( unparseable !== null ) {
		mw.notify( unparseable.message, { title: unparseable.propertyName, type: 'error' } );
		return;
	}

	try {
		if ( draftSchema.value ) {
			await schemaStore.saveSchema( draftSchema.value, summary || undefined );
			draftSchema.value = null;
		}

		const updatedStatements = subjectEditorRef.value.getSubjectData();
		const statementsToSave = [ ...updatedStatements ].filter( ( statement ) => statement.hasValue() );

		const pageId = mw.config.get( 'wgArticleId' );
		const statementList = new StatementList( statementsToSave );
		const commentOrUndefined = summary || undefined;

		if ( props.pageHasMainSubject ) {
			await subjectStore.createChildSubject(
				pageId,
				label,
				selectedSchemaName.value,
				statementList,
				commentOrUndefined
			);
		} else {
			await subjectStore.createMainSubject(
				pageId,
				label,
				selectedSchemaName.value,
				statementList,
				commentOrUndefined
			);
		}
		setPendingNotification( 'neowiki-subject-creator-success' );
		window.location.reload();
	} catch ( error ) {
		if ( error instanceof ValidationFailedError ) {
			serverViolations.value = [ ...error.violations ];
			mw.notify(
				mw.msg( 'neowiki-subject-editor-validation-failed', label ?? placeholderLabel.value ),
				{ type: 'error' }
			);
			return;
		}
		mw.notify(
			error instanceof Error ? error.message : String( error ),
			{
				title: mw.msg( 'neowiki-subject-creator-error' ),
				type: 'error'
			}
		);
	}
};

defineExpose( { hasChanged } );
</script>

<style lang="less">
@import ( reference ) '@wikimedia/codex-design-tokens/theme-wikimedia-ui.less';

.ext-neowiki-subject-creator {
	&-dialog {
		.cdx-dialog {
			/* Replicate the Codex default dialog header styles */
			.cdx-dialog__header {
				display: flex;
				align-items: baseline;
				justify-content: flex-end;
				box-sizing: @box-sizing-base;
				width: @size-full;
			}
		}

		&__header {
			display: flex;
			align-items: center;
			width: @size-full;
			column-gap: @spacing-75;

			&__title-group {
				display: flex;
				flex-grow: 1;
				flex-direction: column;
			}
		}
	}

	&-back-button.cdx-button {
		margin-left: -@spacing-50;
		flex-shrink: 0;
	}

	&-dialog--wide.cdx-dialog {
		max-width: @size-5600;
	}

	&-schema-options.cdx-toggle-button-group {
		margin-bottom: @spacing-150;
		width: inherit;
		display: flex;
		flex-wrap: wrap;

		.cdx-toggle-button {
			flex-grow: 1;
		}
	}

	&-label-field {
		margin-top: @spacing-100;
	}

	&-continue {
		display: flex;

		.cdx-button {
			flex-grow: 1;
			max-width: none;
		}
	}

	&-new {
		.ext-neowiki-schema-creator {
			margin-inline: -@spacing-100;

			@media ( min-width: @min-width-breakpoint-desktop ) {
				margin-inline: -@spacing-150;
			}
		}
	}
}
</style>
