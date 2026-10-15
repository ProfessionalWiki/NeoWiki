<!-- eslint-disable vue/no-multiple-template-root -->
<template>
	<!-- Unmounted rather than closed once the editor shows: setting a Codex dialog's own open
		prop to false here would be indistinguishable from the user closing it. It stays while the
		editor reads what the Subject's form needs, until the editor shows that it is reading, so one
		dialog is on screen throughout. -->
	<CdxDialog
		v-if="!editorVisible"
		:open="props.open"
		class="ext-neowiki-ui ext-neowiki-subject-creator-dialog cdx-dialog--dividers"
		:title="$i18n( 'neowiki-subject-creator-title' ).text()"
		:use-close-button="true"
		:inert="editorStarting || undefined"
		@update:open="onSchemaStepUpdateOpen"
	>
		<EditNoticeList :notices="shownNotices" />

		<SchemaPicker
			v-if="schemaPermissionKnown"
			ref="schemaLookupRef"
			:offers-creation="canCreateSchemas && offeredSchemaNames === undefined"
			:schema-names="offeredSchemaNames"
			:aria-label="$i18n( 'neowiki-subject-creator-schema-field' ).text()"
			:standalone-only="true"
			@select="onSchemaSelected"
			@create="openSchemaCreator"
		>
			<template #no-schemas>
				<p>{{ $i18n( 'neowiki-subject-creator-no-schemas' ).text() }}</p>
			</template>
		</SchemaPicker>
	</CdxDialog>

	<!-- The Subject itself is filled in by the editor, opened on a Subject the wiki does not hold
		yet: the same panes, navigator and relation-target creation as editing one it does. -->
	<SubjectEditorDialog
		v-if="rootSubject !== null && loadedSchema !== null"
		ref="editorRef"
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

				<PageTitleInput
					v-if="asksPageTitle"
					ref="pageTitleInputRef"
					v-model="pageTitle"
					:error="pageTitleError"
					:disabled="saving"
				/>

				<CdxMessage
					v-else-if="pageError !== null"
					type="error"
					:inline="true"
				>
					{{ pageError }}
				</CdxMessage>
			</div>

			<CdxAccordion
				v-else-if="pageChoice !== null && pageQuestionAsked"
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
						:model-value="pageChoice"
						:input-value="option.value"
						:disabled="saving || option.deniedReason !== undefined"
						name="ext-neowiki-subject-creator-page-choice"
						:inline="true"
						@update:model-value="choosePage"
					>
						{{ option.label }}
						<template
							v-if="option.deniedReason !== undefined"
							#description
						>
							{{ option.deniedReason }}
						</template>
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

					<PageTitleInput
						v-if="asksPageTitle"
						ref="pageTitleInputRef"
						v-model="pageTitle"
						:error="pageTitleError"
						:disabled="saving"
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
			</CdxAccordion>
		</template>
	</SubjectEditorDialog>

	<CloseConfirmationDialog
		:open="confirmationOpen"
		@discard="confirmClose"
		@keep-editing="cancelClose"
	/>

	<!-- Mounted only while open, as the confirmations are: Codex stacks dialogs in mount order, and
		the Schema step it stands in front of is mounted afresh each time the creator starts over. The
		Schema's Subjects are standalone, as the Subject being created has no Host Subject. -->
	<SchemaCreatorDialog
		v-if="newSchemaName !== null"
		:open="true"
		:initial-name="newSchemaName"
		:subjects-are-dependent="false"
		@update:open="newSchemaName = null"
		@created="onSchemaCreated"
	/>
</template>

