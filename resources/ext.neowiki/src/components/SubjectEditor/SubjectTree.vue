<template>
	<NeoTree
		class="ext-neowiki-subject-tree"
		:items="[ treeItem ]"
		:label="$i18n( 'neowiki-subject-tree-label' ).text()"
		@select="selectItem"
		@toggle="toggleItem"
	>
		<!-- Rendered here rather than inside NeoTree, which resolves no messages. -->
		<template #trailing="{ item }">
			<UnsavedDot v-if="isUnsaved( item.data.subjectId )" />
		</template>

		<!-- At the row's end: a figure that followed the name would sit at a different place on
			every row. Each of these sits inside the accessible name the treeitem takes from its
			content, so each needs wording of its own — hence `role` and a label on the figure,
			which would otherwise be read out as a bare number. -->
		<template #end="{ item }">
			<span
				v-if="item.data.state === 'closed'"
				class="ext-neowiki-tree__count"
				role="img"
				:aria-label="$i18n( 'neowiki-subject-tree-related-count', item.data.relationTargetCount ).text()"
				:title="$i18n( 'neowiki-subject-tree-related-count', item.data.relationTargetCount ).text()"
			>{{ item.data.relationTargetCount }}</span>

			<CdxIcon
				v-else-if="item.data.state === 'repeated'"
				class="ext-neowiki-tree__mark"
				:data-mw-neowiki-tree-mark="item.data.state"
				:icon="cdxIconLink"
				:icon-label="$i18n( 'neowiki-subject-tree-repeated' ).text()"
				size="x-small"
			/>

			<CdxIcon
				v-else-if="item.data.state === 'unreadable'"
				class="ext-neowiki-tree__mark ext-neowiki-tree__mark--unreadable"
				:data-mw-neowiki-tree-mark="item.data.state"
				:icon="cdxIconAlert"
				:icon-label="$i18n( 'neowiki-subject-tree-unreadable' ).text()"
				size="x-small"
			/>
		</template>
	</NeoTree>
</template>

<script setup lang="ts">
import { computed, reactive, ref, shallowReactive, watch } from 'vue';
import NeoTree from '@/components/common/NeoTree/NeoTree.vue';
import UnsavedDot from '@/components/common/UnsavedDot.vue';
import { CdxIcon } from '@wikimedia/codex';
import { cdxIconAlert, cdxIconLink } from '@wikimedia/codex-icons';
import type { NeoTreeItem } from '@/components/common/NeoTree/NeoTreeModel.ts';
import { nodeFor, pathToSubject, reachableSubjectIds, walkSubjectTree } from './SubjectTreeWalk.ts';
import type { SubjectLookups, SubjectTreeWalkResult, WalkNode } from './SubjectTreeWalk.ts';
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

/**
 * What a row is, from the reader's side: one value, from which the control, the count and the
 * mark all follow.
 *
 * The walk cannot produce it alone. It reports what it does not hold, and that covers both a
 * fetch in flight and a fetch that failed — which must not look alike, or a row claims it could
 * not be read for the length of every request. The two are told apart here, where the fetches are.
 */
type RowState =
	// The walk followed this row's relations and its children are drawn.
	| 'open'
	// It has relations and nobody has opened them.
	| 'closed'
	// It has none.
	| 'leaf'
	// Its target already sits higher up this branch.
	| 'repeated'
	// Something it needs is on its way. Reached on every expansion, so it draws no mark.
	| 'loading'
	// Something it needs did not arrive.
	| 'unreadable';

// What a row needs beyond its label. Carried through NeoTree's payload parameter, so the widget
// stays free of all of it.
interface RowSubject {
	subjectId: string;
	state: RowState;
	relationTargetCount: number;
}

const subjectStore = useSubjectStore();
const schemaRepository = NeoWikiServices.getSchemaRepository();

// Shallow, so a .set() from a landing fetch re-triggers the `walk` computed below without
// proxying what the Maps hold.
const resolvedSubjects = shallowReactive( new Map<string, Subject>() );
const resolvedSchemas = shallowReactive( new Map<string, Schema>() );

/**
 * What the reader has opened and closed by hand, keyed by node key so one occurrence of a Subject
 * opens without opening the others.
 *
 * The only expansion state here; the rest is derived from which Subject is being edited. Storing
 * that too would re-open whatever the reader had just closed, since the walk re-runs on every
 * keystroke in a label field.
 */
