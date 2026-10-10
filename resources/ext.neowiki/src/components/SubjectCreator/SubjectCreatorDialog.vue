<!-- eslint-disable vue/no-multiple-template-root -->
<template>
	<!-- Unmounted rather than closed once the Schema is settled: setting a Codex dialog's own open
		prop to false here would be indistinguishable from the user closing it. -->
	<CdxDialog
		v-if="rootSubject === null"
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
		<template #before-actions>
			<CdxMessage
				v-if="footerError !== null"
				type="error"
				:inline="true"
			>
				{{ footerError }}
			</CdxMessage>

			<!-- Which of the two it is decides whether the Subject becomes the page's topic or joins
				what is already there, and the button says only where it is going. -->
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
		</template>

		<template #action="{ save, saving, disabled }">
			<!-- Nothing to ask, so nothing to open: a destination the caller fixed, a wiki that gives
				every Subject a page of its own, or an answer not yet known. -->
			<CdxButton
				v-if="!destinationPopoverOffered"
				action="progressive"
				weight="primary"
				:disabled="disabled"
				@click="save"
			>
				<CdxIcon :icon="cdxIconCheck" />
				{{ createButtonLabel }}
			</CdxButton>

			<NeoSplitButton
				v-else
				:open="destinationOpen"
				:label="createButtonLabel"
				:toggle-label="destinationToggleLabel"
				:disabled="saving"
				:action-disabled="disabled"
				:icon="cdxIconCheck"
				@click="saveFromHere( save )"
				@update:open="onDestinationOpenChanged( $event )"
			>
				<PagePickerPanel
					:pinned-pages="pinnedPages"
					:existing-pages-only="destinationIsOpenToChange && onlyExistingPagesMayBeChosen"
					:new-page-only="newPageTitleIsOpenToChange"
					:aria-label="$i18n( 'neowiki-subject-creator-page-field' ).text()"
					:error="pageTitleError"
					@update:selected="onDestinationPicked"
				/>
			</NeoSplitButton>
		</template>
	</SubjectEditorDialog>

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
import { CdxButton, CdxDialog, CdxIcon, CdxMessage, CdxToggleButtonGroup } from '@wikimedia/codex';
import { cdxIconAdd, cdxIconArrowNext, cdxIconCheck, cdxIconSearch } from '@wikimedia/codex-icons';
import type { ButtonGroupItem } from '@wikimedia/codex';
import { useSubjectStore } from '@/stores/SubjectStore.ts';
import type { CreatedSubjectPage } from '@/stores/SubjectStore.ts';
import { useSchemaStore } from '@/stores/SchemaStore.ts';
import { Schema } from '@/domain/Schema.ts';
import { StatementList } from '@/domain/StatementList.ts';
import { Subject } from '@/domain/Subject.ts';
import { PageIdentifiers } from '@/domain/PageIdentifiers.ts';
import type { SubjectId } from '@/domain/SubjectId.ts';
import type { SubjectRepository } from '@/domain/SubjectRepository.ts';
import { subjectDisplayName } from '@/presentation/subjectDisplayName.ts';
import SubjectEditorDialog from '@/components/SubjectEditor/SubjectEditorDialog.vue';
import SchemaCreator from '@/components/SchemaCreator/SchemaCreator.vue';
import type { SchemaCreatorExposes } from '@/components/SchemaCreator/SchemaCreator.vue';
import SchemaPicker from '@/components/common/SchemaPicker.vue';
import CloseConfirmationDialog from '@/components/common/CloseConfirmationDialog.vue';
import NeoSplitButton from '@/components/common/NeoSplitButton.vue';
import PagePickerPanel from '@/components/common/PagePickerPanel.vue';
import type { PinnedPage } from '@/composables/usePageSearch.ts';
import I18nSlot from '@/components/common/I18nSlot.vue';
import type { PageChoice } from '@/components/common/PageChoice.ts';
import type { InitialPage, SubjectPageChoice } from '@/components/SubjectCreator/InitialPage.ts';
import { PageTitleTakenError } from '@/persistence/PageTitleTakenError.ts';
import { InvalidPageTitleError } from '@/persistence/InvalidPageTitleError.ts';
import { SubjectIdInUseError } from '@/persistence/SubjectIdInUseError.ts';
import { WriteRefusedError } from '@/components/SubjectEditor/WriteRefusedError.ts';
import SchemaAbandonmentDialog from '@/components/SubjectCreator/SchemaAbandonmentDialog.vue';
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