<script setup lang="ts">
import { ref, shallowRef, computed, watch, nextTick, onMounted } from 'vue';
import { CdxAccordion, CdxDialog, CdxField, CdxMessage, CdxRadio } from '@wikimedia/codex';
import type { ValidationMessages, ValidationStatusType } from '@wikimedia/codex';
import { useSubjectStore } from '@/stores/SubjectStore.ts';
import type { CreatedSubjectPage, DependentSubjects, SubjectCreatorTarget } from '@/stores/SubjectStore.ts';
import { normalizeSchemaName, useSchemaStore } from '@/stores/SchemaStore.ts';
import { Schema } from '@/domain/Schema.ts';
import { StatementList } from '@/domain/StatementList.ts';
import { Subject } from '@/domain/Subject.ts';
import { PageIdentifiers } from '@/domain/PageIdentifiers.ts';
import type { SubjectId } from '@/domain/SubjectId.ts';
import type { SubjectRepository } from '@/domain/SubjectRepository.ts';
import { subjectDisplayName } from '@/presentation/subjectDisplayName.ts';
import SubjectEditorDialog from '@/components/SubjectEditor/SubjectEditorDialog.vue';
import SchemaCreatorDialog from '@/components/SchemaCreator/SchemaCreatorDialog.vue';
import SchemaPicker from '@/components/common/SchemaPicker.vue';
import CloseConfirmationDialog from '@/components/common/CloseConfirmationDialog.vue';
import PagePicker from '@/components/common/PagePicker.vue';
import PageTitleInput from '@/components/SubjectCreator/PageTitleInput.vue';
import I18nSlot from '@/components/common/I18nSlot.vue';
import type { PageChoice } from '@/components/common/PageChoice.ts';
import type { InitialPage, SubjectPageChoice } from '@/components/SubjectCreator/InitialPage.ts';
import { PageTitleTakenError } from '@/persistence/PageTitleTakenError.ts';
import { InvalidPageTitleError } from '@/persistence/InvalidPageTitleError.ts';
import { SubjectIdInUseError } from '@/persistence/SubjectIdInUseError.ts';
import { WriteRefusedError } from '@/components/SubjectEditor/WriteRefusedError.ts';
import { useSchemaPermissions } from '@/composables/useSchemaPermissions.ts';
import { useSubjectPermissions } from '@/composables/useSubjectPermissions.ts';
import { useChangeDetection } from '@/composables/useChangeDetection.ts';
import { useCloseConfirmation } from '@/composables/useCloseConfirmation.ts';
import { NeoWikiExtension } from '@/NeoWikiExtension.ts';
import { NeoWikiServices } from '@/NeoWikiServices.ts';
import { setPendingNotification } from '@/presentation/PendingNotification.ts';
import { subjectPageUrl } from '@/presentation/subjectPageUrl.ts';
import { isSubjectFirst } from '@/wikiMode.ts';
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
	/**
	 * The target of a relation that the Subject is created as: under its id, with one of its Schemas.
	 * The Schema step then offers only those, and opens on the Subject where there is only one.
	 */
	relationTarget?: SubjectCreatorTarget | null;
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
 * Subject itself; the write handlers below read the answer as it stands when the save goes out. On
 * a page-first wiki, a Subject created against this one inherits these identifiers, which is what
 * marks it as bound for the same place, and one created while drilled into a Subject the wiki
 * already holds carries that Subject's own page instead.
 */
const UNANSWERED_PAGE = PageIdentifiers.notYetCreated();

const { notices, loadNotices } = useEditNotices( () => NeoWikiExtension.getInstance().getEditNoticeRepository() );

const loadedSchema = ref<Schema | null>( null );
// eslint-disable-next-line @typescript-eslint/no-explicit-any
const schemaLookupRef = ref<any | null>( null );

// The name the Schema creator stacked on the Schema step opened with, null while it is closed.
const newSchemaName = ref<string | null>( null );

/**
 * The Subject being created, as the editor dialog holds it: an id of its own, so the panes and the
 * navigator can name it and a relation can be recorded against it, and no label until someone types one.
 * Non-null is what puts the dialog on its second step.
 */
const rootSubject = shallowRef<Subject | null>( null );

// Guards loadedSchema against a stale schema fetch: picking a different schema, opening the Schema
// creator, and leaving the picked one (going back, or the dialog closing) all invalidate an
// in-flight response.
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

const editorRef = ref<InstanceType<typeof SubjectEditorDialog> | null>( null );

const editorShown = computed( (): boolean => editorRef.value?.shown === true );

const editorVisible = computed( (): boolean => editorRef.value?.visible === true );

const editorStarting = computed( (): boolean => rootSubject.value !== null && !editorShown.value );

