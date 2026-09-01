<template>
	<div class="ext-neowiki-subject-editor-container">
		<!-- Codex switches `cdx-dialog--dividers` on by itself only when the dialog body scrolls,
			which this body never does: the navigator and the form each scroll inside it. The
			dividers are what the rule between those two surfaces runs into, so ask by name. -->
		<CdxDialog
			:open="open"
			class="ext-neowiki-ui ext-neowiki-subject-editor-dialog cdx-dialog--dividers"
			:class="{ 'ext-neowiki-subject-editor-dialog--wide': showsNavigator }"
			:title="$i18n( 'neowiki-subject-editor-title', props.subject.getDisplayName() ).text()"
			@update:open="onDialogUpdateOpen"
		>
			<template #header>
				<div
					class="cdx-dialog__header__title-group"
					:inert="saving || undefined"
				>
					<div class="cdx-dialog__header__title ext-neowiki-subject-editor-dialog__title">
						<EditableText
							:model-value="subjectLabel"
							:edit-button-label="$i18n( 'neowiki-subject-editor-rename' ).text()"
							:input-aria-label="$i18n( 'neowiki-subject-editor-label-field' ).text()"
							:placeholder="labelPlaceholder"
							@update:model-value="onLabelEdited"
						/>
					</div>

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
					@click="closeRequested"
				>
					<CdxIcon :icon="cdxIconClose" />
				</CdxButton>
			</template>

			<!-- Only the navigator's surface is ever conditionally rendered, and the panes container
				is deliberately unkeyed: a pane must never unmount, because its unsaved values live in
				the ValueInput refs inside it. -->
			<div
				class="ext-neowiki-subject-editor-dialog__content"
				:inert="saving || undefined"
			>
				<EditNoticeList :notices="notices" />

				<div
					v-if="showsNavigator"
					class="ext-neowiki-subject-editor-dialog__surface"
				>
					<!-- Load-bearing, and invisible in the rendered output: it starts a new walk, with
						no memory of which fetches failed, on each opening and each new root. Codex's
						own v-if on the slot happens to unmount the walk too; do not rely on that. -->
					<SubjectTree
						:key="openEpoch"
						:root-subject="props.subject"
						:root-schema="currentSchema"
						:open-ids="openIds"
						:active-id="activePaneId"
						:unsaved-ids="unsavedIds"
						:edited-subjects="editedSubjects"
						@select="openRelationTarget"
					/>
				</div>

				<div class="ext-neowiki-subject-editor-dialog__surface">
					<div class="ext-neowiki-subject-editor-dialog__panels">
						<div
							v-for="pane in panes"
							v-show="pane.id === activePaneId"
							:id="`ext-neowiki-panel-${ pane.id }`"
							:key="pane.id"
							tabindex="-1"
						>
							<SubjectEditPane
								:ref="( el ) => setPaneRef( pane.id, el )"
								:subject="pane.subject"
								:schema="pane.schema"
								:nested="pane.id !== rootPaneId"
								:is-new="pane.isNew"
								:unsaved-target-ids="draftIds"
								@edit-relation-target="openRelationTargetFromForm"
							/>
						</div>
					</div>
				</div>
			</div>

			<template #footer>
				<CdxMessage
					v-if="partialSave !== null"
					:inline="true"
					type="warning"
					class="ext-neowiki-subject-editor-dialog__partial-save"
				>
					{{ partialSaveMessage }}
				</CdxMessage>
				<SummaryAction
					help-text=""
					:footer-text="saveScopeText"
					:save-button-label="$i18n( 'neowiki-subject-editor-save' ).text()"
					:save-disabled="!anyChanged || saving"
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
import { ref, shallowRef, shallowReactive, nextTick, computed, provide, watch } from 'vue';
import SubjectEditPane from '@/components/SubjectEditor/SubjectEditPane.vue';
import type { SubjectEditPaneExposes } from '@/components/SubjectEditor/SubjectEditPane.vue';
import SubjectTree from '@/components/SubjectEditor/SubjectTree.vue';
import SummaryAction from '@/components/common/SummaryAction.vue';
import I18nSlot from '@/components/common/I18nSlot.vue';
import EditableText from '@/components/common/EditableText.vue';
import EditNoticeList from '@/components/common/EditNoticeList.vue';
import { CdxButton, CdxDialog, CdxIcon, CdxMessage } from '@wikimedia/codex';
import { cdxIconClose } from '@wikimedia/codex-icons';
import { Subject } from '@/domain/Subject.ts';
import { enteredSubjectLabel } from '@/domain/enteredSubjectLabel.ts';
import { SubjectWithContext } from '@/domain/SubjectWithContext.ts';
import { SubjectId } from '@/domain/SubjectId.ts';
import type { PageIdentifiers } from '@/domain/PageIdentifiers.ts';
import { StatementList } from '@/domain/StatementList.ts';
import { Schema } from '@/domain/Schema.ts';
import { SubjectCreationKey } from '@/components/common/SubjectCreation.ts';
import { SubjectIdInUseError } from '@/persistence/SubjectIdInUseError';
import SchemaEditorDialog from '@/components/SchemaEditor/SchemaEditorDialog.vue';
import type { SchemaSaveHandler } from '@/components/SchemaEditor/SchemaEditorDialog.vue';
import CloseConfirmationDialog from '@/components/common/CloseConfirmationDialog.vue';
import { useSchemaPermissions } from '@/composables/useSchemaPermissions.ts';
import { useCloseConfirmation } from '@/composables/useCloseConfirmation.ts';
import { useEditNotices } from '@/composables/useEditNotices.ts';
import { NeoWikiExtension } from '@/NeoWikiExtension.ts';
import { ValidationFailedError } from '@/persistence/ValidationFailedError';
import type { UnparseableInput } from '@/components/common/UnparseableInput.ts';
import { NeoWikiServices } from '@/NeoWikiServices.ts';
import { relationTargetsOf } from '@/components/SubjectEditor/SubjectTreeModel.ts';