const gestures = ref<ReadonlyMap<string, boolean>>( new Map() );

// Non-reactive: an in-flight guard must not re-trigger the walk.
const pendingSubjectIds = new Set<string>();
const pendingSchemaNames = new Set<string>();

// The walk re-runs on every resolution anywhere and asks again for whatever it lacks, so without
// these a broken target's fetch would be re-issued each time. Memoised per mount; the dialog keys
// this component on its open epoch, so each opening retries.
//
// Reactive, unlike the in-flight guards above, because they separate a row that is waiting from
// one that failed and a row must redraw when one lands. The walk reads neither, so this cannot
// re-trigger it.
const failedSubjectIds = reactive( new Set<string>() );
const failedSchemaNames = reactive( new Set<string>() );

async function resolveSubject( id: string ): Promise<void> {
	if ( resolvedSubjects.has( id ) || pendingSubjectIds.has( id ) || failedSubjectIds.has( id ) ) {
		return;
	}
	pendingSubjectIds.add( id );
	try {
		resolvedSubjects.set( id, await subjectStore.getOrFetchSubject( new SubjectId( id ) ) );
	} catch ( _error ) {
		// The node keeps showing the raw id and never expands.
		failedSubjectIds.add( id );
	} finally {
		pendingSubjectIds.delete( id );
	}
}

async function resolveSchema( name: string ): Promise<void> {
	if ( resolvedSchemas.has( name ) || pendingSchemaNames.has( name ) || failedSchemaNames.has( name ) ) {
		return;
	}
	pendingSchemaNames.add( name );
	try {
		resolvedSchemas.set( name, await schemaRepository.getSchema( name ) );
	} catch ( _error ) {
		// The Subject renders as a leaf rather than as an error.
		failedSchemaNames.add( name );
	} finally {
		pendingSchemaNames.delete( name );
	}
}

// One place builds these, so the walk and the path search below cannot read different data.
function lookups(): SubjectLookups {
	return {
		rootSubject: props.rootSubject,
		rootSchema: props.rootSchema,
		editedSubject: ( id: string ): Subject | undefined => props.editedSubjects.get( id ),
		fetchedSubject: ( id: string ): Subject | undefined => resolvedSubjects.get( id ),
		fetchedSchema: ( name: string ): Schema | undefined => resolvedSchemas.get( name )
	};
}

/**
 * The rule: the Subject being edited is open, and so is every node above it. Root plus one level
 * on first open is a consequence of it, not a case of its own.
 *
 * Derived rather than stored, and recomputed as Subjects and Schemas land: on the first render
 * the tree holds almost nothing, so the path to a nested active Subject is not yet findable.
 */
const activePath = computed( (): ReadonlySet<string> =>
	new Set( pathToSubject( lookups(), props.activeId ) ) );

const walk = computed( (): SubjectTreeWalkResult =>
	walkSubjectTree( { ...lookups(), isExpanded } ) );

// Everything the tree has a place for, open or not. Deliberately not watched for what it could
// not resolve: fetching from that would pull in the whole graph.
const reachable = computed( (): ReadonlySet<string> => reachableSubjectIds( lookups() ) );

function isExpanded( key: string ): boolean {
	return gestures.value.get( key ) ?? activePath.value.has( key );
}

// A row closed by hand outranks the rule, so the control never lies — but only until the reader
// moves to a Subject beneath it, which would otherwise leave the row being edited hidden and
// listed nowhere. Closes elsewhere are left alone.
watch( (): string => props.activeId, (): void => {
	const kept = new Map( gestures.value );
	let changed = false;

	for ( const [ key, open ] of kept ) {
		if ( !open && activePath.value.has( key ) ) {
			kept.delete( key );
			changed = true;
		}
	}

	if ( changed ) {
		gestures.value = kept;
	}
} );

watch( walk, ( result ) => {
	result.missingSubjectIds.forEach( ( id ) => resolveSubject( id ) );
	result.missingSchemaNames.forEach( ( name ) => resolveSchema( name ) );
}, { immediate: true } );

