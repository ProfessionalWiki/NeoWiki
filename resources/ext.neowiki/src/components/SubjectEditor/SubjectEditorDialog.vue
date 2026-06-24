<template>
	<div class="ext-neowiki-subject-editor-container">
		<CdxDialog
			:open="open"
			class="ext-neowiki-subject-editor-dialog"
			:title="$i18n( 'neowiki-subject-editor-title', props.subject.getLabel() ).text()"
			@update:open="onDialogUpdateOpen"
		>
			<template #header>
				<div class="cdx-dialog__header__title-group">
					<h2 class="cdx-dialog__header__title">
						{{ $i18n( 'neowiki-subject-editor-title', props.subject.getLabel() ).text() }}
					</h2>

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
			<div v-else>
				Loading schema... <!-- Or some other loading indicator -->
			</div>

			<!-- TODO: We should make this into a component-->
			<template #footer>
				<EditSummary
					help-text=""
					:save-button-label="$i18n( 'neowiki-subject-editor-save' ).text()"
					:save-disabled="!hasChanged"
					@save="handleSave"
				/>
			</template>
		</CdxDialog>

		<SchemaEditorDialog
			v-if="loadedSchema"
			:open="isSchemaEditorOpen"
			:initial-schema="loadedSchema as Schema"
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
import { ref, nextTick, computed, watch } from 'vue';
import SubjectEditor from '@/components/SubjectEditor/SubjectEditor.vue';
import EditSummary from '@/components/common/EditSummary.vue';
import I18nSlot from '@/components/common/I18nSlot.vue';
import { CdxButton, CdxDialog, CdxIcon, CdxMessage } from '@wikimedia/codex';
import { cdxIconClose } from '@wikimedia/codex-icons';
import { StatementList } from '@/domain/StatementList.ts';
import { Subject } from '@/domain/Subject.ts';
import { useSchemaStore } from '@/stores/SchemaStore.ts';
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
import { withoutRequiredViolations, type SubjectViolation } from '@/domain/SubjectViolation';

type SubjectSaveHandler = ( subject: Subject, comment: string ) => Promise<void>;

const props = defineProps<{
	subject: Subject;
	onSave: SubjectSaveHandler;
	onSaveSchema: SchemaSaveHandler;
	open: boolean;
}>();

const emit = defineEmits( [ 'update:open' ] );

const schemaStore = useSchemaStore();
const subjectStore = useSubjectStore();

interface SubjectEditorInstance {
	getSubjectData: () => StatementList;
}

const isSchemaEditorOpen = ref( false );
const subjectEditorRef = ref<SubjectEditorInstance | null>( null );
const loadedSchema = ref<Schema | null>( null );
const { canEditSchema, checkEditPermission } = useSchemaPermissions();
const { hasChanged, markChanged, resetChanged } = useChangeDetection();

const { violations: serverViolations, revalidate, flush, reset } = useSubjectValidation(
	async () => {
		if ( !subjectEditorRef.value ) {
			return [];
		}
		const statements = [ ...subjectEditorRef.value.getSubjectData() ].filter( ( s ) => s.hasValue() );
		try {
			const violations = await subjectStore.validateSubjectUpdate(
				props.subject.getId(),
				props.subject.getLabel(),
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
	// schema materialises from its property definitions (so empty/missing
	// properties still get a field). Anchor against THAT list, not the
	// raw subject — otherwise a violation on a missing-but-rendered field
	// would be wrongly banner-routed even though the field is on screen.
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
		resetChanged();
		reset();
	}
} );

watch( () => props.subject, async ( newSubject ) => {
	if ( newSubject ) {
		await checkEditPermission( newSubject.getSchemaName() );
		await loadSchema();
	}
}, { immediate: true } );

async function loadSchema(): Promise<void> {
	try {
		loadedSchema.value = await schemaStore.getOrFetchSchema( props.subject.getSchemaName() );
	} catch ( error ) {
		console.error( 'Failed to load schema:', error );
		mw.notify(
			`Failed to load schema ${ props.subject.getSchemaName() }: ${ error instanceof Error ? error.message : String( error ) }`,
			{ type: 'error' }
		);
	}
}

const statements = computed( (): StatementList | null =>
	loadedSchema.value?.statementsFrom( props.subject.getStatements() ) ?? null
);

const handleSave = async ( summary: string ): Promise<void> => {
	await nextTick();

	if ( !subjectEditorRef.value ) {
		return;
	}

	await flush();

	const updatedStatements = subjectEditorRef.value.getSubjectData();
	// Filter out statements that don't have a value set.
	const statementsToSave = [ ...updatedStatements ].filter( ( statement ) => statement.hasValue() );
	const updatedSubject = props.subject.withStatements( new StatementList( statementsToSave ) );
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
	loadedSchema.value = schema;
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
	}
}
</style>