const selectedSchemaOption = ref( 'existing' );
const { notices, loadNotices } = useEditNotices( () => NeoWikiExtension.getInstance().getEditNoticeRepository() );

const loadedSchema = ref<Schema | null>( null );
// eslint-disable-next-line @typescript-eslint/no-explicit-any
const schemaLookupRef = ref<any | null>( null );
const schemaCreatorRef = ref<SchemaCreatorExposes | null>( null );

const draftSchema = shallowRef<Schema | null>( null );

/**
 * The Subject being created, as the editor dialog holds it: an id of its own, so the panes and the
 * navigator can name it and a relation can be recorded against it, and no label until someone types one.
 * Non-null is what puts the dialog on its second step.
 */
const rootSubject = shallowRef<Subject | null>( null );

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

const editorRef = ref<InstanceType<typeof SubjectEditorDialog> | null>( null );

// The title given in the popover, or by the caller; while null, the title follows the label.
const typedPageTitle = ref<string | null>( null );

// What was refused about the title of the page to create: none given, one already taken, or
// one that titles no page. Only one can stand at a time, so they share the slot they are shown in.
const pageTitleError = ref<string | null>( null );
const pageReadError = ref<string | null>( null );

const { canCreateSubjectPage, checkCreateSubjectPagePermission } = useSubjectPermissions();

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

// A new page is titled here, unless the caller titled it, or the wiki is subject-first and titles
// a Subject's own page itself (ADR 33).
const asksPageTitle = computed( (): boolean =>
	pageChoice.value === 'newPage' && props.initialPage?.page === undefined && !isSubjectFirst() );

// Where a title is asked for, it follows the label of the Subject being created, so its name is
// typed once, until the user types a title of their own.
const pageTitle = computed( (): string => {
	if ( typedPageTitle.value !== null ) {
		return typedPageTitle.value;
	}

	return asksPageTitle.value ? editorRef.value?.rootLabel ?? '' : '';
} );

// The destination is stated by the button; this is the picker it opens to change it.
const destinationOpen = ref( false );

function hostPageId(): number {
	return Number( mw.config.get( 'wgArticleId' ) );
}

function hostPageName(): string {
	return String( mw.config.get( 'wgPageName' ) ?? '' ).replace( /_/g, ' ' );
}

/**
 * Read when the popover opens rather than as it renders: the label cannot be edited while the
 * popover is over it, and a fresh array on every render would rebuild the menu under the user.
 */
const labelWhenDestinationOpened = ref( '' );

/**
 * Shortcuts past searching: the page being viewed, and a page of the Subject's own. Everything else
 * is found by typing. Each is named by the page it leads to, as the results are, and says under
 * that which of the two it is - so one column of the list is page names throughout. Where a page of
 * its own cannot be given, the shortcut is shown saying why rather than left out, so the option is
 * accounted for.
 */
const pinnedPages = computed( (): PinnedPage[] => {
	const pinned: PinnedPage[] = [];

	if ( !destinationIsOpenToChange.value ) {
		return pinned;
	}

	if ( props.hostPage !== null ) {
		pinned.push( {
			label: hostPageName(),
			description: mw.msg( 'neowiki-subject-creator-page-this' ),
			page: { pageId: hostPageId(), title: hostPageName() }
		} );
	}

	// Picking it titles the page after the label, so it is named after the label, whatever title
	// was typed since: that title is the button's to state. Without a label it is named by what it
	// is, which then needs no saying underneath.
	const label = labelWhenDestinationOpened.value.trim();
	const newPage = mw.msg( 'neowiki-subject-creator-page-new' );

	if ( canCreateSubjectPage.value ) {
		pinned.push( {
			label: label || newPage,
			description: label === '' ? undefined : newPage,
			page: { pageId: null, title: '' }
		} );
	} else if ( !isSubjectFirst() ) {
		pinned.push( {
			label: label || newPage,
			description: mw.msg( 'neowiki-subject-creator-page-new-denied' ),
			disabled: true,
			page: { pageId: null, title: '' }
		} );
	}

	return pinned;
} );

