<template>
	<div class="ext-neowiki-subject-editor-container">
		<CdxDialog
			:open="visible"
			class="ext-neowiki-ui ext-neowiki-subject-editor-dialog"
			:class="{
				'ext-neowiki-subject-editor-dialog--wide': showsNavigator,
				'ext-neowiki-dialog--takes-over': props.rootIsNew === true
			}"
			:style="grownHeightStyle"
			:title="dialogTitle"
			@update:open="onDialogUpdateOpen"
		>
			<template #header>
				<DialogHeader
					:title="dialogTitle"
					:use-close-button="true"
					@close="closeRequested"
				>
					<!-- Whatever the host offers ahead of the title, such as a way back to where it opened
						this dialog from. Handed the save's progress, as the header is not inert during it. -->
					<template
						v-if="$slots[ 'start-actions' ]"
						#start-actions
					>
						<slot name="start-actions" :saving="saving" />
					</template>

					<template #metadata>
						<p
							v-if="headerSubjectId !== undefined"
							class="ext-neowiki-subject-editor-dialog__subject-id"
						>
							{{ headerSubjectId }}
						</p>

						<!-- A new tab: this dialog holds unsaved edits for every open pane and nothing
							guards a navigation away from it. Inert during a save, like the form. -->
						<SchemaNameDisplay
							v-if="activePane !== undefined"
							:schema-name="activePane.subject.getSchemaName()"
							:display-name="schemaDisplayName( activePane.schema )"
							link="new-tab"
							:inert="saving || undefined"
							@click="openSchemaEditor"
						/>
					</template>
				</DialogHeader>
			</template>

			<!-- The panes container is deliberately unkeyed: a pane must never unmount, because its
				unsaved values live in the ValueInput refs inside it. Putting the choices made in an edit
				conflict into it is the exception: they replace what was typed. -->
			<div
				ref="content"
				class="ext-neowiki-subject-editor-dialog__content"
				:inert="saving || undefined"
				:aria-busy="pending || undefined"
			>
				<EditNoticeList :notices="notices" />

				<EditConflictEditor
					v-if="conflict !== null"
					class="ext-neowiki-subject-editor-dialog__conflict"
					:conflict="conflict.editConflict"
					:subject-name="conflict.subjectName"
					:choices="conflict.choices"
					:name-of="conflictSubjectName"
					:property-definition-of="conflictPropertyOf"
					@choose="chooseInConflict"
				/>

				<EditorPanes
					v-show="conflict === null"
					ref="editorPanes"
					class="ext-neowiki-subject-editor-dialog__panes"
					:start-pane="{ ...NAVIGATOR_SIZE, resizeLabel: $i18n( 'neowiki-subject-editor-resize-open-subjects' ).text() }"
				>
					<template
						v-if="showsNavigator"
						#start
					>
						<OpenSubjectList
							:subjects="openSubjects"
							:active-id="activePaneId"
							:unsaved-ids="unsavedIds"
							:blocking-ids="blockingIds"
							@select="showPane"
						/>
					</template>

					<div class="ext-neowiki-subject-editor-dialog__panels">
						<CdxProgressBar
							v-if="pending"
							:aria-label="$i18n( 'neowiki-subject-editor-loading' ).text()"
						/>
						<div
							v-for="pane in panes"
							v-show="pane.id === activePaneId"
							:id="`ext-neowiki-panel-${ pane.id }`"
							:key="paneKey( pane.id )"
							tabindex="-1"
						>
							<SubjectEditPane
								:ref="( el ) => setPaneRef( pane.id, el )"
								:subject="pane.subject"
								:schema="pane.schema"
								:nested="pane.id !== rootPaneId"
								:starts-changed="changedPaneIds.has( pane.id )"
								:is-new="pane.isNew"
								:unsaved-target-ids="unsavedTargetIds"
								@edit-relation-target="openRelationTargetFromForm"
							/>
						</div>
					</div>
				</EditorPanes>
			</div>

			<template
				v-if="!pending"
				#footer
			>
				<CdxMessage
					v-if="partialSave !== null"
					:inline="true"
					type="warning"
					class="ext-neowiki-subject-editor-dialog__partial-save"
				>
					{{ partialSaveMessage }}
				</CdxMessage>

				<!-- Whatever the host has to ask alongside the save, such as which page a Subject
					being created goes on. Above the actions, so the question is read before the
					button that answers it. The footer sits outside the body's `inert`, so the host
					is handed the save's progress to freeze its own controls with. -->
				<slot name="before-actions" :saving="saving" />

				<SummaryAction
					help-text=""
					:footer-text="conflict === null ? saveScopeText : choicesLeftText"
					compact-save-button
					:save-button-label="saveButtonLabel"
					:save-disabled="!anyChanged || saving || choicesLeft > 0 || props.saveDisabled === true || blockingIds.length > 0"
					@save="handleSave"
				>
					<template #actions>
						<CdxButton
							v-if="conflict !== null"
							:disabled="saving || choicesLeft > 0"
							@click="editInTheForm"
						>
							{{ $i18n( 'neowiki-subject-editor-edit-conflict-edit-in-form' ).text() }}
						</CdxButton>
					</template>
				</SummaryAction>
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
import { ref, shallowRef, shallowReactive, nextTick, computed, provide, watch, onBeforeUnmount } from 'vue';
import SubjectEditPane from '@/components/SubjectEditor/SubjectEditPane.vue';
import type { SubjectEditPaneExposes } from '@/components/SubjectEditor/SubjectEditPane.vue';
import OpenSubjectList from '@/components/SubjectEditor/OpenSubjectList.vue';
import SummaryAction from '@/components/common/SummaryAction.vue';
import EditNoticeList from '@/components/common/EditNoticeList.vue';
import SchemaNameDisplay from '@/components/common/SchemaNameDisplay.vue';
import { CdxButton, CdxDialog, CdxMessage, CdxProgressBar } from '@wikimedia/codex';
import DialogHeader from '@/components/common/DialogHeader.vue';
import { Subject } from '@/domain/Subject.ts';
import { enteredSubjectLabel } from '@/domain/enteredSubjectLabel.ts';
import { SubjectId } from '@/domain/SubjectId.ts';
import { PageIdentifiers } from '@/domain/PageIdentifiers.ts';
import { StatementList } from '@/domain/StatementList.ts';
import { Schema } from '@/domain/Schema.ts';
import { SubjectCreationKey } from '@/components/common/SubjectCreation.ts';
import { SubjectIdInUseError } from '@/persistence/SubjectIdInUseError';
import SchemaEditorDialog from '@/components/SchemaEditor/SchemaEditorDialog.vue';
import type { SchemaSaveHandler } from '@/components/SchemaEditor/SchemaEditorDialog.vue';
import CloseConfirmationDialog from '@/components/common/CloseConfirmationDialog.vue';
import { useGrowOnlyHeight } from '@/composables/useGrowOnlyHeight.ts';
import EditorPanes, { type StartPaneOptions } from '@/components/common/EditorPanes.vue';
import { useSchemaPermissions } from '@/composables/useSchemaPermissions.ts';
import { useCloseConfirmation } from '@/composables/useCloseConfirmation.ts';
import { useSaveGuard } from '@/composables/useSaveGuard.ts';
import { useEditNotices } from '@/composables/useEditNotices.ts';
import { NeoWikiExtension } from '@/NeoWikiExtension.ts';
import { ValidationFailedError } from '@/persistence/ValidationFailedError';
import { EditConflictError } from '@/persistence/EditConflictError';
import { EditConflict, relationTargetIdsOf, type Choice } from '@/domain/EditConflict';
import { dependentSubjectEntryName } from '@/presentation/dependentSubjectEntryName';
import { propertyDisplayName } from '@/presentation/propertyDisplayName';
import EditConflictEditor from '@/components/SubjectEditor/EditConflictEditor.vue';
import { WriteRefusedError } from '@/components/SubjectEditor/WriteRefusedError.ts';
import type { SaveBlocker } from '@/components/common/SaveBlocker.ts';
import { NeoWikiServices } from '@/NeoWikiServices.ts';
import { subjectDisplayName } from '@/presentation/subjectDisplayName.ts';
import { schemaDisplayName } from '@/presentation/schemaDisplayName.ts';
import { isSubjectFirst } from '@/wikiMode.ts';
import { isPlainLeftClick } from '@/presentation/plainLeftClick.ts';
import { reachableTargetIds, writeOrder } from '@/components/SubjectEditor/SubjectDraftGraph.ts';
import type { HeldSubject } from '@/components/SubjectEditor/SubjectDraftGraph.ts';
import { DependentSubjectLoader, loadedSchemasOf, type DependentSubjectsForEditing } from '@/application/DependentSubjectLoader.ts';
import { PropertyName, type PropertyDefinition } from '@/domain/PropertyDefinition';
import { useDependentSubjectEditing } from '@/composables/useDependentSubjectEditing.ts';
import { focusFirstValueControl } from '@/components/common/valueControlFocus.ts';
import { useSubjectStore, type DependentSubjects } from '@/stores/SubjectStore.ts';
import { useSchemaStore } from '@/stores/SchemaStore.ts';

