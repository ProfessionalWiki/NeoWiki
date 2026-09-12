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

		<EditNoticeList :notices="shownNotices" />

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
			<CdxField
				v-if="pageChoice !== null"
				class="ext-neowiki-subject-creator-page-field"
				:is-fieldset="true"
				:status="pageFieldStatus"
				:messages="pageFieldMessages"
			>
				<template #label>
					{{ $i18n( 'neowiki-subject-creator-page-field' ).text() }}
				</template>

				<CdxRadio
					v-for="option in pageOptions"
					:key="option.value"
					v-model="pageChoice"
					:input-value="option.value"
					:disabled="saving"
					name="ext-neowiki-subject-creator-page-choice"
					:inline="true"
				>
					{{ option.label }}
				</CdxRadio>

				<PagePicker
					v-if="pageChoice === 'anotherPage'"
					class="ext-neowiki-subject-creator-page-picker"
					:existing-pages-only="true"
					:disabled="saving"
					:aria-label="$i18n( 'neowiki-subject-creator-page-another' ).text()"
					@update:selected="onPageSelected"
				/>
			</CdxField>

			<p
				v-if="pickedPageTitle !== null"
				class="ext-neowiki-subject-creator-page-note"
			>
				<I18nSlot
					v-if="chosenPageMainSubjectName !== null"
					message-key="neowiki-subject-creator-page-has-main-subject"
				>
					<strong>{{ chosenPageMainSubjectName }}</strong>
				</I18nSlot>
				<template v-else>
					{{ $i18n( 'neowiki-subject-creator-page-picked', pickedPageTitle ).text() }}
				</template>
			</p>

			<CdxField
				class="ext-neowiki-subject-creator-label-field"
				:optional="true"
			>
				<CdxTextInput
					v-model="subjectLabel"
					:placeholder="placeholderLabel"
					@input="handleLabelInput"
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
				:save-disabled="!hasChanged || !pageChosen || saving"
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
import { CdxButton, CdxDialog, CdxField, CdxIcon, CdxRadio, CdxTextInput, CdxToggleButtonGroup } from '@wikimedia/codex';
import { cdxIconAdd, cdxIconArrowNext, cdxIconArrowPrevious, cdxIconClose, cdxIconSearch } from '@wikimedia/codex-icons';
import type { ButtonGroupItem, ValidationMessages, ValidationStatusType } from '@wikimedia/codex';
import { useSubjectStore } from '@/stores/SubjectStore.ts';
import { useSchemaStore } from '@/stores/SchemaStore.ts';
import { Schema } from '@/domain/Schema.ts';
import { StatementList } from '@/domain/StatementList.ts';
import { enteredSubjectLabel } from '@/domain/enteredSubjectLabel.ts';
import { newSubjectNamePreview, subjectDisplayName } from '@/presentation/subjectDisplayName.ts';
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
import PagePicker from '@/components/common/PagePicker.vue';
import I18nSlot from '@/components/common/I18nSlot.vue';
import type { PageChoice } from '@/components/common/PageChoice.ts';
import { PageTitleTakenError } from '@/persistence/PageTitleTakenError.ts';
import SchemaAbandonmentDialog from '@/components/SubjectCreator/SchemaAbandonmentDialog.vue';
import { useSchemaPermissions } from '@/composables/useSchemaPermissions.ts';
import { useSubjectPermissions } from '@/composables/useSubjectPermissions.ts';
import { useChangeDetection } from '@/composables/useChangeDetection.ts';
import { useCloseConfirmation } from '@/composables/useCloseConfirmation.ts';
import { useSubjectValidation } from '@/composables/useSubjectValidation.ts';
import { NeoWikiExtension } from '@/NeoWikiExtension.ts';
import { NeoWikiServices } from '@/NeoWikiServices.ts';
import { setPendingNotification } from '@/presentation/PendingNotification.ts';
import EditNoticeList from '@/components/common/EditNoticeList.vue';
import { useEditNotices } from '@/composables/useEditNotices.ts';

