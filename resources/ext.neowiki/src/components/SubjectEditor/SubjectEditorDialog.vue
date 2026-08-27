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
								:edited-copy="editedCopyFor( pane.id )"
								:nested="pane.id !== rootPaneId"
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
import { ref, shallowRef, shallowReactive, nextTick, computed, watch } from 'vue';
import SubjectEditPane from '@/components/SubjectEditor/SubjectEditPane.vue';
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
import { Schema } from '@/domain/Schema.ts';
import SchemaEditorDialog from '@/components/SchemaEditor/SchemaEditorDialog.vue';
import type { SchemaSaveHandler } from '@/components/SchemaEditor/SchemaEditorDialog.vue';
import CloseConfirmationDialog from '@/components/common/CloseConfirmationDialog.vue';
import { useSchemaPermissions } from '@/composables/useSchemaPermissions.ts';
import { useCloseConfirmation } from '@/composables/useCloseConfirmation.ts';
import { useEditNotices } from '@/composables/useEditNotices.ts';
import { NeoWikiExtension } from '@/NeoWikiExtension.ts';
import { ValidationFailedError } from '@/persistence/ValidationFailedError';
import type { SubjectViolation } from '@/domain/SubjectViolation';
import type { UnparseableInput } from '@/components/common/UnparseableInput.ts';
import { NeoWikiServices } from '@/NeoWikiServices.ts';
import { relationTargetsOf } from '@/components/SubjectEditor/SubjectTreeModel.ts';

type SubjectSaveHandler = ( subject: Subject, comment: string ) => Promise<void>;

const props = defineProps<{
	subject: Subject;
	schema: Schema;
	onSave: SubjectSaveHandler;
	onSaveSchema: SchemaSaveHandler;
	open: boolean;
}>();

const emit = defineEmits( [ 'update:open' ] );

interface SubjectEditPaneInstance {
	hasChanged: boolean;
	label: string;
	// Refreshed on relation changes alone, so its other statements lag: read it for the
	// tree, never to save or validate from.
	editedSubject: Subject;
	setLabel: ( value: string ) => void;
	resetChanged: () => void;
	buildUpdatedSubject: () => Subject | null;
	setServerViolations: ( violations: readonly SubjectViolation[] ) => void;
	unparseableInput: () => UnparseableInput | null;
	flushValidation: () => Promise<void>;
}

