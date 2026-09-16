<!-- eslint-disable vue/no-multiple-template-root -->
<template>
	<!-- Each step's confirmation mounts with the step's dialog, and after it: Codex stacks open dialogs
		in the order they mounted, so a confirmation mounted earlier opens hidden behind the dialog. -->
	<!-- Unmounted rather than closed once the Schema is settled: setting a Codex dialog's own open
		prop to false here would be indistinguishable from the user closing it. -->
	<template v-if="rootSubject === null">
		<CdxDialog
			:open="props.open"
			class="ext-neowiki-ui ext-neowiki-subject-creator-dialog cdx-dialog--dividers"
			:class="{ 'ext-neowiki-subject-creator-dialog--wide': selectedSchemaOption === 'new' }"
			:title="$i18n( 'neowiki-subject-creator-title' ).text()"
			:subtitle="headerSubtitle"
			:use-close-button="true"
			@update:open="onSchemaStepUpdateOpen"
		>
			<EditNoticeList :notices="shownNotices" />

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

			<template
				v-if="selectedSchemaOption === 'new'"
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
		</CdxDialog>

		<CloseConfirmationDialog
			:open="confirmationOpen"
			@discard="confirmClose"
			@keep-editing="cancelClose"
		/>
	</template>

	<template v-if="rootSubject !== null && loadedSchema !== null">
		<!-- The Subject itself is filled in by the editor, opened on a Subject the wiki does not hold
			yet: the same panes, tree and relation-target creation as editing one it does. -->
		<SubjectEditorDialog
			:open="props.open"
			:subject="rootSubject as Subject"
			:schema="loadedSchema as Schema"
			:root-is-new="true"
			:save-disabled="!pageChosen"
			:host-has-unsaved-changes="pageAnswered"
			:on-save="handleSaveExisting"
			:on-create="handleCreate"
			:on-save-schema="handleSchemaSave"
			:on-saved="handleSaved"
			@update:open="onEditorUpdateOpen"
		>
			<template #before-actions="{ saving }">
				<div
					v-if="pageChoice !== null && pageFixed"
					class="ext-neowiki-subject-creator-page-summary"
				>
					<span class="ext-neowiki-subject-creator-page-section__label">
						{{ $i18n( 'neowiki-subject-creator-page-section' ).text() }}
						<span class="ext-neowiki-subject-creator-page-section__choice">
							<I18nSlot
								v-if="chosenPageSummary.title !== null"
								:message-key="chosenPageSummary.messageKey"
							>
								<strong>{{ chosenPageSummary.title }}</strong>
							</I18nSlot>
							<template v-else>{{ $i18n( chosenPageSummary.messageKey ).text() }}</template>
						</span>
					</span>

					<CdxMessage
						v-if="pageError !== null"
						type="error"
						:inline="true"
					>
						{{ pageError }}
					</CdxMessage>
				</div>

				<CdxAccordion
					v-else-if="pageChoice !== null"
					class="ext-neowiki-subject-creator-page-section"
					:open="pageSectionOpen"
					@toggle="onPageSectionToggle"
				>
					<template #title>
						<span class="ext-neowiki-subject-creator-page-section__label">
							{{ $i18n( 'neowiki-subject-creator-page-section' ).text() }}
							<span
								v-if="!pageSectionOpen"
								class="ext-neowiki-subject-creator-page-section__choice"
							>
								<I18nSlot
									v-if="chosenPageSummary.title !== null"
									:message-key="chosenPageSummary.messageKey"
								>
									<strong>{{ chosenPageSummary.title }}</strong>
								</I18nSlot>
								<template v-else>{{ $i18n( chosenPageSummary.messageKey ).text() }}</template>
							</span>
						</span>
					</template>

					<CdxField
						ref="pageFieldRef"
						class="ext-neowiki-subject-creator-page-field"
						:is-fieldset="true"
						:hide-label="true"
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
							ref="pagePickerRef"
							class="ext-neowiki-subject-creator-page-picker"
							:existing-pages-only="true"
							:disabled="saving"
							:aria-label="existingPageLabel"
							@update:selected="onPageSelected"
						/>

						<CdxField
							v-if="pageChoice === 'newPage'"
							class="ext-neowiki-subject-creator-page-title-field"
							:optional="true"
							:status="pageTitleFieldStatus"
							:messages="pageTitleFieldMessages"
						>
							<CdxTextInput
								ref="pageTitleInputRef"
								v-model="pageTitle"
								:disabled="saving"
								@input="handlePageTitleInput"
							/>
							<template #label>
								{{ $i18n( 'neowiki-subject-creator-page-title-field' ).text() }}
							</template>
							<template #help-text>
								{{ $i18n( 'neowiki-subject-creator-page-title-help' ).text() }}
							</template>
						</CdxField>
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
				</CdxAccordion>
			</template>
		</SubjectEditorDialog>

		<SchemaAbandonmentDialog
			:open="schemaAbandonmentOpen"
			@abandon="abandonAll"
			@save-schema="saveSchemaAndClose"
			@keep-editing="cancelSchemaAbandonment"
		/>
	</template>