// Open Subjects with no place in the tree at all: unlinked from the form after being opened, or
// orphaned by a Schema edit. Each is one the reader has to be able to get back to.
//
// Asked of what the tree has a place for, not of what it drew: a Subject under a closed row is
// absent from the drawing and still perfectly well linked, and the row that would wrongly be
// captioned unlinked can be the one being edited.
const strayNodes = computed( (): WalkNode[] => {
	// The root is always open, and already has a node of its own.
	const rootId = props.rootSubject.getId().text;

	return props.openIds
		.filter( ( id ) => id !== rootId && !reachable.value.has( id ) )
		.map( ( id ) => nodeFor( `stray:${ id }`, id, props.editedSubjects.get( id ) ) );
} );

// Captioned rather than given a relation they do not have.
const treeItem = computed( (): NeoTreeItem<RowSubject> => {
	const root = toTreeItem( walk.value.root );

	if ( strayNodes.value.length === 0 ) {
		return root;
	}

	const caption = mw.message( 'neowiki-subject-tree-not-linked' ).text();

	return {
		...root,
		// A parent whatever its own relations say, since these rows hang from it: left to its own
		// count it would call itself an end node while rendering a group beneath it.
		expandable: true,
		children: [
			...root.children ?? [],
			...strayNodes.value.map( ( node ) => toStrayItem( node, caption ) )
		]
	};
} );

// The tree reaches this Subject from nowhere, so it holds no path to hang the Subject's own
// targets on and a control could never be answered. Drawn as closed so the count still shows,
// which is the only thing telling the row from a Subject with nothing under it.
function toStrayItem( node: WalkNode, groupLabel: string ): NeoTreeItem<RowSubject> {
	const item = toTreeItem( node, groupLabel );

	return { ...item, expandable: false, data: { ...item.data, state: 'closed' } };
}

function toTreeItem( node: WalkNode, groupLabel?: string ): NeoTreeItem<RowSubject> {
	const state = rowStateOf( node );
	// The root never closes: it is the only way back to the Subject being edited, and the only
	// thing the "not linked here" rows hang from.
	const isRoot = node.key === walk.value.root.key;

	return {
		key: node.key,
		label: node.label,
		groupLabel,
		active: node.subjectId === props.activeId,
		attrs: {
			'data-mw-neowiki-subject-id': node.subjectId,
			// Open over children that have not arrived, which `aria-expanded` cannot say.
			...state === 'loading' ? { 'aria-busy': 'true' } : {}
		},
		children: node.children.map( ( child ) => toTreeItem( child, child.propertyName ) ),
		data: {
			subjectId: node.subjectId,
			state,
			relationTargetCount: node.relationTargetCount
		},
		// A row still waiting keeps its control, or the control would vanish and come back on
		// every expansion. A repeat and an unreadable row get none: either would promise children
		// the tree cannot produce however often it is pressed.
		expandable: state === 'open' || state === 'closed' || state === 'loading',
		// What the reader has open, not what has arrived. A row appears as soon as its parent
		// does, its own Subject still in flight and nobody having opened it; and since the walk
		// reads the unresolved arm before the closed one, calling such a row expanded also
		// swallows the key that climbs out of the branch.
		expanded: isRoot || isExpanded( node.key ),
		collapsible: !isRoot
	};
}

function rowStateOf( node: WalkNode ): RowState {
	switch ( node.frontier ) {
		case 'collapsed':
			return 'closed';
		case 'repeated':
			return 'repeated';
		case 'subjectUnresolved':
			return failedSubjectIds.has( node.subjectId ) ? 'unreadable' : 'loading';
		case 'schemaUnresolved':
			return node.schemaName !== undefined && failedSchemaNames.has( node.schemaName ) ?
				'unreadable' :
				'loading';
		default:
			return node.relationTargetCount > 0 ? 'open' : 'leaf';
	}
}

function isUnsaved( subjectId: string ): boolean {
	return props.unsavedIds.includes( subjectId );
}

function selectItem( item: NeoTreeItem<RowSubject> ): void {
	// On this row's own key rather than left to the rule: the host reaches the Subject
	// asynchronously, and one Subject can hold several rows.
	gestures.value = new Map( gestures.value ).set( item.key, true );
	emit( 'select', new SubjectId( item.data.subjectId ) );
}

function toggleItem( item: NeoTreeItem<RowSubject> ): void {
	gestures.value = new Map( gestures.value ).set( item.key, !isExpanded( item.key ) );
}
</script>