type SubjectSaveHandler = ( subject: Subject, comment: string ) => Promise<void>;

/**
 * Writes a Subject this session invented, carrying the id minted for it, as a Subject of the
 * given page. Hosts that leave it out get no create affordance in the relation fields.
 */
type SubjectCreateHandler = ( subject: Subject, pageId: number, comment: string ) => Promise<void>;

const props = defineProps<{
	subject: Subject;
	schema: Schema;
	onSave: SubjectSaveHandler;
	onSaveSchema: SchemaSaveHandler;
	onCreate?: SubjectCreateHandler;
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

const isSchemaEditorOpen = ref( false );
// Mirrors the prop so a schema saved through the nested SchemaEditorDialog takes effect here
// without waiting for the host to pass a new one down.
const currentSchema = shallowRef<Schema>( props.schema );
const { canEditSchema, checkEditPermission } = useSchemaPermissions();
const { notices, loadNotices } = useEditNotices( () => NeoWikiExtension.getInstance().getEditNoticeRepository() );
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

const extraPanes = shallowRef<EditPane[]>( [] );
const activePaneId = ref<string>( rootPaneId.value );

// Set when a save wrote some Subjects and then failed: the toast that says so vanishes,
// and the dialog is still open.
const partialSave = ref<{ written: number; attempted: number } | null>( null );

const panes = computed( (): EditPane[] => [
	{ id: rootPaneId.value, subject: props.subject, schema: currentSchema.value, isNew: false },
	...extraPanes.value
] );

// Subjects this session invented, as the panes editing them currently hold them, so a draft
// renamed in its pane is renamed everywhere that names it.
const draftSubjects = computed( (): Subject[] => panes.value
	.filter( ( pane ) => pane.isNew )
	.map( ( pane ) => editedSubjects.value.get( pane.id ) ?? pane.subject )
);

const draftIds = computed( (): string[] => draftSubjects.value.map( ( subject ) => subject.getId().text ) );

// Drafts something being edited still points at. A draft reached only through another draft counts
// too, so a chain stands or falls together. One the user has since pointed the relation away from
// does not: it is not written, and Save neither waits on it nor leaves it behind.
const referencedDraftIds = computed( (): Set<string> => {
	const reached = new Set<string>();
	// Only Subjects the wiki already holds anchor the walk: a draft cannot justify itself.
	const queue = panes.value.filter( ( pane ) => !pane.isNew ).map( ( pane ) => pane.id );

	while ( queue.length > 0 ) {
		const id = queue.pop() as string;
		const pane = panes.value.find( ( candidate ) => candidate.id === id );

		if ( pane === undefined ) {
			continue;
		}

		const subject = editedSubjects.value.get( pane.id ) ?? pane.subject;

		for ( const { targetId } of relationTargetsOf( subject, pane.schema ) ) {
			if ( !reached.has( targetId ) ) {
				reached.add( targetId );
				queue.push( targetId );
			}
		}
	}

	return reached;
} );

function isUnwrittenDraft( pane: EditPane ): boolean {
	return pane.isNew && referencedDraftIds.value.has( pane.id );
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
			isUnwrittenDraft( entry.pane ) :
			entry.instance.hasChanged
	) )
);