</template>

<script setup lang="ts">
import { ref, shallowRef, computed, watch, nextTick, onMounted } from 'vue';
import { CdxAccordion, CdxButton, CdxDialog, CdxField, CdxIcon, CdxMessage, CdxRadio, CdxTextInput, CdxToggleButtonGroup } from '@wikimedia/codex';
import { cdxIconAdd, cdxIconArrowNext, cdxIconSearch } from '@wikimedia/codex-icons';
import type { ButtonGroupItem, ValidationMessages, ValidationStatusType } from '@wikimedia/codex';
import { useSubjectStore } from '@/stores/SubjectStore.ts';
import type { CreatedSubjectPage } from '@/stores/SubjectStore.ts';
import { useSchemaStore } from '@/stores/SchemaStore.ts';
import { Schema } from '@/domain/Schema.ts';
import { StatementList } from '@/domain/StatementList.ts';
import { Subject } from '@/domain/Subject.ts';
import { SubjectWithContext } from '@/domain/SubjectWithContext.ts';
import { PageIdentifiers } from '@/domain/PageIdentifiers.ts';
import type { SubjectId } from '@/domain/SubjectId.ts';
import type { SubjectRepository } from '@/domain/SubjectRepository.ts';
import { subjectDisplayName } from '@/presentation/subjectDisplayName.ts';
import SubjectEditorDialog from '@/components/SubjectEditor/SubjectEditorDialog.vue';
import SchemaCreator from '@/components/SchemaCreator/SchemaCreator.vue';
import type { SchemaCreatorExposes } from '@/components/SchemaCreator/SchemaCreator.vue';
import SchemaPicker from '@/components/common/SchemaPicker.vue';
import CloseConfirmationDialog from '@/components/common/CloseConfirmationDialog.vue';
import PagePicker from '@/components/common/PagePicker.vue';
import I18nSlot from '@/components/common/I18nSlot.vue';
import type { PageChoice } from '@/components/common/PageChoice.ts';
import type { InitialPage, SubjectPageChoice } from '@/components/SubjectCreator/InitialPage.ts';
import { PageTitleTakenError } from '@/persistence/PageTitleTakenError.ts';
import { InvalidPageTitleError } from '@/persistence/InvalidPageTitleError.ts';
import { SubjectIdInUseError } from '@/persistence/SubjectIdInUseError.ts';
import SchemaAbandonmentDialog from '@/components/SubjectCreator/SchemaAbandonmentDialog.vue';
import { useSchemaPermissions } from '@/composables/useSchemaPermissions.ts';
import { useSubjectPermissions } from '@/composables/useSubjectPermissions.ts';
import { useChangeDetection } from '@/composables/useChangeDetection.ts';
import { useCloseConfirmation } from '@/composables/useCloseConfirmation.ts';
import { NeoWikiExtension } from '@/NeoWikiExtension.ts';
import { NeoWikiServices } from '@/NeoWikiServices.ts';
import { setPendingNotification } from '@/presentation/PendingNotification.ts';
import { subjectPageUrl } from '@/presentation/subjectPageUrl.ts';
import EditNoticeList from '@/components/common/EditNoticeList.vue';
import { useEditNotices } from '@/composables/useEditNotices.ts';