/**
 * Writes a Subject the wiki holds and its `dependentSubjects` in one write. Answers with the Subject as the write stored
 * it, where the wiki's answer carries it.
 */
type SubjectSaveHandler = ( subject: Subject, dependentSubjects: DependentSubjects, comment: string ) => Promise<Subject | undefined>;

/**
 * Writes a Subject this session invented, carrying the id minted for it, as a Subject of the
 * given page, together with the Dependent Subjects it hosts. Hosts that leave it out get no create
 * affordance in the relation fields. A WriteRefusedError stops the save without a message from this
 * dialog.
 */
type SubjectCreateHandler = (
	subject: Subject,
	pageId: number,
	dependentSubjects: DependentSubjects,
	comment: string
) => Promise<void>;

/** Told that the whole save is through, by a host that reports it somewhere this dialog cannot. */
type SaveReporter = () => void;

const props = defineProps<{
	subject: Subject;
	schema: Schema;
	onSave: SubjectSaveHandler;
	onSaveSchema: SchemaSaveHandler;
	onCreate?: SubjectCreateHandler;
	/**
	 * Whether the root Subject is one the wiki does not hold yet, which the save creates rather
	 * than updates. It is what the subject creator opens this dialog on.
	 */
	rootIsNew?: boolean;
	/**
	 * Withholds Save although there are changes, for a host with a question of its own still
	 * unanswered. It can only withhold: the dialog's own reasons to disable Save still hold.
	 */
	saveDisabled?: boolean;
	/**
	 * Whether the host holds something of its own that closing would throw away, such as an
	 * answer given in the footer. Counted alongside the panes' own edits, so the dialog asks
	 * once for everything a close would discard rather than leaving the host to ask again.
	 */
	hostHasUnsavedChanges?: boolean;
	/**
	 * Called in place of the success message and the close, for a host that leaves the page once
	 * the save is through and reports it there.
	 */
	onSaved?: SaveReporter;
	open: boolean;
}>();

const emit = defineEmits( [ 'update:open' ] );

interface EditPane {
	id: string;
	subject: Subject;
	schema: Schema;
	// Holds a Subject the server has never seen. Cleared as soon as its create lands, so a save
	// that fails further down re-runs as an update rather than offering the same id twice.
	isNew: boolean;
}

const content = ref<HTMLElement | null>( null );

// Codex hands out no ref to the dialog element itself.
const dialogElement = computed( (): HTMLElement | undefined =>
	content.value?.closest<HTMLElement>( '.cdx-dialog' ) ?? undefined );

// Held at the tallest it has been this session, so that neither a field taken away nor a switch to a shorter Subject
// moves its footer up: it only grows.
const grownHeight = useGrowOnlyHeight( dialogElement );

const grownHeightStyle = computed( () => ( {
	'--ext-neowiki-dialog-grown-height': `${ grownHeight.tallest.value }px`
} ) );

const isSchemaEditorOpen = ref( false );
// Mirrors the prop so a schema saved through the nested SchemaEditorDialog takes effect here
// without waiting for the host to pass a new one down.
const currentSchema = shallowRef<Schema>( props.schema );
const { canEditSchema, checkEditPermission } = useSchemaPermissions();
const { notices, loading: noticesLoading, loadNotices } = useEditNotices(
	() => NeoWikiExtension.getInstance().getEditNoticeRepository()
);
const subjectRepository = NeoWikiServices.getSubjectRepository();
const schemaRepository = NeoWikiServices.getSchemaRepository();

const rootPaneId = computed( (): string => props.subject.getId().text );
const paneRefs = shallowReactive( new Map<string, SubjectEditPaneExposes>() );

function setPaneRef( id: string, el: unknown ): void {
	if ( el ) {
		paneRefs.set( id, el as SubjectEditPaneExposes );
	} else {
		paneRefs.delete( id );
	}
}

// The root Subject as its pane edits it; for a new root, pointing at the Dependent Subjects it starts with. Null
// until what the root's form needs has loaded.
const loadedRoot = shallowRef<Subject | null>( null );

// Once reading what the form needs takes a while, the dialog shows that it is reading, rather than leave the control
// that opened it looking inert.
const PENDING_DELAY_MS = 300;
const readingLong = ref( false );
let pendingTimer: ReturnType<typeof setTimeout> | undefined;

// The form shows complete or not at all, and with its notices, so that none lands above it and pushes it down. Notices
// that take longer than that delay are not waited for: an editor must open whether or not they could be fetched.
const shown = computed( (): boolean =>
	props.open && loadedRoot.value !== null && ( !noticesLoading.value || readingLong.value ) );

const pending = computed( (): boolean => props.open && loadedRoot.value === null && readingLong.value );
const visible = computed( (): boolean => shown.value || pending.value );

const extraPanes = shallowRef<EditPane[]>( [] );
const activePaneId = ref<string>( rootPaneId.value );

// Set when a save wrote some Subjects and then failed: the toast that says so vanishes,
// and the dialog is still open.
const partialSave = ref<{ written: number; attempted: number } | null>( null );

interface ConflictReview {
	paneId: string;
	subjectName: string;
	editConflict: EditConflict;
	choices: ReadonlyMap<string, Choice>;
	theirSubject: Subject;
	theirDependentSubjects: DependentSubjectsForEditing;
}

