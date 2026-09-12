<template>
	<div class="ext-neowiki-subjects-manager">
		<div class="ext-neowiki-subjects-manager__controls">
			<span
				v-if="!loading && subjects.length > 0"
				class="ext-neowiki-subjects-manager__count"
			>
				{{ $i18n( 'neowiki-managesubjects-count', subjects.length ).text() }}
			</span>
			<span v-else class="ext-neowiki-subjects-manager__count" />
			<div class="ext-neowiki-subjects-manager__controls-actions">
				<DataExportButton
					v-if="!loading && subjects.length > 0"
					:label="$i18n( 'neowiki-managesubjects-export-all-button' ).text()"
					:projections="rdfProjections"
					v-bind="pageExportUrls( pageId )"
				/>
				<CdxButton
					v-if="canCreate && !isCompletelyEmpty"
					weight="primary"
					action="progressive"
					@click="onAddClicked"
				>
					<CdxIcon :icon="cdxIconAdd" />
					{{ $i18n( 'neowiki-managesubjects-add-button' ).text() }}
				</CdxButton>
			</div>
		</div>

		<p
			v-if="loading"
			class="ext-neowiki-subjects-manager__loading"
		>
			…
		</p>

		<div
			v-else-if="isCompletelyEmpty"
			class="ext-neowiki-subjects-manager__empty-state"
		>
			<div class="ext-neowiki-subjects-manager__empty-state-text">
				<div class="ext-neowiki-subjects-manager__empty-state-title">
					{{ $i18n( 'neowiki-managesubjects-empty-title' ).text() }}
				</div>
				<div class="ext-neowiki-subjects-manager__empty-state-description">
					{{ $i18n( 'neowiki-managesubjects-empty-description' ).text() }}
				</div>
			</div>
			<CdxButton
				v-if="canCreate"
				weight="primary"
				action="progressive"
				@click="onAddClicked"
			>
				<CdxIcon :icon="cdxIconAdd" />
				{{ $i18n( 'neowiki-managesubjects-add-button' ).text() }}
			</CdxButton>
		</div>

		<template v-else>
			<ul
				ref="mainSlotRef"
				class="ext-neowiki-subjects-manager__main-slot"
			>
				<SubjectRow
					v-if="mainSubject !== null"
					:subject="mainSubject as Subject"
					emphasized
					main-subject-control="demote"
					:expanded="expandedIds.has( mainSubject.getId().text )"
					:highlighted="highlightedId === mainSubject.getId().text"
					:focused="focusedId === mainSubject.getId().text"
					:can-edit="canEdit"
					:can-delete="canDelete"
					:can-move="canEdit"
					:show-drag-handle="canEdit"
					:subject-page-url="subjectPageUrl( mainSubject.getId().text )"
					@toggle="toggleExpanded"
					@edit="openEditor"
					@demote="demoteFromMain"
					@move="openMoveDialog"
					@delete="confirmDelete"
					@copy-link="copySubjectLink"
				/>

				<li
					v-else
					class="ext-neowiki-subjects-manager__empty-state"
				>
					<div class="ext-neowiki-subjects-manager__empty-state-text">
						<CdxIcon
							class="ext-neowiki-subjects-manager__empty-state-icon"
							:icon="cdxIconPushPin"
						/>
						<div class="ext-neowiki-subjects-manager__empty-state-title">
							{{ $i18n( 'neowiki-managesubjects-no-main-title' ).text() }}
						</div>
						<div class="ext-neowiki-subjects-manager__empty-state-description">
							{{ $i18n( 'neowiki-managesubjects-no-main-description' ).text() }}
						</div>
					</div>
				</li>
			</ul>

			<h2 class="ext-neowiki-subjects-manager__section-heading">
				{{ $i18n( 'neowiki-managesubjects-other-subjects-heading' ).text() }}
			</h2>

			<ul
				ref="childListRef"
				class="ext-neowiki-subjects-manager__list"
				:class="{ 'ext-neowiki-subjects-manager__list--empty': !hasChildSubjects && canEdit }"
			>
				<SubjectRow
					v-for="subject in otherSubjects"
					:key="subject.getId().text"
					:subject="subject"
					main-subject-control="promote"
					:expanded="expandedIds.has( subject.getId().text )"
					:highlighted="highlightedId === subject.getId().text"
					:focused="focusedId === subject.getId().text"
					:can-edit="canEdit"
					:can-delete="canDelete"
					:can-move="canEdit"
					:show-drag-handle="canEdit"
					:subject-page-url="subjectPageUrl( subject.getId().text )"
					@toggle="toggleExpanded"
					@edit="openEditor"
					@promote="promoteToMain"
					@move="openMoveDialog"
					@delete="confirmDelete"
					@copy-link="copySubjectLink"
				/>
			</ul>

			<CdxButton
				v-if="canCreate"
				class="ext-neowiki-subjects-manager__add-more"
				weight="quiet"
				size="large"
				@click="onAddClicked"
			>
				<CdxIcon :icon="cdxIconAdd" />
				{{ $i18n( 'neowiki-managesubjects-add-button' ).text() }}
			</CdxButton>
		</template>

		<SubjectCreatorDialog
			v-if="canCreate"
			:host-page="{ hasMainSubject }"
		/>

		<SubjectEditorDialog
			v-if="editingSubject !== null && editingSchema !== null"
			v-model:open="editorOpen"
			:subject="editingSubject as Subject"
			:schema="editingSchema as Schema"
			:on-save="handleEditSave"
			:on-create="handleEditCreate"
			:on-save-schema="handleSchemaSave"
		/>

		<MoveSubjectDialog
			v-if="movingSubject !== null"
			v-model:open="moveDialogOpen"
			:subject-id="movingSubject.getId().text"
			:subject-name="movingSubject.getDisplayName()"
			:current-page-id="pageId"
			:current-page-title="currentPageTitle"
			:subject-is-main-subject="isMainSubject( movingSubject as Subject )"
			@moved="onSubjectMoved"
		/>

		<SubjectDeleteDialog
			v-model:open="deleteConfirmOpen"
			:subject-name="deletingSubjectName"
			@confirm="executeDelete"
		/>
	</div>