const props = defineProps<{
	/**
	 * The page the dialog was opened on, which the Subject can go on. Null where it was opened on
	 * no page of its own - Special:CreateSubject, a Schema page - so that "this page" is not among
	 * the pages offered, and saving leaves for the Subject's own page.
	 */
	hostPage: { hasMainSubject: boolean } | null;
	initialSchemaName?: string;
	/** The page the caller wants; undefined asks the user as usual. */
	initialPage?: InitialPage;
	open: boolean;
}>();

const emit = defineEmits<{
	'update:open': [ value: boolean ];
}>();

/**
 * The page the Subject being created is bound for, as the editor is handed it: unanswered. Which
 * page it lands on is asked in the footer and can still change, so no answer is written into the
 * Subject itself; the write handlers below read the answer as it stands when the save goes out.
 * MediaWiki numbers a page that is not there 0. A Subject created against this one inherits these
 * identifiers, which is what marks it as bound for the same place; one created while drilled into a
 * Subject the wiki already holds carries that Subject's own page instead.
 */
const UNANSWERED_PAGE = new PageIdentifiers( 0, '' );

const selectedSchemaOption = ref( 'existing' );
const { notices, loadNotices } = useEditNotices( () => NeoWikiExtension.getInstance().getEditNoticeRepository() );

const loadedSchema = ref<Schema | null>( null );
// eslint-disable-next-line @typescript-eslint/no-explicit-any
const schemaLookupRef = ref<any | null>( null );
const schemaCreatorRef = ref<SchemaCreatorExposes | null>( null );

const draftSchema = shallowRef<Schema | null>( null );

/**
 * The Subject being created, as the editor dialog holds it: an id of its own, so the panes and the
 * tree can name it and a relation can be recorded against it, and no label until someone types one.
 * Non-null is what puts the dialog on its second step.
 */
const rootSubject = shallowRef<SubjectWithContext | null>( null );

// Guards loadedSchema against a stale schema fetch: picking a different schema, and leaving the
// picked one (going back, or the dialog closing), both invalidate an in-flight response.
let requestSequence = 0;

const subjectStore = useSubjectStore();

// Resolved per call, like the notice repository below: reaching the extension singleton must not
// happen while a component is setting up.
function subjectRepository(): SubjectRepository {
	return NeoWikiExtension.getInstance().getSubjectRepository();
}

const pageFixed = computed( (): boolean => props.initialPage?.fixed === true );

const chosenPage = ref<PageChoice | null>( null );
const chosenPageRead = ref( false );
const chosenPageHasMainSubject = ref( false );
const chosenPageMainSubjectName = ref<string | null>( null );
const pageTitle = ref( '' );
const titleTakenError = ref<string | null>( null );
const invalidTitleError = ref<string | null>( null );
const pageReadError = ref<string | null>( null );

// Which page the Subject goes on is asked below the Subject itself, and collapsed: the page it is
// opened on, or one of its own, answers it for most.
const pageSectionOpen = ref( false );

const pageFieldRef = ref<InstanceType<typeof CdxField> | null>( null );
const pageTitleInputRef = ref<InstanceType<typeof CdxTextInput> | null>( null );
const pagePickerRef = ref<InstanceType<typeof PagePicker> | null>( null );

const { canCreateSubjectPage, checkCreateSubjectPagePermission } = useSubjectPermissions();

interface PageOption {
	value: SubjectPageChoice;
	label: string;
}

// The page picked is another one than the page being viewed, and where there is none, just a page
// that already exists.
const existingPageLabel = computed( (): string => mw.msg(
	props.hostPage === null ?
		'neowiki-subject-creator-page-existing' :
		'neowiki-subject-creator-page-another'
) );