// Set when a save was refused because someone else changed the same fields since the Subject was read. The list of
// the changes stands in for the form until the choices it asks for go into the pane.
const conflict = shallowRef<ConflictReview | null>( null );

const choicesLeft = computed( (): number => conflict.value === null ? 0 :
	conflict.value.editConflict.bothChanged.filter( ( change ) => !conflict.value?.choices.has( change.key ) ).length );

const choicesLeftText = computed( (): string => choicesLeft.value === 0 ? '' :
	mw.message( 'neowiki-subject-editor-edit-conflict-to-choose', choicesLeft.value ).text() );

// Panes holding choices made in an edit conflict, which the save writes although nothing was typed into them.
const changedPaneIds = shallowReactive( new Set<string>() );

// What a pane's next conflict is judged against once its choices went in: their version they were made on, which the
// pane's next save is based on, rather than the resolved version the pane now starts from.
const conflictBases = new Map<string, Subject[]>();

// How often each pane's Subject was loaded again, which starts the pane over.
const paneReloads = shallowReactive( new Map<string, number>() );

function paneKey( paneId: string ): string {
	return `${ paneId }:${ paneReloads.get( paneId ) ?? 0 }`;
}

const panes = computed( (): EditPane[] => loadedRoot.value === null ? [] : [
	{
		id: rootPaneId.value,
		subject: loadedRoot.value,
		schema: currentSchema.value,
		isNew: props.rootIsNew === true
	},
	...extraPanes.value
] );

const activePane = computed( (): EditPane | undefined =>
	panes.value.find( ( pane ) => pane.id === activePaneId.value ) );

// Subjects this session invented, as the panes editing them currently hold them, so a draft renamed
// in its pane is renamed everywhere that names it. Read from the draft panes alone: going through
// editedSubjects would make every rename and relation pick anywhere in the dialog rebuild the menu
// of every relation field.
// The root is left out although it may be new: whether a Subject created alongside it may point
// back at it is open (#1449).
const draftSubjects = computed( (): Subject[] => panes.value
	.filter( ( pane ) => pane.isNew && pane.id !== rootPaneId.value )
	.map( ( pane ) => {
		const instance = paneRefs.get( pane.id );
		return instance === undefined ? pane.subject : withLiveLabel( instance );
	} )
);

const draftIds = computed( (): string[] => draftSubjects.value.map( ( subject ) => subject.getId().text ) );

const schemaStore = useSchemaStore();
const dependentSubjectLoader = new DependentSubjectLoader(
	subjectRepository,
	schemaRepository,
	() => schemaStore.fetchAllSchemaSummaries()
);

const dependentSubjectEditing = useDependentSubjectEditing( dependentSubjectLoader, () => !saving.value, draftIds );

const unsavedTargetIds = dependentSubjectEditing.unsavedTargetIds;

function withSchemas( subjects: readonly Subject[] ): HeldSubject[ 'dependentSubjects' ] {
	const held: { subject: Subject; schema: Schema }[] = [];

	for ( const subject of subjects ) {
		const schema = dependentSubjectEditing.schemas.get( subject.getSchemaName() );

		if ( schema !== undefined ) {
			held.push( { subject, schema } );
		}
	}

	return held;
}

// What the walks below read: a pane's Subject as its own form currently holds it. editedSubjects
// carries an entry for every pane, so this never falls back.
function heldSubjects(): HeldSubject[] {
	return panes.value.map( ( pane ) => ( {
		id: pane.id,
		subject: editedSubjects.value.get( pane.id ) as Subject,
		schema: pane.schema,
		isNew: pane.isNew,
		dependentSubjects: withSchemas( paneRefs.get( pane.id )?.editedDependentSubjects ?? [] )
	} ) );
}

// Drafts something being edited still points at. One the user has pointed the relation away from is
// not written, and Save neither waits on it nor leaves it behind.
/**
 * Panes the save is committed to writing whatever points at them. The Subjects the wiki already
 * holds are committed by being there; a root it does not hold is committed by being what the save
 * exists to write. Everything below follows from that one fact: it anchors the reachability walk,
 * it is in the write set untouched, it is not something a close would be sorry to lose, and it is
 * written before the Subjects it points at — which is what lets them be stored on the page its own
 * write settles.
 */
const committedIds = computed( (): string[] =>
	props.rootIsNew === true ? [ rootPaneId.value ] : [] );

const referencedDraftIds = computed( (): Set<string> =>
	reachableTargetIds( heldSubjects(), committedIds.value ) );

// A Subject about to be written, as the draft graph wants to see it, with the Dependent Subjects its
// write carries.
interface WriteTarget extends HeldSubject {
	pane: EditPane;
	dependentSubjectsToWrite: DependentSubjects;
}

interface DirtyPane {
	pane: EditPane;
	instance: SubjectEditPaneExposes;
}

// The whole save set: the gates, the footer's counts and the write loop all read it. A pane
// never unmounts while the dialog is open, so a dirty pane is the only place an unsaved edit
// can live. A Subject this save already wrote drops out until its pane is edited again, so
// it neither keeps Save enabled nor confirms a discard over nothing.
// A Subject this session invented counts as unsaved from the moment it exists: nobody has to type
// into it for the relation pointing at it to need something to point at. It stops counting once
// nothing points at it any more — an editor that wrote a Subject the user had already turned away
// from would leave exactly the debris this dialog promises not to.
const dirtyPanes = computed( (): DirtyPane[] => panes.value
	.map( ( pane ) => ( { pane, instance: paneRefs.get( pane.id ) } ) )
	.filter( ( entry ): entry is DirtyPane => entry.instance !== undefined && (
		entry.pane.isNew ?
			( committedIds.value.includes( entry.pane.id ) || referencedDraftIds.value.has( entry.pane.id ) ) :
			entry.instance.hasChanged
	) )
);

const unsavedIds = computed( (): string[] => dirtyPanes.value.map( ( { pane } ) => pane.id ) );

const anyChanged = computed( (): boolean => dirtyPanes.value.length > 0 );

// Subjects in the save whose forms show something it cannot get past: text a field cannot read, or a violation the wiki
// would refuse the save over.
const blockingIds = computed( (): string[] => dirtyPanes.value
	.filter( ( { instance } ) => instance.violationsBlockSaving || instance.saveBlocker() !== null )
	.map( ( { pane } ) => pane.id ) );

// What closing would throw away, which is not the same as what saving would write: an untouched
// root the save is committed to writing is in the write set, and is nothing the user would be
// sorry to lose. Everywhere the root already exists the two are the same.
const hasUnsavedEdits = computed( (): boolean =>
	props.hostHasUnsavedChanges === true ||
	// A save that wrote something and then stopped is recorded by the amber line alone, and the
	// close takes that line with it. What was written is worth a word before it goes.
	partialSave.value !== null ||
	dirtyPanes.value.some(
		( { pane, instance } ) => !committedIds.value.includes( pane.id ) || instance.hasChanged )
);

// A Subject the wiki does not hold is being created, whoever opened the dialog on it.
const dialogTitle = computed( (): string => mw.msg(
	props.rootIsNew === true ? 'neowiki-subject-creator-title' : 'neowiki-subject-editor-title'
) );

const headerSubjectId = computed( (): string | undefined => {
	if ( props.rootIsNew === true ) {
		return undefined;
	}

	const pane = activePane.value;

	return pane === undefined || pane.isNew ? undefined : pane.subject.getId().text;
} );