const props = defineProps<{
	/**
	 * The page the dialog was opened on, which the Subject can go on. Null where it was opened on
	 * no page of its own - Special:CreateSubject, a Schema page - so that "this page" is not among
	 * the pages offered.
	 */
	hostPage: { hasMainSubject: boolean } | null;
	initialSchemaName?: string;
}>();

/** Which page the Subject being created goes on. */
type SubjectPageChoice = 'thisPage' | 'anotherPage' | 'newPage';

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

const chosenPage = ref<PageChoice | null>( null );
const chosenPageRead = ref( false );
const chosenPageHasMainSubject = ref( false );
const chosenPageMainSubjectName = ref<string | null>( null );
const chosenPageSubjectIds = ref<string[]>( [] );
const titleTakenError = ref<string | null>( null );
const pageReadError = ref<string | null>( null );

const { canCreateSubjectPage, checkCreateSubjectPagePermission } = useSubjectPermissions();

// "This page" needs a page the dialog was opened on; a new page needs the right to make one.
const pageOptions = computed( (): { value: SubjectPageChoice; label: string }[] => {
	const options: { value: SubjectPageChoice; label: string }[] = [];

	if ( props.hostPage !== null ) {
		options.push( { value: 'thisPage', label: mw.msg( 'neowiki-subject-creator-page-this' ) } );
	}

	options.push( { value: 'anotherPage', label: mw.msg( 'neowiki-subject-creator-page-another' ) } );

	if ( canCreateSubjectPage.value ) {
		options.push( { value: 'newPage', label: mw.msg( 'neowiki-subject-creator-page-new' ) } );
	}

	return options;
} );

// The page being viewed where there is one, and a page of the Subject's own where there is not.
function defaultPageChoice(): SubjectPageChoice {
	if ( props.hostPage !== null ) {
		return 'thisPage';
	}

	return canCreateSubjectPage.value ? 'newPage' : 'anotherPage';
}

// Null until the permission answer that decides which options there are arrives, which it does
// after the first render: offering a choice before it would change that choice under the user,
// and drop whatever they had answered with it.
const pageChoice = ref<SubjectPageChoice | null>( null );

// The notices belong to the page the dialog was opened on, so they say nothing about a Subject
// going anywhere else.
const shownNotices = computed( () => pageChoice.value === 'thisPage' ? notices.value : [] );

// Each choice answers the page question its own way, so what the previous one answered is gone.
watch( pageChoice, () => {
	resetPageChoice();
} );

// One field, so one message: a title already taken and a page that would not read cannot both be
// standing at once, since each belongs to a different page choice.
const pageError = computed( (): string | null => titleTakenError.value ?? pageReadError.value );

// A page-field error is cleared by picking again, or by retyping the label that took the title, so
// the choice behind one is not a page to save onto.
const pageChosen = computed( (): boolean =>
	pageChoice.value !== null && pageError.value === null &&
	( pageChoice.value !== 'anotherPage' || chosenPage.value !== null ) );

const pageFieldStatus = computed( (): ValidationStatusType =>
	pageError.value === null ? 'default' : 'error' );

const pageFieldMessages = computed( (): ValidationMessages =>
	pageError.value === null ? {} : { error: pageError.value } );

const targetHasMainSubject = computed( (): boolean => {
	if ( pageChoice.value === 'thisPage' ) {
		return props.hostPage?.hasMainSubject ?? false;
	}

	// A page that does not exist yet has no Main Subject, and neither has an unpicked one.
	return pageChoice.value === 'anotherPage' && chosenPageHasMainSubject.value;
} );

// The page the Subject joins, once it is known what that page holds. Reported before the read
// lands, the note would promise a placement the answer can still change.
const pickedPageTitle = computed( (): string | null =>
	pageChoice.value === 'anotherPage' && chosenPageRead.value ? chosenPage.value?.title ?? null : null );

