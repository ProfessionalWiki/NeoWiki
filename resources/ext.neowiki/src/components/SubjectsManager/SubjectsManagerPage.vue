<template>
	<div class="ext-neowiki-subjects-manager">
		<div class="ext-neowiki-subjects-manager__controls">
			<div class="ext-neowiki-subjects-manager__main-field">
				<label
					for="ext-neowiki-subjects-manager-main-select"
					class="ext-neowiki-subjects-manager__main-field-label"
				>
					{{ $i18n( 'neowiki-managesubjects-main-subject-label' ).text() }}
				</label>
				<CdxSelect
					id="ext-neowiki-subjects-manager-main-select"
					v-model:selected="selectedMainId"
					:menu-items="mainOptions"
					:disabled="loading || !canEdit || subjects.length === 0"
					@update:selected="onMainChanged"
				/>
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

		<p
			v-if="loading"
			class="ext-neowiki-subjects-manager__loading"
		>
			…
		</p>

		<p
			v-else-if="subjects.length === 0"
			class="ext-neowiki-subjects-manager__empty"
		>
			{{ $i18n( 'neowiki-managesubjects-empty' ).text() }}
		</p>

		<ul
			v-else
			class="ext-neowiki-subjects-manager__list"
		>
			<li
				v-for="subject in subjects"
				:id="`ext-neowiki-subject-row-${subject.getId().text}`"
				:key="subject.getId().text"
				class="ext-neowiki-subjects-manager__row"
				:class="{
					'ext-neowiki-subjects-manager__row--main': isMain( subject ),
					'ext-neowiki-subjects-manager__row--highlighted': highlightedId === subject.getId().text
				}"
			>
				<details :open="expandedIds.has( subject.getId().text )">
					<summary
						class="ext-neowiki-subjects-manager__row-header"
						@click.prevent="toggleExpanded( subject.getId().text )"
					>
						<CdxIcon
							class="ext-neowiki-subjects-manager__row-chevron"
							:icon="expandedIds.has( subject.getId().text ) ? cdxIconCollapse : cdxIconExpand"
							size="small"
						/>
						<span class="ext-neowiki-subjects-manager__row-title">
							<span class="ext-neowiki-subjects-manager__row-label">
								{{ subject.getLabel() }}
							</span>
							<CdxInfoChip
								v-if="isMain( subject )"
								class="ext-neowiki-subjects-manager__row-main-badge"
							>
								{{ $i18n( 'neowiki-managesubjects-main-subject-pill' ).text() }}
							</CdxInfoChip>
						</span>
						<a
							class="ext-neowiki-subjects-manager__row-schema"
							:href="schemaUrl( subject.getSchemaName() )"
							@click.stop
						>
							{{ subject.getSchemaName() }}
						</a>
						<button
							type="button"
							class="ext-neowiki-subjects-manager__row-id"
							:title="$i18n( 'neowiki-managesubjects-id-copy', subject.getId().text ).text()"
							@click.stop="copySubjectId( subject.getId().text )"
						>
							<code>{{ subject.getId().text }}</code>
						</button>
						<span class="ext-neowiki-subjects-manager__row-actions">
							<CdxButton
								v-if="canEdit"
								weight="quiet"
								:aria-label="$i18n( 'neowiki-managesubjects-row-edit' ).text()"
								@click.stop="openEditor( subject )"
							>
								<CdxIcon :icon="cdxIconEdit" />
							</CdxButton>
							<CdxButton
								v-if="canDelete"
								weight="quiet"
								action="destructive"
								:aria-label="$i18n( 'neowiki-managesubjects-row-delete' ).text()"
								@click.stop="confirmDelete( subject )"
							>
								<CdxIcon :icon="cdxIconTrash" />
							</CdxButton>
						</span>
					</summary>
					<div class="ext-neowiki-subjects-manager__row-expanded">
						<SubjectStatementsView :subject="subject" />
					</div>
				</details>
			</li>
		</ul>

		<SubjectCreatorDialog
			v-if="canCreate"
			:page-has-main-subject="hasMainSubject"
		/>

		<SubjectEditorDialog
			v-if="editingSubject !== null"
			v-model:open="editorOpen"
			:subject="editingSubject as Subject"
			:on-save="handleEditSave"
			:on-save-schema="handleSchemaSave"
		/>

		<CdxDialog
			:open="deleteConfirmOpen"
			:title="$i18n( 'neowiki-managesubjects-delete-confirm-title' ).text()"
			:use-close-button="true"
			@update:open="deleteConfirmOpen = $event"
		>
			<I18nSlot message-key="neowiki-managesubjects-delete-confirm-message">
				<strong>{{ deletingLabel }}</strong>
			</I18nSlot>
			<template #footer>
				<EditSummary
					help-text=""
					:save-button-label="$i18n( 'neowiki-managesubjects-delete-confirm-button' ).text()"
					:save-disabled="false"
					@save="executeDelete"
				/>
			</template>
		</CdxDialog>
	</div>