</template>

<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, shallowRef, nextTick } from 'vue';
import { CdxButton, CdxIcon } from '@wikimedia/codex';
import {
	cdxIconAdd,
	cdxIconPushPin
} from '@wikimedia/codex-icons';
import { NeoWikiServices } from '@/NeoWikiServices.ts';
import { useSubjectStore } from '@/stores/SubjectStore.ts';
import { useSchemaStore } from '@/stores/SchemaStore.ts';
import { useSubjectPermissions } from '@/composables/useSubjectPermissions.ts';
import { useSubjectDrag } from '@/composables/useSubjectDrag.ts';
import { subjectRowDomId, subjectIdFromHash } from '@/presentation/subjectRowAnchor.ts';
import { subjectDisplayName } from '@/presentation/subjectDisplayName.ts';
import { subjectPageUrl } from '@/presentation/subjectPageUrl.ts';
import { copyToClipboard } from '@/presentation/copyToClipboard.ts';
import { Subject } from '@/domain/Subject';
import { Schema } from '@/domain/Schema';
import { SubjectId } from '@/domain/SubjectId';
import SubjectCreatorDialog from '@/components/SubjectCreator/SubjectCreatorDialog.vue';
import SubjectEditorDialog from '@/components/SubjectEditor/SubjectEditorDialog.vue';
import MoveSubjectDialog from '@/components/SubjectsManager/MoveSubjectDialog.vue';
import SubjectDeleteDialog from '@/components/SubjectsManager/SubjectDeleteDialog.vue';
import SubjectRow from '@/components/SubjectsManager/SubjectRow.vue';
import DataExportButton from '@/components/SubjectsManager/DataExportButton.vue';
import { pageExportUrls } from '@/presentation/DataExportMenu.ts';

const pageId = Number( mw.config.get( 'wgNeoWikiManageSubjectsPageId' ) );

// RDF projections readable by the viewing user (native + ontology mappings), permission-filtered
// server-side. Drives the export-all menu; native is always present, so this is never truly empty.
const rdfProjections = ( mw.config.get( 'wgNeoWikiRdfProjections' ) as string[] | null ) ?? [];