const saveButtonLabel = computed( (): string => mw.msg(
	props.rootIsNew === true ? 'neowiki-subject-creator-save' : 'neowiki-subject-editor-save'
) );

// One copy per mounted pane. A pane's own copy is refreshed on relation changes alone, so
// the live label is laid over it here and the navigator follows a rename as it is typed.
const editedSubjects = computed( (): Map<string, Subject> => {
	const subjects = new Map<string, Subject>();

	for ( const pane of panes.value ) {
		const instance = paneRefs.get( pane.id );
		// The pane's own copy until its ref registers, one tick behind the pane being added.
		// Without it a pane would spend that tick unnamed in the navigator.
		subjects.set( pane.id, instance === undefined ? pane.subject : withLiveLabel( instance ) );
	}

	return subjects;
} );

// Rebuilt only when the label has moved, so an unrenamed Subject keeps the very object the
// navigator already rendered. The field's text is read the way a write reads it.
function withLiveLabel( instance: SubjectEditPaneExposes ): Subject {
	const edited = instance.editedSubject;
	const label = enteredSubjectLabel( instance.label );
	return edited.getLabel() === label ? edited : edited.withLabel( label );
}

// The root Subject's label as its form holds it, for a host that follows the name being given.
const rootLabel = computed( (): string | null =>
	editedSubjects.value.get( rootPaneId.value )?.getLabel() ?? null );

// The Subjects the navigator lists, in the order their panes were opened.
const openSubjects = computed( (): Subject[] => panes.value.map(
	( pane ) => editedSubjects.value.get( pane.id ) as Subject ) );

// A list of one says nothing the form beside it does not, so the navigator waits for a second
// Subject — and then stays, because a pane is never unmounted, whatever becomes of the relation
// that opened it.
const showsNavigator = computed( (): boolean => extraPanes.value.length > 0 );

const openIds = computed( (): string[] => panes.value.map( ( pane ) => pane.id ) );

// The form keeps at least as much as the list starts with, which leaves the list about 40% of a dialog too narrow
// for both.
const NAVIGATOR_SIZE: Omit<StartPaneOptions, 'resizeLabel'> = {
	defaultSize: 320,
	minSize: 128,
	minOtherSize: 320,
	storageKey: 'neowiki-subject-editor-pane-size'
};

const editorPanes = ref<InstanceType<typeof EditorPanes> | null>( null );
const formScroller = computed( (): HTMLElement | null => editorPanes.value?.mainPane ?? null );

// The panes share one scroller, so each keeps its own place in it here while another is on screen. One that is not
// open any more, after a session has started over, has no place to keep.
const scrollPositions = new Map<string, number>();

// Read before the switch renders, and restored once it has: `v-show` swaps the panes after post-flush watchers have
// run, and a position set before the pane it belongs to shows is cut to the height of the one it replaces.
watch( activePaneId, async ( shownId, hiddenId ) => {
	const scroller = formScroller.value;

	if ( scroller === null ) {
		return;
	}

	if ( openIds.value.includes( hiddenId ) ) {
		scrollPositions.set( hiddenId, scroller.scrollTop );
	}

	await nextTick();
	scroller.scrollTop = scrollPositions.get( shownId ) ?? 0;
} );

// Tells one opening of the dialog from the next. The watcher further down bumps it, for an
// opening and a replaced root alike, and so does unmounting. A fetch that outlives its opening is
// dropped by it: the hosts keep this dialog mounted after it closes, so the fetch would otherwise
// land in the next opening.
/**
 * Opens the Schema editor in place of following the link, for a plain left click by someone
 * who may edit it. Anything else — a modifier, the middle button, no edit right — is left to
 * the browser, so the Schema page stays reachable and nothing here is a dead end.
 *
 * Only while the root pane is on screen: `SchemaEditorDialog` below is bound to the root's
 * Schema, and would put the reader of a nested pane in front of a Schema they did not point at.
 */
function openSchemaEditor( event: MouseEvent ): void {
	if ( activePaneId.value !== rootPaneId.value || !canEditSchema.value || !isPlainLeftClick( event ) ) {
		return;
	}

	event.preventDefault();
	isSchemaEditorOpen.value = true;
}

const openEpoch = ref( 0 );

// So a double-click on one target's edit button does not fire a second request. Keyed per
// opening, or a fetch left over from the last one would stand in for a fresh click.
const pendingTargetKeys = new Set<string>();

async function openRelationTarget( targetId: SubjectId ): Promise<void> {
	const id = targetId.text;
	if ( openIds.value.includes( id ) ) {
		activePaneId.value = id;
		return;
	}
	const epoch = openEpoch.value;
	const pendingKey = `${ epoch }:${ id }`;
	if ( pendingTargetKeys.has( pendingKey ) ) {
		return;
	}
	pendingTargetKeys.add( pendingKey );
	try {
		const subject = await subjectRepository.getSubjectForEditing( targetId );
		const schema = await schemaRepository.getSchema( subject.getSchemaName() );
		const dependentSubjects = await dependentSubjectLoader.loadFor( subject, schema, dependentSubjectEditing.schemas );
		if ( epoch !== openEpoch.value ) {
			return;
		}
		dependentSubjectEditing.include( dependentSubjects, false );
		extraPanes.value = [ ...extraPanes.value, { id, subject, schema, isNew: false } ];
		activePaneId.value = id;
	} catch ( error ) {
		if ( epoch !== openEpoch.value ) {
			return;
		}
		console.error( 'Failed to load relation target for editing:', error );
		mw.notify( mw.msg( 'neowiki-subject-editor-target-load-error' ), { type: 'error' } );
	} finally {
		pendingTargetKeys.delete( pendingKey );
	}
}

// The page a Subject created here is stored on. A subject-first wiki gives it a page of its own,
// whichever pane it was created from. A page-first wiki stores it on the page holding the Subject
// whose relation is being filled in, since that is the Subject the new one belongs beside. Panes
// routinely span pages, so the dialog's own page would be the wrong answer as soon as the user has
// drilled in. The root stands in for a pane whose Subject arrived without page context.
function creationPage(): PageIdentifiers | null {
	if ( isSubjectFirst() ) {
		return PageIdentifiers.notYetCreated();
	}

	for ( const subject of [ activePane.value?.subject, props.subject ] ) {
		if ( subject !== undefined && Number.isInteger( subject.getPageIdentifiers().getPageId() ) ) {
			return subject.getPageIdentifiers();
		}
	}

	return null;
}