</template>

<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, nextTick } from 'vue';
import {
	CdxButton,
	CdxDialog,
	CdxIcon,
	CdxInfoChip,
	CdxSelect
} from '@wikimedia/codex';
import type { MenuItemData } from '@wikimedia/codex';
import {
	cdxIconAdd,
	cdxIconCollapse,
	cdxIconEdit,
	cdxIconExpand,
	cdxIconTrash
} from '@wikimedia/codex-icons';
import { useSubjectStore } from '@/stores/SubjectStore.ts';
import { useSchemaStore } from '@/stores/SchemaStore.ts';
import { useSubjectPermissions } from '@/composables/useSubjectPermissions.ts';
import { Subject } from '@/domain/Subject';
import { Schema } from '@/domain/Schema';
import { SubjectId } from '@/domain/SubjectId';
import SubjectCreatorDialog from '@/components/SubjectCreator/SubjectCreatorDialog.vue';
import SubjectEditorDialog from '@/components/SubjectEditor/SubjectEditorDialog.vue';
import EditSummary from '@/components/common/EditSummary.vue';
import I18nSlot from '@/components/common/I18nSlot.vue';
import SubjectStatementsView from '@/components/SubjectsManager/SubjectStatementsView.vue';

const pageId = Number( mw.config.get( 'wgNeoWikiManageSubjectsPageId' ) );

const subjectStore = useSubjectStore();
const schemaStore = useSchemaStore();
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

const editingSubjectId = ref<SubjectId | null>( null );
const editorOpen = ref( false );

const deleteConfirmOpen = ref( false );
const deletingSubject = ref<Subject | null>( null );

// Read subjects through the reactive store so refreshes (e.g. on openEditor) flow into the list.
const subjects = computed<Subject[]>( () =>
	subjectStore.pageSubjects?.getSubjects()
		.map( ( s ) => subjectStore.getSubject( s.getId() ) ) ?? []
);

const editingSubject = computed<Subject | null>( () =>
	editingSubjectId.value === null ? null : subjectStore.getSubject( editingSubjectId.value )
);
const hasMainSubject = computed( () => subjectStore.pageSubjects?.getMainSubjectId() !== null && subjectStore.pageSubjects?.getMainSubjectId() !== undefined );

const canCreate = computed( () => canCreateMainSubject.value || canCreateChildSubject.value );
const canEdit = computed( () => canEditSubject.value );
const canDelete = computed( () => canDeleteSubject.value );

const selectedMainId = ref<string | null>( null );

const mainOptions = computed<MenuItemData[]>( () => {
	const items: MenuItemData[] = [
		{ value: '__none__', label: mw.msg( 'neowiki-managesubjects-main-subject-none' ) }
	];
	for ( const subject of subjects.value ) {
		items.push( {
			value: subject.getId().text,
			label: `${ subject.getLabel() } (${ subject.getSchemaName() })`
		} );
	}
	return items;
} );

const deletingLabel = computed( () => deletingSubject.value?.getLabel() ?? '' );

function isMain( subject: Subject ): boolean {
	return subjectStore.pageSubjects?.isMain( subject.getId() ) ?? false;
}

function schemaUrl( name: string ): string {
	return mw.util.getUrl( `Schema:${ name }` );
}

function toggleExpanded( id: string ): void {
	const next = new Set( expandedIds.value );
	if ( next.has( id ) ) {
		next.delete( id );
	} else {
		next.add( id );
	}
	expandedIds.value = next;
}

function onAddClicked(): void {
	subjectStore.openSubjectCreator();
}

async function copySubjectId( id: string ): Promise<void> {
	try {
		await navigator.clipboard.writeText( id );
		mw.notify( mw.msg( 'neowiki-managesubjects-id-copied', id ), { type: 'success' } );
	} catch ( error ) {
		mw.notify(
			error instanceof Error ? error.message : String( error ),
			{ title: mw.msg( 'neowiki-managesubjects-id-copy-error' ), type: 'error' }
		);
	}
}

async function loadSubjects(): Promise<void> {
	loading.value = true;
	try {
		await subjectStore.loadPageSubjects( pageId );
		selectedMainId.value = subjectStore.pageSubjects?.getMainSubjectId()?.text ?? '__none__';
	} catch ( error ) {
		mw.notify(
			error instanceof Error ? error.message : String( error ),
			{ title: mw.msg( 'neowiki-managesubjects-load-error' ), type: 'error' }
		);
	} finally {
		loading.value = false;
	}
}

async function onMainChanged( newValue: string | null ): Promise<void> {
	const currentMain = subjectStore.pageSubjects?.getMainSubjectId()?.text ?? '__none__';
	if ( newValue === currentMain || newValue === null ) {
		return;
	}

	try {
		const targetSubjectId = newValue === '__none__' ? null : new SubjectId( newValue );
		await subjectStore.setPageMainSubject( pageId, targetSubjectId );
		mw.notify( mw.msg( 'neowiki-managesubjects-main-subject-changed' ), { type: 'success' } );
	} catch ( error ) {
		mw.notify(
			error instanceof Error ? error.message : String( error ),
			{ title: mw.msg( 'neowiki-managesubjects-main-subject-error' ), type: 'error' }
		);
		// revert UI selection by reloading the canonical state
		selectedMainId.value = subjectStore.pageSubjects?.getMainSubjectId()?.text ?? '__none__';
	}
}

