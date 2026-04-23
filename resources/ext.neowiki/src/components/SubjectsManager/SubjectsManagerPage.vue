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
			<details
				v-if="mainSubject !== null"
				:id="`ext-neowiki-subject-row-${mainSubject.getId().text}`"
				class="ext-neowiki-subjects-manager__row ext-neowiki-subjects-manager__row--main"
				:class="{
					'ext-neowiki-subjects-manager__row--highlighted':
						highlightedId === mainSubject.getId().text,
					'ext-neowiki-subjects-manager__row--focused':
						focusedId === mainSubject.getId().text
				}"
				:open="expandedIds.has( mainSubject.getId().text )"
			>
				<summary
					class="ext-neowiki-subjects-manager__row-header"
					@click.prevent="toggleExpanded( mainSubject.getId().text )"
				>
					<CdxIcon
						class="ext-neowiki-subjects-manager__row-chevron"
						:icon="expandedIds.has( mainSubject.getId().text ) ? cdxIconCollapse : cdxIconExpand"
						size="small"
					/>
					<CdxButton
						v-if="canEdit"
						class="ext-neowiki-subjects-manager__row-main-indicator"
						weight="quiet"
						:aria-label="$i18n( 'neowiki-managesubjects-row-demote' ).text()"
						:title="$i18n( 'neowiki-managesubjects-row-demote' ).text()"
						@click.stop="demoteFromMain"
					>
						<CdxIcon :icon="cdxIconPushPin" />
					</CdxButton>
					<CdxIcon
						v-else
						class="ext-neowiki-subjects-manager__row-main-indicator"
						:icon="cdxIconPushPin"
						:icon-label="$i18n( 'neowiki-managesubjects-main-subject-indicator' ).text()"
					/>
					<span class="ext-neowiki-subjects-manager__row-title">
						<span class="ext-neowiki-subjects-manager__row-label">
							{{ mainSubject.getLabel() }}
						</span>
						<span class="ext-neowiki-subjects-manager__row-subtitle">
							<a
								class="ext-neowiki-subjects-manager__row-schema"
								:href="schemaUrl( mainSubject.getSchemaName() )"
								@click.stop
							>
								{{ mainSubject.getSchemaName() }}
							</a>
							<span class="ext-neowiki-subjects-manager__row-count">
								{{ $i18n( 'neowiki-managesubjects-statement-count', statementCount( mainSubject ) ).text() }}
							</span>
						</span>
					</span>
					<span class="ext-neowiki-subjects-manager__row-actions">
						<CdxButton
							v-if="canEdit"
							weight="quiet"
							:aria-label="$i18n( 'neowiki-managesubjects-row-edit' ).text()"
							:title="$i18n( 'neowiki-managesubjects-row-edit' ).text()"
							@click.stop="openEditor( mainSubject )"
						>
							<CdxIcon :icon="cdxIconEdit" />
						</CdxButton>
						<CdxButton
							v-if="canDelete"
							weight="quiet"
							action="destructive"
							:aria-label="$i18n( 'neowiki-managesubjects-row-delete' ).text()"
							:title="$i18n( 'neowiki-managesubjects-row-delete' ).text()"
							@click.stop="confirmDelete( mainSubject )"
						>
							<CdxIcon :icon="cdxIconTrash" />
						</CdxButton>
					</span>
					<span
						class="ext-neowiki-subjects-manager__row-actions-menu"
						@click.stop
					>
						<CdxMenuButton
							v-model:selected="rowMenuSelection"
							:menu-items="mainRowMenuItems"
							:aria-label="$i18n( 'neowiki-managesubjects-row-more' ).text()"
							:title="$i18n( 'neowiki-managesubjects-row-more' ).text()"
							@update:selected="( value ) => dispatchRowAction( value, mainSubject as Subject )"
						>
							<CdxIcon :icon="cdxIconEllipsis" />
						</CdxMenuButton>
					</span>
				</summary>
				<div class="ext-neowiki-subjects-manager__row-expanded">
					<SubjectStatementsView :subject="mainSubject" />
					<footer class="ext-neowiki-subjects-manager__row-footer">
						<span class="ext-neowiki-subjects-manager__row-id">
							<span class="ext-neowiki-subjects-manager__row-id-label">
								{{ $i18n( 'neowiki-managesubjects-id-label' ).text() }}
							</span>
							<button
								type="button"
								class="ext-neowiki-subjects-manager__row-id-button"
								:title="$i18n( 'neowiki-managesubjects-id-copy', mainSubject.getId().text ).text()"
								:aria-label="$i18n( 'neowiki-managesubjects-id-copy', mainSubject.getId().text ).text()"
								@click="copySubjectId( mainSubject.getId().text )"
							>
								<data :value="mainSubject.getId().text">
									{{ mainSubject.getId().text }}
								</data>
							</button>
						</span>
					</footer>
				</div>
			</details>

			<div
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
			</div>

			<h2 class="ext-neowiki-subjects-manager__section-heading">
				{{ $i18n( 'neowiki-managesubjects-other-subjects-heading' ).text() }}
			</h2>

			<ul
				v-if="hasChildSubjects"
				class="ext-neowiki-subjects-manager__list"
			>
				<li
					v-for="subject in otherSubjects"
					:id="`ext-neowiki-subject-row-${subject.getId().text}`"
					:key="subject.getId().text"
					class="ext-neowiki-subjects-manager__row"
					:class="{
						'ext-neowiki-subjects-manager__row--highlighted':
							highlightedId === subject.getId().text,
						'ext-neowiki-subjects-manager__row--focused':
							focusedId === subject.getId().text
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
								<span class="ext-neowiki-subjects-manager__row-subtitle">
									<a
										class="ext-neowiki-subjects-manager__row-schema"
										:href="schemaUrl( subject.getSchemaName() )"
										@click.stop
									>
										{{ subject.getSchemaName() }}
									</a>
									<span class="ext-neowiki-subjects-manager__row-count">
										{{ $i18n( 'neowiki-managesubjects-statement-count', statementCount( subject ) ).text() }}
									</span>
								</span>
							</span>
							<span class="ext-neowiki-subjects-manager__row-actions">
								<CdxButton
									v-if="canEdit"
									weight="quiet"
									:aria-label="$i18n( 'neowiki-managesubjects-row-promote' ).text()"
									:title="$i18n( 'neowiki-managesubjects-row-promote' ).text()"
									@click.stop="promoteToMain( subject )"
								>
									<CdxIcon :icon="cdxIconPushPin" />
								</CdxButton>
								<CdxButton
									v-if="canEdit"
									weight="quiet"
									:aria-label="$i18n( 'neowiki-managesubjects-row-edit' ).text()"
									:title="$i18n( 'neowiki-managesubjects-row-edit' ).text()"
									@click.stop="openEditor( subject )"
								>
									<CdxIcon :icon="cdxIconEdit" />
								</CdxButton>
								<CdxButton
									v-if="canDelete"
									weight="quiet"
									action="destructive"
									:aria-label="$i18n( 'neowiki-managesubjects-row-delete' ).text()"
									:title="$i18n( 'neowiki-managesubjects-row-delete' ).text()"
									@click.stop="confirmDelete( subject )"
								>
									<CdxIcon :icon="cdxIconTrash" />
								</CdxButton>
							</span>
							<span
								class="ext-neowiki-subjects-manager__row-actions-menu"
								@click.stop
							>
								<CdxMenuButton
									v-model:selected="rowMenuSelection"
									:menu-items="otherRowMenuItems"
									:aria-label="$i18n( 'neowiki-managesubjects-row-more' ).text()"
									:title="$i18n( 'neowiki-managesubjects-row-more' ).text()"
									@update:selected="( value ) => dispatchRowAction( value, subject )"
								>
									<CdxIcon :icon="cdxIconEllipsis" />
								</CdxMenuButton>
							</span>
						</summary>
						<div class="ext-neowiki-subjects-manager__row-expanded">
							<SubjectStatementsView :subject="subject" />
							<footer class="ext-neowiki-subjects-manager__row-footer">
								<span class="ext-neowiki-subjects-manager__row-id">
									<span class="ext-neowiki-subjects-manager__row-id-label">
										{{ $i18n( 'neowiki-managesubjects-id-label' ).text() }}
									</span>
									<button
										type="button"
										class="ext-neowiki-subjects-manager__row-id-button"
										:title="$i18n( 'neowiki-managesubjects-id-copy', subject.getId().text ).text()"
										:aria-label="$i18n( 'neowiki-managesubjects-id-copy', subject.getId().text ).text()"
										@click="copySubjectId( subject.getId().text )"
									>
										<data :value="subject.getId().text">
											{{ subject.getId().text }}
										</data>
									</button>
								</span>
							</footer>
						</div>
					</details>
				</li>
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
					save-button-action="destructive"
					:save-button-icon="cdxIconTrash"
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
	CdxMenuButton
} from '@wikimedia/codex';
import type { MenuButtonItemData } from '@wikimedia/codex';
import {
	cdxIconAdd,
	cdxIconCollapse,
	cdxIconEdit,
	cdxIconEllipsis,
	cdxIconExpand,
	cdxIconPushPin,
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
const focusedId = ref<string | null>( null );
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
		document.getElementById( `ext-neowiki-subject-row-${ id }` )
			?.scrollIntoView( { behavior: scrollBehavior(), block: 'nearest' } );
	} ).catch( ( err ) => {
		console.error( 'Failed to scroll to subject row:', err );
	} );
}