// Creates the Subject a relation field is about to point at, and opens it for editing. Nothing
// is written: the id is minted, which reserves nothing, and the Subject itself reaches the wiki
// only when this dialog is saved. The pane is added and made active before this returns, so the
// relation the caller then records lands in the same render as the pane and its row.
async function createRelationTarget( schemaName: string, label: string | null ): Promise<Subject | null> {
	const page = creationPage();

	// A Subject added while the write loop is running would be referenced by a Subject already
	// written and yet never written itself, so creation is closed for the duration of a save.
	if ( saving.value || page === null ) {
		mw.notify( mw.msg( 'neowiki-subject-editor-create-target-error' ), { type: 'error' } );
		return null;
	}

	const epoch = openEpoch.value;

	try {
		const [ schema, id ] = await Promise.all( [
			schemaRepository.getSchema( schemaName ),
			subjectRepository.mintSubjectId()
		] );

		const started = await dependentSubjectLoader.startFor(
			new Subject(
				id,
				label,
				// What the server would derive for a Subject with no label of its own (ADR 31).
				label ?? schemaName,
				label === null,
				schemaName,
				new StatementList( [] ),
				page,
				null,
				schema.getLabels(),
				null
			),
			schema,
			dependentSubjectEditing.schemas
		);

		// The dialog was reopened or given another root while this was in flight, or a save has
		// started: its panes are gone or already harvested, and a pane added now would belong to
		// nothing.
		if ( epoch !== openEpoch.value || saving.value ) {
			return null;
		}

		const subject = started.subject;

		dependentSubjectEditing.include( started.dependentSubjects, true );
		extraPanes.value = [ ...extraPanes.value, { id: id.text, subject, schema, isNew: true } ];
		activePaneId.value = id.text;
		focusPane( id.text );

		return subject;
	} catch ( error ) {
		// Guarded like openRelationTarget's: a failure that outlives its opening must not report
		// itself over whatever the dialog is editing now.
		if ( epoch !== openEpoch.value ) {
			return null;
		}

		console.error( 'Failed to create relation target:', error );
		mw.notify( mw.msg( 'neowiki-subject-editor-create-target-error' ), { type: 'error' } );
		return null;
	}
}

if ( props.onCreate !== undefined ) {
	provide( SubjectCreationKey, {
		create: createRelationTarget,
		drafts: ( schemaName: string ): readonly Subject[] =>
			draftSubjects.value.filter( ( subject ) => subject.getSchemaName() === schemaName )
	} );
}

// Every listed Subject has a pane already, so this neither fetches nor can fail. Focus stays on
// the row that was chosen, which is the navigator's own tab stop.
function showPane( subjectId: SubjectId ): void {
	activePaneId.value = subjectId.text;
}

// Navigating from a control inside a form hides the pane that control lives in, so the
// browser blurs it to <body> and the next Tab restarts at the top of the dialog. The newly
// shown pane takes the focus instead, as the first pane does when the dialog opens.
// Activation from the navigator is left alone: focus belongs on the row there.
async function openRelationTargetFromForm( targetId: SubjectId ): Promise<void> {
	await openRelationTarget( targetId );
	await focusPane( targetId.text );
}

function close(): void {
	emit( 'update:open', false );
}

const { confirmationOpen, requestClose, confirmClose, cancelClose } = useCloseConfirmation( hasUnsavedEdits, close );

// Nothing may change or close while the loop below is writing: it harvests every dirty
// pane up front and marks each one clean as its write lands, so an edit made meanwhile
// would be reverted by the written copy and left unguarded by the discard confirmation.
// Bound as `undefined` rather than `false` where it is off. A browser that knows `inert`
// exposes it as a property, which Vue sets, so `false` would do there; where it is only an
// attribute, as in jsdom, Vue would write inert="false", which an element reads as present.
const { saving, save: handleSave } = useSaveGuard( writeDirtyPanes );

function closeRequested(): void {
	if ( saving.value ) {
		return;
	}
	requestClose();
}

function onDialogUpdateOpen( value: boolean ): void {
	if ( !value ) {
		closeRequested();
	}
}

// An opening and a replaced root both start over: a dialog only reaches `open: false` with
// nothing unsaved or after a confirmed discard (see useCloseConfirmation), so nothing an
// earlier session opened or reported carries into the next.
function startSession(): void {
	openEpoch.value += 1;
	grownHeight.reset();
	scrollPositions.clear();
	extraPanes.value = [];
	activePaneId.value = rootPaneId.value;
	partialSave.value = null;
	conflict.value = null;
	changedPaneIds.clear();
	conflictBases.clear();
	paneReloads.clear();
	loadedRoot.value = null;
	readingLong.value = false;
	clearTimeout( pendingTimer );
	pendingTimer = setTimeout( () => {
		readingLong.value = true;
	}, PENDING_DELAY_MS );
	loadRoot();
}

let focusTimer: ReturnType<typeof setTimeout> | undefined;

async function loadRoot(): Promise<void> {
	const epoch = openEpoch.value;
	const root = props.subject;
	const rootIsNew = props.rootIsNew === true;

	try {
		const loaded = rootIsNew ?
			await dependentSubjectLoader.startFor( root, currentSchema.value, new Map() ) :
			{ subject: root, dependentSubjects: await dependentSubjectLoader.loadFor( root, currentSchema.value, new Map() ) };

		if ( epoch !== openEpoch.value ) {
			return;
		}

		dependentSubjectEditing.startFrom( loaded.dependentSubjects, rootIsNew );
		loadedRoot.value = loaded.subject;
	} catch ( error ) {
		if ( epoch !== openEpoch.value ) {
			return;
		}

		console.error( 'Failed to load the subject for editing:', error );
		clearTimeout( pendingTimer );
		mw.notify( mw.msg( 'neowiki-subject-editor-load-error' ), { type: 'error' } );
		close();
	}
}

// A task later: Codex focuses the dialog's first focusable element one or two ticks after it opens, depending on its
// version, and would otherwise win. Without scrolling: the form is at its top already, and focusing the pane itself
// would shift it by its padding. Post-flush, because a reopening reads as shown until its session has started over,
// and the closing dialog, still fading out, would take the focus and lose it.
watch( shown, ( isShown ) => {
	if ( isShown ) {
		clearTimeout( focusTimer );
		focusTimer = setTimeout( () => focusPane( rootPaneId.value, { preventScroll: true } ) );
	}
}, { flush: 'post' } );

// Fetched per opening: approval state and the viewer's permissions both change
// without an edit. Keyed on the page being viewed and on the root Subject's Schema.
watch( () => props.open, ( isOpen ) => {
	if ( isOpen ) {
		loadNotices( Number( mw.config.get( 'wgArticleId' ) ), props.subject.getSchemaName() );
	}
}, { immediate: true } );

// Immediate, because the hosts render this dialog with v-if: it mounts with open
// already true, so a deferred watcher would never see the opening that created it.
// The host can also replace props.subject while the dialog stays open, e.g. saving one subject
// navigates straight to editing another. One watcher for both, because opening the dialog on
// another Subject changes both at once, and the root would otherwise be read twice.
watch( [ () => props.open, () => props.subject ], ( [ isOpen ] ) => {
	if ( isOpen ) {
		startSession();
	}
}, { immediate: true } );

onBeforeUnmount( () => {
	clearTimeout( focusTimer );
	clearTimeout( pendingTimer );
	openEpoch.value += 1;
} );

// When no field can take the focus, the pane holds it: that is what its tabindex="-1" is for.
async function focusPane( paneId: string, options?: { preventScroll: boolean } ): Promise<void> {
	await nextTick();

	const panel = document.getElementById( `ext-neowiki-panel-${ paneId }` );

	if ( panel !== null ) {
		focusFirstValueControl( panel, options );
	}
}

watch( () => props.subject, ( newSubject ) => {
	checkEditPermission( newSubject.getSchemaName() );
}, { immediate: true } );