const subjectStore = useSubjectStore();
const schemaStore = useSchemaStore();
const subjectRepo = NeoWikiServices.getSubjectRepository();
const schemaRepo = NeoWikiServices.getSchemaRepository();
const {
	canCreateMainSubject,
	canCreateChildSubject,
	canEditSubject,
	canDeleteSubject,
	checkPermissions
} = useSubjectPermissions();

const loading = ref( true );
const expandedIds = ref<Set<string>>( new Set() );
const highlightedId = ref<string | null>( null );
const focusedId = ref<string | null>( null );
const mainSlotRef = ref<HTMLElement | null>( null );
const childListRef = ref<HTMLElement | null>( null );
let focusTimeoutId: ReturnType<typeof setTimeout> | null = null;

function focusSubject( id: string ): void {
	focusedId.value = id;
	if ( focusTimeoutId !== null ) {
		clearTimeout( focusTimeoutId );
	}
	focusTimeoutId = setTimeout( () => {
		focusedId.value = null;
		focusTimeoutId = null;
	}, 2000 );

	nextTick().then( () => {
		document.getElementById( subjectRowDomId( id ) )
			?.scrollIntoView( { behavior: scrollBehavior(), block: 'nearest' } );
	} ).catch( ( err ) => {
		console.error( 'Failed to scroll to subject row:', err );
	} );
}

function scrollBehavior(): 'auto' | 'smooth' {
	return window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches ? 'auto' : 'smooth';
}

// Editor state is component-local (ADR 16): the dialog opens on data fetched straight from the
// repositories, not on the store the list below renders from.
const editingSubject = shallowRef<Subject | null>( null );
const editingSchema = shallowRef<Schema | null>( null );
const editorOpen = ref( false );

const deleteConfirmOpen = ref( false );
const deletingSubject = shallowRef<Subject | null>( null );

const moveDialogOpen = ref( false );
const movingSubject = ref<Subject | null>( null );

// The page the Data tab is showing, named for the warning a move of the Main Subject carries.
const currentPageTitle = String( mw.config.get( 'wgPageName' ) ?? '' ).replace( /_/g, ' ' );

// Read subjects through the reactive store so the session's own writes flow into the list.
const subjects = computed<Subject[]>( () =>
	subjectStore.pageSubjects?.getSubjects()
		.map( ( s ) => subjectStore.getSubject( s.getId() ) ) ?? []
);

const canCreate = computed( () => canCreateMainSubject.value || canCreateChildSubject.value );
const canEdit = computed( () => canEditSubject.value );
const canDelete = computed( () => canDeleteSubject.value );

const mainSubject = computed<Subject | null>( () => {
	const mainId = subjectStore.pageSubjects?.getMainSubjectId();
	if ( !mainId ) {
		return null;
	}
	return subjects.value.find( ( s ) => s.getId().text === mainId.text ) ?? null;
} );

const otherSubjects = computed<Subject[]>( () => {
	const mainId = subjectStore.pageSubjects?.getMainSubjectId();
	if ( !mainId ) {
		return subjects.value;
	}
	return subjects.value.filter( ( s ) => s.getId().text !== mainId.text );
} );

const hasMainSubject = computed( () => mainSubject.value !== null );
const hasChildSubjects = computed( () => otherSubjects.value.length > 0 );
const isCompletelyEmpty = computed( () => !hasMainSubject.value && !hasChildSubjects.value );

const deletingSubjectName = computed( () => deletingSubject.value === null ? '' : subjectDisplayName( deletingSubject.value ) );

function toggleExpanded( subject: Subject ): void {
	const id = subject.getId().text;
	// The arrival highlight is a one-time "you landed here" cue from a deep link. The first manual
	// expand/collapse is the user taking over, so dismiss it: otherwise it would linger on the
	// originally linked row while the address-bar fragment (rewritten below) moves to a different one,
	// leaving the visible highlight and a copied URL disagreeing. Mirrors focusedId, likewise transient.
	highlightedId.value = null;
	const next = new Set( expandedIds.value );
	if ( next.has( id ) ) {
		next.delete( id );
	} else {
		next.add( id );
		// Make the address bar a shareable deep link to the row the user just opened. The fragment is the
		// bare Subject id (like Wikibase's `#P123`); replaceState (not a location.hash assignment) adds no
		// history entry and fires no hashchange, so it does not re-trigger applyHash. Collapsing
		// deliberately leaves the fragment in place.
		history.replaceState( null, '', '#' + id );
	}
	expandedIds.value = next;
}

