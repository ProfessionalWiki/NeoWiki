<template>
	<div class="ext-neowiki-subject-page">
		<p
			v-if="errorText !== null"
			class="ext-neowiki-subject-page__error"
		>
			{{ errorText }}
		</p>

		<template v-else-if="subject !== null">
			<ul class="ext-neowiki-subject-page__subject">
				<SubjectRow
					:subject="subject as Subject"
					emphasized
					:expanded="expandedIds.has( subjectId.text )"
					:can-edit="canEditSubject"
					:can-delete="canDeleteSubject"
					:page="hostingPage"
					@toggle="toggleExpanded"
					@edit="openEditor"
					@delete="confirmDelete"
					@copy-link="copySubjectLink"
				/>
			</ul>

			<template v-if="referencedSubjects.length > 0">
				<h2 class="ext-neowiki-subject-page__referenced-heading">
					{{ $i18n( 'neowiki-special-subject-referenced-heading' ).text() }}
				</h2>

				<ul class="ext-neowiki-subject-page__referenced">
					<SubjectRow
						v-for="referenced in referencedSubjects"
						:key="referenced.getId().text"
						:subject="referenced"
						:subject-page-url="subjectPageUrl( referenced.getId().text )"
						:expanded="expandedIds.has( referenced.getId().text )"
						:can-edit="canEditSubject"
						:can-delete="canDeleteSubject"
						:page="pageOf( referenced )"
						@toggle="toggleExpanded"
						@edit="openEditor"
						@delete="confirmDelete"
						@copy-link="copySubjectLink"
					/>
				</ul>
			</template>
		</template>

		<p
			v-else
			class="ext-neowiki-subject-page__loading"
		>
			…
		</p>

		<SubjectEditorDialog
			v-if="editingSubject !== null && editingSchema !== null"
			v-model:open="editorOpen"
			:subject="editingSubject as Subject"
			:schema="editingSchema as Schema"
			:on-save="handleEditSave"
			:on-create="handleEditCreate"
			:on-save-schema="handleSchemaSave"
		/>

		<SubjectDeleteDialog
			v-model:open="deleteConfirmOpen"
			:subject-name="deletingSubjectName"
			@confirm="executeDelete"
		/>
	</div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, shallowRef } from 'vue';
import { NeoWikiServices } from '@/NeoWikiServices.ts';
import { useSubjectStore } from '@/stores/SubjectStore.ts';
import { useSchemaStore } from '@/stores/SchemaStore.ts';
import { useSubjectPermissions } from '@/composables/useSubjectPermissions.ts';
import { subjectDisplayName } from '@/presentation/subjectDisplayName.ts';
import { Subject } from '@/domain/Subject.ts';
import { Schema } from '@/domain/Schema.ts';
import { SubjectId } from '@/domain/SubjectId.ts';
import { SubjectWithContext } from '@/domain/SubjectWithContext.ts';
import { PageIdentifiers } from '@/domain/PageIdentifiers.ts';
import { SubjectNotFoundError } from '@/persistence/SubjectNotFoundError.ts';
import { subjectPageUrl } from '@/presentation/subjectPageUrl.ts';
import { copyToClipboard } from '@/presentation/copyToClipboard.ts';
import SubjectRow from '@/components/SubjectsManager/SubjectRow.vue';
import SubjectDeleteDialog from '@/components/SubjectsManager/SubjectDeleteDialog.vue';
import SubjectEditorDialog from '@/components/SubjectEditor/SubjectEditorDialog.vue';

const props = defineProps<{
	subjectId: string;
}>();

const subjectId = new SubjectId( props.subjectId );

const subjectStore = useSubjectStore();
const schemaStore = useSchemaStore();
const subjectRepo = NeoWikiServices.getSubjectRepository();
const schemaRepo = NeoWikiServices.getSchemaRepository();
const { canEditSubject, canDeleteSubject, checkPermissions } = useSubjectPermissions();

// What the read returned, rather than a live read of the Subject registry: deleting a Subject takes
// it out of that registry, and the registry's getter throws for an id it no longer holds. Every
// write this page makes is followed by a re-read, so the rows still show what the wiki holds.
//
// These two are the page's whole state: neither set is the read in flight, an error is the read that
// failed, and a Subject is the read that landed.
const subject = shallowRef<Subject | null>( null );
const errorText = ref<string | null>( null );
// Bundle order, which is the order the Subject's relations name them.
const referencedSubjects = shallowRef<Subject[]>( [] );

// The Subject the page is about is open on arrival; the ones it references are not, so the page
// opens on one Subject's data rather than on all of them.
const expandedIds = ref<Set<string>>( new Set( [ subjectId.text ] ) );

