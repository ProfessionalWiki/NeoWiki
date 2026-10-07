<template>
	<div class="ext-neowiki-schemas-page">
		<div class="ext-neowiki-schemas-page__toolbar">
			<CdxSearchInput
				v-model="findText"
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

		<CdxMessage
			v-if="listState === 'failed'"
			type="error"
			:inline="true"
		>
			{{ $i18n( 'neowiki-schemas-load-error' ).text() }}
		</CdxMessage>
		<p
			v-else-if="listState === 'loading'"
			class="ext-neowiki-schemas-page__loading"
		>
			…
		</p>
		<div
			v-else-if="foundSchemas.length > 0"
			class="ext-neowiki-schemas-page__grid"
		>
			<SchemaCard
				v-for="summary in foundSchemas"
				:key="summary.name"
				:summary="summary"
				:can-edit="canEditSchema"
				:can-delete="canDeleteSchema"
				:can-create-subject="canCreateSubjectPage"
				:subject-list-available="subjectListAvailable"
				:subject-previews="subjectPreviews"
				:subject-count="subjectCountOf( summary.name )"
				@edit="openEditor( summary.name )"
				@delete="confirmDelete( summary.name )"
				@create-subject="openSubjectCreator( summary.name )"
			/>
		</div>
		<p
			v-else
			class="ext-neowiki-schemas-page__empty"
		>
			{{ emptyText }}
		</p>

		<SchemaCreatorDialog
			v-if="canCreateSchemas"
			:open="isCreatorOpen"
			@update:open="isCreatorOpen = $event"
			@created="onSchemaCreated"
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
import { computed, nextTick, onMounted, ref, shallowRef } from 'vue';
import { CdxButton, CdxIcon, CdxMessage, CdxSearchInput } from '@wikimedia/codex';
import { cdxIconAdd } from '@wikimedia/codex-icons';
import { useSchemaPermissions } from '@/composables/useSchemaPermissions.ts';
import { useSubjectPermissions } from '@/composables/useSubjectPermissions.ts';
import { useSubjectCounts } from '@/composables/useSubjectCounts.ts';
import { NeoWikiServices } from '@/NeoWikiServices.ts';
import { useSchemaStore } from '@/stores/SchemaStore.ts';
import { useSubjectStore } from '@/stores/SubjectStore.ts';
import { Schema } from '@/domain/Schema.ts';
import type { SchemaSummary } from '@/application/SchemaLookup.ts';
import { isSubjectListAvailable } from '@/subjectListAvailability.ts';
import SchemaCard from './SchemaCard.vue';
import { SubjectPreviews } from './SubjectPreviews.ts';
import SchemaCreatorDialog from './SchemaCreatorDialog.vue';
import SchemaEditorDialog from '@/components/SchemaEditor/SchemaEditorDialog.vue';
import DeletePageDialog from '@/components/common/DeletePageDialog.vue';
import SubjectCreatorDialog from '@/components/SubjectCreator/SubjectCreatorDialog.vue';

const SCHEMA_PREFIX = 'Schema:';

const {
	canEditSchema,
	canDeleteSchema,
	canCreateSchemas,
	checkEditPermission,
	checkDeletePermission,
	checkCreatePermission
} = useSchemaPermissions();
const { canCreateSubjectPage, checkCreateSubjectPagePermission } = useSubjectPermissions();
const { subjectCountOf, loadSubjectCounts } = useSubjectCounts();
const schemaStore = useSchemaStore();
const subjectStore = useSubjectStore();
const schemaRepo = NeoWikiServices.getSchemaRepository();
const subjectListAvailable = isSubjectListAvailable();
// Kept for the whole page view, so a card filtered out and back in shows its Subjects without asking again.
const subjectPreviews = new SubjectPreviews( NeoWikiServices.getSubjectSummaryLookup() );

const schemas = ref<SchemaSummary[]>( [] );
const listState = ref<'loading' | 'loaded' | 'failed'>( 'loading' );
const findText = ref( '' );
// Numbers the listings asked for, so one answered after a later one cannot replace its newer list.
let listingSequence = 0;

const isCreatorOpen = ref( false );
const isEditorOpen = ref( false );
const editingSchema = shallowRef<Schema | null>( null );
const isDeleteConfirmOpen = ref( false );
const deletingSchemaName = ref( '' );
// The Schema the Subject creator opens on, which the clicked card decides.
const pinnedSchema = ref<string | undefined>( undefined );

// The Schema picker's rule: any part of the name, in any case.
const foundSchemas = computed( () => {
	const query = findText.value.trim().toLowerCase();
	return schemas.value.filter( ( summary ) => summary.name.toLowerCase().includes( query ) );
} );

const emptyText = computed( () => schemas.value.length === 0 ?
	mw.msg( 'neowiki-schemas-empty' ) :
	mw.msg( 'neowiki-schemas-no-match', findText.value.trim() ) );

// The cards wait for the counts too, so a count does not replace "View all subjects" under the reader's eyes.
async function loadSchemas(): Promise<void> {
	const sequence = ++listingSequence;

	try {
		const [ listing ] = await Promise.all( [ schemaStore.fetchAllSchemaSummaries(), loadSubjectCounts() ] );

		if ( sequence !== listingSequence ) {
			return;
		}

		schemas.value = listing;
		listState.value = 'loaded';
	} catch ( error ) {
		if ( sequence !== listingSequence ) {
			return;
		}

		console.error( 'Failed to load schemas:', error );
		listState.value = 'failed';
	}
}

// A find text the new Schema's name does not contain would hide its card.
function onSchemaCreated(): void {
	findText.value = '';
	loadSchemas();
}

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
	loadSchemas();
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

	&__loading {
		color: @color-subtle;
		font-style: italic;
	}

	&__empty {
		color: @color-subtle;
	}
}
</style>