function onAddClicked(): void {
	subjectStore.openSubjectCreator();
}

function copySubjectLink( subject: Subject ): Promise<void> {
	// Build the deep link from the live address bar rather than mw.util.getUrl, so it inherits the
	// wiki's URL style (short URLs, action paths, query strings) as-served. The fragment is the bare
	// Subject id — the same anchor the deep-link mount handler resolves to this row.
	const url = new URL( location.href );
	url.hash = subject.getId().text;

	return copyToClipboard(
		url.toString(),
		mw.msg( 'neowiki-managesubjects-link-copied' ),
		mw.msg( 'neowiki-managesubjects-link-copy-error' )
	);
}

async function loadSubjects(): Promise<void> {
	loading.value = true;
	try {
		await subjectStore.loadPageSubjects( pageId );
	} catch ( error ) {
		console.error( 'Failed to load subjects:', error );
		mw.notify( mw.msg( 'neowiki-managesubjects-load-error' ), { type: 'error' } );
	} finally {
		loading.value = false;
	}
}

useSubjectDrag( mainSlotRef, childListRef, {
	onPromote: ( id, oldChildIndex ) => {
		dragPromote( id, oldChildIndex );
	},
	onDemote: ( newChildIndex ) => {
		dragDemote( newChildIndex );
	},
	onReorderChildren: ( oldIndex, newIndex ) => {
		dragReorderChildren( oldIndex, newIndex );
	}
} );

async function dragPromote( newMainId: SubjectId, oldChildIndex: number | undefined ): Promise<void> {
	// Swap-into-position: previous main lands in the dragged child's old slot.
	const childIds = currentChildIds();
	const draggedIndex = childIds.findIndex( ( id ) => id.text === newMainId.text );
	if ( draggedIndex === -1 ) {
		return;
	}
	childIds.splice( draggedIndex, 1 );
	const previousMainId = mainSubject.value?.getId() ?? null;
	if ( previousMainId !== null ) {
		const insertAt = oldChildIndex !== undefined && oldChildIndex >= 0 && oldChildIndex <= childIds.length ?
			oldChildIndex :
			childIds.length;
		childIds.splice( insertAt, 0, previousMainId );
	}
	await applyOrdering( newMainId, childIds, () => mainSubjectSetMessage( newMainId ), newMainId.text );
}

async function dragDemote( newChildIndex: number | undefined ): Promise<void> {
	const previousMain = mainSubject.value;
	if ( previousMain === null ) {
		return;
	}
	const childIds = currentChildIds();
	const insertAt = newChildIndex !== undefined && newChildIndex >= 0 && newChildIndex <= childIds.length ?
		newChildIndex :
		childIds.length;
	childIds.splice( insertAt, 0, previousMain.getId() );
	await applyOrdering( null, childIds, () => mw.msg( 'neowiki-managesubjects-main-subject-cleared' ), previousMain.getId().text );
}

async function dragReorderChildren( oldIndex: number, newIndex: number ): Promise<void> {
	const childIds = currentChildIds();
	const [ moved ] = childIds.splice( oldIndex, 1 );
	if ( moved === undefined ) {
		return;
	}
	childIds.splice( newIndex, 0, moved );
	const mainId = mainSubject.value?.getId() ?? null;
	await applyOrdering( mainId, childIds, () => mw.msg( 'neowiki-managesubjects-reordered' ), moved.text );
}

// Read after the write: promotion moves a Subject to the page-name tier, so a name read before it
// is one the Subject no longer has.
function mainSubjectSetMessage( id: SubjectId ): string {
	return mw.msg( 'neowiki-managesubjects-main-subject-set', subjectDisplayName( subjectStore.getSubject( id ) ) );
}