const hostingPage = computed<PageIdentifiers | null>( () => pageOf( subject.value ) );

/**
 * The page a Subject is stored on, or null when the read served none. Every Subject the
 * deserializer builds carries PageIdentifiers, filled from a payload that omits both fields for a
 * Subject whose hosting page the server could not resolve — so the values, not the type, decide.
 */
function pageOf( each: Subject | null ): PageIdentifiers | null {
	if ( !( each instanceof SubjectWithContext ) ) {
		return null;
	}

	const page = each.getPageIdentifiers();
	const pageId = page.getPageId() as number | undefined;
	const pageName = page.getPageName() as string | undefined;

	return pageId === undefined || pageName === undefined ? null : page;
}

function toggleExpanded( toggled: Subject ): void {
	const id = toggled.getId().text;
	const next = new Set( expandedIds.value );
	if ( next.has( id ) ) {
		next.delete( id );
	} else {
		next.add( id );
	}
	expandedIds.value = next;
}

// The delete controls stay live while a re-read is in flight, so reads can overlap; only the one
// started last decides what the rows show.
let latestRead = 0;

async function readSubject(): Promise<void> {
	const read = ++latestRead;
	const subjectEpoch = subjectStore.mutationEpoch;
	const bundle = await subjectRepo.getSubjectWithReferencedSubjects( subjectId );
	const requested = bundle.requestedSubject;

	// Seeding the registry is what lets RelationDisplay resolve this Subject's relation targets,
	// the same way the Data tab's own load does. A write acknowledged meanwhile may postdate what
	// this read returned, so the registry is then left alone (ADR 30 rule 3).
	if ( subjectEpoch === subjectStore.mutationEpoch ) {
		subjectStore.setSubject( requested );
		bundle.referencedSubjects.forEach( ( referenced ) => subjectStore.setSubject( referenced ) );
	}
	await Promise.all( [
		loadSchemas( [ requested, ...bundle.referencedSubjects ] ),
		seedRelationTargetsOf( bundle.referencedSubjects )
	] );

	if ( read !== latestRead ) {
		return;
	}

	subject.value = requested;
	referencedSubjects.value = bundle.referencedSubjects;
}

async function loadSubject(): Promise<void> {
	try {
		await readSubject();
	} catch ( error ) {
		console.error( 'Failed to load subject:', error );
		errorText.value = error instanceof SubjectNotFoundError ?
			notFoundMessage() :
			mw.msg( 'neowiki-special-subject-load-error' );
	}
}

/**
 * A re-read after a write. The write committed whatever this does, so a failure reports itself and
 * leaves the page showing what was saved, rather than replacing it with "no such Subject".
 */
async function reloadSubject(): Promise<void> {
	try {
		await readSubject();
	} catch ( error ) {
		console.error( 'Failed to reload subject:', error );
		mw.notify( mw.msg( 'neowiki-special-subject-load-error' ), { type: 'error' } );
	}
}

function notFoundMessage(): string {
	return mw.msg( 'neowiki-special-subject-not-found', props.subjectId );
}

/**
 * The read expands relations one level, so a referenced Subject's own relation targets are not in
 * it, and RelationDisplay renders a target it cannot resolve as an error. This fetches them, which
 * is the depth the rows below render: their targets' targets are not shown, so not fetched.
 */
async function seedRelationTargetsOf( subjects: Subject[] ): Promise<void> {
	const targets = new Map<string, SubjectId>();
	for ( const each of subjects ) {
		each.getStatements().getIdsOfReferencedSubjects()
			.forEach( ( target ) => targets.set( target.text, target ) );
	}

	await Promise.all( [ ...targets.values() ].map( async ( target ) => {
		try {
			await subjectStore.getOrFetchSubject( target );
		} catch ( error ) {
			// RelationDisplay marks the unresolved target; the row around it still renders.
			console.error( `Failed to load relation target ${ target.text }:`, error );
		}
	} ) );
}

/**
 * The Subject read carries no Schemas, and SubjectStatementsView renders from the Schema store, so
 * each distinct Schema the store does not hold yet is fetched into it — which after the first read
 * is usually none, since a re-read shows the same Subjects. A Schema that fails to load leaves its
 * Subjects rendered without their property definitions, which that view already handles; failing the
 * whole page over one would not.
 */
async function loadSchemas( subjects: Subject[] ): Promise<void> {
	const names = [ ...new Set( subjects.map( ( each ) => each.getSchemaName() ) ) ]
		.filter( ( name ) => !schemaStore.schemas.has( name ) );
	const epoch = schemaStore.mutationEpoch;

	await Promise.all( names.map( async ( name ) => {
		try {
			const schema = await schemaRepo.getSchema( name );

			if ( epoch === schemaStore.mutationEpoch ) {
				schemaStore.setSchema( name, schema );
			}
		} catch ( error ) {
			console.error( `Failed to load schema ${ name }:`, error );
		}
	} ) );
}