function onDestinationOpenChanged( open: boolean ): void {
	if ( open ) {
		openDestination();
		return;
	}

	destinationOpen.value = false;
}

function openDestination(): void {
	labelWhenDestinationOpened.value = editorRef.value?.rootLabel ?? '';
	destinationOpen.value = true;
}

/**
 * A subject-first wiki titles a created page by the Subject's id whatever title it is asked for,
 * so offering to create one under a typed name would promise a title the save throws away.
 */
const onlyExistingPagesMayBeChosen = computed( (): boolean =>
	!canCreateSubjectPage.value || isSubjectFirst() );

/**
 * Whether the destination is the user's to change here. It is not where the caller fixed it, nor on
 * a wiki that gives every Subject a page of its own, nor before the answer to what the options are
 * has arrived - offering a choice before that would change it under whoever had already answered.
 */
const destinationIsOpenToChange = computed( (): boolean =>
	!pageFixed.value && pageQuestionAsked.value && pageChoice.value !== null );

/**
 * A caller that fixed the destination to a page of the Subject's own fixed where the Subject goes,
 * not what the page is called. The title is still the user's to give, and the popover is where a
 * page to create is named, so it opens there with nothing in it but that - no other page being
 * choosable without unfixing what the caller fixed. A caller that named the page left nothing
 * to give.
 */
const newPageTitleIsOpenToChange = computed( (): boolean =>
	pageFixed.value && asksPageTitle.value );

const destinationPopoverOffered = computed( (): boolean =>
	destinationIsOpenToChange.value || newPageTitleIsOpenToChange.value );

// The chevron changes where the Subject goes, or - the destination being fixed - only its name.
const destinationToggleLabel = computed( (): string => mw.msg(
	destinationIsOpenToChange.value ?
		'neowiki-subject-creator-page-change' :
		'neowiki-subject-creator-page-retitle'
) );

// The popover stands over the footer, so a save that fails would report itself underneath it.
function saveFromHere( save: () => void ): void {
	destinationOpen.value = false;
	save();
}

/** The button says where the Subject is going, the choice being folded into it. */
const createButtonLabel = computed( (): string => {
	// A page of the Subject's own on a subject-first wiki is titled by the wiki and has no name to
	// state, so the button is left to say what it does.
	if ( isSubjectFirst() && pageChoice.value === 'newPage' ) {
		return mw.msg( 'neowiki-subject-creator-save' );
	}

	if ( pageChoice.value === 'thisPage' ) {
		return mw.msg( 'neowiki-subject-creator-save-on-this-page' );
	}

	if ( pageChoice.value === 'anotherPage' ) {
		const title = chosenPage.value?.title;

		return title === undefined ?
			mw.msg( 'neowiki-subject-creator-save' ) :
			mw.msg( 'neowiki-subject-creator-save-on-page', title );
	}

	const title = enteredPageTitle();

	return title === null ?
		mw.msg( 'neowiki-subject-creator-save-on-a-new-page' ) :
		mw.msg( 'neowiki-subject-creator-save-on-new-page', title );
} );

/**
 * Answers the page question from one picked page, which is all the picker reports: a page that
 * exists is joined, and one that does not is created under the title typed for it.
 */