async function onPageSelected( choice: PageChoice | null ): Promise<void> {
	resetPageChoice();
	chosenPage.value = choice;

	if ( choice === null || choice.pageId === null ) {
		return;
	}

	// Read straight from the repository, not the store: the store's pageSubjects belongs to the
	// page being viewed, if any.
	try {
		const { pageSubjects } = await NeoWikiExtension.getInstance()
			.getSubjectRepository().getPageSubjects( choice.pageId );
		const mainSubjectId = pageSubjects.getMainSubjectId();
		const mainSubject = mainSubjectId === null ? undefined : pageSubjects.getSubject( mainSubjectId );

		if ( chosenPage.value?.pageId === choice.pageId ) {
			chosenPageRead.value = true;
			chosenPageHasMainSubject.value = mainSubjectId !== null;
			chosenPageMainSubjectName.value = mainSubject === undefined ? null : subjectDisplayName( mainSubject );
			chosenPageSubjectIds.value = pageSubjects.getSubjects().map( ( subject ) => subject.getId().text );
		}
	} catch ( error ) {
		console.error( 'Failed to read the chosen page\'s main subject:', error );

		// Whether the page has a Main Subject decides which tier the Subject is created at, so an
		// unread page goes back to the user rather than being guessed at.
		if ( chosenPage.value?.pageId === choice.pageId ) {
			pageReadError.value = mw.msg( 'neowiki-subject-creator-page-read-error', choice.title );
		}
	}
}

function resetPageChoice(): void {
	chosenPage.value = null;
	chosenPageRead.value = false;
	chosenPageHasMainSubject.value = false;
	chosenPageMainSubjectName.value = null;
	chosenPageSubjectIds.value = [];
	titleTakenError.value = null;
	pageReadError.value = null;
}

// Reloaded when the Schema is chosen too, since Schema-scoped notices cannot apply before there
// is a Schema to scope them to.
watch(
	() => [ subjectStore.subjectCreatorOpen, selectedSchemaName.value ],
	() => {
		if ( subjectStore.subjectCreatorOpen && props.hostPage !== null ) {
			loadNotices( Number( mw.config.get( 'wgArticleId' ) ), selectedSchemaName.value ?? undefined );
		}
	}
);
const schemaStore = useSchemaStore();
const schemaRepo = NeoWikiServices.getSchemaRepository();
const { canCreateSchemas, checkCreatePermission } = useSchemaPermissions();
const { hasChanged: formChanged, markChanged, resetChanged } = useChangeDetection();

// Picking a page is not an edit of the form, and reporting it as one would leave a dialog whose
// page choice was taken back asking to be discarded.
const hasChanged = computed( (): boolean => formChanged.value || chosenPage.value !== null );

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

function handleLabelInput(): void {
	// The label titles the page a new one gets, so retyping it is the answer to a title already
	// taken, and the complaint about the old one goes. A page that would not read is untouched by
	// it: what that page holds is still unknown.
	titleTakenError.value = null;
	handleEditorChange();
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
	await Promise.all( [ checkCreatePermission(), checkCreateSubjectPagePermission() ] );
	pageChoice.value = defaultPageChoice();
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

	markChanged();
	await loadSchema( schemaName );
}

async function loadSchema( schemaName: string ): Promise<void> {
	selectedSchemaName.value = schemaName;

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

/**
 * The page the Subject is going on, where it has a name already. Null for a page that has yet to be
 * made: it is titled by the label, and by the Subject's own id when the label titles no page, so
 * there is no name to preview.
 */
function targetPageName(): string | null {
	if ( pageChoice.value === 'thisPage' ) {
		return pageName();
	}

	return pageChoice.value === 'anotherPage' ? ( chosenPage.value?.title ?? null ) : null;
}

/**
 * The ids of the Subjects the target page holds, which are what say whether its title was chosen by
 * anyone. Known for a page picked, whose Subjects the dialog reads to place the new one.
 */
function targetPageSubjectIds(): string[] {
	return pageChoice.value === 'anotherPage' ? chosenPageSubjectIds.value : [];
}

// The name the Subject will be shown under, marker included: the label field's greyed placeholder,
// previewing the outcome rather than pre-filling it, and the name a failed save calls it by.
const placeholderLabel = computed( (): string =>
	selectedSchemaName.value === null ?
		'' :
		newSubjectNamePreview(
			targetHasMainSubject.value,
			targetPageName(),
			targetPageSubjectIds(),
			selectedSchemaName.value
		)
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
		await pinInitialSchema();
		await nextTick();
		focusInitialInput( selectedSchemaOption.value );
	} else {
		resetForm();
	}
} );