async function applyOrdering(
	mainId: SubjectId | null,
	childIds: SubjectId[],
	successMessage: () => string,
	focusId: string | null
): Promise<void> {
	// Only the write is guarded: naming the Subject afterwards reads the store, and reporting a
	// committed write as failed because that read threw would be worse than the read's own error.
	try {
		await subjectStore.setPageSubjectsOrdering( pageId, mainId, childIds );
	} catch ( error ) {
		console.error( 'Failed to update subjects ordering:', error );
		mw.notify( mw.msg( 'neowiki-managesubjects-ordering-error' ), { type: 'error' } );
		return;
	}

	mw.notify( successMessage(), { type: 'success' } );

	if ( focusId !== null ) {
		focusSubject( focusId );
	}
}

function currentChildIds(): SubjectId[] {
	return otherSubjects.value.map( ( s ) => s.getId() );
}

async function promoteToMain( subject: Subject ): Promise<void> {
	// As in applyOrdering, only the write is guarded: naming the Subject afterwards reads the store.
	try {
		await subjectStore.setPageMainSubject( pageId, subject.getId() );
	} catch ( error ) {
		console.error( 'Failed to set main subject:', error );
		mw.notify( mw.msg( 'neowiki-managesubjects-main-subject-error' ), { type: 'error' } );
		return;
	}

	mw.notify( mainSubjectSetMessage( subject.getId() ), { type: 'success' } );
	focusSubject( subject.getId().text );
}

// Takes the Subject the row reported, which is the Main Subject, and reads the store for the rest.
async function demoteFromMain(): Promise<void> {
	const demoted = mainSubject.value;
	try {
		await subjectStore.setPageMainSubject( pageId, null );
		mw.notify( mw.msg( 'neowiki-managesubjects-main-subject-cleared' ), { type: 'success' } );
		if ( demoted !== null ) {
			focusSubject( demoted.getId().text );
		}
	} catch ( error ) {
		console.error( 'Failed to clear main subject:', error );
		mw.notify( mw.msg( 'neowiki-managesubjects-main-subject-error' ), { type: 'error' } );
	}
}

async function openEditor( subject: Subject ): Promise<void> {
	try {
		// Fetch both subject and schema so the editor never opens against stale data
		// (e.g. after the subject or its schema was edited in another tab).
		const [ freshSubject, schema ] = await Promise.all( [
			subjectRepo.getSubjectForEditing( subject.getId() ),
			schemaRepo.getSchema( subject.getSchemaName() )
		] );

		editingSubject.value = freshSubject;
		editingSchema.value = schema;
		editorOpen.value = true;
	} catch ( error ) {
		mw.notify(
			error instanceof Error ? error.message : String( error ),
			{ type: 'error' }
		);
	}
}

async function handleEditSave( updatedSubject: Subject, comment: string ): Promise<void> {
	await subjectStore.updateSubject( updatedSubject, comment );
	await loadSubjects();
}

// No listing refresh of its own: a created Subject is always accompanied by the update that points
// at it, and that goes through handleEditSave, which refreshes once the whole save is through.
async function handleEditCreate( subject: Subject, targetPageId: number, comment: string ): Promise<void> {
	await subjectStore.createSubject( subject, targetPageId, comment );
}

async function handleSchemaSave( updatedSchema: Schema, comment: string ): Promise<void> {
	await schemaStore.saveSchema( updatedSchema, comment );
}

function isMainSubject( subject: Subject ): boolean {
	return mainSubject.value?.getId().text === subject.getId().text;
}

function openMoveDialog( subject: Subject ): void {
	movingSubject.value = subject;
	moveDialogOpen.value = true;
}

// The listing needs no refresh here: moveSubject re-syncs it as part of the move, because dropping
// the Subject from the registry before the listing stops naming it is what crashes the render.
function onSubjectMoved( targetTitle: string ): void {
	const subjectName = movingSubject.value?.getDisplayName() ?? '';
	movingSubject.value = null;

	const link = document.createElement( 'a' );
	link.href = mw.util.getUrl( targetTitle, { action: 'subjects' } );
	link.textContent = targetTitle;

	// parseDom rather than a message string: it takes the link as a node, which leaves the subject
	// name beside it escaped.
	mw.notify(
		mw.message( 'neowiki-managesubjects-move-success', subjectName, link ).parseDom(),
		{ type: 'success' }
	);
}

function confirmDelete( subject: Subject ): void {
	deletingSubject.value = subject;
	deleteConfirmOpen.value = true;
}