// "This page" needs a page the dialog was opened on; a new page needs the right to make one. The
// page each set defaults to leads it.
const pageOptions = computed( (): PageOption[] => {
	const existingPage: PageOption = { value: 'anotherPage', label: existingPageLabel.value };
	const newPage: PageOption = { value: 'newPage', label: mw.msg( 'neowiki-subject-creator-page-new' ) };

	if ( props.hostPage === null ) {
		return canCreateSubjectPage.value ? [ newPage, existingPage ] : [ existingPage ];
	}

	const options: PageOption[] = [
		{ value: 'thisPage', label: mw.msg( 'neowiki-subject-creator-page-this' ) },
		existingPage
	];

	if ( canCreateSubjectPage.value ) {
		options.push( newPage );
	}

	return options;
} );

// The page being viewed where there is one, and a page of the Subject's own where there is not.
function defaultPageChoice(): SubjectPageChoice {
	if ( props.initialPage !== undefined ) {
		return props.initialPage.choice;
	}

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

	// The section is where the choice is made, so one made while it is closed is the dialog
	// resetting itself rather than a user to follow.
	if ( pageSectionOpen.value ) {
		focusChosenInput();
	}
} );

// One message per field: each of these belongs to a different page choice, so no two of them can
// be standing at once.
const pageTitleError = computed( (): string | null => titleTakenError.value ?? invalidTitleError.value );
const pageError = computed( (): string | null => pageTitleError.value ?? pageReadError.value );

// Answered, and answerable: a page still to be picked leaves the question open, and a page that
// could not be read leaves it unanswerable — what that page holds decides whether the Subject
// becomes its topic or joins it, and that is not to be guessed at. A title the server refused
// blocks neither, because one answer to it is a different label, which is typed in the editor
// rather than here — a Save held shut until this field changed could never be given it.
const pageChosen = computed( (): boolean =>
	pageChoice.value !== null && pageReadError.value === null &&
	( pageChoice.value !== 'anotherPage' || chosenPage.value !== null ) );

const pageFieldStatus = computed( (): ValidationStatusType =>
	pageReadError.value === null ? 'default' : 'error' );

const pageFieldMessages = computed( (): ValidationMessages =>
	pageReadError.value === null ? {} : { error: pageReadError.value } );

const pageTitleFieldStatus = computed( (): ValidationStatusType =>
	pageTitleError.value === null ? 'default' : 'error' );

const pageTitleFieldMessages = computed( (): ValidationMessages =>
	pageTitleError.value === null ? {} : { error: pageTitleError.value } );

// A collapsed section would hide what the server refused, and the field it belongs to with it.
watch( pageError, ( error ) => {
	if ( error !== null ) {
		pageSectionOpen.value = true;
	}
} );

// The element opens and closes itself, so its own state is what the summary and the next open
// follow, rather than the value last rendered onto it.
function onPageSectionToggle( event: Event ): void {
	pageSectionOpen.value = ( event.target as HTMLDetailsElement ).open;

	if ( pageSectionOpen.value ) {
		focusOpenedSection();
	}
}

/**
 * The input the current choice leaves to fill in: the title of the page to create, or the picker
 * that finds the page to join. Null for a page that is named already.
 */
function choiceInput(): { focus: () => void } | null {
	if ( pageChoice.value === 'newPage' ) {
		return pageTitleInputRef.value;
	}

	if ( pageChoice.value === 'anotherPage' ) {
		return pagePickerRef.value;
	}

	return null;
}

/** The option standing when the section was opened, which is where a user reads the choice. */
function chosenOptionInput(): HTMLElement | null {
	return ( pageFieldRef.value?.$el as HTMLElement | undefined )
		?.querySelector( 'input[type="radio"]:checked' ) ?? null;
}

// Opening the section is asking to answer it, so the answer can be given without reaching for the
// mouse again, and a keyboard user is not left behind the header they just opened.
async function focusOpenedSection(): Promise<void> {
	await nextTick();
	( choiceInput() ?? chosenOptionInput() )?.focus();
}

// A choice made in the open section hands over to the field it reveals, and leaves focus on the
// option itself where it reveals none.
async function focusChosenInput(): Promise<void> {
	await nextTick();
	choiceInput()?.focus();
}