// A Schema that cannot be loaded leaves the picker to do its job rather than a second step with
// nothing to edit.
async function pinInitialSchema(): Promise<void> {
	if ( props.initialSchemaName === undefined ) {
		return;
	}

	await loadSchema( props.initialSchemaName );

	if ( loadedSchema.value === null ) {
		goBack();
	}
}

function resetForm(): void {
	requestSequence++;
	selectedSchemaName.value = null;
	loadedSchema.value = null;
	draftSchema.value = null;
	subjectLabel.value = '';
	selectedSchemaOption.value = 'existing';
	schemaCreatorRef.value?.reset();

	// Back to the page a fresh open would offer, unless the permission answer that decides which
	// one that is has yet to land, in which case no choice is being offered to reset.
	if ( pageChoice.value !== null ) {
		pageChoice.value = defaultPageChoice();
	}

	resetPageChoice();
	resetChanged();
}

function goBack(): void {
	requestSequence++;
	selectedSchemaName.value = null;
	loadedSchema.value = null;
	subjectLabel.value = '';
	resetPageChoice();

	if ( draftSchema.value ) {
		selectedSchemaOption.value = 'new';
	} else {
		resetChanged();
	}
}

const saving = ref( false );

const handleSave = async ( summary: string ): Promise<void> => {
	await nextTick();

	if ( !subjectEditorRef.value || !selectedSchemaName.value || !pageChosen.value || saving.value ) {
		return;
	}

	// Taken once, up front: the fields stay live while the writes below are out, so a change to one
	// would otherwise move the page the write lands on after it had landed.
	const goingTo = pageChoice.value;
	const chosen = chosenPage.value;
	const addAlongsideMainSubject = targetHasMainSubject.value;

	const label = enteredLabel();

	saving.value = true;

	try {
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

		if ( draftSchema.value ) {
			await schemaStore.saveSchema( draftSchema.value, summary || undefined );
			draftSchema.value = null;
		}

		const updatedStatements = subjectEditorRef.value.getSubjectData();
		const statementsToSave = [ ...updatedStatements ].filter( ( statement ) => statement.hasValue() );

		const statementList = new StatementList( statementsToSave );
		const commentOrUndefined = summary || undefined;

		if ( goingTo === 'newPage' ) {
			const { pageTitle } = await subjectStore.createSubjectPage(
				label,
				selectedSchemaName.value,
				statementList,
				commentOrUndefined
			);
			setPendingNotification( 'neowiki-subject-creator-success' );
			window.location.href = mw.util.getUrl( pageTitle );
			return;
		}

		const pageId = goingTo === 'thisPage' ?
			Number( mw.config.get( 'wgArticleId' ) ) :
			( chosen as PageChoice ).pageId as number;

		if ( addAlongsideMainSubject ) {
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
		leaveForCreatedSubject( goingTo === 'thisPage' ? null : chosen );
	} catch ( error ) {
		if ( error instanceof PageTitleTakenError ) {
			titleTakenError.value = mw.msg( 'neowiki-subject-creator-page-taken', error.pageTitle );
			return;
		}
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
	} finally {
		saving.value = false;
	}
};

function leaveForCreatedSubject( chosen: PageChoice | null ): void {
	if ( chosen === null ) {
		window.location.reload();
		return;
	}

	window.location.href = mw.util.getUrl( chosen.title );
}

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

	&-page-field {
		margin-top: @spacing-100;
	}

	&-page-picker {
		margin-top: @spacing-50;
	}

	&-page-note {
		margin: @spacing-50 0 0;
		color: @color-subtle;
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
