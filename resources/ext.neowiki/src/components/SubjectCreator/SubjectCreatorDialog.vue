<!-- eslint-disable vue/no-multiple-template-root -->
<template>
	<CdxDialog
		:open="subjectStore.subjectCreatorOpen"
		class="ext-neowiki-subject-creator-dialog"
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
				<SchemaLookup
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
			<CdxField class="ext-neowiki-subject-creator-label-field">
				<CdxTextInput
					v-model="subjectLabel"
					:placeholder="$i18n( 'neowiki-subject-creator-label-placeholder' ).text()"
					@input="handleEditorChange"
					@blur="handleEditorBlur"
				/>
				<template #label>
					{{ $i18n( 'neowiki-subject-creator-label-field' ).text() }}
				</template>
			</CdxField>

			<CdxMessage
				v-if="anchorlessViolations.length > 0"
				type="error"
			>
				<ul class="ext-neowiki-subject-editor__form-errors">
					<li
						v-for="( v, idx ) in anchorlessViolations"
						:key="idx"
					>
						{{ formatViolationMessage( v ) }}
					</li>
				</ul>
			</CdxMessage>

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
			<EditSummary
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
import { withoutRequiredViolations, type SubjectViolation } from '@/domain/SubjectViolation';
import { ValidationFailedError } from '@/persistence/ValidationFailedError';
import { CdxMessage } from '@wikimedia/codex';
import SubjectEditor from '@/components/SubjectEditor/SubjectEditor.vue';
import SchemaCreator from '@/components/SchemaCreator/SchemaCreator.vue';
import type { SchemaCreatorExposes } from '@/components/SchemaCreator/SchemaCreator.vue';
import EditSummary from '@/components/common/EditSummary.vue';
import SchemaLookup from '@/components/SubjectCreator/SchemaLookup.vue';
import CloseConfirmationDialog from '@/components/common/CloseConfirmationDialog.vue';
import SchemaAbandonmentDialog from '@/components/SubjectCreator/SchemaAbandonmentDialog.vue';
import { useSchemaPermissions } from '@/composables/useSchemaPermissions.ts';
import { useChangeDetection } from '@/composables/useChangeDetection.ts';
import { useCloseConfirmation } from '@/composables/useCloseConfirmation.ts';
import { useSubjectValidation } from '@/composables/useSubjectValidation.ts';
import { NeoWikiExtension } from '@/NeoWikiExtension.ts';
import { setPendingNotification } from '@/presentation/PendingNotification.ts';

const props = defineProps<{
	pageHasMainSubject: boolean;
}>();

const selectedSchemaOption = ref( 'existing' );
const selectedSchemaName = ref<string | null>( null );
const loadedSchema = ref<Schema | null>( null );
const subjectLabel = ref( '' );
// eslint-disable-next-line @typescript-eslint/no-explicit-any
const schemaLookupRef = ref<any | null>( null );
const schemaCreatorRef = ref<SchemaCreatorExposes | null>( null );

const draftSchema = shallowRef<Schema | null>( null );

const subjectStore = useSubjectStore();
const schemaStore = useSchemaStore();
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

interface SubjectEditorInstance {
	getSubjectData: () => StatementList;
}

const subjectEditorRef = ref<SubjectEditorInstance | null>( null );

const { violations: serverViolations, revalidate, flush, reset } = useSubjectValidation(
	async () => {
		if ( !subjectEditorRef.value || !selectedSchemaName.value ) {
			return [];
		}
		const statements = [ ...subjectEditorRef.value.getSubjectData() ].filter( ( s ) => s.hasValue() );
		try {
			const violations = await subjectStore.validateSubject(
				subjectLabel.value.trim(),
				selectedSchemaName.value,
				new StatementList( statements )
			);
			return withoutRequiredViolations( violations );
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

function formatViolationMessage( v: SubjectViolation ): string {
	return mw.message( `neowiki-field-${ v.code }`, ...( v.args as string[] ) ).text();
}

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
	subjectLabel.value = String( mw.config.get( 'wgTitle' ) ?? '' );
	markChanged();

	try {
		loadedSchema.value = await schemaStore.getOrFetchSchema( schemaName );
	} catch ( error ) {
		console.error( 'Failed to load schema:', error );
		loadedSchema.value = null;
	}
}

async function handleCreateSchema(): Promise<void> {
	if ( !schemaCreatorRef.value ) {
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
	subjectLabel.value = String( mw.config.get( 'wgTitle' ) ?? '' );
	markChanged();
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
	selectedSchemaName.value = null;
	loadedSchema.value = null;
	draftSchema.value = null;
	subjectLabel.value = '';
	selectedSchemaOption.value = 'existing';
	schemaCreatorRef.value?.reset();
	resetChanged();
}

function goBack(): void {
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

	const label = subjectLabel.value.trim();

	if ( !label ) {
		mw.notify( mw.msg( 'neowiki-subject-creator-error' ), { type: 'error' } );
		return;
	}

	if ( !subjectEditorRef.value || !selectedSchemaName.value ) {
		return;
	}

	await flush();

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
				mw.msg( 'neowiki-subject-editor-validation-failed', label ),
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