// The title the page created gets, where the user chose one over the label.
function enteredPageTitle(): string | null {
	const entered = pageTitle.value.trim();

	return entered === '' ? null : entered;
}

// The choice, in words, for the collapsed section's header: open, the options say it themselves.
const chosenPageSummary = computed( (): { messageKey: string; title: string | null } => {
	if ( pageChoice.value === 'thisPage' ) {
		return { messageKey: 'neowiki-subject-creator-page-section-this', title: null };
	}

	if ( pageChoice.value === 'newPage' ) {
		const title = enteredPageTitle();

		return title === null ?
			{ messageKey: 'neowiki-subject-creator-page-section-new', title: null } :
			{ messageKey: 'neowiki-subject-creator-page-section-new-titled', title };
	}

	const picked = chosenPage.value?.title;

	if ( picked !== undefined ) {
		return { messageKey: 'neowiki-subject-creator-page-section-picked', title: picked };
	}

	return {
		messageKey: props.hostPage === null ?
			'neowiki-subject-creator-page-section-existing' :
			'neowiki-subject-creator-page-section-another',
		title: null
	};
} );

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
		const { pageSubjects } = await subjectRepository().getPageSubjects( choice.pageId );
		const mainSubjectId = pageSubjects.getMainSubjectId();
		const mainSubject = mainSubjectId === null ? undefined : pageSubjects.getSubject( mainSubjectId );

		if ( chosenPage.value?.pageId === choice.pageId ) {
			chosenPageRead.value = true;
			chosenPageHasMainSubject.value = mainSubjectId !== null;
			chosenPageMainSubjectName.value = mainSubject === undefined ? null : subjectDisplayName( mainSubject );
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
	pageTitle.value = '';
	titleTakenError.value = null;
	invalidTitleError.value = null;
	pageReadError.value = null;
}

/**
 * The choice watcher clears what the previous choice answered, so the nextTick lets it run first.
 */
async function applyInitialPageTarget(): Promise<void> {
	const named = props.initialPage?.page;

	if ( named === undefined ) {
		return;
	}

	await nextTick();

	if ( named.pageId === null ) {
		pageTitle.value = named.title;
		return;
	}

	await onPageSelected( named );
}

// The Schema's own notices cannot apply before there is a Schema, and once there is one the editor
// dialog fetches them itself. These are the page's, for the step that comes first.
watch( () => props.open, ( isOpen ) => {
	if ( isOpen && props.hostPage !== null ) {
		loadNotices( Number( mw.config.get( 'wgArticleId' ) ) );
	}
} );

const schemaStore = useSchemaStore();
const schemaRepo = NeoWikiServices.getSchemaRepository();
const { canCreateSchemas, checkCreatePermission } = useSchemaPermissions();
const { hasChanged: formChanged, markChanged, resetChanged } = useChangeDetection();

// The page question answered, where it was the user's to answer. An answer taken back counts for
// nothing, so a dialog whose answer was withdrawn does not go on asking to be discarded.
const pageAnswered = computed( (): boolean =>
	!pageFixed.value && ( chosenPage.value !== null || enteredPageTitle() !== null ) );

// Answering the page question is not an edit of the form, but it is still something a close would
// throw away.
const hasChanged = computed( (): boolean => formChanged.value || pageAnswered.value );