// Synchronously, so that a session started by the same update loads the root by the new Schema.
watch( () => props.schema, ( newSchema ) => {
	currentSchema.value = newSchema;
}, { flush: 'sync' } );

async function showSubject( id: string ): Promise<void> {
	await openRelationTarget( new SubjectId( id ) );
	await nextTick();
}

const partialSaveMessage = computed( (): string => partialSave.value === null ?
	'' :
	mw.message(
		'neowiki-subject-editor-partial-save',
		partialSave.value.written,
		partialSave.value.attempted
	).text()
);

// Counted in pages as well as in Subjects on a page-first wiki, since each page written gets
// revisions of its own. A Subject with no resolved page counts as a page of its own.
function pageKeyOf( subject: Subject ): string {
	const pageId = subject.getPageIdentifiers().getPageId();

	return Number.isInteger( pageId ) ?
		`page:${ pageId }` :
		`subject:${ subject.getId().text }`;
}

const dirtyPageCount = computed( (): number => new Set(
	dirtyPanes.value.map( ( { pane } ) => pageKeyOf( pane.subject ) )
).size );

// Said only when it tells the user something the screen does not: the Save button plainly
// writes the one dirty Subject in front of them. A subject-first wiki counts no pages, since
// its users need not care which pages store their Subjects.
const saveScopeText = computed( (): string => {
	const dirty = unsavedIds.value;

	if ( dirty.length === 0 || ( dirty.length === 1 && dirty[ 0 ] === activePaneId.value ) ) {
		return '';
	}

	if ( isSubjectFirst() ) {
		return mw.message( 'neowiki-subject-editor-save-scope-subject-first', dirty.length ).text();
	}

	return mw.message( 'neowiki-subject-editor-save-scope', dirty.length, dirtyPageCount.value ).text();
} );

// A pane stops being new the moment its Subject exists on the wiki, so a save that fails further
// down re-runs as an update rather than offering the same id to the server a second time.
function markPaneWritten( id: string ): void {
	changeExtraPane( id, { isNew: false } );
}

// The panes are a shallowRef, so an entry is changed by replacing it rather than mutating it.
function changeExtraPane( id: string, change: Partial<EditPane> ): void {
	extraPanes.value = extraPanes.value.map( ( pane ) => pane.id === id ? { ...pane, ...change } : pane );
}

// A Subject this session invented has to be created rather than replaced, on the page its pane
// was opened against.
async function writeSubject(
	pane: EditPane,
	subject: Subject,
	dependentSubjects: DependentSubjects,
	comment: string
): Promise<Subject | undefined> {
	if ( !pane.isNew ) {
		const stored = await props.onSave( subject, dependentSubjects, comment );
		dependentSubjectEditing.markWritten( dependentSubjects );
		return stored;
	}

	if ( props.onCreate === undefined ) {
		throw new Error( 'No onCreate handler to create this Subject with' );
	}

	try {
		await props.onCreate( subject, pane.subject.getPageIdentifiers().getPageId(), dependentSubjects, comment );
	} catch ( error ) {
		// The id was minted for this Subject alone, so the server holding it already means this
		// very create landed and only its answer was lost. Retrying the create would refuse
		// forever; recording it as written turns the retry into the update it now needs to be.
		if ( !( error instanceof SubjectIdInUseError ) ) {
			throw error;
		}
	}

	markPaneWritten( pane.id );
	dependentSubjectEditing.markWritten( dependentSubjects );
	return undefined;
}

async function writeDirtyPanes( summary: string ): Promise<void> {
	await writePanes( summary, true );
}

// A conflict that leaves nothing to choose, such as one over a move of the Subject, is written onto their version
// once without asking, as a merge the server makes is.
async function writePanes( summary: string, mayWriteUnasked: boolean ): Promise<void> {
	await applyConflictChoices();
	await nextTick();

	partialSave.value = null;

	// Each flush writes only its own pane's violations, so none has to wait for another. The write
	// set is read again afterwards rather than before: a validation round trip is awaited here, and
	// a Subject created inside that window would otherwise go unwritten while the relation naming
	// it was written.
	await Promise.all( dirtyPanes.value.map( ( { instance } ) => instance.flushValidation() ) );

	const dirty = dirtyPanes.value;

	// Saving now would silently drop text the user can still see. Checked across every
	// dirty Subject before any write, or a blocked later one would leave the stack
	// half written.
	const blocked = dirty
		.map( ( entry ) => ( { id: entry.pane.id, blocker: entry.instance.saveBlocker() } ) )
		.find( ( entry ): entry is { id: string; blocker: SaveBlocker } => entry.blocker !== null );

	if ( blocked !== undefined ) {
		await showSubject( blocked.id );
		mw.notify( blocked.blocker.message, { title: blocked.blocker.propertyName, type: 'error' } );
		return;
	}

	// The panes' own validation just awaited can find what the wiki would refuse the save over, when Save was pressed
	// before the edit's validation came back. A Dependent Subject's validation still in flight is not waited for.
	const refused = dirty.find( ( { instance } ) => instance.violationsBlockSaving );

	if ( refused !== undefined ) {
		await showSubject( refused.pane.id );
		mw.notify(
			mw.msg( 'neowiki-subject-editor-validation-failed', subjectDisplayName( withLiveLabel( refused.instance ) ) ),
			{ type: 'error' }
		);
		return;
	}

	const savedNames: string[] = [];

	// A Subject an earlier attempt already wrote is not in `dirty`: its pane reset its
	// changed flag.
	const targets: WriteTarget[] = [];

	for ( const { pane, instance } of dirty ) {
		const updated = instance.buildUpdatedSubject();
		if ( updated === null ) {
			// A dirty pane with no data to save means it lost its editor ref;
			// surface this as an error instead of silently discarding the edit. Named after the pane
			// that failed, which in a nested edit is not the dialog's root Subject.
			mw.notify( mw.msg( 'neowiki-subject-editor-error', subjectDisplayName( pane.subject ) ), { type: 'error' } );
			return;
		}
		const dependentSubjects = instance.buildDependentSubjects();
		targets.push( {
			id: pane.id,
			pane,
			subject: updated,
			schema: pane.schema,
			isNew: pane.isNew,
			dependentSubjects: withSchemas( dependentSubjects ),
			dependentSubjectsToWrite: dependentSubjectEditing.edited( dependentSubjects )
		} );
	}

	const orderedTargets = writeOrder( targets, committedIds.value );

	// Carried out of the loop rather than returned from inside it, so a part-way stop is
	// reported once, below.
	let failed = false;
	const storedSubjects = new Map<string, Subject | undefined>();

	for ( const { id, pane, subject: updatedSubject, dependentSubjectsToWrite } of orderedTargets ) {
		const subjectName = subjectDisplayName( updatedSubject );

		try {
			const stored = await writeSubject( pane, updatedSubject, dependentSubjectsToWrite, summary || defaultSummaryFor( pane ) );
			paneRefs.get( id )?.markWritten();
			changedPaneIds.delete( id );
			storedSubjects.set( id, stored );
			savedNames.push( subjectName );
		} catch ( error ) {
			failed = true;
			// The toast naming the refused Subject vanishes; its pane is what stays.
			await showSubject( id );
			await reportWriteFailure( error, id, subjectName );

			break;
		}
	}

	if ( failed ) {
		if ( savedNames.length > 0 ) {
			partialSave.value = { written: savedNames.length, attempted: targets.length };
		}

		startOverFromWhatTheWritesStored( storedSubjects );

		if ( mayWriteUnasked && conflict.value?.editConflict.bothChanged.length === 0 ) {
			await writePanes( summary, false );
		}
		return;
	}

	if ( savedNames.length === 0 ) {
		return;
	}

	// A host that leaves the page reports the save where it lands, so neither a message that the
	// navigation takes away nor a close of a dialog that is going with it.
	if ( props.onSaved !== undefined ) {
		props.onSaved();
		return;
	}

	mw.notify(
		savedNames.length === 1 ?
			mw.msg( 'neowiki-subject-editor-success', savedNames[ 0 ] ) :
			mw.msg( 'neowiki-subject-editor-success-multiple', savedNames.length ),
		{ type: 'success' }
	);
	close();
}

