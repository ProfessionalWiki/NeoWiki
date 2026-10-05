<template>
	<div class="ext-neowiki-schemas-page">
		<div class="ext-neowiki-schemas-page__toolbar">
			<CdxSearchInput
				v-model="searchText"
				class="ext-neowiki-schemas-page__find"
				:placeholder="$i18n( 'neowiki-schemas-find' ).text()"
				:aria-label="$i18n( 'neowiki-schemas-find' ).text()"
			/>
			<CdxButton
				v-if="canCreateSchemas"
				class="ext-neowiki-schemas-page__create"
				@click="isCreatorOpen = true"
			>
				<CdxIcon :icon="cdxIconAdd" />
				{{ $i18n( 'neowiki-schema-creator-button' ).text() }}
			</CdxButton>
		</div>

		<div
			v-if="schemas.length > 0"
			class="ext-neowiki-schemas-page__grid"
		>
			<SchemaCard
				v-for="summary in schemas"
				:key="summary.name"
				:summary="summary"
				:can-edit="canEditSchema"
				:can-delete="canDeleteSchema"
				:can-create-subject="canCreateSubjectPage"
				:subject-list-available="subjectListAvailable"
				@edit="openEditor( summary.name )"
				@delete="confirmDelete( summary.name )"
				@create-subject="openSubjectCreator( summary.name )"
			/>
		</div>
		<p
			v-else-if="listingIsEmpty"
			class="ext-neowiki-schemas-page__empty"
		>
			{{ emptyText }}
		</p>

		<CdxButton
			v-if="nextCursor !== null"
			class="ext-neowiki-schemas-page__more"
			:disabled="loading"
			@click="load( nextCursor )"
		>
			{{ $i18n( 'neowiki-schemas-show-more' ).text() }}
		</CdxButton>

		<SchemaCreatorDialog
			v-if="canCreateSchemas"
			:open="isCreatorOpen"
			@update:open="isCreatorOpen = $event"
			@created="listFromStart"
		/>

		<SchemaEditorDialog
			v-if="canEditSchema && editingSchema !== null"
			:open="isEditorOpen"
			:initial-schema="editingSchema"
			:on-save="handleSaveSchema"
			@update:open="onEditorOpenChange"
		/>

		<DeletePageDialog
			:open="isDeleteConfirmOpen"
			:page-title="SCHEMA_PREFIX + deletingSchemaName"
			:display-name="deletingSchemaName"
			:type-label="$i18n( 'neowiki-schema-noun' ).text()"
			@update:open="isDeleteConfirmOpen = $event"
			@deleted="onSchemaDeleted"
		/>

		<SubjectCreatorDialog
			v-if="canCreateSubjectPage"
			v-model:open="subjectStore.subjectCreatorOpen"
			:host-page="null"
			:initial-schema-name="pinnedSchema"
		/>
	</div>
</template>

<script setup lang="ts">
import { computed, nextTick, onMounted, onScopeDispose, ref, shallowRef, watch } from 'vue';
import { CdxButton, CdxIcon, CdxSearchInput } from '@wikimedia/codex';
import { cdxIconAdd } from '@wikimedia/codex-icons';
import { useSchemaPermissions } from '@/composables/useSchemaPermissions.ts';
import { useSubjectPermissions } from '@/composables/useSubjectPermissions.ts';
import { NeoWikiServices } from '@/NeoWikiServices.ts';
import { useSchemaStore } from '@/stores/SchemaStore.ts';
import { useSubjectStore } from '@/stores/SubjectStore.ts';
import { Schema } from '@/domain/Schema.ts';
import type { SchemaSummary } from '@/application/SchemaLookup.ts';
import { isSubjectListAvailable } from '@/subjectListAvailability.ts';
import SchemaCard from './SchemaCard.vue';
import SchemaCreatorDialog from './SchemaCreatorDialog.vue';
import SchemaEditorDialog from '@/components/SchemaEditor/SchemaEditorDialog.vue';
import DeletePageDialog from '@/components/common/DeletePageDialog.vue';
import SubjectCreatorDialog from '@/components/SubjectCreator/SubjectCreatorDialog.vue';

// Fills rows of three, two or one card.
const SCHEMAS_PER_LOAD = 12;
const SCHEMA_PREFIX = 'Schema:';
const SEARCH_DELAY_MS = 300;

const {
	canEditSchema,
	canDeleteSchema,
	canCreateSchemas,
	checkEditPermission,
	checkDeletePermission,
	checkCreatePermission
} = useSchemaPermissions();
const { canCreateSubjectPage, checkCreateSubjectPagePermission } = useSubjectPermissions();
const schemaStore = useSchemaStore();
const subjectStore = useSubjectStore();
const schemaRepo = NeoWikiServices.getSchemaRepository();
const subjectListAvailable = isSubjectListAvailable();

const schemas = ref<SchemaSummary[]>( [] );
const nextCursor = ref<string | null>( null );
const loading = ref( true );
const loadFailed = ref( false );
const searchText = ref( '' );
const appliedSearch = ref( '' );
let searchTimer: ReturnType<typeof setTimeout> | null = null;
let requestSequence = 0;