async function openEditor( subject: Subject ): Promise<void> {
	try {
		// Refresh both subject and schema so the editor never opens against stale data
		// (e.g. after the subject or its schema was edited in another tab).
		await Promise.all( [
			subjectStore.fetchSubject( subject.getId() ),
			schemaStore.fetchSchema( subject.getSchemaName() )
		] );

		editingSubjectId.value = subject.getId();
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

async function handleSchemaSave( updatedSchema: Schema, comment: string ): Promise<void> {
	await schemaStore.saveSchema( updatedSchema, comment );
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

	const label = subject.getLabel();
	const summary = comment || mw.msg( 'neowiki-managesubjects-delete-summary-default' );

	try {
		await subjectStore.deleteSubject( subject.getId(), summary );
		mw.notify( mw.msg( 'neowiki-managesubjects-delete-success', label ), { type: 'success' } );
		await loadSubjects();
	} catch ( error ) {
		mw.notify(
			error instanceof Error ? error.message : String( error ),
			{ title: mw.msg( 'neowiki-managesubjects-delete-error', label ), type: 'error' }
		);
	} finally {
		deletingSubject.value = null;
	}
}

function applyHash(): void {
	const id = window.location.hash.slice( 1 );
	if ( !id ) {
		return;
	}
	highlightedId.value = id;
	const next = new Set( expandedIds.value );
	next.add( id );
	expandedIds.value = next;
	nextTick().then( () => {
		document.getElementById( `ext-neowiki-subject-row-${ id }` )
			?.scrollIntoView( { behavior: 'smooth', block: 'center' } );
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
} );
</script>

<style lang="less">
@import ( reference ) '@wikimedia/codex-design-tokens/theme-wikimedia-ui.less';

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

	&__main-field {
		display: flex;
		align-items: center;
		gap: @spacing-100;
		flex-grow: 1;
		max-width: 48rem;
	}

	&__main-field-label {
		font-weight: @font-weight-bold;
		flex-shrink: 0;
	}

	&__loading,
	&__empty {
		color: @color-subtle;
		font-style: italic;
	}

	&__list {
		list-style: none;
		padding: 0;
		margin: 0;
		display: flex;
		flex-direction: column;
		gap: @spacing-50;
	}

	&__row {
		border: @border-base;
		border-radius: @border-radius-base;
		background: @background-color-base;
		transition: background-color 0.4s ease;

		&--main {
			border-color: @border-color-progressive;
		}

		&--highlighted {
			background: @background-color-progressive-subtle;
		}
	}

	&__row-header {
		display: flex;
		align-items: center;
		gap: @spacing-75;
		padding: @spacing-75 @spacing-100;
		cursor: pointer;
		user-select: none;
		list-style: none;
		transition-property: background-color, color, border-color, box-shadow;
		transition-duration: @transition-duration-medium;
		transition-timing-function: @transition-timing-function-system;

		&::-webkit-details-marker {
			display: none;
		}

		&:hover {
			background-color: @background-color-interactive-subtle;
		}

		&:active {
			background-color: @background-color-interactive;
		}

		&:focus-visible {
			outline: @outline-base--focus;
			box-shadow: inset 0 0 0 2px @box-shadow-color-progressive--focus;
		}
	}

	&__row-chevron {
		flex-shrink: 0;
		color: @color-subtle;
	}

	&__row-title {
		display: flex;
		align-items: center;
		gap: @spacing-50;
		flex-grow: 1;
		min-width: 0;
	}

	&__row-label {
		font-weight: @font-weight-bold;
		min-width: 0;
		overflow: hidden;
		text-overflow: ellipsis;
		white-space: nowrap;
	}

	&__row-schema {
		color: @color-subtle;
		flex-shrink: 0;
	}

	&__row-id {
		appearance: none;
		background: transparent;
		border: 0;
		padding: 0;
		flex-shrink: 0;
		cursor: pointer;
		color: @color-subtle;
		font: inherit;

		code {
			font-family: monospace;
			font-size: @font-size-small;
			padding: @spacing-12 @spacing-25;
			border-radius: @border-radius-base;
			background: @background-color-neutral-subtle;
		}

		&:hover code {
			background: @background-color-neutral;
			color: @color-base;
		}
	}

	&__row-main-badge {
		flex-shrink: 0;
	}

	&__row-actions {
		display: inline-flex;
		gap: @spacing-25;
		flex-shrink: 0;
	}

	&__row-expanded {
		padding: @spacing-100;
		border-top: @border-base;
		background: @background-color-neutral-subtle;
	}
}
</style>