function scrollBehavior(): 'auto' | 'smooth' {
	return window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches ? 'auto' : 'smooth';
}

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

const deletingLabel = computed( () => deletingSubject.value?.getLabel() ?? '' );

function schemaUrl( name: string ): string {
	return mw.util.getUrl( `Schema:${ name }` );
}

function statementCount( subject: Subject ): number {
	return subject.getStatements().withNonEmptyValues().getPropertyNames().length;
}

const promoteMenuItem = computed<MenuButtonItemData>( () => ( {
	value: 'promote',
	label: mw.msg( 'neowiki-managesubjects-row-promote' ),
	icon: cdxIconPushPin
} ) );

const editMenuItem = computed<MenuButtonItemData>( () => ( {
	value: 'edit',
	label: mw.msg( 'neowiki-managesubjects-row-edit' ),
	icon: cdxIconEdit
} ) );

const deleteMenuItem = computed<MenuButtonItemData>( () => ( {
	value: 'delete',
	label: mw.msg( 'neowiki-managesubjects-row-delete' ),
	icon: cdxIconTrash,
	action: 'destructive'
} ) );

const mainRowMenuItems = computed<MenuButtonItemData[]>( () => {
	const items: MenuButtonItemData[] = [];
	if ( canEdit.value ) {
		items.push( editMenuItem.value );
	}
	if ( canDelete.value ) {
		items.push( deleteMenuItem.value );
	}
	return items;
} );