function close(): void {
	emit( 'update:open', false );
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

function onSchemaStepUpdateOpen( value: boolean ): void {
	if ( !value ) {
		requestClose();
	}
}

// The editor runs the discard confirmation, for the answer given in its footer as much as for the
// Subject, so the only question left is the one it cannot ask: a Schema drafted in the step before,
// which closing would throw away alongside the Subject that was to use it.
function onEditorUpdateOpen( value: boolean ): void {
	if ( value ) {
		return;
	}

	if ( hasDraftSchema.value ) {
		schemaAbandonmentOpen.value = true;
		return;
	}

	close();
}

const headerSubtitle = computed( (): string =>
	selectedSchemaOption.value === 'new' ? mw.msg( 'neowiki-subject-creator-creating-schema' ) : ''
);

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

	// Already open: the open watcher ran before pageChoice existed.
	if ( props.open ) {
		await applyInitialPageTarget();
	}
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
	const currentSequence = ++requestSequence;

	try {
		// The id is minted alongside the Schema because the step it opens cannot start without
		// one: the panes and the tree name the Subject by it, and so does a relation recorded
		// against it before anything has been written.
		const [ schema, id ] = await Promise.all( [
			schemaRepo.getSchema( schemaName ),
			subjectRepository().mintSubjectId()
		] );

		if ( currentSequence !== requestSequence ) {
			return;
		}

		openSubjectStep( schema, id );
	} catch ( error ) {
		if ( currentSequence !== requestSequence ) {
			return;
		}

		console.error( 'Failed to load schema:', error );
		loadedSchema.value = null;
		rootSubject.value = null;
	}
}