const isCreatorOpen = ref( false );
const isEditorOpen = ref( false );
const editingSchema = shallowRef<Schema | null>( null );
const isDeleteConfirmOpen = ref( false );
const deletingSchemaName = ref( '' );
// The Schema the Subject creator opens on, which the clicked card decides.
const pinnedSchema = ref<string | undefined>( undefined );

const listingIsEmpty = computed( () => !loading.value && !loadFailed.value && nextCursor.value === null );

const emptyText = computed( () => appliedSearch.value === '' ?
	mw.msg( 'neowiki-schemas-empty' ) :
	mw.msg( 'neowiki-schemas-no-match', appliedSearch.value ) );

async function load( cursor: string | null ): Promise<void> {
	const sequence = ++requestSequence;
	loading.value = true;

	try {
		const page = await schemaRepo.getSchemaSummaries( appliedSearch.value, cursor, SCHEMAS_PER_LOAD );

		if ( sequence !== requestSequence ) {
			return;
		}

		// Replacing the list without clearing it first keeps the cards of Schemas still listed mounted.
		schemas.value = cursor === null ? page.schemas : [ ...schemas.value, ...page.schemas ];
		nextCursor.value = page.nextCursor;
		loadFailed.value = false;
	} catch ( error ) {
		if ( sequence !== requestSequence ) {
			return;
		}

		if ( cursor === null ) {
			schemas.value = [];
			nextCursor.value = null;
		}

		loadFailed.value = true;
		mw.notify( error instanceof Error ? error.message : String( error ), { type: 'error' } );
	}

	loading.value = false;
}

function listFromStart(): void {
	load( null );
}

function clearSearchTimer(): void {
	if ( searchTimer !== null ) {
		clearTimeout( searchTimer );
		searchTimer = null;
	}
}

watch( searchText, ( text ) => {
	clearSearchTimer();
	searchTimer = setTimeout( () => {
		appliedSearch.value = text.trim();
	}, SEARCH_DELAY_MS );
} );

watch( appliedSearch, listFromStart );

onScopeDispose( clearSearchTimer );

// Vue patches the new pin onto the dialog before the dialog's pre-flush watcher on the open flag
// reads it, so the creator opens on this card's Schema rather than the one clicked before it.
function openSubjectCreator( schemaName: string ): void {
	pinnedSchema.value = schemaName;
	subjectStore.openSubjectCreator();
}

async function openEditor( schemaName: string ): Promise<void> {
	try {
		editingSchema.value = null;
		await nextTick();

		editingSchema.value = await schemaRepo.getSchema( schemaName );
		isEditorOpen.value = true;
	} catch ( error ) {
		mw.notify( error instanceof Error ? error.message : String( error ), { type: 'error' } );
	}
}

const handleSaveSchema = async ( updatedSchema: Schema, comment: string ): Promise<void> => {
	await schemaStore.saveSchema( updatedSchema, comment );

	schemas.value = schemas.value.map( ( summary ) => summary.name === updatedSchema.getName() ?
		{ ...summary, description: updatedSchema.getDescription() } :
		summary );
};

function onEditorOpenChange( value: boolean ): void {
	isEditorOpen.value = value;

	if ( !value ) {
		editingSchema.value = null;
	}
}

function confirmDelete( schemaName: string ): void {
	deletingSchemaName.value = schemaName;
	isDeleteConfirmOpen.value = true;
}

function onSchemaDeleted( pageTitle: string ): void {
	const schemaName = pageTitle.slice( SCHEMA_PREFIX.length );

	schemaStore.removeSchema( schemaName );
	schemas.value = schemas.value.filter( ( summary ) => summary.name !== schemaName );
}

onMounted( () => {
	checkCreateSubjectPagePermission();
	checkCreatePermission();
	checkEditPermission( '' );
	checkDeletePermission( '' );
	listFromStart();
} );
</script>

<style lang="less">
@import ( reference ) '@wikimedia/codex-design-tokens/theme-wikimedia-ui.less';

.ext-neowiki-schemas-page {
	&__toolbar {
		display: flex;
		flex-wrap: wrap;
		align-items: center;
		gap: @spacing-50;
		margin-bottom: @spacing-125;
	}

	&__find {
		flex: 0 1 22rem;
		min-width: @size-1600;
	}

	// Scoped under the toolbar to outrank `.cdx-button`'s margin, which MediaWiki's Codex loads after this.
	&__toolbar &__create {
		margin-inline-start: auto;
	}

	&__grid {
		display: grid;
		// Never wider than the page, which a phone's can be narrower than 18rem.
		grid-template-columns: repeat( auto-fill, minmax( min( 18rem, 100% ), 1fr ) );
		gap: @spacing-100;
	}

	&__empty {
		color: @color-subtle;
	}

	// Scoped under the page for the same reason as the Create button.
	& &__more {
		margin-top: @spacing-125;
	}
}
</style>