// A write the server merged holds what changed since beside the pane's edit, which the pane's form lacks: a pane left
// open after a partial save starts over from what its write stored, so a later save of it keeps that.
function startOverFromWhatTheWritesStored( storedSubjects: ReadonlyMap<string, Subject | undefined> ): void {
	for ( const [ paneId, stored ] of storedSubjects ) {
		if ( stored !== undefined ) {
			replacePaneSubject( paneId, stored );
			paneReloads.set( paneId, ( paneReloads.get( paneId ) ?? 0 ) + 1 );
		}
	}
}

async function reportWriteFailure( error: unknown, paneId: string, subjectName: string ): Promise<void> {
	// The host has said why, where it can be answered.
	if ( error instanceof WriteRefusedError ) {
		return;
	}

	if ( error instanceof EditConflictError ) {
		await reviewConflict( paneId, subjectName );
		return;
	}

	if ( error instanceof ValidationFailedError ) {
		paneRefs.get( paneId )?.setServerViolations( error.violations );
		mw.notify( mw.msg( 'neowiki-subject-editor-validation-failed', subjectName ), { type: 'error' } );
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

// Their version, read again, beside the form's and the one the editor started from. The Dependent Subjects the
// editor started from are those it read, which it still holds as they were.
async function reviewConflict( paneId: string, subjectName: string ): Promise<void> {
	const pane = panes.value.find( ( candidate ) => candidate.id === paneId );
	const instance = paneRefs.get( paneId );
	const yours = instance?.buildUpdatedSubject() ?? null;

	if ( pane === undefined || instance === undefined || yours === null ) {
		return;
	}

	const epoch = openEpoch.value;

	try {
		const theirs = await subjectRepository.getSubjectForEditing( pane.subject.getId() );
		const theirDependentSubjects = await dependentSubjectLoader.loadFor( theirs, pane.schema, dependentSubjectEditing.schemas );

		if ( epoch !== openEpoch.value ) {
			return;
		}

		const yourDependentSubjects = instance.buildDependentSubjects();
		const editConflict = EditConflict.between(
			conflictBases.get( paneId ) ??
				[ pane.subject, ...startingDependentSubjects( [ ...yourDependentSubjects, ...theirDependentSubjects.subjects ] ) ],
			[ theirs, ...theirDependentSubjects.subjects ],
			[ yours, ...yourDependentSubjects ]
		);

		await loadRelationTargets( editConflict, theirDependentSubjects.subjects, yourDependentSubjects );

		if ( epoch !== openEpoch.value ) {
			return;
		}

		conflict.value = {
			paneId,
			subjectName,
			editConflict,
			choices: new Map(),
			theirSubject: theirs,
			theirDependentSubjects
		};
	} catch ( error ) {
		if ( epoch !== openEpoch.value ) {
			return;
		}

		console.error( 'Failed to load the subject again:', error );
		mw.notify( mw.msg( 'neowiki-subject-editor-load-error' ), { type: 'error' } );
	}
}

// A relation shows its targets by name only once they are read, and the list may hold targets the form never showed.
// Your Dependent Subjects their version lacks are left unread: new or removed, the wiki would answer each as missing.
async function loadRelationTargets(
	editConflict: EditConflict,
	theirDependentSubjects: readonly Subject[],
	yourDependentSubjects: readonly Subject[]
): Promise<void> {
	const subjectStore = useSubjectStore();
	const theirsById = new Map( theirDependentSubjects.map( ( subject ) => [ subject.getId().text, subject ] ) );
	const onlyYours = new Set( yourDependentSubjects.map( ( subject ) => subject.getId().text ).filter( ( id ) => !theirsById.has( id ) ) );
	const targetIds = relationTargetIdsOf( editConflict.bothChanged ).filter( ( id ) => !onlyYours.has( id ) );

	// A target that cannot be read shows as the relation display shows any missing target.
	await Promise.all( targetIds.map( async ( id ) => {
		const dependentSubject = theirsById.get( id );

		if ( dependentSubject !== undefined ) {
			subjectStore.setSubject( dependentSubject );
			return;
		}

		await subjectStore.getOrFetchSubject( new SubjectId( id ) ).catch( () => undefined );
	} ) );
}

function startingDependentSubjects( subjects: readonly Subject[] ): Subject[] {
	return [ ...new Set( subjects.map( ( subject ) => subject.getId().text ) ) ]
		.filter( ( id ) => !dependentSubjectEditing.isNew( id ) )
		.map( ( id ) => dependentSubjectEditing.subject( id ) )
		.filter( ( subject ): subject is Subject => subject !== undefined );
}

function chooseInConflict( key: string, choice: Choice ): void {
	if ( conflict.value !== null ) {
		conflict.value = { ...conflict.value, choices: new Map( conflict.value.choices ).set( key, choice ) };
	}
}

// Dependent Subjects held by the same property share their display name, so the list tells them apart by a field.
function conflictSubjectName( subjectId: string ): string {
	const review = conflict.value;
	const subject = review?.editConflict.subjectFor( subjectId );

	if ( review === null || subject === undefined ) {
		return subjectId;
	}

	const holder = review.editConflict.holderOf( subjectId );
	const holdingProperty = holder === undefined ? undefined : conflictPropertyOf( holder.subjectId, holder.propertyName );

	return holdingProperty === undefined ?
		subjectDisplayName( subject ) :
		dependentSubjectEntryName( propertyDisplayName( holdingProperty ), subject, conflictSchemaOf( subject, review ) );
}

function conflictPropertyOf( subjectId: string, propertyName: string ): PropertyDefinition | undefined {
	const review = conflict.value;
	const subject = review?.editConflict.subjectFor( subjectId );

	if ( review === null || subject === undefined ) {
		return undefined;
	}

	const schema = subjectId === review.editConflict.hostId ?
		panes.value.find( ( pane ) => pane.id === review.paneId )?.schema :
		conflictSchemaOf( subject, review );

	return schema?.getPropertyDefinitions().has( new PropertyName( propertyName ) ) ?
		schema.getPropertyDefinition( new PropertyName( propertyName ) ) :
		undefined;
}

function conflictSchemaOf( subject: Subject, review: ConflictReview ): Schema | undefined {
	return review.theirDependentSubjects.schemas.find( ( candidate ) => candidate.getName() === subject.getSchemaName() ) ??
		dependentSubjectEditing.schemas.get( subject.getSchemaName() );
}

// Their version with your changes and the choices made goes into the pane, which starts over from it as changed. Its
// Dependent Subjects are all written, so none is left as either side had it; those their version lacks are new to it.
async function applyConflictChoices(): Promise<void> {
	const review = conflict.value;

	if ( review === null ) {
		return;
	}

	const [ subject, ...dependentSubjects ] = review.editConflict.resolve( review.choices, conflictPropertyOf );
	const theirIds = new Set( review.theirDependentSubjects.subjects.map( ( theirs ) => theirs.getId().text ) );
	const schemas = review.theirDependentSubjects.schemas;

	dependentSubjectEditing.include( { subjects: dependentSubjects.filter( ( dependent ) => theirIds.has( dependent.getId().text ) ), schemas }, false );
	dependentSubjectEditing.include( { subjects: dependentSubjects.filter( ( dependent ) => !theirIds.has( dependent.getId().text ) ), schemas }, true );
	dependentSubjects.forEach( ( dependentSubject ) => dependentSubjectEditing.markChanged( dependentSubject.getId().text ) );
	conflictBases.set( review.paneId, [ review.theirSubject, ...review.theirDependentSubjects.subjects ] );
	replacePaneSubject( review.paneId, subject );
	changedPaneIds.add( review.paneId );
	paneReloads.set( review.paneId, ( paneReloads.get( review.paneId ) ?? 0 ) + 1 );
	conflict.value = null;
	await nextTick();
}

async function editInTheForm(): Promise<void> {
	const paneId = conflict.value?.paneId;
	await applyConflictChoices();

	if ( paneId !== undefined ) {
		await focusPane( paneId );
	}
}

function replacePaneSubject( paneId: string, subject: Subject ): void {
	if ( paneId === rootPaneId.value ) {
		loadedRoot.value = subject;
		return;
	}

	changeExtraPane( paneId, { subject } );
}

// Said of the write it is attached to, not of the save as a whole: one save can create a Subject
// and update the one that points at it, on two pages, each getting a revision of its own.
function defaultSummaryFor( pane: EditPane ): string {
	return mw.msg( pane.isNew ?
		'neowiki-subject-editor-summary-default-create' :
		'neowiki-subject-editor-summary-default' );
}

// The saved Schema may target Schemas the editor has not loaded, which decide how its fields edit.
const onSchemaSaved = async ( schema: Schema ): Promise<void> => {
	currentSchema.value = schema;

	const epoch = openEpoch.value;
	let schemas: Map<string, Schema | null>;

	try {
		schemas = await dependentSubjectLoader.loadSchemas( [ schema ], dependentSubjectEditing.schemas );
	} catch ( error ) {
		console.error( 'Failed to load the Schemas the saved Schema targets:', error );
		return;
	}

	if ( epoch === openEpoch.value ) {
		dependentSubjectEditing.include( { subjects: [], schemas: loadedSchemasOf( schemas ) }, false );
	}
};

defineExpose( { hasChanged: hasUnsavedEdits, rootLabel, shown, visible } );

</script>

<style lang="less">
@import ( reference ) '@wikimedia/codex-design-tokens/theme-wikimedia-ui.less';
@import ( reference ) '@/assets/mixins.less';

.ext-neowiki-subject-editor-dialog {
	/* The navigator's width before the reader sets one, as `NAVIGATOR_SIZE` has it. A literal
		because Codex's size scale has no step between `@size-1600` and `@size-2400`. */
	@navigator-width: 20rem;
	/* The form's width plus the navigator and the divider beside it. */
	@wide-width: calc( @size-3200 + @navigator-width + @spacing-75 );

	/* The height limit is set here: a Subject that reached a limit above the height held would make
		its dialog move on every switch.

		Codex's own 16px/24px body padding is dropped by the mixin and handed to the regions inside,
		so the navigator's rows can reach the dialog's edge and each pane scrolls with its own
		inset. Compounded, as the Schema dialogs' is, to outrank Codex's dialog rules however it
		scopes them. */
	&.cdx-dialog {
		.ext-neowiki-dialog-grid-body();
		max-height: @ext-neowiki-dialog-height-limit;
		min-height: min( var( --ext-neowiki-dialog-grown-height ), @ext-neowiki-dialog-height-limit );
	}

	/* Directed by its own first letter: an id from another Source may end in punctuation, which a
		right-to-left page would otherwise move to the front. Sized like the id on the Data tab, and
		trimmed like the title beside it, as the monospace font leaves more room below its letters. */
	&__subject-id {
		margin: 0;
		color: @color-subtle;
		unicode-bidi: plaintext;
		font-family: @font-family-monospace;
		font-size: @font-size-x-small;
		text-box: trim-both cap alphabetic;
	}

	/* Widened by the navigator alone, so the form keeps the width it had.

		Compounds `.cdx-dialog` because Codex sets `max-width: 32rem` on it, in a sheet the
		component injects at runtime, after our own linked one — so an equal-specificity tie goes
		to Codex. Drop the compound and the dialog silently narrows to 512px, which no test sees. */
	&--wide.cdx-dialog {
		max-width: @wide-width;
	}

	/* Scoped to this dialog: the same list appears in the subject creator, whose body keeps
		Codex's padding. No inset below, because the list sets its own margin there.
		Codex zeroes the top and bottom insets of the body's own first and last children; the
		notices escape that only by being a child of `__content` rather than of the body. */
	&__content > .ext-neowiki-edit-notices {
		padding: @spacing-50 @spacing-150 0;
	}

	/* The notices across the top, the panes beneath them taking the height that is left.

		`minmax( 0, 1fr )` rather than `auto` for the second row, so the panes scroll themselves
		rather than push the scrolling up to the dialog body, which would take the navigator
		off-screen with the form.

		jsdom resolves no layout, so the suite cannot see this grid collapse. Measure every change
		in a browser, at more than one viewport height, with and without an edit notice, and with
		and without the navigator. */
	&__content {
		display: grid;
		grid-template-rows: auto minmax( 0, 1fr );
		/* Otherwise this grid item takes its whole natural length out of the dialog body's
			scroller instead of dividing the height the body has. */
		min-height: 0;
	}

	/* Placed rather than flowed: the notices above are conditional, so auto-placement puts the
		panes in the notices' row whenever there is no notice. */
	&__panes {
		grid-row: 2;
	}

	/* Nothing at the sides: a row carries the form's inset itself, so its name lines up with the
		dialog's title, the notices above and the form beside it, while its hover and selected
		backgrounds reach the dialog's edge. Nothing above: the first row's text starts as far down
		as the form's first line. */
	& .ext-neowiki-open-subject-list {
		padding: 0 0 @spacing-50;
	}

	/* Every inset the form has, on this padded element rather than on the scroller around it:
		a padding-bottom inside an overflow container is the one browsers have historically
		dropped. */
	&__panels {
		padding: @spacing-50 @spacing-150 @spacing-100;
	}

	&__partial-save {
		margin: 0 0 @spacing-50;
	}

	/* In the panes' row while they are hidden, so it scrolls itself as they do, with the inset of a pane. */
	&__conflict {
		overflow-y: auto;
		padding: @spacing-50 @spacing-150 @spacing-100;
	}
}
</style>
