<template>
	<NeoTree
		class="ext-neowiki-subject-tree"
		:items="[ treeItem ]"
		:label="$i18n( 'neowiki-subject-tree-label' ).text()"
		@select="selectItem"
	>
		<!-- Rendered here rather than inside NeoTree: the dot carries an i18n message of its
			own, and it belongs in the row a treeitem takes its accessible name from. -->
		<template #trailing="{ item }">
			<UnsavedDot v-if="isUnsaved( item.data )" />
		</template>
	</NeoTree>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import NeoTree from '@/components/common/NeoTree/NeoTree.vue';
import UnsavedDot from '@/components/common/UnsavedDot.vue';
import type { NeoTreeItem } from '@/components/common/NeoTree/NeoTreeModel.ts';
import { nodeFor, walkSubjectTree } from './SubjectTreeWalk.ts';
import type { SubjectTreeWalkResult, WalkNode } from './SubjectTreeWalk.ts';
import { Subject } from '@/domain/Subject.ts';
import { Schema } from '@/domain/Schema.ts';
import { SubjectId } from '@/domain/SubjectId.ts';
import { useSubjectStore } from '@/stores/SubjectStore.ts';
import { NeoWikiServices } from '@/NeoWikiServices.ts';

const props = defineProps<{
	rootSubject: Subject;
	rootSchema: Schema;
	openIds: readonly string[];
	activeId: string;
	unsavedIds: readonly string[];
	// The editor's client copies, preferred by the walk over rootSubject and over anything
	// fetched, so a relation picked but not yet saved has a node. Only their relation
	// statements are current; nothing here may be saved or validated from.
	editedSubjects: ReadonlyMap<string, Subject>;
}>();

const emit = defineEmits<{
	select: [ SubjectId ];
}>();

const subjectStore = useSubjectStore();
const schemaRepository = NeoWikiServices.getSchemaRepository();

// Reactive Maps, so a .set() from a landing fetch re-triggers the `walk` computed below.
const resolvedSubjects = ref( new Map<string, Subject>() );
const resolvedSchemas = ref( new Map<string, Schema>() );

// Non-reactive: an in-flight guard must not re-trigger the walk.
const pendingSubjectIds = new Set<string>();
const pendingSchemaNames = new Set<string>();

// The walk re-runs over the whole tree on every resolution anywhere and asks again for whatever
// it still lacks, so without these a broken target's fetch would be re-issued on every re-run.
// Memoised per mount, and the dialog keys this component on its open epoch, so each
// opening retries.
const failedSubjectIds = new Set<string>();
const failedSchemaNames = new Set<string>();

async function resolveSubject( id: string ): Promise<void> {
	if ( resolvedSubjects.value.has( id ) || pendingSubjectIds.has( id ) || failedSubjectIds.has( id ) ) {
		return;
	}
	pendingSubjectIds.add( id );
	try {
		resolvedSubjects.value.set( id, await subjectStore.getOrFetchSubject( new SubjectId( id ) ) );
	} catch ( _error ) {
		// The node keeps showing the raw id and never expands.
		failedSubjectIds.add( id );
	} finally {
		pendingSubjectIds.delete( id );
	}
}

async function resolveSchema( name: string ): Promise<void> {
	if ( resolvedSchemas.value.has( name ) || pendingSchemaNames.has( name ) || failedSchemaNames.has( name ) ) {
		return;
	}
	pendingSchemaNames.add( name );
	try {
		resolvedSchemas.value.set( name, await schemaRepository.getSchema( name ) );
	} catch ( _error ) {
		// The Subject renders as a leaf rather than as an error.
		failedSchemaNames.add( name );
	} finally {
		pendingSchemaNames.delete( name );
	}
}

const walk = computed( (): SubjectTreeWalkResult => walkSubjectTree( {
	rootSubject: props.rootSubject,
	rootSchema: props.rootSchema,
	// Vue's reactive-Map typing structurally unwraps the class values; cast back to the
	// real classes, as SubjectStore's own getter does.
	editedSubject: ( id: string ): Subject | undefined =>
		props.editedSubjects.get( id ) as Subject | undefined,
	fetchedSubject: ( id: string ): Subject | undefined =>
		resolvedSubjects.value.get( id ) as Subject | undefined,
	fetchedSchema: ( name: string ): Schema | undefined =>
		resolvedSchemas.value.get( name ) as Schema | undefined
} ) );

watch( walk, ( result ) => {
	result.missingSubjectIds.forEach( ( id ) => resolveSubject( id ) );
	result.missingSchemaNames.forEach( ( name ) => resolveSchema( name ) );
}, { immediate: true } );

// Subjects the editor holds that the walk cannot reach: past its depth cap, unlinked from the
// form after being opened, or orphaned by a schema edit. Open panes and unsaved edits both
// count; either is an edit the user has to be able to get back to.
const strayNodes = computed( (): WalkNode[] => {
	// The root is always open, and already has a node of its own.
	const rootId = props.rootSubject.getId().text;
	const heldIds = new Set( [ ...props.openIds, ...props.unsavedIds ] );

	return [ ...heldIds ]
		.filter( ( id ) => id !== rootId && !walk.value.reachedIds.has( id ) )
		.map( ( id ) => nodeFor( `stray:${ id }`, id, props.editedSubjects.get( id ) ) );
} );

const treeShape = computed( (): WalkNode => {
	const root = walk.value.root;

	if ( strayNodes.value.length === 0 ) {
		return root;
	}

	const caption = mw.message( 'neowiki-subject-tree-not-linked' ).text();

	return {
		...root,
		children: [
			...root.children,
			...strayNodes.value.map( ( node ) => ( { ...node, propertyName: caption } ) )
		]
	};
} );

const treeItem = computed( (): NeoTreeItem<string> => toTreeItem( treeShape.value ) );

function toTreeItem( node: WalkNode ): NeoTreeItem<string> {
	return {
		key: node.key,
		label: node.label,
		// Set apart by Schema unless the name already is the Schema: a Subject with no label is
		// shown under its Schema name (ADR 31), and one labelled after its Schema reads the same.
		secondaryLabel: node.schemaName === node.label ? undefined : node.schemaName,
		active: node.subjectId === props.activeId,
		attrs: { 'data-mw-neowiki-subject-id': node.subjectId },
		children: childItemsOf( node ),
		data: node.subjectId
	};
}

// Each child carries its relation property's name; NeoTree gathers the contiguous run of
// children sharing one into a single captioned group.
function childItemsOf( node: WalkNode ): NeoTreeItem<string>[] {
	return node.children.map( ( child ) => ( { ...toTreeItem( child ), groupLabel: child.propertyName } ) );
}

function isUnsaved( subjectId: string ): boolean {
	return props.unsavedIds.includes( subjectId );
}

function selectItem( item: NeoTreeItem<string> ): void {
	emit( 'select', new SubjectId( item.data ) );
}
</script>