// The title typed, cleared included, or the caller's; while null, the title follows the label.
const typedPageTitle = ref<string | null>( null );

// What was refused about the title of the page to create: none given, one already taken, or
// one that titles no page. Only one can stand at a time, so they share the slot they are shown in.
const pageTitleError = ref<string | null>( null );
const pageReadError = ref<string | null>( null );

// Which page the Subject goes on is asked below the Subject itself. startPageQuestion decides
// whether the section starts open.
const pageSectionOpen = ref( false );

const pageFieldRef = ref<InstanceType<typeof CdxField> | null>( null );
const pageTitleInputRef = ref<InstanceType<typeof PageTitleInput> | null>( null );
const pagePickerRef = ref<InstanceType<typeof PagePicker> | null>( null );

const { canCreateSubjectPage, checkCreateSubjectPagePermission } = useSubjectPermissions();

interface PageOption {
	value: SubjectPageChoice;
	label: string;
	/** Why the option cannot be chosen, where it cannot. */
	deniedReason?: string;
}

// The page picked is another one than the page being viewed, and where there is none, just a page
// that already exists.
const existingPageLabel = computed( (): string => mw.msg(
	props.hostPage === null ?
		'neowiki-subject-creator-page-existing' :
		'neowiki-subject-creator-page-another'
) );

// "This page" needs a page the dialog was opened on. A new page leads where there is none, and
// otherwise comes last.
const pageOptions = computed( (): PageOption[] => {
	const existingPage: PageOption = { value: 'anotherPage', label: existingPageLabel.value };
	const thisPage: PageOption = { value: 'thisPage', label: mw.msg( 'neowiki-subject-creator-page-this' ) };
	const options = props.hostPage === null ?
		[ newPageOption(), existingPage ] :
		[ thisPage, existingPage, newPageOption() ];

	return options.filter( ( option ): option is PageOption => option !== null );
} );

// A new page needs the right to create one. Without it, a page-first wiki shows the option
// disabled, saying why, and a subject-first wiki leaves it out.
function newPageOption(): PageOption | null {
	const option: PageOption = { value: 'newPage', label: mw.msg( 'neowiki-subject-creator-page-new' ) };

	if ( canCreateSubjectPage.value ) {
		return option;
	}

	return isSubjectFirst() ?
		null :
		{ ...option, deniedReason: mw.msg( 'neowiki-subject-creator-page-new-denied' ) };
}

/**
 * Whether the dialog asks which page the Subject goes on. A subject-first wiki gives it a page of
 * its own, so there is nothing to ask - unless the caller named a page, or this user cannot create
 * one and so could not be given it either way (ADR 33). A caller that passed no page= named no
 * page, whatever choice it preselected.
 */
const pageQuestionAsked = computed( (): boolean =>
	!isSubjectFirst() ||
	props.initialPage?.page !== undefined ||
	!canCreateSubjectPage.value );