async function onDestinationPicked( picked: PageChoice | null ): Promise<void> {
	// Emptying the field is not an answer, and un-answering would leave the button naming a
	// destination nothing had replaced.
	if ( picked === null ) {
		return;
	}

	destinationOpen.value = false;
	resetPageChoice();

	if ( picked.pageId === null ) {
		pageChoice.value = 'newPage';
		// The choice watcher resets what the previous answer left, so the title follows it. An
		// empty title is the shortcut rather than a title typed, and leaves the label to name the
		// page, which is what resetPageChoice has just put back.
		await nextTick();

		if ( picked.title !== '' ) {
			typedPageTitle.value = picked.title;
		}

		return;
	}

	// Only where the dialog has a page of its own: another surface may sit on a page with an id all
	// the same - a Schema page, say - which the search can turn up and which is not "this page".
	if ( props.hostPage !== null && picked.pageId === hostPageId() ) {
		pageChoice.value = 'thisPage';
		return;
	}

	pageChoice.value = 'anotherPage';
	await nextTick();
	await onPageSelected( picked );
}

// The notices belong to the page the dialog was opened on, so they say nothing about a Subject
// going anywhere else.
const shownNotices = computed( () => pageChoice.value === 'thisPage' ? notices.value : [] );

// Each choice answers the page question its own way, so what the previous one answered is gone.
watch( pageChoice, resetPageChoice );

// One message at a time: each of these belongs to a different page choice, so no two of them can
// be standing at once. A refused title is shown in the popover instead while that is open, since
// the popover stands over the footer.
const footerError = computed( (): string | null =>
	destinationOpen.value ? pageReadError.value : pageTitleError.value ?? pageReadError.value );

// Answered, and answerable: a page still to be picked leaves the question open, and a page that
// could not be read leaves it unanswerable — what that page holds decides whether the Subject
// becomes its topic or joins it, and that is not to be guessed at. A title the server refused
// blocks neither: the answer, another title, is what the next save tries.
const pageChosen = computed( (): boolean =>
	pageChoice.value !== null && pageReadError.value === null &&
	( pageChoice.value !== 'anotherPage' || chosenPage.value !== null ) );

function enteredPageTitle(): string | null {
	const entered = pageTitle.value.trim();

	return entered === '' ? null : entered;
}

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
		typedPageTitle.value = named.title;
		return;
	}

	await onPageSelected( named );
}

// The Schema's own notices cannot apply before there is a Schema, and once there is one the editor
// dialog fetches them itself. These are the page's, for the step that comes first.
watch( () => props.open, ( isOpen ) => {
	if ( isOpen && props.hostPage !== null ) {
		loadNotices( hostPageId() );
	}
} );

const schemaStore = useSchemaStore();
const schemaRepo = NeoWikiServices.getSchemaRepository();
const { canCreateSchemas, checkCreatePermission } = useSchemaPermissions();
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
		// one: the panes and the navigator name the Subject by it, and so does a relation recorded
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
	rootSubject.value = new Subject(
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

	// Continuing now would freeze a draft schema with the unparseable text dropped, or
	// one the wiki refuses once the Subject is saved.
	const blocker = schemaCreatorRef.value.saveBlocker();

	if ( blocker !== null ) {
		mw.notify( blocker.message, { title: blocker.propertyName, type: 'error' } );
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
		return hostPageId();
	}

	return pageChoice.value === 'anotherPage' ? chosenPage.value?.pageId ?? null : null;
}

/**
 * Where the root's write put it, once it has landed. Taken from the write rather than read again
 * from the footer, which stays live while the rest of the save is out: a Subject created alongside
 * the root goes where the root went, whatever the footer says by then.
 */
let writtenRoot: { subjectId: SubjectId; pageId: number | null; pageTitle: string | null } | null = null;