const otherRowMenuItems = computed<MenuButtonItemData[]>( () => {
	const items: MenuButtonItemData[] = [];
	if ( canEdit.value ) {
		items.push( promoteMenuItem.value, editMenuItem.value );
	}
	if ( canDelete.value ) {
		items.push( deleteMenuItem.value );
	}
	return items;
} );

const rowMenuSelection = ref<string | number | null>( null );

function dispatchRowAction( value: string | number | null, subject: Subject ): void {
	rowMenuSelection.value = null;
	if ( value === 'promote' ) {
		promoteToMain( subject );
	} else if ( value === 'edit' ) {
		openEditor( subject );
	} else if ( value === 'delete' ) {
		confirmDelete( subject );
	}
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
		console.error( 'Failed to copy subject ID:', error );
		mw.notify( mw.msg( 'neowiki-managesubjects-id-copy-error' ), { type: 'error' } );
	}
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

async function promoteToMain( subject: Subject ): Promise<void> {
	try {
		await subjectStore.setPageMainSubject( pageId, subject.getId() );
		mw.notify( mw.msg( 'neowiki-managesubjects-main-subject-set', subject.getLabel() ), { type: 'success' } );
		focusSubject( subject.getId().text );
	} catch ( error ) {
		console.error( 'Failed to set main subject:', error );
		mw.notify( mw.msg( 'neowiki-managesubjects-main-subject-error' ), { type: 'error' } );
	}
}

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
		console.error( 'Failed to delete subject:', error );
		mw.notify( mw.msg( 'neowiki-managesubjects-delete-error', label ), { type: 'error' } );
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

	&__section-heading {
		margin: @spacing-150 0 @spacing-75;
	}

	&__list {
		list-style: none;
		padding: 0;
		margin: @spacing-100 0 0 0;
		display: flex;
		flex-direction: column;
		gap: @spacing-50;
	}

	&__row {
		border: @border-base;
		border-radius: @border-radius-base;
		background: @background-color-base;
		// Baseline zero-color shadow so the focused-state ring can transition in/out smoothly
		// rather than snap between "none" and a value.
		box-shadow: @box-shadow-outset-small transparent;
		transition: @transition-property-base @transition-duration-medium @transition-timing-function-system;

		@media ( prefers-reduced-motion: reduce ) {
			transition-duration: 0s;
		}
		font-size: @font-size-small;
		line-height: 1.375rem; // Codex 2.0+ line-height-small

		&--main {
			margin-bottom: @spacing-150;
			border-color: @border-color-progressive;

			> .ext-neowiki-subjects-manager__row-header {
				background-color: @background-color-progressive-subtle;

				&:hover {
					background-color: @background-color-interactive-subtle;
				}

				&:active {
					background-color: @background-color-interactive;
				}

				.ext-neowiki-subjects-manager__row-label {
					color: @color-progressive;
				}
			}
		}

		&--highlighted {
			background: @background-color-progressive-subtle;
		}

		&--focused {
			border-color: @border-color-progressive--focus;
			box-shadow: @box-shadow-outset-small @box-shadow-color-progressive--focus;
			// Transparent 1px outline for Windows high-contrast mode — Codex focus pattern.
			outline: @outline-base--focus;
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
		transition-duration: @transition-duration-base;
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

	&__row-main-indicator {
		flex-shrink: 0;

		// Codex sets an explicit `color` on `.cdx-icon`, matching our class's specificity.
		// Chain the class to win the cascade regardless of Codex/bundle load order.
		&.cdx-icon {
			color: @color-progressive;
		}

		// When the user can edit, the indicator renders as a quiet CdxButton so clicking it
		// demotes the subject. Paint the nested icon progressive to match the read-only case.
		&.cdx-button .cdx-icon {
			color: @color-progressive;
		}
	}

	&__row-title {
		display: flex;
		flex-direction: column;
		gap: @spacing-12;
		flex-grow: 1;
		min-width: 0;
	}

	&__row-subtitle {
		display: flex;
		flex-wrap: wrap;
		align-items: center;
		gap: 0 @spacing-50;
		min-width: 0;
		font-size: @font-size-small;
		color: @color-subtle;
	}

	&__row-schema {
		min-width: 0;
		overflow: hidden;
		text-overflow: ellipsis;
		white-space: nowrap;
	}

	&__row-count {
		white-space: nowrap;
	}

	&__row-label {
		font-size: @font-size-medium;
		font-weight: @font-weight-bold;
		min-width: 0;
		overflow: hidden;
		text-overflow: ellipsis;
		white-space: nowrap;
	}

	&__row-id {
		display: inline-flex;
		align-items: baseline;
		gap: @spacing-25;
	}

	&__row-id-button {
		appearance: none;
		background: transparent;
		border: 0;
		padding: 0;
		cursor: pointer;
		color: inherit;
		font: inherit;
		font-family: @font-family-monospace;

		&:hover {
			color: @color-base;
		}
	}

	&__row-count::before {
		content: '•';
		margin-inline-end: @spacing-50;
	}

	&__row-actions {
		display: inline-flex;
		gap: @spacing-25;
		flex-shrink: 0;

		@media ( max-width: @max-width-breakpoint-mobile ) {
			display: none;
		}

		@media ( min-width: @min-width-breakpoint-tablet ) and ( hover: hover ) {
			opacity: 0;
			transform: translateX( @spacing-50 );
			transition: opacity @transition-duration-medium @transition-timing-function-system, transform @transition-duration-medium @transition-timing-function-system;

			.ext-neowiki-subjects-manager__row:hover &,
			.ext-neowiki-subjects-manager__row:focus-within &,
			.ext-neowiki-subjects-manager__row--highlighted & {
				opacity: 1;
				transform: translateX( 0 );
			}
		}
	}

	&__row-actions-menu {
		flex-shrink: 0;

		@media ( min-width: @min-width-breakpoint-tablet ) {
			display: none;
		}

		@media ( max-width: @max-width-breakpoint-mobile ) and ( hover: hover ) {
			opacity: 0;
			transition: opacity @transition-duration-medium @transition-timing-function-system;

			.ext-neowiki-subjects-manager__row:hover &,
			.ext-neowiki-subjects-manager__row:focus-within &,
			.ext-neowiki-subjects-manager__row--highlighted &,
			&:has( [ aria-expanded='true' ] ) {
				opacity: 1;
			}
		}
	}

	&__row-expanded {
		padding: @spacing-100;
		border-top: @border-base;
		background: @background-color-neutral-subtle;
	}

	&__row-footer {
		display: flex;
		justify-content: flex-start;
		gap: @spacing-100;
		margin-top: @spacing-100;
		padding-top: @spacing-75;
		border-top: @border-width-base @border-style-base @border-color-subtle;
		font-size: @font-size-x-small;
		color: @color-subtle;
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