async function executeDelete( comment: string ): Promise<void> {
	const subject = deletingSubject.value;
	deleteConfirmOpen.value = false;

	if ( subject === null ) {
		return;
	}

	const name = subjectDisplayName( subject );
	const summary = comment || mw.msg( 'neowiki-managesubjects-delete-summary-default' );

	try {
		await subjectStore.deleteSubject( subject.getId(), summary );
		mw.notify( mw.msg( 'neowiki-managesubjects-delete-success' ), { type: 'success' } );
	} catch ( error ) {
		console.error( 'Failed to delete subject:', error );
		mw.notify( mw.msg( 'neowiki-managesubjects-delete-error', name ), { type: 'error' } );
	} finally {
		deletingSubject.value = null;
	}
}

function applyHash(): void {
	const id = subjectIdFromHash( window.location.hash.slice( 1 ) );
	if ( id === null ) {
		// The fragment no longer names a Subject (navigated to a foreign anchor, or cleared), so no row
		// is the deep-link target any more: drop any stale highlight rather than leave it on a row the
		// address bar no longer points at.
		highlightedId.value = null;
		return;
	}
	highlightedId.value = id;
	const next = new Set( expandedIds.value );
	next.add( id );
	expandedIds.value = next;
	nextTick().then( () => {
		document.getElementById( subjectRowDomId( id ) )
			?.scrollIntoView( { behavior: scrollBehavior(), block: 'center' } );
	} ).catch( ( err ) => {
		console.error( 'Failed to scroll to subject row:', err );
	} );
}

onMounted( async () => {
	await checkPermissions( pageId );
	await loadSubjects();
	applyHash();
	window.addEventListener( 'hashchange', applyHash );
} );

onUnmounted( () => {
	window.removeEventListener( 'hashchange', applyHash );
	if ( focusTimeoutId !== null ) {
		clearTimeout( focusTimeoutId );
	}
} );
</script>

<style lang="less">
@import ( reference ) '@wikimedia/codex-design-tokens/theme-wikimedia-ui.less';

// Fixed row height. Rows are content-sized in practice but we pin a value here so the
// add-subject placeholder button matches the rows without fragile text-height math.
@row-height: 70px;

.ext-neowiki-subjects-manager {
	max-width: 64rem;

	&__controls {
		display: flex;
		flex-wrap: wrap;
		align-items: center;
		justify-content: space-between;
		gap: @spacing-100;
		margin-bottom: @spacing-150;
	}

	&__controls-actions {
		display: flex;
		align-items: center;
		gap: @spacing-75;
	}

	&__count {
		color: @color-subtle;
	}

	&__loading {
		color: @color-subtle;
		font-style: italic;
	}

	&__empty-state {
		display: flex;
		flex-direction: column;
		align-items: center;
		gap: @spacing-150;
		padding: @spacing-200;
		margin-top: @spacing-100;
		background-color: @background-color-neutral-subtle;
		border: @border-width-base dashed @border-color-subtle;
		border-radius: @border-radius-base;
		text-align: center;
	}

	&__empty-state-text {
		display: flex;
		flex-direction: column;
		align-items: center;
		gap: @spacing-50;
	}

	&__empty-state-title {
		font-size: @font-size-large;
		font-weight: @font-weight-bold;
	}

	&__empty-state-description {
		max-width: 36rem;
		color: @color-subtle;
	}

	&__empty-state-icon.cdx-icon {
		width: @size-150;
		height: @size-150;
		color: @color-subtle;
	}

	&__main-slot {
		list-style: none;
		padding: 0;
		margin: 0 0 @spacing-150;
	}

	&__list {
		list-style: none;
		padding: 0;
		margin: @spacing-100 0 0 0;
		display: flex;
		flex-direction: column;
		gap: @spacing-50;

		&--empty {
			// Give the empty list visual presence so it remains a viable drop
			// target for demote-via-drag when the page has no other subjects.
			min-height: @size-200;
		}
	}

	&__add-more.cdx-button {
		width: @size-full;
		min-height: @row-height;
		margin-top: @spacing-75;
		max-width: none;

		&:enabled.cdx-button--weight-quiet {
			border-style: dashed;
			border-color: @border-color-interactive;
		}
	}
}
</style>