const unsavedIds = computed( (): string[] => dirtyPanes.value.map( ( { pane } ) => pane.id ) );

const anyChanged = computed( (): boolean => dirtyPanes.value.length > 0 );

// One copy per mounted pane. A pane's own copy is refreshed on relation changes alone, so
// the live label is laid over it here and the tree names a Subject the way its form does.
const editedSubjects = computed( (): Map<string, Subject> => {
	const subjects = new Map<string, Subject>();

	for ( const pane of panes.value ) {
		const instance = paneRefs.get( pane.id );
		// The pane's own copy until its ref registers, one tick behind the pane being added.
		// Without it the tree would take a Subject it already holds for one still to fetch,
		// and a Subject created here has nothing to fetch.
		subjects.set( pane.id, instance === undefined ? pane.subject : withLiveLabel( instance ) );
	}

	return subjects;
} );

// Rebuilt only when the label has moved, so an unrenamed Subject keeps the very object the
// tree already walked. The field's text is read the way a write reads it.
function withLiveLabel( instance: SubjectEditPaneExposes ): Subject {
	const edited = instance.editedSubject;
	const label = enteredSubjectLabel( instance.label );
	return edited.getLabel() === label ? edited : edited.withLabel( label );
}

const rootPane = computed( (): SubjectEditPaneExposes | undefined => paneRefs.get( rootPaneId.value ) );

// The root as the tree will walk it: the root pane's copy once that pane has registered, the
// prop before. Labels have no bearing on relation targets, so the live label is left off.
const treeRootSubject = computed( (): Subject => rootPane.value?.editedSubject ?? props.subject );

// The navigator is rendered only once the tree would draw a row other than its own root.
// The root's relation statements settle the walk, which starts there. An open pane is the
// second case: it is never unmounted, and the tree is the only control that reaches it, so
// clearing the relation that led to it must not take the navigator away.
//
// Both are read synchronously, so a target counts as soon as it is picked and the gate
// cannot flicker while a label fetch is in flight.
const showsNavigator = computed( (): boolean =>
	relationTargetsOf( treeRootSubject.value, currentSchema.value ).length > 0 ||
	extraPanes.value.length > 0
);

const openIds = computed( (): string[] => panes.value.map( ( pane ) => pane.id ) );

// Owned by the pane that edits the Subject. Falls back to the prop for the first render,
// before that pane has registered its ref.
const subjectLabel = computed( (): string => rootPane.value?.label ?? props.subject.getLabel() ?? '' );

// The rename field previews the name a cleared label leaves behind, which the client knows only
// for a Subject that already has none: the server holds the inputs, and it is what the display
// name of such a Subject already is. For a labelled one the generic hint stands in.
const labelPlaceholder = computed( (): string =>
	props.subject.getLabel() === null ?
		props.subject.getDisplayName() :
		mw.msg( 'neowiki-subject-editor-label-field' )
);

function onLabelEdited( value: string ): void {
	rootPane.value?.setLabel( value );
}

// Keys the tree, and nothing else: re-keying the panes would unmount them and destroy
// unsaved values. The tree remembers which fetches failed for the life of its mount, so
// without a remount one transient failure would leave that branch empty for the rest of the
// session. The two watchers further down bump it, an opening and a replaced root alike, and
// a target fetch that outlives its opening is dropped by it: the hosts keep this dialog
// mounted after it closes, so the fetch would otherwise land in the next opening.
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
		const subject = await subjectRepository.getSubject( targetId );
		const schema = await schemaRepository.getSchema( subject.getSchemaName() );
		if ( epoch !== openEpoch.value ) {
			return;
		}
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