interface EditPane {
	id: string;
	subject: Subject;
	schema: Schema;
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
const paneRefs = shallowReactive( new Map<string, SubjectEditPaneInstance>() );

function setPaneRef( id: string, el: unknown ): void {
	if ( el ) {
		paneRefs.set( id, el as SubjectEditPaneInstance );
	} else {
		paneRefs.delete( id );
	}
}

const extraPanes = shallowRef<EditPane[]>( [] );
const activePaneId = ref<string>( rootPaneId.value );

// What the server took, per Subject written in this session, kept because it is newer than
// the fetched Subject its pane would otherwise fall back to. Cleared only when the dialog
// reopens or the root Subject is replaced.
const writtenSubjects = shallowRef<Map<string, Subject>>( new Map() );

// Set when a save wrote some Subjects and then failed: the toast that says so vanishes,
// and the dialog is still open.
const partialSave = ref<{ written: number; attempted: number } | null>( null );

function editedCopyFor( id: string ): Subject | undefined {
	return writtenSubjects.value.get( id );
}

const panes = computed( (): EditPane[] => [
	{ id: rootPaneId.value, subject: props.subject, schema: currentSchema.value },
	...extraPanes.value
] );

// A pane never unmounts while the dialog is open, so a dirty pane is the only place an
// unsaved edit can live. A Subject this save already wrote drops out until its pane is
// edited again, so it neither keeps Save enabled nor confirms a discard over nothing.
const unsavedIds = computed( (): string[] => panes.value
	.map( ( pane ) => pane.id )
	.filter( ( id ) => paneRefs.get( id )?.hasChanged === true )
);

const anyChanged = computed( (): boolean => unsavedIds.value.length > 0 );

// The written copies overlaid with the mounted panes, a mounted pane winning because it
// holds the newer values. A pane's own copy is refreshed on relation changes alone, so the
// live label is laid over it here and the tree names a Subject the way its form does.
const editedSubjects = computed( (): Map<string, Subject> => {
	const subjects = new Map<string, Subject>( writtenSubjects.value );

	for ( const pane of panes.value ) {
		const instance = paneRefs.get( pane.id );
		if ( instance !== undefined ) {
			subjects.set( pane.id, withLiveLabel( instance ) );
		}
	}

	return subjects;
} );

// Rebuilt only when the label has moved, so an unrenamed Subject keeps the very object the
// tree already walked. The field's text is read the way a write reads it.
function withLiveLabel( instance: SubjectEditPaneInstance ): Subject {
	const edited = instance.editedSubject;
	const label = enteredSubjectLabel( instance.label );
	return edited.getLabel() === label ? edited : edited.withLabel( label );
}

// The root as the tree will walk it, picked out the same way SubjectTree picks it: were the
// two to differ, the gate below and the tree would disagree about what the tree holds.
const treeRootSubject = computed( (): Subject =>
	editedSubjects.value.get( rootPaneId.value ) ?? props.subject
);

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

const rootPane = computed( (): SubjectEditPaneInstance | undefined => paneRefs.get( rootPaneId.value ) );

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
	if ( panes.value.some( ( pane ) => pane.id === id ) ) {
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
		extraPanes.value = [ ...extraPanes.value, { id, subject, schema } ];
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

// Navigating from a control inside a form hides the pane that control lives in, so the
// browser blurs it to <body> and the next Tab restarts at the top of the dialog. The panel
// wrapper takes the focus instead, which is what its tabindex="-1" is for. Activation from
// the tree is left alone: focus belongs on the treeitem there.
async function openRelationTargetFromForm( targetId: SubjectId ): Promise<void> {
	await openRelationTarget( targetId );
	await nextTick();
	document.getElementById( `ext-neowiki-panel-${ targetId.text }` )?.focus();
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
watch( () => props.open, ( isOpen ) => {
	if ( isOpen ) {
		openEpoch.value += 1;
		// Fetched per opening: approval state and the viewer's permissions both change
		// without an edit. Keyed on the page being viewed and on the root Subject's Schema.
		loadNotices( Number( mw.config.get( 'wgArticleId' ) ), props.subject.getSchemaName() );
		extraPanes.value = [];
		activePaneId.value = rootPaneId.value;
		// A dialog only reaches `open: false` with nothing unsaved or after a confirmed
		// discard (see useCloseConfirmation), so what an earlier session wrote must not
		// seed this one's panes.
		writtenSubjects.value = new Map();
		partialSave.value = null;
	}
}, { immediate: true } );

// The host can replace props.subject while the dialog stays open, e.g. saving one subject
// navigates straight to editing another. Without this, activePaneId keeps naming the old
// root, no pane in the new `panes` list matches it, and nothing renders at all.
watch( rootPaneId, ( newRootPaneId ) => {
	openEpoch.value += 1;
	extraPanes.value = [];
	activePaneId.value = newRootPaneId;
	// Written copies are keyed by Subject id alone, so they would otherwise seed a pane
	// of an unrelated root.
	writtenSubjects.value = new Map();
	partialSave.value = null;
} );

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

interface DirtyPane {
	id: string;
	instance: SubjectEditPaneInstance;
}

// The whole save set: the gates below and the write loop all read it.
function dirtyPanes(): DirtyPane[] {
	return panes.value
		.map( ( pane ) => ( { id: pane.id, instance: paneRefs.get( pane.id ) } ) )
		.filter( ( entry ): entry is DirtyPane => entry.instance?.hasChanged === true );
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
	panes.value
		.filter( ( pane ) => unsavedIds.value.includes( pane.id ) )
		.map( ( pane ) => pageKeyOf( pane.subject ) )
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

// A pane whose write succeeded while a later one failed keeps showing the values the server
// took rather than reverting to its pre-edit read. Non-disruptive to the pane: its own
// harvest built this Subject out of the Value instances its inputs still hold.
function recordWrite( id: string, written: Subject ): void {
	const next = new Map( writtenSubjects.value );
	next.set( id, written );
	writtenSubjects.value = next;
}

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

async function writeDirtyPanes( summary: string ): Promise<void> {
	await nextTick();

	partialSave.value = null;

	const dirty = dirtyPanes();

	for ( const { instance } of dirty ) {
		await instance.flushValidation();
	}

	// Saving now would silently drop text the user can still see. Checked across every
	// dirty Subject before any write, or an unparseable later one would leave the stack
	// half written.
	const unparseable = dirty
		.map( ( entry ) => ( { id: entry.id, input: entry.instance.unparseableInput() } ) )
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
	const targets = new Map<string, Subject>();

	for ( const { id, instance } of dirty ) {
		const updated = instance.buildUpdatedSubject();
		if ( updated === null ) {
			// A dirty pane with no data to save means it lost its editor ref;
			// surface this as an error instead of silently discarding the edit.
			mw.notify( mw.msg( 'neowiki-subject-editor-error', props.subject.getDisplayName() ), { type: 'error' } );
			return;
		}
		targets.set( id, updated );
	}

	// Carried out of the loop rather than returned from inside it, so a part-way stop is
	// reported once, below.
	let failed = false;

	for ( const [ id, updatedSubject ] of targets ) {
		const subjectName = updatedSubject.getDisplayName();

		try {
			await props.onSave( updatedSubject, editSummary );
			paneRefs.get( id )?.resetChanged();
			recordWrite( id, updatedSubject );
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