// The page being viewed where there is one, and a page of the Subject's own where there is not, or
// where the wiki gives every Subject one.
function defaultPageChoice(): SubjectPageChoice {
	if ( props.initialPage !== undefined ) {
		return props.initialPage.choice;
	}

	if ( !pageQuestionAsked.value ) {
		return 'newPage';
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

// The answer the page question starts from. On a page-first wiki the section starts open where
// that answer still needs something filled in, a title or a page to pick, rather than leaving it
// for a save to refuse.
function startPageQuestion(): void {
	pageChoice.value = defaultPageChoice();
	pageSectionOpen.value = !isSubjectFirst() && pageChoice.value !== 'thisPage';
}

// A new page is titled here, unless the caller titled it, or the wiki is subject-first and titles
// a Subject's own page itself (ADR 33).
const asksPageTitle = computed( (): boolean =>
	pageChoice.value === 'newPage' && props.initialPage?.page === undefined && !isSubjectFirst() );

// Where a title is asked for, it follows the label of the Subject being created, so its name is
// typed once, until the user types a title of their own.
const pageTitle = computed( {
	get: (): string => {
		if ( typedPageTitle.value !== null ) {
			return typedPageTitle.value;
		}

		return asksPageTitle.value ? editorRef.value?.rootLabel ?? '' : '';
	},
	set: ( title: string ): void => {
		typedPageTitle.value = title;
	}
} );

// The notices belong to the page the dialog was opened on, so they say nothing about a Subject
// going anywhere else.
const shownNotices = computed( () => pageChoice.value === 'thisPage' ? notices.value : [] );

// Each choice answers the page question its own way, so what the previous one answered is gone.
watch( pageChoice, resetPageChoice );

// Only a choice the user makes moves focus; the ones the dialog makes itself do not.
function choosePage( choice: SubjectPageChoice ): void {
	pageChoice.value = choice;
	focusChosenInput();
}

// One message per field: each of these belongs to a different page choice, so no two of them can
// be standing at once.
const pageError = computed( (): string | null => pageTitleError.value ?? pageReadError.value );

// Answered, and answerable: a page still to be picked leaves the question open, and a page that
// could not be read leaves it unanswerable — what that page holds decides whether the Subject
// becomes its topic or joins it, and that is not to be guessed at. A title the server refused
// blocks neither: the answer, another title, is what the next save tries.
const pageChosen = computed( (): boolean =>
	pageChoice.value !== null && pageReadError.value === null &&
	( pageChoice.value !== 'anotherPage' || chosenPage.value !== null ) );

const pageFieldStatus = computed( (): ValidationStatusType =>
	pageReadError.value === null ? 'default' : 'error' );

const pageFieldMessages = computed( (): ValidationMessages =>
	pageReadError.value === null ? {} : { error: pageReadError.value } );

// A collapsed section would hide what was refused, and the field it belongs to with it. A refused
// title also takes focus to its field, a task later, once the save has let go of it; a failed page
// read can land after the user has moved on, so it leaves focus alone.
// Watched synchronously: a save refused for a title still missing sets the same message again,
// which a deferred watcher would not see change.
watch( pageError, ( error ) => {
	if ( error === null ) {
		return;
	}

	pageSectionOpen.value = true;

	if ( pageTitleError.value !== null ) {
		setTimeout( focusChosenInput );
	}
}, { flush: 'sync' } );

// The element opens and closes itself, so its own state is what the summary and the next open
// follow, rather than the value last rendered onto it. Rendered open, it reports opening as a
// click does, so only an opening from closed moves focus.
function onPageSectionToggle( event: Event ): void {
	const open = ( event.target as HTMLDetailsElement ).open;
	const openedFromClosed = open && !pageSectionOpen.value;

	pageSectionOpen.value = open;

	if ( openedFromClosed ) {
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

// The title the page to create is given, as a write reads it: none where it is blank.
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
		// Below a fixed page's summary, the title has a field that says it.
		const title = pageFixed.value && asksPageTitle.value ? null : enteredPageTitle();

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
	typedPageTitle.value = null;
	pageTitleError.value = null;
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

// The picker is rendered once this answer is in: Codex's lookup decides when it is created whether
// focus opens its menu, and on a wiki without Schemas the offer to create one is all that menu holds.
const schemaPermissionKnown = ref( false );
const schemaPermissionChecked = checkCreatePermission().then( () => {
	schemaPermissionKnown.value = true;
} );

const { hasChanged: formChanged, markChanged, resetChanged } = useChangeDetection();

// The page question answered, where it was the user's to answer: a page picked, or a title typed
// where one is asked for. An answer taken back counts for nothing, so a dialog whose answer was
// withdrawn does not go on asking to be discarded.
const pageAnswered = computed( (): boolean =>
	( !pageFixed.value && chosenPage.value !== null ) ||
	( asksPageTitle.value && ( typedPageTitle.value ?? '' ).trim() !== '' ) );

// Answering the page question is not an edit of the form, but it is still something a close would
// throw away.
const hasChanged = computed( (): boolean => formChanged.value || pageAnswered.value );

function close(): void {
	emit( 'update:open', false );
}

const { confirmationOpen, requestClose, confirmClose, cancelClose } = useCloseConfirmation( hasChanged, close );

// Its backdrop is outside the inert part of the step, and its clicks count for nothing either.
function onSchemaStepUpdateOpen( value: boolean ): void {
	if ( !value && !editorStarting.value ) {
		requestClose();
	}
}

// The editor has asked about discarding already. One that closes before it shows could not read
// what the Subject's form needs, and has said so: the Schema step stays, to pick again from.
function onEditorUpdateOpen( value: boolean ): void {
	if ( value ) {
		return;
	}

	if ( editorShown.value ) {
		close();
	} else {
		leaveSchemaStepOpen();
	}
}

onMounted( async () => {
	await Promise.all( [ schemaPermissionChecked, checkCreateSubjectPagePermission() ] );
	startPageQuestion();

	// Already open: the open watcher ran before pageChoice existed.
	if ( props.open ) {
		await applyInitialPageTarget();
	}
} );

function openSchemaCreator( schemaName: string ): void {
	requestSequence++;
	newSchemaName.value = schemaName;
}

// The wiki saved the Schema under its page title, which is the name the picker would list it by.
async function onSchemaCreated( schema: Schema ): Promise<void> {
	await onSchemaSelected( normalizeSchemaName( schema.getName() ) );
}

async function onSchemaSelected( schemaName: string ): Promise<void> {
	if ( !schemaName ) {
		return;
	}

	markChanged();
	await loadSchema( schemaName );
}

const offeredSchemaNames = computed( (): readonly string[] | undefined => props.relationTarget?.schemaNames );

const givenSubjectId = computed( (): SubjectId | undefined => props.relationTarget?.subjectId );

async function loadSchema( schemaName: string ): Promise<void> {
	const currentSequence = ++requestSequence;

	try {
		// The id is settled alongside the Schema because the step it opens cannot start without
		// one: the panes and the navigator name the Subject by it, and so does a relation recorded
		// against it before anything has been written.
		const [ schema, id ] = await Promise.all( [
			schemaRepo.getSchema( schemaName ),
			givenSubjectId.value ?? subjectRepository().mintSubjectId()
		] );

		// The picker leaves these Schemas out, so only a Schema a caller names gets here.
		if ( schema.subjectsAreDependent() ) {
			if ( currentSequence === requestSequence ) {
				leaveSchemaStepOpen();
				mw.notify( mw.msg( 'neowiki-create-subject-error-dependent-subjects', schema.getName() ), { type: 'error' } );
			}

			return;
		}

		if ( currentSequence !== requestSequence ) {
			return;
		}

		openSubjectStep( schema, id );
	} catch ( error ) {
		if ( currentSequence !== requestSequence ) {
			return;
		}

		console.error( 'Failed to load schema:', error );
		leaveSchemaStepOpen();
	}
}

function leaveSchemaStepOpen(): void {
	loadedSchema.value = null;
	rootSubject.value = null;
}

function openSubjectStep( schema: Schema, id: SubjectId ): void {
	// A close the Schema step was still asking about goes with the step, or the question would come
	// back by itself the next time that step shows.
	cancelClose();

	loadedSchema.value = schema;
	rootSubject.value = new Subject(
		id,
		null,
		// What the server derives for a Subject nobody has named (ADR 31).
		schema.getName(),
		true,
		schema.getName(),
		new StatementList( [] ),
		UNANSWERED_PAGE,
		null
	);
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

async function handleCreate( subject: Subject, pageId: number, dependentSubjects: DependentSubjects, comment: string ): Promise<void> {
	if ( subject.getId().text === rootSubject.value?.getId().text ) {
		try {
			await writeRootSubject( subject, dependentSubjects, comment );
		} catch ( error ) {
			throw refusalOfGivenId( error );
		}
		return;
	}

	// A subject-first wiki gives every new Subject a page of its own, whatever page it is handed. On
	// a page-first wiki, a target created from a pane the user drilled into belongs on the page that
	// pane's Subject is stored on, which the editor resolved, and one created against the Subject
	// being created carries no page of its own and follows it instead.
	const page = isSubjectFirst() || pageId > 0 ? pageId : writtenRoot?.pageId ?? null;

	if ( page === null ) {
		throw new Error( mw.msg( 'neowiki-subject-creator-error' ) );
	}

	await subjectStore.createSubject( subject, page, dependentSubjects, comment );
}

/**
 * The editor takes an id in use as this very create having landed, which holds only for an id minted
 * here. A given id in use is another Subject's: the create was refused.
 */
function refusalOfGivenId( error: unknown ): unknown {
	if ( givenSubjectId.value === undefined || !( error instanceof SubjectIdInUseError ) ) {
		return error;
	}

	return new Error( mw.msg( 'neowiki-subject-creator-id-in-use', givenSubjectId.value.text ) );
}

async function writeRootSubject( subject: Subject, dependentSubjects: DependentSubjects, comment: string ): Promise<void> {
	// A save that stopped part way has created it already, and the answer it got says where. Creating
	// it again would be refused: its id, and any page title it was given, are taken now.
	if ( writtenRoot !== null ) {
		await subjectStore.updateSubject( asWrittenRoot( subject, writtenRoot ), dependentSubjects, comment );
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

	const label = subject.getLabel();
	const schemaName = subject.getSchemaName();
	const statements = subject.getStatements();

	if ( answer.goingTo === 'newPage' ) {
		const created = await createOnNewPage( subject, dependentSubjects, comment, answer.title );

		writtenRoot = { subjectId: created.subjectId, pageId: created.pageId, pageTitle: created.pageTitle };
		return;
	}

	const pageId = answer.pageId;

	if ( pageId === null ) {
		throw new Error( mw.msg( 'neowiki-subject-creator-error' ) );
	}

	const subjectId = answer.besideMainSubject ?
		await createBesideMainSubject( pageId, label, schemaName, statements, dependentSubjects, comment, subject.getId() ) :
		await subjectStore.createMainSubject( pageId, label, schemaName, statements, dependentSubjects, comment, subject.getId() );

	writtenRoot = { subjectId, pageId, pageTitle: answer.pageTitle };
}

/**
 * The Subject as it now stands, placed where the first pass created it, for a second pass over
 * that root.
 */
function asWrittenRoot( subject: Subject, written: { subjectId: SubjectId; pageId: number | null; pageTitle: string | null } ): Subject {
	return new Subject(
		written.subjectId,
		subject.getLabel(),
		subject.getDisplayName(),
		subject.hasGeneratedDisplayName(),
		subject.getSchemaName(),
		subject.getStatements(),
		new PageIdentifiers( written.pageId ?? 0, written.pageTitle ?? '' ),
		subject.getHostSubject()
	);
}

/**
 * Adds the Subject beside a page's Main Subject, under its id. An id minted for it here was minted
 * for this Subject alone, so the server holding it already means this very create landed and only
 * its answer was lost: the id it refused is the id the Subject has. Reporting a failure instead would
 * leave the dialog with no record of what it made, and every retry would be refused the same way.
 */
async function createBesideMainSubject(
	pageId: number,
	label: string | null,
	schemaName: string,
	statements: StatementList,
	dependentSubjects: DependentSubjects,
	comment: string,
	id: SubjectId
): Promise<SubjectId> {
	try {
		return await subjectStore.createOtherSubject( pageId, label, schemaName, statements, dependentSubjects, comment, id );
	} catch ( error ) {
		if ( error instanceof SubjectIdInUseError && givenSubjectId.value === undefined ) {
			return id;
		}

		throw error;
	}
}

/**
 * A title missing, or one the server refuses, is answered at the field it is given in, and stops
 * the save as a refusal the dialog says itself: the writes stop there, with no toast to repeat it.
 */
async function createOnNewPage(
	subject: Subject,
	dependentSubjects: DependentSubjects,
	comment: string,
	title: string | null
): Promise<CreatedSubjectPage> {
	if ( isSubjectFirst() ) {
		return await subjectStore.createSubjectOnOwnPage( subject, dependentSubjects, comment );
	}

	// Re-decided on each attempt, so a title given since the last one clears what it answered.
	pageTitleError.value = null;

	if ( title === null ) {
		pageTitleError.value = mw.msg( 'neowiki-subject-creator-page-title-required' );

		throw new WriteRefusedError( pageTitleError.value );
	}

	try {
		return await subjectStore.createSubjectPage(
			subject.getLabel(),
			subject.getSchemaName(),
			subject.getStatements(),
			dependentSubjects,
			comment,
			title,
			subject.getId()
		);
	} catch ( error ) {
		if ( error instanceof PageTitleTakenError ) {
			// A caller that fixed a new page leaves only its title to change: that page is no answer.
			pageTitleError.value = mw.msg(
				pageFixed.value && asksPageTitle.value ?
					'neowiki-subject-creator-page-taken-retitle' :
					'neowiki-subject-creator-page-taken',
				error.pageTitle
			);
			throw new WriteRefusedError( pageTitleError.value );
		}

		if ( error instanceof InvalidPageTitleError ) {
			pageTitleError.value = mw.msg( 'neowiki-subject-creator-page-title-invalid', error.pageTitle );
			throw new WriteRefusedError( pageTitleError.value );
		}

		throw error;
	}
}

// A changed title answers the one refused, whichever way it was refused, typed or filled in from
// the label.
watch( pageTitle, () => {
	pageTitleError.value = null;
} );

// A Subject the user drilled into from a relation exists already, so the editor updates it.
async function handleSaveExisting( subject: Subject, dependentSubjects: DependentSubjects, comment: string ): Promise<void> {
	await subjectStore.updateSubject( subject, dependentSubjects, comment );
}

async function handleSchemaSave( updatedSchema: Schema, comment: string ): Promise<void> {
	await schemaStore.saveSchema( updatedSchema, comment );
	loadedSchema.value = updatedSchema;
}

/**
 * Where the dialog leaves for is the wiki's mode (ADR 33). A subject-first wiki is about the
 * Subject, so it leaves for the Subject itself, which shows what was created. A page-first wiki is
 * about the page, so it leaves for the page the Subject went on, with a notice that it was created;
 * a null title is the page being viewed, which is reloaded rather than navigated to.
 */
function handleSaved(): void {
	if ( writtenRoot === null ) {
		return;
	}

	if ( isSubjectFirst() ) {
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
		await schemaPermissionChecked;
		await nextTick();

		// A pinned Schema has moved on to the editor.
		if ( rootSubject.value === null ) {
			schemaLookupRef.value?.focus();
		}
	} else {
		resetForm();
	}
} );

const pinnedSchemaName = computed( (): string | undefined =>
	props.initialSchemaName ?? ( offeredSchemaNames.value?.length === 1 ? offeredSchemaNames.value[ 0 ] : undefined ) );

// A Schema that cannot be loaded leaves the picker to do its job rather than a second step with
// nothing to edit.
async function pinInitialSchema(): Promise<void> {
	if ( pinnedSchemaName.value === undefined ) {
		return;
	}

	// The load's own catch clears the Schema and the Subject together, which leaves the picker
	// showing; nothing further is needed to fall back to it.
	await loadSchema( pinnedSchemaName.value );
}

function resetForm(): void {
	requestSequence++;
	loadedSchema.value = null;
	rootSubject.value = null;
	writtenRoot = null;

	// Back to where a fresh open starts, unless the permission answer that decides it has yet to
	// land, in which case no choice is being offered to reset.
	if ( pageChoice.value !== null ) {
		startPageQuestion();
	}

	resetPageChoice();
	resetChanged();
}

defineExpose( { hasChanged } );
</script>

<style lang="less">
@import ( reference ) '@wikimedia/codex-design-tokens/theme-wikimedia-ui.less';

.ext-neowiki-subject-creator {
	/* Sibling of the edit-summary section below it, and built to read as one. */
	&-page-section.cdx-accordion {
		border-bottom: 0;
		/* Codex 2.3.2 and later hide an accordion's overflow, which would cut off the page picker's menu. */
		overflow: visible;

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

	/* Codex runs an inline radio's description on from its label; as the label's own column, the
		reason a page option cannot be chosen sits under it. */
	&-page-field .cdx-radio--inline .cdx-radio__label {
		display: inline-flex;
		white-space: normal;
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
}
</style>