// The page a Subject created here is stored on: the one holding the Subject whose relation is
// being filled in, since that is the Subject the new one belongs beside. Panes routinely span
// pages, so the dialog's own page would be the wrong answer as soon as the user has drilled in.
// The root stands in for a pane whose Subject arrived without page context.
function creationPage(): PageIdentifiers | null {
	const activePane = panes.value.find( ( pane ) => pane.id === activePaneId.value );

	for ( const subject of [ activePane?.subject, props.subject ] ) {
		if ( subject instanceof SubjectWithContext && Number.isInteger( subject.getPageIdentifiers().getPageId() ) ) {
			return subject.getPageIdentifiers();
		}
	}

	return null;
}

// Creates the Subject a relation field is about to point at, and opens it for editing. Nothing
// is written: the id is minted, which reserves nothing, and the Subject itself reaches the wiki
// only when this dialog is saved. The pane is added and made active before this returns, so the
// relation the caller then records lands in the same render as the pane and the tree node.
async function createRelationTarget( schemaName: string, label: string | null ): Promise<Subject | null> {
	const page = creationPage();

	// A Subject added while the write loop is running would be referenced by a Subject already
	// written and yet never written itself, so creation is closed for the duration of a save.
	if ( page === null || saving.value ) {
		mw.notify( mw.msg( 'neowiki-subject-editor-create-target-error' ), { type: 'error' } );
		return null;
	}

	const epoch = openEpoch.value;

	try {
		const [ schema, id ] = await Promise.all( [
			schemaRepository.getSchema( schemaName ),
			subjectRepository.mintSubjectId()
		] );

		// The dialog was reopened or given another root while this was in flight, or a save has
		// started: its panes are gone or already harvested, and a pane added now would belong to
		// nothing.
		if ( epoch !== openEpoch.value || saving.value ) {
			return null;
		}

		const subject = new SubjectWithContext(
			id,
			label,
			// What the server would derive for a Subject with no label of its own (ADR 31).
			label ?? schemaName,
			schemaName,
			new StatementList( [] ),
			page
		);

		extraPanes.value = [ ...extraPanes.value, { id: id.text, subject, schema, isNew: true } ];
		activePaneId.value = id.text;
		focusPanel( id.text );

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

// Navigating from a control inside a form hides the pane that control lives in, so the
// browser blurs it to <body> and the next Tab restarts at the top of the dialog. The panel
// wrapper takes the focus instead, which is what its tabindex="-1" is for. Activation from
// the tree is left alone: focus belongs on the treeitem there.
async function openRelationTargetFromForm( targetId: SubjectId ): Promise<void> {
	await openRelationTarget( targetId );
	await focusPanel( targetId.text );
}

async function focusPanel( paneId: string ): Promise<void> {
	await nextTick();
	document.getElementById( `ext-neowiki-panel-${ paneId }` )?.focus();
}

function close(): void {
	emit( 'update:open', false );
}

const { confirmationOpen, requestClose, confirmClose, cancelClose } = useCloseConfirmation( anyChanged, close );

// Nothing may change or close while the loop below is writing: it harvests every dirty
// pane up front and marks each one clean as its write lands, so an edit made meanwhile
// would be reverted by the written copy and left unguarded by the discard confirmation.
// Bound as `undefined` rather than `false` where it is off. A browser that knows `inert`
// exposes it as a property, which Vue sets, so `false` would do there; where it is only an
// attribute, as in jsdom, Vue would write inert="false", which an element reads as present.
const saving = ref( false );

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

// Immediate, because the hosts render this dialog with v-if: it mounts with open
// already true, so a deferred watcher would never see the opening that created it.
// An opening and a replaced root both start over: a dialog only reaches `open: false` with
// nothing unsaved or after a confirmed discard (see useCloseConfirmation), so nothing an
// earlier session opened or reported carries into the next.
function startSession(): void {
	openEpoch.value += 1;
	extraPanes.value = [];
	activePaneId.value = rootPaneId.value;
	partialSave.value = null;
}

watch( () => props.open, ( isOpen ) => {
	if ( isOpen ) {
		startSession();
		// Fetched per opening: approval state and the viewer's permissions both change
		// without an edit. Keyed on the page being viewed and on the root Subject's Schema.
		loadNotices( Number( mw.config.get( 'wgArticleId' ) ), props.subject.getSchemaName() );
	}
}, { immediate: true } );

// The host can replace props.subject while the dialog stays open, e.g. saving one subject
// navigates straight to editing another. Without this, activePaneId keeps naming the old
// root, no pane in the new `panes` list matches it, and nothing renders at all.
watch( rootPaneId, startSession );

watch( () => props.subject, ( newSubject ) => {
	checkEditPermission( newSubject.getSchemaName() );
}, { immediate: true } );

watch( () => props.schema, ( newSchema ) => {
	currentSchema.value = newSchema;
} );

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

// Counted in pages as well as in Subjects, since each page written gets revisions of its own.
// A Subject with no resolved page counts as a page of its own.
function pageKeyOf( subject: Subject ): string {
	return subject instanceof SubjectWithContext ?
		`page:${ subject.getPageIdentifiers().getPageId() }` :
		`subject:${ subject.getId().text }`;
}

const dirtyPageCount = computed( (): number => new Set(
	dirtyPanes.value.map( ( { pane } ) => pageKeyOf( pane.subject ) )
).size );

// Said only when it tells the user something the screen does not: the Save button plainly
// writes the one dirty Subject in front of them.
const saveScopeText = computed( (): string => {
	const dirty = unsavedIds.value;

	if ( dirty.length === 0 || ( dirty.length === 1 && dirty[ 0 ] === activePaneId.value ) ) {
		return '';
	}

	return mw.message( 'neowiki-subject-editor-save-scope', dirty.length, dirtyPageCount.value ).text();
} );

const handleSave = async ( summary: string ): Promise<void> => {
	if ( saving.value ) {
		return;
	}
	saving.value = true;
	try {
		await writeDirtyPanes( summary );
	} finally {
		saving.value = false;
	}
};

// A pane stops being new the moment its Subject exists on the wiki, so a save that fails further
// down re-runs as an update rather than offering the same id to the server a second time. The
// panes are a shallowRef, so the flag is cleared by replacing the entry rather than mutating it.
function markPaneWritten( id: string ): void {
	extraPanes.value = extraPanes.value.map(
		( pane ) => pane.id === id ? { ...pane, isNew: false } : pane
	);
}

// A Subject this session invented has to be created rather than replaced, on the page its pane
// was opened against.
async function writeSubject( pane: EditPane, subject: Subject, comment: string ): Promise<void> {
	if ( !pane.isNew ) {
		await props.onSave( subject, comment );
		return;
	}

	if ( props.onCreate === undefined || !( pane.subject instanceof SubjectWithContext ) ) {
		throw new Error( 'No page to create this Subject on' );
	}

	try {
		await props.onCreate( subject, pane.subject.getPageIdentifiers().getPageId(), comment );
	} catch ( error ) {
		// The id was minted for this Subject alone, so the server holding it already means this
		// very create landed and only its answer was lost. Retrying the create would refuse
		// forever; recording it as written turns the retry into the update it now needs to be.
		if ( !( error instanceof SubjectIdInUseError ) ) {
			throw error;
		}
	}

	markPaneWritten( pane.id );
}

type WriteTarget = { pane: EditPane; subject: Subject };

// A Subject is written after every Subject it points at that this save is also creating, so no
// write ever names a target the server does not have yet. Only the created Subjects need ordering
// among themselves — the ones the wiki already holds can be written in any order, and go last so a
// save that stops part way has not yet pointed an existing Subject at a target it failed to create.
// Depth-first over the drafts, emitting each after its own; a cycle stops at the visited check,
// which the relation model allows and which no ordering could satisfy anyway.
function writeOrder( targets: ReadonlyMap<string, WriteTarget> ): [ string, WriteTarget ][] {
	const ordered: [ string, WriteTarget ][] = [];
	const visited = new Set<string>();

	function emitDraft( id: string ): void {
		const target = targets.get( id );

		if ( visited.has( id ) || target === undefined || !target.pane.isNew ) {
			return;
		}

		visited.add( id );

		for ( const { targetId } of relationTargetsOf( target.subject, target.pane.schema ) ) {
			emitDraft( targetId );
		}

		ordered.push( [ id, target ] );
	}

	for ( const [ id, target ] of targets ) {
		if ( target.pane.isNew ) {
			emitDraft( id );
		}
	}

	for ( const entry of targets ) {
		if ( !entry[ 1 ].pane.isNew ) {
			ordered.push( entry );
		}
	}

	return ordered;
}

async function writeDirtyPanes( summary: string ): Promise<void> {
	await nextTick();

	partialSave.value = null;

	// Every mounted pane, not just the dirty ones: a validation round trip is awaited here, and a
	// pane can join the dirty set while it runs. The write set is taken afterwards, or a Subject
	// created inside that window would go unwritten while the relation naming it was written.
	await Promise.all( panes.value.map( ( pane ) => paneRefs.get( pane.id )?.flushValidation() ) );

	const dirty = dirtyPanes.value;

	// Saving now would silently drop text the user can still see. Checked across every
	// dirty Subject before any write, or an unparseable later one would leave the stack
	// half written.
	const unparseable = dirty
		.map( ( entry ) => ( { id: entry.pane.id, input: entry.instance.unparseableInput() } ) )
		.find( ( entry ): entry is { id: string; input: UnparseableInput } => entry.input !== null );

	if ( unparseable !== undefined ) {
		await showSubject( unparseable.id );
		mw.notify( unparseable.input.message, { title: unparseable.input.propertyName, type: 'error' } );
		return;
	}

	const editSummary = summary || mw.msg( 'neowiki-subject-editor-summary-default' );
	const savedNames: string[] = [];

	// A Subject an earlier attempt already wrote is not in `dirty`: its pane reset its
	// changed flag.
	const targets = new Map<string, { pane: EditPane; subject: Subject }>();

	for ( const { pane, instance } of dirty ) {
		const updated = instance.buildUpdatedSubject();
		if ( updated === null ) {
			// A dirty pane with no data to save means it lost its editor ref;
			// surface this as an error instead of silently discarding the edit.
			mw.notify( mw.msg( 'neowiki-subject-editor-error', props.subject.getDisplayName() ), { type: 'error' } );
			return;
		}
		targets.set( pane.id, { pane, subject: updated } );
	}

	const orderedTargets = writeOrder( targets );

	// Carried out of the loop rather than returned from inside it, so a part-way stop is
	// reported once, below.
	let failed = false;

	for ( const [ id, { pane, subject: updatedSubject } ] of orderedTargets ) {
		const subjectName = updatedSubject.getDisplayName();

		try {
			await writeSubject( pane, updatedSubject, editSummary );
			paneRefs.get( id )?.resetChanged();
			savedNames.push( subjectName );
		} catch ( error ) {
			failed = true;
			// The toast naming the refused Subject vanishes; its pane is what stays.
			await showSubject( id );

			if ( error instanceof ValidationFailedError ) {
				paneRefs.get( id )?.setServerViolations( error.violations );
				mw.notify(
					mw.msg( 'neowiki-subject-editor-validation-failed', subjectName ),
					{ type: 'error' }
				);
			} else {
				mw.notify(
					error instanceof Error ? error.message : String( error ),
					{
						title: mw.msg( 'neowiki-subject-editor-error', subjectName ),
						type: 'error'
					}
				);
			}

			break;
		}
	}

	if ( failed ) {
		if ( savedNames.length > 0 ) {
			partialSave.value = { written: savedNames.length, attempted: targets.size };
		}
		return;
	}

	if ( savedNames.length === 0 ) {
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

const onSchemaSaved = ( schema: Schema ): void => {
	currentSchema.value = schema;
};

defineExpose( { hasChanged: anyChanged } );

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

	/* Codex puts these on `.cdx-dialog__header--default`, a class it adds only to the header
		it builds itself, so a slotted header never receives them and there is nothing here to
		out-weigh — they are replicated, not overridden. */
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

	&--wide.cdx-dialog {
		/* Compounds `.cdx-dialog` because Codex sets `max-width: 32rem` on it, in a sheet the
			component injects at runtime, after our own linked one — so an equal-specificity tie goes
			to Codex. Drop the compound and the dialog silently narrows to 512px, which no test sees.
			A literal because the scale stops short: Codex's largest size token is `@size-5600`. */
		max-width: 80rem;
	}

	/* Codex gives this body `flex-grow: 1` and `overflow-y: auto` but no `min-height: 0`, so as
		a flex item it refuses to shrink below its content and that overflow never engages.
		`min-height: 0` engages it, and `display: grid` hands the bounded height down: the body
		holds one child, which as a grid item stretches to the body instead of overflowing it.

		Codex's own 16px/24px padding is dropped here and handed to the regions inside, because
		the border between the two surfaces is the only rule between the navigator and the form
		and that padding would hold it short of the header's and the footer's rules. */
	.cdx-dialog__body {
		padding: 0;
		min-height: 0;
		display: grid;
	}

	/* Scoped to this dialog: the same list appears in the subject creator, whose body keeps
		Codex's padding. No inset below, because the list sets its own margin there.
		Codex zeroes the top and bottom insets of the body's own first and last children; the
		notices escape that only by being a child of `__content` rather than of the body. */
	&__content > .ext-neowiki-edit-notices {
		grid-column: 1 / -1;
		padding: @spacing-100 @spacing-150 0;
	}

	/* The notices across the top, the navigator and the form side by side beneath them.

		`minmax( 0, 1fr )` rather than `auto` for the second row: both cells stretch to it and
		each scrolls itself, which keeps the navigator in view while the form is scrolled. Under
		`auto` the taller cell sets the row's height, pushing the scrolling up to the dialog body
		and taking the navigator off-screen with the form.

		No column gap: the rule between the two surfaces is a border, and a gap would leave it
		floating in empty space instead of terminating an edge.

		jsdom resolves no layout, so the suite cannot see this grid collapse. Measure every change
		in a browser, at more than one viewport height, with and without an edit notice, and with
		and without the navigator. */
	&__content {
		display: grid;
		grid-template-columns: 1fr;
		grid-template-rows: auto minmax( 0, 1fr );
		/* Otherwise this grid item takes its whole natural length out of the dialog body's
			scroller instead of dividing the height the body has. */
		min-height: 0;
	}

	/* `--wide` is bound to the same condition as the navigator's own `v-if`. Declaring both
		columns unconditionally would leave a navigator-wide void beside a form with no
		navigator to fill it. */
	&--wide &__content {
		grid-template-columns: @size-2400 1fr;
	}

	/* Both columns of the second row: a scroll container holding one inner element that
		carries the padding. Scrolling here rather than at the dialog body is what keeps the
		navigator in view while the form is scrolled, and `min-height: 0` is what lets a grid
		item shrink below its content at all. */
	&__surface {
		min-width: 0;
		min-height: 0;
		overflow-y: auto;
	}

	/* The tree carries no inset of its own; this dialog gives it the form's, on its padded
		element rather than on the scroller around it. The gutter is the dialog's 24px less the
		6px a row already carries, so a node's TEXT lands on that gutter, in line with the
		notices above and the form beside it, while the row's hover and selected backgrounds
		keep reaching the 6px further out — a background flush with the text would read as
		clipped. Nothing on the end side, so the scrollbar sits flush with the divider. */
	& .ext-neowiki-subject-tree {
		padding: @spacing-100 0 @spacing-100 calc( @spacing-150 - @spacing-35 );
	}

	/* Drawn on the boundary rather than owned by either side. A surface only ever follows
		another when the navigator is rendered. */
	&__surface + &__surface {
		border-inline-start: @border-subtle;
	}

	/* Every inset the form has, on this padded element rather than on the scroller around it:
		a padding-bottom inside an overflow container is the one browsers have historically
		dropped. */
	&__panels {
		padding: @spacing-100 @spacing-150;
	}

	&__partial-save {
		margin: 0 0 @spacing-50;
	}
}
</style>