async function handleCreate( subject: Subject, pageId: number, comment: string ): Promise<void> {
	if ( subject.getId().text === rootSubject.value?.getId().text ) {
		await writeRootSubject( subject, comment );
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

	await subjectStore.createSubject( subject, page, comment );
}

async function writeRootSubject( subject: Subject, comment: string ): Promise<void> {
	// A save that stopped part way has created it already, and the answer it got says where. Creating
	// it again would be refused: its id, and any page title it was given, are taken now.
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

	const label = subject.getLabel();
	const schemaName = subject.getSchemaName();
	const statements = subject.getStatements();

	if ( answer.goingTo === 'newPage' ) {
		const created = await createOnNewPage( subject, comment, answer.title );

		writtenRoot = { subjectId: created.subjectId, pageId: created.pageId, pageTitle: created.pageTitle };
		return;
	}

	const pageId = answer.pageId;

	if ( pageId === null ) {
		throw new Error( mw.msg( 'neowiki-subject-creator-error' ) );
	}

	await saveDraftSchema( comment );

	const subjectId = answer.besideMainSubject ?
		await createBesideMainSubject( pageId, label, schemaName, statements, comment, subject.getId() ) :
		await subjectStore.createMainSubject( pageId, label, schemaName, statements, comment, subject.getId() );

	writtenRoot = { subjectId, pageId, pageTitle: answer.pageTitle };
}

/**
 * The Schema drafted in the step before goes onto the wiki with the Subject that uses it, and only
 * once nothing is left to refuse ahead of that Subject's write: refused, the save would leave it
 * behind, no longer offered for abandoning.
 */
async function saveDraftSchema( comment: string ): Promise<void> {
	if ( draftSchema.value === null ) {
		return;
	}

	await schemaStore.saveSchema( draftSchema.value, comment );
	draftSchema.value = null;
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

/**
 * A title missing, or one the server refuses, stops the save as a refusal the dialog says itself,
 * where a title is given.
 */
async function createOnNewPage(
	subject: Subject,
	comment: string,
	title: string | null
): Promise<CreatedSubjectPage> {
	if ( isSubjectFirst() ) {
		await saveDraftSchema( comment );
		return await subjectStore.createSubjectOnOwnPage( subject, comment );
	}

	// Re-decided on each attempt, so a title given since the last one clears what it answered.
	pageTitleError.value = null;

	// A page-first wiki names a page after the Subject, so a Subject nobody has named leaves it
	// nothing to be called and the question comes back rather than a page titled by an id.
	if ( title === null ) {
		refuseTitle( mw.msg( 'neowiki-subject-creator-page-title-required' ) );
	}

	await saveDraftSchema( comment );

	try {
		return await subjectStore.createSubjectPage(
			subject.getLabel(),
			subject.getSchemaName(),
			subject.getStatements(),
			comment,
			title,
			subject.getId()
		);
	} catch ( error ) {
		if ( error instanceof PageTitleTakenError ) {
			// Where only the title is the user's to change, choosing that page instead is no answer.
			refuseTitle( mw.msg(
				newPageTitleIsOpenToChange.value ?
					'neowiki-subject-creator-page-taken-retitle' :
					'neowiki-subject-creator-page-taken',
				error.pageTitle
			) );
		}

		if ( error instanceof InvalidPageTitleError ) {
			refuseTitle( mw.msg( 'neowiki-subject-creator-page-title-invalid', error.pageTitle ) );
		}

		throw error;
	}
}

/**
 * Stops the save over the title of the page to create, saying why where a title is given: in the
 * popover, opened a task later, once the save has let go of the footer.
 */
function refuseTitle( reason: string ): never {
	pageTitleError.value = reason;

	if ( destinationPopoverOffered.value ) {
		setTimeout( openDestination );
	}

	throw new WriteRefusedError( reason );
}

// A changed title answers the one refused, whichever way it was refused, typed or filled in from
// the label.
watch( pageTitle, () => {
	pageTitleError.value = null;
} );

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

	// Back to where a fresh open starts, unless the permission answer that decides it has yet to
	// land, in which case no choice is being offered to reset.
	if ( pageChoice.value !== null ) {
		pageChoice.value = defaultPageChoice();
	}

	resetPageChoice();
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

	/* The new-Schema step gives the Schema creator the height left below the content above
		it, which keeps its natural height, so the Schema editor's columns scroll on their own.
		Only where they are side by side: stacked, the body scrolls the whole step. */
	&-dialog--wide .cdx-dialog__body {
		@media ( min-width: @min-width-breakpoint-desktop ) {
			display: flex;
			flex-direction: column;
			padding-block-end: 0;

			> :not( .ext-neowiki-subject-creator-new ) {
				flex-shrink: 0;
			}

			> .ext-neowiki-subject-creator-new {
				display: grid;
				min-height: 0;
			}
		}
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