function openSubjectStep( schema: Schema, id: SubjectId ): void {
	// A close the Schema step was still asking about goes with the step, or the question would come
	// back by itself the next time that step shows.
	cancelClose();

	loadedSchema.value = schema;
	rootSubject.value = new SubjectWithContext(
		id,
		null,
		// What the server derives for a Subject nobody has named (ADR 31).
		schema.getName(),
		true,
		schema.getName(),
		new StatementList( [] ),
		UNANSWERED_PAGE
	);
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

	const incomplete = schemaCreatorRef.value.incompleteProperty();

	if ( incomplete !== null ) {
		mw.notify( incomplete.message, { title: incomplete.propertyName, type: 'error' } );
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

	// Takes its turn in the same sequence as a Schema being fetched, so neither can land on the
	// other. Nothing is written here: the Schema reaches the wiki when the Subject is saved.
	const currentSequence = ++requestSequence;

	let id: SubjectId;

	try {
		id = await subjectRepository().mintSubjectId();
	} catch ( error ) {
		if ( currentSequence !== requestSequence ) {
			return;
		}

		console.error( 'Failed to mint a subject id:', error );
		mw.notify( mw.msg( 'neowiki-subject-creator-error' ), { type: 'error' } );
		return;
	}

	if ( currentSequence !== requestSequence ) {
		return;
	}

	draftSchema.value = schema;
	openSubjectStep( schema, id );
	markChanged();
}

/**
 * The page the Subjects created here are stored on, as the answer in the footer stands. Null for a
 * page that is not there yet, whose id only its own creation reports.
 */
function answeredPageId(): number | null {
	if ( pageChoice.value === 'thisPage' ) {
		return Number( mw.config.get( 'wgArticleId' ) );
	}

	return pageChoice.value === 'anotherPage' ? chosenPage.value?.pageId ?? null : null;
}

/**
 * Where the root's write put it, once it has landed. Taken from the write rather than read again
 * from the footer, which stays live while the rest of the save is out: a Subject created alongside
 * the root goes where the root went, whatever the fields say by then.
 */
let writtenRoot: { subjectId: SubjectId; pageId: number | null; pageTitle: string | null } | null = null;

async function handleCreate( subject: Subject, pageId: number, comment: string ): Promise<void> {
	if ( subject.getId().text === rootSubject.value?.getId().text ) {
		await writeRootSubject( subject, comment );
		return;
	}

	// A target created from a pane the user drilled into belongs on the page that pane's Subject is
	// stored on, which the editor resolved. One created against the Subject being created carries
	// no page of its own and follows it instead.
	const page = pageId > 0 ? pageId : writtenRoot?.pageId ?? null;

	if ( page === null ) {
		throw new Error( mw.msg( 'neowiki-subject-creator-error' ) );
	}

	await subjectStore.createSubject( subject, page, comment );
}

async function writeRootSubject( subject: Subject, comment: string ): Promise<void> {
	// A save that stopped part way has created it already, and the answer it got carries the id the
	// server minted for it. Creating it again would make a second Subject, or be refused outright
	// by a page title that is now taken.
	if ( writtenRoot !== null ) {
		await subjectStore.updateSubject( asWrittenRoot( subject, writtenRoot ), comment );
		return;
	}

	// The whole answer, read before the first await. The footer is frozen while the writes are
	// out, but the guarantee is this one: the page a write lands on, and the page the dialog then
	// says it landed on, are read once and cannot drift apart across the awaits below.
	const answer = {
		goingTo: pageChoice.value,
		pageId: answeredPageId(),
		title: enteredPageTitle(),
		pageTitle: pageChoice.value === 'thisPage' ? null : chosenPage.value?.title ?? null,
		besideMainSubject: targetHasMainSubject.value
	};

	if ( draftSchema.value !== null ) {
		await schemaStore.saveSchema( draftSchema.value, comment );
		draftSchema.value = null;
	}

	const label = subject.getLabel();
	const schemaName = subject.getSchemaName();
	const statements = subject.getStatements();

	if ( answer.goingTo === 'newPage' ) {
		const created = await createOnNewPage( label, schemaName, statements, comment, answer.title );

		writtenRoot = { subjectId: created.subjectId, pageId: created.pageId, pageTitle: created.pageTitle };
		return;
	}

	const pageId = answer.pageId;

	if ( pageId === null ) {
		throw new Error( mw.msg( 'neowiki-subject-creator-error' ) );
	}

	// A page that has a Main Subject already gets this one beside it; one that has none is being
	// given its topic. Only the second route takes the id minted here, so a Subject created on the
	// first can be pointed at it before either exists.
	const subjectId = answer.besideMainSubject ?
		await createBesideMainSubject( pageId, label, schemaName, statements, comment, subject.getId() ) :
		await subjectStore.createMainSubject( pageId, label, schemaName, statements, comment );

	writtenRoot = { subjectId, pageId, pageTitle: answer.pageTitle };
}

/**
 * The Subject as it now stands under the id the server gave it, for a second pass over a root the
 * first one created.
 */
function asWrittenRoot( subject: Subject, written: { subjectId: SubjectId; pageId: number | null; pageTitle: string | null } ): Subject {
	return new SubjectWithContext(
		written.subjectId,
		subject.getLabel(),
		subject.getDisplayName(),
		subject.hasGeneratedDisplayName(),
		subject.getSchemaName(),
		subject.getStatements(),
		new PageIdentifiers( written.pageId ?? 0, written.pageTitle ?? '' )
	);
}

/**
 * Adds the Subject beside a page's Main Subject, under the id minted for it here. That id was
 * minted for this Subject alone, so the server holding it already means this very create landed and
 * only its answer was lost: the id it refused is the id the Subject has. Reporting a failure
 * instead would leave the dialog with no record of what it made, and every retry would be refused
 * the same way.
 */
async function createBesideMainSubject(
	pageId: number,
	label: string | null,
	schemaName: string,
	statements: StatementList,
	comment: string,
	id: SubjectId
): Promise<SubjectId> {
	try {
		return await subjectStore.createOtherSubject( pageId, label, schemaName, statements, comment, id );
	} catch ( error ) {
		if ( error instanceof SubjectIdInUseError ) {
			return id;
		}

		throw error;
	}
}

// The two ways a title is refused are answered at the field it was typed in, and reported as the
// save's own failure too: the writes stop there, and the toast is what says so.
async function createOnNewPage(
	label: string | null,
	schemaName: string,
	statements: StatementList,
	comment: string,
	chosenTitle: string | null
): Promise<CreatedSubjectPage> {
	try {
		return await subjectStore.createSubjectPage( label, schemaName, statements, comment, chosenTitle ?? undefined );
	} catch ( error ) {
		if ( error instanceof PageTitleTakenError ) {
			// A fixed destination offers no other page and no title field: only the label can change.
			titleTakenError.value = mw.msg(
				pageFixed.value && chosenTitle === null ?
					'neowiki-subject-creator-page-taken-fixed' :
					'neowiki-subject-creator-page-taken',
				error.pageTitle
			);
			throw new Error( titleTakenError.value );
		}

		if ( error instanceof InvalidPageTitleError ) {
			invalidTitleError.value = mw.msg( 'neowiki-subject-creator-page-title-invalid', error.pageTitle );
			throw new Error( invalidTitleError.value );
		}

		throw error;
	}
}

// Retyping the title is the answer to a title the server refused, whichever way it refused it.
function handlePageTitleInput(): void {
	titleTakenError.value = null;
	invalidTitleError.value = null;
}

// A Subject the user drilled into from a relation exists already, so the editor updates it.
async function handleSaveExisting( subject: Subject, comment: string ): Promise<void> {
	await subjectStore.updateSubject( subject, comment );
}

/**
 * The editor offers the Schema editor from the root pane, for a Schema still being drafted as much
 * as for one the wiki holds. Once it is saved there it is on the wiki, so it stops being a draft:
 * writing the drafted copy over it on save would drop whatever was added, and closing would offer
 * to abandon a Schema that is already there.
 */
async function handleSchemaSave( updatedSchema: Schema, comment: string ): Promise<void> {
	await schemaStore.saveSchema( updatedSchema, comment );

	draftSchema.value = null;
	loadedSchema.value = updatedSchema;
}

/**
 * Opened without a page of its own, the creator is about the Subject, so it leaves for the
 * Subject's own page, which shows what was created. Opened on a page, it leaves for the page the
 * Subject went on, with a notice that it was created; a null title is the page being viewed.
 */
function handleSaved(): void {
	if ( writtenRoot === null ) {
		return;
	}

	if ( props.hostPage === null ) {
		window.location.href = subjectPageUrl( writtenRoot.subjectId.text );
		return;
	}

	setPendingNotification( 'neowiki-subject-creator-success' );

	if ( writtenRoot.pageTitle === null ) {
		window.location.reload();
		return;
	}

	window.location.href = mw.util.getUrl( writtenRoot.pageTitle );
}

watch( () => props.open, async ( isOpen ) => {
	if ( isOpen ) {
		// Before mount there is no choice yet; onMounted fills it in then.
		if ( pageChoice.value !== null ) {
			applyInitialPageTarget();
		}

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

	// The load's own catch clears the Schema and the Subject together, which leaves the picker
	// showing; nothing further is needed to fall back to it.
	await loadSchema( props.initialSchemaName );
}

function resetForm(): void {
	requestSequence++;
	loadedSchema.value = null;
	rootSubject.value = null;
	draftSchema.value = null;
	writtenRoot = null;
	selectedSchemaOption.value = 'existing';
	schemaCreatorRef.value?.reset();

	// Back to the page a fresh open would offer, unless the permission answer that decides which
	// one that is has yet to land, in which case no choice is being offered to reset.
	if ( pageChoice.value !== null ) {
		pageChoice.value = defaultPageChoice();
	}

	resetPageChoice();
	pageSectionOpen.value = false;
	resetChanged();
}

defineExpose( { hasChanged } );
</script>

<style lang="less">
@import ( reference ) '@wikimedia/codex-design-tokens/theme-wikimedia-ui.less';

.ext-neowiki-subject-creator {
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

	/* Sibling of the edit-summary section below it, and built to read as one. */
	&-page-section.cdx-accordion {
		border-bottom: 0;

		> summary {
			margin-top: -@spacing-50;
			margin-inline: -@spacing-50;
		}

		/* Reset the font size from accordion to match CdxField */
		.cdx-accordion__header,
		.cdx-accordion__header__title,
		.cdx-accordion__content {
			font-size: inherit;
		}

		.cdx-accordion__content {
			padding: 0;
		}
	}

	/* Matches the accordion header it replaces. */
	&-page-summary {
		font-weight: @font-weight-bold;

		.cdx-message {
			margin-top: @spacing-50;
			font-weight: @font-weight-normal;
		}
	}

	&-page-section__choice {
		color: @color-subtle;
		font-weight: @font-weight-normal;
	}

	&-page-picker {
		margin-top: @spacing-50;
	}

	&-page-title-field {
		margin-top: @spacing-75;
	}

	&-page-note {
		margin: @spacing-50 0 0;
		color: @color-subtle;
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