function copySubjectLink( target: Subject ): Promise<void> {
	// This Subject's own page, absolute, rather than the address bar plus a fragment: every row here
	// has a page of its own, so a link to the row means a link to that page.
	return copyToClipboard(
		new URL( subjectPageUrl( target.getId().text ), location.href ).toString(),
		mw.msg( 'neowiki-managesubjects-link-copied' ),
		mw.msg( 'neowiki-managesubjects-link-copy-error' )
	);
}

// Editor state is component-local (ADR 16): the dialog opens on data fetched straight from the
// repositories, not on the store this page renders from.
const editingSubject = shallowRef<Subject | null>( null );
const editingSchema = shallowRef<Schema | null>( null );
const editorOpen = ref( false );

async function openEditor( subjectToEdit: Subject ): Promise<void> {
	try {
		// Both, so the editor never opens against data another tab has moved on from.
		const [ freshSubject, schema ] = await Promise.all( [
			subjectRepo.getSubjectForEditing( subjectToEdit.getId() ),
			schemaRepo.getSchema( subjectToEdit.getSchemaName() )
		] );

		editingSubject.value = freshSubject;
		editingSchema.value = schema;
		editorOpen.value = true;
	} catch ( error ) {
		mw.notify( error instanceof Error ? error.message : String( error ), { type: 'error' } );
	}
}

// Re-read rather than patch: a save can add or drop relations, so the referenced Subjects below are
// no longer the ones the page was built from.
async function handleEditSave( updatedSubject: Subject, comment: string ): Promise<void> {
	await subjectStore.updateSubject( updatedSubject, comment );
	await reloadSubject();
}

// No reload of its own: a created Subject always comes with the update that points at it, and that
// goes through handleEditSave.
async function handleEditCreate( subject: Subject, targetPageId: number, comment: string ): Promise<void> {
	await subjectStore.createSubject( subject, targetPageId, comment );
}

async function handleSchemaSave( updatedSchema: Schema, comment: string ): Promise<void> {
	await schemaStore.saveSchema( updatedSchema, comment );
}

const deleteConfirmOpen = ref( false );
const deletingSubject = shallowRef<Subject | null>( null );

const deletingSubjectName = computed( () =>
	deletingSubject.value === null ? '' : subjectDisplayName( deletingSubject.value ) );

function confirmDelete( target: Subject ): void {
	deletingSubject.value = target;
	deleteConfirmOpen.value = true;
}

async function executeDelete( comment: string ): Promise<void> {
	const target = deletingSubject.value;
	deleteConfirmOpen.value = false;

	if ( target === null ) {
		return;
	}

	const name = subjectDisplayName( target );
	const deletedTheSubjectShown = target.getId().text === subjectId.text;

	try {
		await subjectStore.deleteSubject(
			target.getId(),
			comment || mw.msg( 'neowiki-managesubjects-delete-summary-default' )
		);
		mw.notify( mw.msg( 'neowiki-managesubjects-delete-success' ), { type: 'success' } );

		if ( deletedTheSubjectShown ) {
			// Nothing left for this page to show, and the wiki now answers its id the way it answers
			// one it never minted.
			subject.value = null;
			referencedSubjects.value = [];
			errorText.value = notFoundMessage();
		} else {
			await reloadSubject();
		}
	} catch ( error ) {
		console.error( 'Failed to delete subject:', error );
		mw.notify( mw.msg( 'neowiki-managesubjects-delete-error', name ), { type: 'error' } );
	} finally {
		deletingSubject.value = null;
	}
}

onMounted( async () => {
	await loadSubject();

	// Editing rights are the hosting page's. Without one there is nothing to ask about, and the
	// controls stay hidden.
	if ( hostingPage.value !== null ) {
		await checkPermissions( hostingPage.value.getPageId() );
	}
} );
</script>

<style lang="less">
@import ( reference ) '@wikimedia/codex-design-tokens/theme-wikimedia-ui.less';

.ext-neowiki-subject-page {
	max-width: 64rem;

	&__loading,
	&__error {
		color: @color-subtle;
		font-style: italic;
	}

	&__subject,
	&__referenced {
		list-style: none;
		padding: 0;
		margin: 0;
		display: flex;
		flex-direction: column;
		gap: @spacing-50;
	}

	&__referenced-heading {
		margin: @spacing-150 0 @spacing-75;
	}
}
</style>
