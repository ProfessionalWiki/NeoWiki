import { mount, flushPromises, DOMWrapper, VueWrapper } from '@vue/test-utils';
import { describe, it, expect, beforeEach, vi } from 'vitest';
import { nextTick } from 'vue';
import { createPinia, setActivePinia, type Pinia } from 'pinia';
import SubjectTree from '@/components/SubjectEditor/SubjectTree.vue';
import { useSubjectStore } from '@/stores/SubjectStore.ts';
import { SubjectId } from '@/domain/SubjectId.ts';
import { createI18nMock, setupMwMock } from '../../VueTestHelpers.ts';
import { NeoWikiTestServices } from '../../NeoWikiTestServices.ts';
import { Service } from '@/NeoWikiServices.ts';
import { InMemorySchemaRepository } from '@/application/SchemaRepository.ts';
import { newSchema, newSubject } from '@/TestHelpers.ts';
import { PropertyDefinitionList } from '@/domain/PropertyDefinitionList.ts';
import { createPropertyDefinitionFromJson, PropertyName } from '@/domain/PropertyDefinition.ts';
import { Statement } from '@/domain/Statement.ts';
import { StatementList } from '@/domain/StatementList.ts';
import { newRelation, RelationValue } from '@/domain/Value.ts';
import type { Subject } from '@/domain/Subject.ts';
import type { Schema } from '@/domain/Schema.ts';

// SubjectId's format (ADR 14) excludes '0', 'O', 'I' and 'l', hence the runs of '1's.
const ROOT_ID = 's1person1111111';
const SPOUSE_ID = 's4spouse1111111';
const BIRTH_ID = 's2bach111111111';
const TIMESPAN_ID = 's3span111111111';

// Reached from no relation statement anywhere: a Subject the user edited and then unlinked.
const STRAY_ID = 'sSada1111111111';

const CYCLE_ROOT_ID = 's5a111111111111';
const CYCLE_B_ID = 's6b111111111111';

const TWOHOP_A_ID = 'sBtwohopa111111';
const TWOHOP_B_ID = 'sCtwohopb111111';

const DIAMOND_ROOT_ID = 's7root111111111';
const DIAMOND_LEFT_ID = 's8branch1111111';
const DIAMOND_RIGHT_ID = 's9branch2111111';
const DIAMOND_SHARED_ID = 'sAshared1111111';

const MULTI_ROOT_ID = 'sHmany111111111';
const MULTI_ONE_ID = 'sJmany211111111';
const MULTI_TWO_ID = 'sKmany311111111';

const CHAIN_ROOT_ID = 'sMchain11111111';
const CHAIN_ONE_ID = 'sNchain21111111';
const CHAIN_TWO_ID = 'sPchain31111111';
const CHAIN_THREE_ID = 'sQchain41111111';
const CHAIN_FOUR_ID = 'sRchain51111111';

const MEMO_ROOT_ID = 'sDmemoroot11111';
const MEMO_MID_ID = 'sEmemomid111111';
const MEMO_LEAF_ID = 'sFmemoend111111';
const MEMO_BROKEN_ID = 'sGmemobroken111';

// Installed as a plugin by the mount helpers below, not merely via setActivePinia, so
// useSubjectStore() resolves inside SubjectTree's own setup().
let activePinia: Pinia;

const nameSchema = newSchema( {
	title: 'Name',
	properties: new PropertyDefinitionList( [] ),
} );

const timeSpanSchema = newSchema( {
	title: 'TimeSpan',
	properties: new PropertyDefinitionList( [] ),
} );

const eventSchema = newSchema( {
	title: 'Event',
	properties: new PropertyDefinitionList( [
		createPropertyDefinitionFromJson( 'Time span', { type: 'relation', targetSchema: 'TimeSpan' } ),
	] ),
} );

// Property order decides DOM order: "Spouse" is a real, childless node at index 0, so the
// Birth node lands at index 1, which is what the index-based assertions below expect.
const personSchema = newSchema( {
	title: 'Person',
	properties: new PropertyDefinitionList( [
		createPropertyDefinitionFromJson( 'Spouse', { type: 'relation', targetSchema: 'Name' } ),
		createPropertyDefinitionFromJson( 'Birth event', { type: 'relation', targetSchema: 'Event' } ),
		createPropertyDefinitionFromJson( 'Death event', { type: 'relation', targetSchema: 'Event' } ),
	] ),
} );

const cycleSchema = newSchema( {
	title: 'CycleSchema',
	properties: new PropertyDefinitionList( [
		createPropertyDefinitionFromJson( 'Colleague', { type: 'relation', targetSchema: 'CycleSchema' } ),
	] ),
} );

const spouseSubject = newSubject( {
	id: SPOUSE_ID,
	label: 'Anna Magdalena Bach',
	schemaName: 'Name',
} );

const timeSpanSubject = newSubject( {
	id: TIMESPAN_ID,
	label: '1685-1750',
	schemaName: 'TimeSpan',
} );

const birthSubject = newSubject( {
	id: BIRTH_ID,
	label: 'Birth of J. S. Bach',
	schemaName: 'Event',
	statements: new StatementList( [
		new Statement(
			new PropertyName( 'Time span' ),
			'relation',
			new RelationValue( [ newRelation( undefined, TIMESPAN_ID ) ] ),
		),
	] ),
} );

const rootSubject = newSubject( {
	id: ROOT_ID,
	label: 'Johann Sebastian Bach',
	schemaName: 'Person',
	statements: new StatementList( [
		new Statement(
			new PropertyName( 'Spouse' ),
			'relation',
			new RelationValue( [ newRelation( undefined, SPOUSE_ID ) ] ),
		),
		new Statement(
			new PropertyName( 'Birth event' ),
			'relation',
			new RelationValue( [ newRelation( undefined, BIRTH_ID ) ] ),
		),
		// "Death event" is declared on the schema and left without a statement here.
	] ),
} );

// `rootSubject` minus every relation statement: a form the user has not filled in yet.
const rootWithoutRelations = newSubject( {
	id: ROOT_ID,
	label: 'Johann Sebastian Bach',
	schemaName: 'Person',
} );

// Only the Spouse relation: what the form holds once one target has been picked.
const rootWithSpouseOnly = newSubject( {
	id: ROOT_ID,
	label: 'Johann Sebastian Bach',
	schemaName: 'Person',
	statements: new StatementList( [
		new Statement(
			new PropertyName( 'Spouse' ),
			'relation',
			new RelationValue( [ newRelation( undefined, SPOUSE_ID ) ] ),
		),
	] ),
} );

// The stored Birth event without its Time span, so only the edited copy carries it.
const birthWithoutTimeSpan = newSubject( {
	id: BIRTH_ID,
	label: 'Birth of J. S. Bach',
	schemaName: 'Event',
} );

// Two Subjects that store no label (ADR 31): the server's derived name is all the tree has.
const labellessSpouse = newSubject( {
	id: SPOUSE_ID,
	label: null,
	displayName: 'Name',
	displayNameIsGenerated: true,
	schemaName: 'Name',
} );

const labellessRoot = newSubject( {
	id: ROOT_ID,
	label: null,
	displayName: 'Bach, Johann Sebastian',
	schemaName: 'Person',
	statements: new StatementList( [
		new Statement(
			new PropertyName( 'Spouse' ),
			'relation',
			new RelationValue( [ newRelation( undefined, SPOUSE_ID ) ] ),
		),
	] ),
} );

const straySubject = newSubject( {
	id: STRAY_ID,
	label: 'Ada Lovelace',
	schemaName: 'Name',
} );

// Nothing to open of its own, so whatever the tree draws under it can only be an unreachable
// pane's row.
const bareRootSubject = newSubject( {
	id: ROOT_ID,
	label: 'Bare root',
	schemaName: 'Name',
} );

// An unreachable pane whose own Subject holds a relation. The tree reaches this Subject from
// nowhere, so it has no path to build its target's row on and cannot draw one.
const strayHoldingRelationSubject = newSubject( {
	id: STRAY_ID,
	label: 'Ada Lovelace',
	schemaName: 'Name',
	statements: new StatementList( [
		new Statement(
			new PropertyName( 'Ghost' ),
			'relation',
			new RelationValue( [ newRelation( undefined, SPOUSE_ID ) ] ),
		),
	] ),
} );

// Holds a relation under a property its Schema does not declare, which is what a Schema edit
// leaves behind. A Schema is what says which Statements are relations to follow, so the tree
// cannot draw this link however much is open — while the target's pane can still be open.
const orphanRootSubject = newSubject( {
	id: ROOT_ID,
	label: 'Orphan root',
	schemaName: 'Name',
	statements: new StatementList( [
		new Statement(
			new PropertyName( 'Ghost' ),
			'relation',
			new RelationValue( [ newRelation( undefined, STRAY_ID ) ] ),
		),
	] ),
} );

const cycleRootSubject = newSubject( {
	id: CYCLE_ROOT_ID,
	label: 'A',
	schemaName: 'CycleSchema',
	statements: new StatementList( [
		new Statement(
			new PropertyName( 'Colleague' ),
			'relation',
			new RelationValue( [ newRelation( undefined, CYCLE_B_ID ) ] ),
		),
	] ),
} );

const cycleBSubject = newSubject( {
	id: CYCLE_B_ID,
	label: 'B',
	schemaName: 'CycleSchema',
	statements: new StatementList( [
		new Statement(
			new PropertyName( 'Colleague' ),
			'relation',
			// Self-loop: B's own "Colleague" relation targets B.
			new RelationValue( [ newRelation( undefined, CYCLE_B_ID ) ] ),
		),
	] ),
} );

// A --Colleague--> B --Colleague--> A: a cycle closing on the ROOT. A guard narrowed to "is
// this target the subject I am expanding" passes the self-loop above but not this.
const twoHopASubject = newSubject( {
	id: TWOHOP_A_ID,
	label: 'A',
	schemaName: 'CycleSchema',
	statements: new StatementList( [
		new Statement(
			new PropertyName( 'Colleague' ),
			'relation',
			new RelationValue( [ newRelation( undefined, TWOHOP_B_ID ) ] ),
		),
	] ),
} );

const twoHopBSubject = newSubject( {
	id: TWOHOP_B_ID,
	label: 'B',
	schemaName: 'CycleSchema',
	statements: new StatementList( [
		new Statement(
			new PropertyName( 'Colleague' ),
			'relation',
			new RelationValue( [ newRelation( undefined, TWOHOP_A_ID ) ] ),
		),
	] ),
} );

// Diamond: root --Left--> branch1 --Shared--> shared, root --Right--> branch2 --Shared--> shared.
// `shared` is reached by two paths and is never its own ancestor, so it is no cycle: both
// occurrences render.
const diamondRootSchema = newSchema( {
	title: 'DiamondRoot',
	properties: new PropertyDefinitionList( [
		createPropertyDefinitionFromJson( 'Left', { type: 'relation', targetSchema: 'DiamondBranch' } ),
		createPropertyDefinitionFromJson( 'Right', { type: 'relation', targetSchema: 'DiamondBranch' } ),
	] ),
} );

const diamondBranchSchema = newSchema( {
	title: 'DiamondBranch',
	properties: new PropertyDefinitionList( [
		createPropertyDefinitionFromJson( 'Shared', { type: 'relation', targetSchema: 'DiamondShared' } ),
	] ),
} );

// Three relation properties, all left empty, so the fixture covers a Subject whose declared
// relations contribute nothing to the tree.
const diamondSharedSchema = newSchema( {
	title: 'DiamondShared',
	properties: new PropertyDefinitionList( [
		createPropertyDefinitionFromJson( 'Alpha', { type: 'relation', targetSchema: 'DiamondLeaf' } ),
		createPropertyDefinitionFromJson( 'Beta', { type: 'relation', targetSchema: 'DiamondLeaf' } ),
		createPropertyDefinitionFromJson( 'Gamma', { type: 'relation', targetSchema: 'DiamondLeaf' } ),
	] ),
} );

const diamondRootSubject = newSubject( {
	id: DIAMOND_ROOT_ID,
	label: 'Diamond root',
	schemaName: 'DiamondRoot',
	statements: new StatementList( [
		new Statement(
			new PropertyName( 'Left' ),
			'relation',
			new RelationValue( [ newRelation( undefined, DIAMOND_LEFT_ID ) ] ),
		),
		new Statement(
			new PropertyName( 'Right' ),
			'relation',
			new RelationValue( [ newRelation( undefined, DIAMOND_RIGHT_ID ) ] ),
		),
	] ),
} );

const diamondLeftSubject = newSubject( {
	id: DIAMOND_LEFT_ID,
	label: 'Left branch',
	schemaName: 'DiamondBranch',
	statements: new StatementList( [
		new Statement(
			new PropertyName( 'Shared' ),
			'relation',
			new RelationValue( [ newRelation( undefined, DIAMOND_SHARED_ID ) ] ),
		),
	] ),
} );

const diamondRightSubject = newSubject( {
	id: DIAMOND_RIGHT_ID,
	label: 'Right branch',
	schemaName: 'DiamondBranch',
	statements: new StatementList( [
		new Statement(
			new PropertyName( 'Shared' ),
			'relation',
			new RelationValue( [ newRelation( undefined, DIAMOND_SHARED_ID ) ] ),
		),
	] ),
} );

const diamondSharedSubject = newSubject( {
	id: DIAMOND_SHARED_ID,
	label: 'Shared',
	schemaName: 'DiamondShared',
} );

// root --Chain--> mid --Leaf--> end resolves over several async waves; root --Broken--> names a
// target whose schema ("MemoMissing") is never registered. The healthy chain's waves are what
// would re-trigger the broken branch's failing fetch if failures were not memoised.
const memoRootSchema = newSchema( {
	title: 'MemoRoot',
	properties: new PropertyDefinitionList( [
		createPropertyDefinitionFromJson( 'Chain', { type: 'relation', targetSchema: 'MemoMid' } ),
		createPropertyDefinitionFromJson( 'Broken', { type: 'relation', targetSchema: 'MemoMissing' } ),
	] ),
} );

const memoMidSchema = newSchema( {
	title: 'MemoMid',
	properties: new PropertyDefinitionList( [
		createPropertyDefinitionFromJson( 'Leaf', { type: 'relation', targetSchema: 'MemoEnd' } ),
	] ),
} );

const memoEndSchema = newSchema( {
	title: 'MemoEnd',
	properties: new PropertyDefinitionList( [] ),
} );

const memoRootSubject = newSubject( {
	id: MEMO_ROOT_ID,
	label: 'Memo root',
	schemaName: 'MemoRoot',
	statements: new StatementList( [
		new Statement(
			new PropertyName( 'Chain' ),
			'relation',
			new RelationValue( [ newRelation( undefined, MEMO_MID_ID ) ] ),
		),
		new Statement(
			new PropertyName( 'Broken' ),
			'relation',
			new RelationValue( [ newRelation( undefined, MEMO_BROKEN_ID ) ] ),
		),
	] ),
} );

const memoMidSubject = newSubject( {
	id: MEMO_MID_ID,
	label: 'Memo mid',
	schemaName: 'MemoMid',
	statements: new StatementList( [
		new Statement(
			new PropertyName( 'Leaf' ),
			'relation',
			new RelationValue( [ newRelation( undefined, MEMO_LEAF_ID ) ] ),
		),
	] ),
} );

const memoEndSubject = newSubject( {
	id: MEMO_LEAF_ID,
	label: 'Memo end',
	schemaName: 'MemoEnd',
} );

// The Subject resolves; its declared schema ("MemoMissing") is what never does.
// Holding a relation, so the walk needs the Schema it names: without one it is a leaf, and a
// leaf's Schema is never asked for.
const memoBrokenTargetSubject = newSubject( {
	id: MEMO_BROKEN_ID,
	label: 'Memo broken target',
	schemaName: 'MemoMissing',
	statements: new StatementList( [
		new Statement(
			new PropertyName( 'Next' ),
			'relation',
			new RelationValue( [ newRelation( undefined, MEMO_LEAF_ID ) ] ),
		),
	] ),
} );

// One relation property holding two targets, so its name is printed once above the pair.
const multiSchema = newSchema( {
	title: 'Multi',
	properties: new PropertyDefinitionList( [
		createPropertyDefinitionFromJson( 'Sibling', { type: 'relation', targetSchema: 'Name' } ),
	] ),
} );

const multiRootSubject = newSubject( {
	id: MULTI_ROOT_ID,
	label: 'Multi root',
	schemaName: 'Multi',
	statements: new StatementList( [
		new Statement(
			new PropertyName( 'Sibling' ),
			'relation',
			new RelationValue( [
				newRelation( undefined, MULTI_ONE_ID ),
				newRelation( undefined, MULTI_TWO_ID ),
			] ),
		),
	] ),
} );

const multiOneSubject = newSubject( {
	id: MULTI_ONE_ID,
	label: 'First sibling',
	schemaName: 'Name',
} );

const multiTwoSubject = newSubject( {
	id: MULTI_TWO_ID,
	label: 'Second sibling',
	schemaName: 'Name',
} );

// An unbranched chain five subjects long, so a level always sits unopened below the deepest one
// a test opens: whatever the tree stops at, it stopped there because nobody asked for more. Every
// link resolves and none repeats, so neither a failed fetch nor the visited set can be what ends
// the walk.
const chainSchema = newSchema( {
	title: 'Chain',
	properties: new PropertyDefinitionList( [
		createPropertyDefinitionFromJson( 'Next', { type: 'relation', targetSchema: 'Chain' } ),
	] ),
} );

function newChainLink( id: string, label: string, nextId?: string ): Subject {
	return newSubject( {
		id,
		label,
		schemaName: 'Chain',
		statements: new StatementList(
			nextId === undefined ?
				[] :
				[ new Statement(
					new PropertyName( 'Next' ),
					'relation',
					new RelationValue( [ newRelation( undefined, nextId ) ] ),
				) ],
		),
	} );
}

const chainRootSubject = newChainLink( CHAIN_ROOT_ID, 'Chain root', CHAIN_ONE_ID );
const chainOneSubject = newChainLink( CHAIN_ONE_ID, 'Depth 1', CHAIN_TWO_ID );
const chainTwoSubject = newChainLink( CHAIN_TWO_ID, 'Depth 2', CHAIN_THREE_ID );
const chainThreeSubject = newChainLink( CHAIN_THREE_ID, 'Depth 3', CHAIN_FOUR_ID );
// Reachable from Depth 3 and resolvable, so nothing but an unopened parent keeps it out.
const chainFourSubject = newChainLink( CHAIN_FOUR_ID, 'Depth 4' );

interface MountOverrides {
	unsavedIds?: string[];
	openIds?: string[];
	activeId?: string;
	editedSubjects?: Map<string, Subject>;
}

function mountWithServices(
	rootSubjectProp: Subject,
	rootSchemaProp: Schema,
	schemas: Schema[],
	seedSubjects: Subject[],
	overrides: MountOverrides = {},
): VueWrapper {
	const subjectStore = useSubjectStore();
	for ( const subject of seedSubjects ) {
		subjectStore.setSubject( subject );
	}

	const services = NeoWikiTestServices.getServices();
	services[ Service.SchemaRepository ] = new InMemorySchemaRepository( schemas );

	return mount( SubjectTree, {
		props: {
			rootSubject: rootSubjectProp,
			rootSchema: rootSchemaProp,
			openIds: overrides.openIds ?? [ rootSubjectProp.getId().text ],
			activeId: overrides.activeId ?? rootSubjectProp.getId().text,
			unsavedIds: overrides.unsavedIds ?? [],
			editedSubjects: overrides.editedSubjects ?? new Map<string, Subject>(),
		},
		global: {
			plugins: [ activePinia ],
			provide: services,
			mocks: {
				$i18n: createI18nMock(),
			},
		},
	} );
}

function mountTree( overrides: MountOverrides = {} ): VueWrapper {
	return mountWithServices(
		rootSubject,
		personSchema,
		[ nameSchema, eventSchema, timeSpanSchema, personSchema ],
		[ spouseSubject, birthSubject, timeSpanSubject ],
		overrides,
	);
}

function mountTreeWithCycle( overrides: MountOverrides = {} ): VueWrapper {
	return mountWithServices(
		cycleRootSubject,
		cycleSchema,
		[ cycleSchema ],
		[ cycleBSubject ],
		overrides,
	);
}

function mountTreeWithTwoHopCycle( overrides: MountOverrides = {} ): VueWrapper {
	return mountWithServices(
		twoHopASubject,
		cycleSchema,
		[ cycleSchema ],
		// The root is seeded too: B's "Colleague" targets A, and A must resolve, or a failed fetch
		// rather than the visited set could be what stops the walk.
		[ twoHopASubject, twoHopBSubject ],
		overrides,
	);
}

function mountTreeWithTwoTargets(): VueWrapper {
	return mountWithServices(
		multiRootSubject,
		multiSchema,
		[ multiSchema, nameSchema ],
		[ multiOneSubject, multiTwoSubject ],
	);
}

function mountDeepChain( overrides: MountOverrides = {} ): VueWrapper {
	return mountWithServices(
		chainRootSubject,
		chainSchema,
		[ chainSchema ],
		[ chainOneSubject, chainTwoSubject, chainThreeSubject, chainFourSubject ],
		overrides,
	);
}

function mountDiamond( overrides: MountOverrides = {} ): VueWrapper {
	return mountWithServices(
		diamondRootSubject,
		diamondRootSchema,
		[ diamondRootSchema, diamondBranchSchema, diamondSharedSchema ],
		[ diamondLeftSubject, diamondRightSubject, diamondSharedSubject ],
		overrides,
	);
}

describe( 'SubjectTree', () => {
	beforeEach( () => {
		activePinia = createPinia();
		setActivePinia( activePinia );
		setupMwMock( {
			// `util` is stubbed although the tree's badge is unlinked and so never reaches
			// mw.util.getUrl: without it, linking the badge fails as a TypeError in all 40
			// tests here instead of in the one that checks it is unlinked.
			functions: [ 'message', 'msg', 'util' ],
			messages: {
				'neowiki-subject-tree-not-linked': 'Not linked here',
			},
		} );
	} );

	// The tree minus its own root node. Positions in this list are what the index-based
	// assertions throughout this file refer to.
	function targetNodes( wrapper: VueWrapper ): DOMWrapper<Element>[] {
		return wrapper.findAll( '.ext-neowiki-tree__group .ext-neowiki-tree__node' );
	}

	function targetNodeLabels( wrapper: VueWrapper ): string[] {
		return targetNodes( wrapper ).map( ( n ) => n.get( '.ext-neowiki-tree__node-label' ).text() );
	}

	function rootTreeNode( wrapper: VueWrapper ): Omit<DOMWrapper<Element>, 'exists'> {
		return wrapper.get( '.ext-neowiki-tree__list > .ext-neowiki-tree__node' );
	}

	// A node's own <li> spans its whole rendered subtree, so the clickable part is this row.
	function nameRow( node: Omit<DOMWrapper<Element>, 'exists'> ): Omit<DOMWrapper<Element>, 'exists'> {
		return node.get( '.ext-neowiki-tree__node-name' );
	}

	// The kind of each element in a row, in order: the base class alone, since a modifier on
	// one of them says what state it is in rather than what it is.
	function markOn( wrapper: VueWrapper, subjectId: string ): string | undefined {
		return wrapper
			.get( `[data-mw-neowiki-subject-id="${ subjectId }"] .ext-neowiki-tree__mark` )
			.attributes( 'data-mw-neowiki-tree-mark' );
	}

	// Codex renders an icon's label as the svg's accessible name, so this reads what a screen
	// reader would, rather than the class the other assertions go by.
	function markName( mark: Omit<DOMWrapper<Element>, 'exists'> ): string {
		return mark.get( 'svg title' ).text();
	}

	function countOn( wrapper: VueWrapper, subjectId: string ): string {
		return wrapper
			.get( `[data-mw-neowiki-subject-id="${ subjectId }"] .ext-neowiki-tree__count` )
			.text();
	}

	// Presses the disclosure control on the row holding this Subject, which is how a reader
	// reaches a level the rule has not opened for them.
	async function open( wrapper: VueWrapper, subjectId: string ): Promise<void> {
		await wrapper
			.get( `[data-mw-neowiki-subject-id="${ subjectId }"] .ext-neowiki-tree__twisty` )
			.trigger( 'click' );
		await flushPromises();
	}

	function childClassNames( row: Omit<DOMWrapper<Element>, 'exists'> ): string[] {
		return [ ...row.element.children ].map( ( child ) => child.className.split( ' ' )[ 0 ] );
	}

	// A relation is named on its own caption line when it heads several rows and on the row
	// itself when it heads one. These tests care that it is named, not which of the two.
	function relationNames( wrapper: VueWrapper ): string[] {
		return wrapper.findAll( '.ext-neowiki-tree__edge, .ext-neowiki-tree__node-caption' )
			.map( ( named ) => named.text() );
	}

	it( 'renders a node per relation target, labelled with the relation property', async () => {
		// person --Birth event--> birth --Time span--> timespan
		const wrapper = mountTree();
		await flushPromises();

		expect( targetNodeLabels( wrapper ) ).toContain( 'Birth of J. S. Bach' );
		expect( relationNames( wrapper ) ).toContain( 'Birth event' );
	} );

	it( 'names a relation once per group, not once per sibling node', async () => {
		const wrapper = mountTreeWithTwoTargets();
		await flushPromises();

		expect( targetNodes( wrapper ).length ).toBe( 2 );

		const siblingLabels = wrapper.findAll( '.ext-neowiki-tree__edge' )
			.map( ( n ) => n.text() )
			.filter( ( text ) => text === 'Sibling' );
		expect( siblingLabels.length ).toBe( 1 );
		// Named on the caption line or on the rows, never on both.
		expect( wrapper.findAll( '.ext-neowiki-tree__node-caption' ) ).toHaveLength( 0 );
	} );

	// A declared relation earns a place in the navigator only once data fills it.
	it( 'gives a declared relation with no target neither a node nor a group caption', async () => {
		// Time span sits a level down, so the Subject being edited has to be down there for the
		// tree to draw it at all.
		const wrapper = mountTree( { activeId: TIMESPAN_ID } );
		await flushPromises();

		expect( relationNames( wrapper ) ).toEqual( [ 'Spouse', 'Birth event', 'Time span' ] );
		expect( targetNodes( wrapper ) ).toHaveLength( 3 );
	} );

	// The store is seeded with the targets the filled fixture uses, so reaching one could only
	// come from a relation statement rather than from what happens to be resolvable.
	it( 'renders the root alone for a subject whose declared relations are all empty', async () => {
		const wrapper = mountWithServices(
			rootWithoutRelations,
			personSchema,
			[ nameSchema, eventSchema, timeSpanSchema, personSchema ],
			[ spouseSubject, birthSubject, timeSpanSubject ],
		);
		await flushPromises();

		expect( wrapper.findAll( '[role="treeitem"]' ) ).toHaveLength( 1 );
		expect( rootTreeNode( wrapper ).attributes( 'data-mw-neowiki-subject-id' ) ).toBe( ROOT_ID );
		expect( wrapper.findAll( '[role="group"]' ) ).toHaveLength( 0 );
		expect( wrapper.findAll( '.ext-neowiki-tree__edge' ) ).toHaveLength( 0 );
	} );

	it( 'emits select with the subject id of a clicked node', async () => {
		const wrapper = mountTree();
		await flushPromises();

		await nameRow( targetNodes( wrapper )[ 1 ] ).trigger( 'click' );

		const emitted = wrapper.emitted( 'select' );
		expect( ( emitted![ 0 ][ 0 ] as SubjectId ).text ).toBe( BIRTH_ID );
	} );

	// Naming from the stored label alone leaves a blank row; falling back to the id prints an
	// ADR 14 identifier at the user.
	it( 'names a label-less target by its marked display name', async () => {
		const wrapper = mountWithServices(
			rootSubject,
			personSchema,
			[ nameSchema, eventSchema, timeSpanSchema, personSchema ],
			[ labellessSpouse, birthSubject, timeSpanSubject ],
		);
		await flushPromises();

		expect( targetNodeLabels( wrapper ) ).toContain( '(unnamed Name)' );
		expect( targetNodeLabels( wrapper ) ).not.toContain( SPOUSE_ID );
	} );

	// A label-less child is shown as "(unnamed Name)", the Schema in the name, ahead of which
	// the row prints the relation that reaches it.
	it( 'names a label-less target by its relation and its marked name', async () => {
		const wrapper = mountWithServices(
			rootSubject,
			personSchema,
			[ nameSchema, eventSchema, timeSpanSchema, personSchema ],
			[ labellessSpouse, birthSubject, timeSpanSubject ],
		);
		await flushPromises();

		expect( nameRow( wrapper.get( `[data-mw-neowiki-subject-id="${ SPOUSE_ID }"]` ) ).text() ).toBe( 'Spouse(unnamed Name)' );
	} );

	// The only way back to the root once a relation target is being edited.
	describe( 'Root node', () => {
		it( 'renders the root subject as the tree\'s first node', async () => {
			const wrapper = mountTree();
			await flushPromises();

			const root = rootTreeNode( wrapper );
			expect( root.attributes( 'data-mw-neowiki-subject-id' ) ).toBe( ROOT_ID );
			expect( root.get( '.ext-neowiki-tree__node-label' ).text() )
				.toBe( 'Johann Sebastian Bach' );
			expect( root.attributes( 'role' ) ).toBe( 'treeitem' );
		} );

		// The browser computes each node's level from that containment, not from its position.
		it( 'nests the relation targets inside the root node', async () => {
			const wrapper = mountTree();
			await flushPromises();

			const root = rootTreeNode( wrapper );
			expect( root.find( `[data-mw-neowiki-subject-id="${ SPOUSE_ID }"]` ).exists() ).toBe( true );
			expect( root.find( `[data-mw-neowiki-subject-id="${ BIRTH_ID }"]` ).exists() ).toBe( true );
		} );

		it( 'marks the root node as selected while the root subject is the one being edited', async () => {
			const wrapper = mountTree( { activeId: ROOT_ID } );
			await flushPromises();

			expect( rootTreeNode( wrapper ).attributes( 'aria-selected' ) ).toBe( 'true' );
		} );

		it( 'unmarks the root node while a relation target is being edited', async () => {
			const wrapper = mountTree( { activeId: TIMESPAN_ID } );
			await flushPromises();

			expect( rootTreeNode( wrapper ).attributes( 'aria-selected' ) ).toBe( 'false' );
		} );

		it( 'emits select for the root node', async () => {
			const wrapper = mountTree( { activeId: TIMESPAN_ID } );
			await flushPromises();

			await nameRow( rootTreeNode( wrapper ) ).trigger( 'click' );

			const emitted = wrapper.emitted( 'select' );
			expect( emitted ).toHaveLength( 1 );
			expect( ( emitted![ 0 ][ 0 ] as SubjectId ).text ).toBe( ROOT_ID );
		} );

		it( 'shows the unsaved dot on the root node', async () => {
			const wrapper = mountTree( { unsavedIds: [ ROOT_ID ] } );
			await flushPromises();

			expect( rootTreeNode( wrapper ).get( `#${ rootTreeNode( wrapper ).attributes( 'id' ) }-name` )
				.find( '.ext-neowiki-unsaved-dot' ).exists() ).toBe( true );
		} );

		it( 'names a label-less root by its display name', async () => {
			const wrapper = mountWithServices(
				labellessRoot,
				personSchema,
				[ nameSchema, personSchema ],
				[ spouseSubject ],
			);
			await flushPromises();

			expect( rootTreeNode( wrapper ).get( '.ext-neowiki-tree__node-label' ).text() )
				.toBe( 'Bach, Johann Sebastian' );
		} );

		// Both halves of the node come from the editor's copy: its name, so a rename in the form
		// reaches the navigator, and the walk beneath it, so a target picked there has a node.
		it( 'labels the root node from the edited copy when there is one', async () => {
			const wrapper = mountWithServices(
				rootWithoutRelations,
				personSchema,
				[ nameSchema, eventSchema, timeSpanSchema, personSchema ],
				[ spouseSubject ],
				{ editedSubjects: new Map( [ [ ROOT_ID, rootWithSpouseOnly.withLabel( 'Renamed in the form' ) ] ] ) },
			);
			await flushPromises();

			expect( rootTreeNode( wrapper ).get( '.ext-neowiki-tree__node-label' ).text() )
				.toBe( 'Renamed in the form' );
			expect( rootTreeNode( wrapper ).find( `[data-mw-neowiki-subject-id="${ SPOUSE_ID }"]` ).exists() )
				.toBe( true );
		} );
	} );

	describe( 'Tree semantics', () => {
		// Fixture order (see mountTree): Spouse (depth 1), Birth event (depth 1), Time span
		// (depth 2, under Birth event). The root node precedes them and is excluded here.

		// NeoTree takes plain text and resolves no messages, so the name is this component's
		// to supply.
		it( 'names the navigation landmark the tree sits in', async () => {
			const wrapper = mountTree();
			await flushPromises();

			expect( wrapper.get( 'nav.ext-neowiki-subject-tree' ).attributes( 'aria-label' ) )
				.toBe( 'neowiki-subject-tree-label' );
		} );

		it( 'nests a relation target inside the node whose relation names it', async () => {
			const wrapper = mountTree( { activeId: TIMESPAN_ID } );
			await flushPromises();

			const birthNode = wrapper.find( `[data-mw-neowiki-subject-id="${ BIRTH_ID }"]` );
			expect( birthNode.find( `[data-mw-neowiki-subject-id="${ TIMESPAN_ID }"]` ).exists() ).toBe( true );
		} );

		// The other way to be a leaf: DiamondShared declares three relation properties and fills
		// none, where Spouse's Schema declares none at all. Left branch, which fills one, is the
		// contrast.
		it( 'announces a node whose declared relations are all empty as a leaf too', async () => {
			const wrapper = mountDiamond( { activeId: DIAMOND_SHARED_ID } );
			await flushPromises();

			const shared = wrapper.findAll( `[data-mw-neowiki-subject-id="${ DIAMOND_SHARED_ID }"]` )[ 0 ];
			expect( shared.attributes( 'aria-expanded' ) ).toBeUndefined();

			const left = wrapper.get( `[data-mw-neowiki-subject-id="${ DIAMOND_LEFT_ID }"]` );
			expect( left.attributes( 'aria-expanded' ) ).toBe( 'true' );
		} );

		// These enumerate what a row may hold, and read the row's whole text besides, so anything
		// printed beside the spans is caught. The root is reached by no relation, so it has no
		// caption, and it never closes, so it has no control.
		it( 'renders nothing in the root\'s row but its name', async () => {
			const wrapper = mountTree();
			await flushPromises();

			const root = nameRow( rootTreeNode( wrapper ) );

			expect( childClassNames( root ) ).toEqual( [ 'ext-neowiki-tree__node-line' ] );
			expect( root.text() ).toBe( 'Johann Sebastian Bach' );
		} );

		it( 'renders nothing in a child\'s row but its control, its relation and its name', async () => {
			const wrapper = mountTree( { activeId: TIMESPAN_ID } );
			await flushPromises();

			const rows = targetNodes( wrapper ).map( ( node ) => nameRow( node ) );
			expect( rows.length ).toBe( 3 );

			// A control is drawn only where there is something to open, and nothing is reserved
			// where there is not, so it is the one optional member. Asserted from both sides
			// below, or "optional" would also cover a control gone missing everywhere.
			for ( const row of rows ) {
				expect( childClassNames( row ).filter(
					( name ) => name !== 'ext-neowiki-tree__twisty',
				) ).toEqual( [
					'ext-neowiki-tree__node-caption',
					'ext-neowiki-tree__node-line',
				] );
				// The control carries an icon and never text, so it adds nothing to the name.
				expect( row.text() ).toBe(
					row.get( '.ext-neowiki-tree__node-caption' ).text() +
					row.get( '.ext-neowiki-tree__node-label' ).text(),
				);
			}

			// Birth event holds a Time span and is open; Spouse and the Time span hold nothing.
			expect( rows.map( ( row ) => row.find( '.ext-neowiki-tree__twisty' ).exists() ) )
				.toEqual( [ false, true, false ] );
		} );

		it( 'marks the active node as selected', async () => {
			const wrapper = mountTree( { activeId: TIMESPAN_ID } );
			await flushPromises();

			const nodes = targetNodes( wrapper );
			expect( nodes[ 2 ].attributes( 'aria-selected' ) ).toBe( 'true' );
			expect( nodes[ 0 ].attributes( 'aria-selected' ) ).toBe( 'false' );
		} );

		// Widget behaviour asserted through this component: NeoTree's own Home test presses it
		// while the root is active, where focus falls back to the root anyway, so a Home that
		// moved no key would still look right. Here only a moved key passes.
		it( 'Home moves the roving key, not just DOM focus, while a deep node is active', async () => {
			const wrapper = mountTree( { activeId: TIMESPAN_ID } );
			await flushPromises();

			await targetNodes( wrapper )[ 2 ].trigger( 'keydown', { key: 'Home' } );

			expect( rootTreeNode( wrapper ).attributes( 'tabindex' ) ).toBe( '0' );
			expect( targetNodes( wrapper )[ 2 ].attributes( 'tabindex' ) ).toBe( '-1' );
		} );
	} );

	// What bounds the tree is how far down the Subject being edited sits, plus whatever the reader
	// opened, and nothing else. Asserted from both sides: the chain runs one hop past the deepest
	// level opened here, and that hop is not drawn.
	it( 'walks down as far as the reader has opened, and no further', async () => {
		const wrapper = mountDeepChain();
		await flushPromises();

		await open( wrapper, CHAIN_ONE_ID );
		await open( wrapper, CHAIN_TWO_ID );

		// The chain runs one hop past Depth 3, and that hop is not drawn: nothing but a gesture
		// adds a level, so there is no depth at which the tree stops of its own accord.
		expect( targetNodeLabels( wrapper ) ).toEqual( [ 'Depth 1', 'Depth 2', 'Depth 3' ] );
	} );

	// A row closed by hand outranks the rule, until the reader moves to a Subject beneath it —
	// which would otherwise have no row anywhere, being neither drawn nor unreachable. The step
	// away is required as well as realistic: a watcher fires on a change, not on a re-set.
	it( 'reopens a row closed by hand once the subject being edited moves beneath it', async () => {
		const wrapper = mountTree( {
			activeId: TIMESPAN_ID,
			openIds: [ ROOT_ID, BIRTH_ID, TIMESPAN_ID ],
		} );
		await flushPromises();

		await open( wrapper, BIRTH_ID );
		expect( wrapper.find( `[data-mw-neowiki-subject-id="${ TIMESPAN_ID }"]` ).exists() )
			.toBe( false );

		await wrapper.setProps( { activeId: SPOUSE_ID } );
		await flushPromises();
		await wrapper.setProps( { activeId: TIMESPAN_ID } );
		await flushPromises();

		expect( wrapper.find( `[data-mw-neowiki-subject-id="${ TIMESPAN_ID }"]` ).exists() )
			.toBe( true );
	} );

	// The other half of the settled vocabulary: a control on what opens, a mark on what does not.
	// Four situations drew one row before any of this, so each needs its own.
	describe( 'what a row says about what it is not showing', () => {

		it( 'puts the number of subjects behind a closed row on it', async () => {
			const wrapper = mountTree();
			await flushPromises();

			// The Birth event holds one relation, its Time span, and is closed.
			expect( countOn( wrapper, BIRTH_ID ) ).toBe( '1' );
		} );

		// The figure sits inside the name the treeitem takes from its content, so on its own it
		// would be read out as a bare number. The wording is what is asserted here, rather than
		// the marker attribute the other tests read, because the wording is what a reader gets.
		it( 'says in words what the number counts', async () => {
			const wrapper = mountTree();
			await flushPromises();

			const count = wrapper.get( `[data-mw-neowiki-subject-id="${ BIRTH_ID }"] .ext-neowiki-tree__count` );
			expect( count.attributes( 'role' ) ).toBe( 'img' );
			expect( count.attributes( 'aria-label' ) ).toBe( 'neowiki-subject-tree-related-count1' );
			expect( count.attributes( 'title' ) ).toBe( 'neowiki-subject-tree-related-count1' );
		} );

		it( 'drops the number once the row is open', async () => {
			const wrapper = mountTree();
			await flushPromises();

			await open( wrapper, BIRTH_ID );

			expect( wrapper.get( `[data-mw-neowiki-subject-id="${ BIRTH_ID }"]` )
				.find( '.ext-neowiki-tree__count' ).exists() ).toBe( false );
		} );

		it( 'says nothing on a row with nothing behind it', async () => {
			const wrapper = mountTree();
			await flushPromises();

			const spouse = wrapper.get( `[data-mw-neowiki-subject-id="${ SPOUSE_ID }"]` );
			expect( spouse.find( '.ext-neowiki-tree__count' ).exists() ).toBe( false );
			expect( spouse.find( '.ext-neowiki-tree__mark' ).exists() ).toBe( false );
		} );

		it( 'marks a row that repeats one higher up the branch', async () => {
			const wrapper = mountTreeWithCycle( { activeId: CYCLE_B_ID } );
			await flushPromises();

			// B's own Colleague relation targets B, so the second B closes the branch.
			const both = wrapper.findAll( `[data-mw-neowiki-subject-id="${ CYCLE_B_ID }"]` );
			expect( both ).toHaveLength( 2 );
			const mark = both[ 1 ].get( '.ext-neowiki-tree__mark' );
			expect( mark.attributes( 'data-mw-neowiki-tree-mark' ) ).toBe( 'repeated' );
			// What a reader is actually told, which the marker attribute does not pin: the two
			// marks could otherwise swap their wording and their icons with every test green.
			expect( markName( mark ) ).toBe( 'neowiki-subject-tree-repeated' );
		} );

		// The state that made this worth splitting out: before it, a row drew the warning for the
		// length of every request, so every expansion flashed one on the row pressed and on the
		// row it revealed. Asserted before `flushPromises`, which is the window itself.
		it( 'says nothing on a row whose Subject has not arrived yet', () => {
			const wrapper = mountWithServices(
				rootSubject,
				personSchema,
				[ nameSchema, eventSchema, timeSpanSchema, personSchema ],
				[],
			);

			const row = wrapper.get( `[data-mw-neowiki-subject-id="${ SPOUSE_ID }"]` );
			expect( row.find( '.ext-neowiki-tree__mark' ).exists() ).toBe( false );
			expect( row.find( '.ext-neowiki-tree__count' ).exists() ).toBe( false );
		} );

		// And the control does not come and go around that window: a row waiting on what it needs
		// keeps the one the reader just pressed.
		it( 'keeps a control on a row that is still waiting', async () => {
			const wrapper = mountTree();
			await flushPromises();

			await open( wrapper, BIRTH_ID );

			expect( wrapper.get( `[data-mw-neowiki-subject-id="${ BIRTH_ID }"]` )
				.find( '.ext-neowiki-tree__twisty' ).exists() ).toBe( true );
		} );

		// One tick in, the fetch is still in flight — the state every expansion passes through.
		// Nobody opened this row and nothing is under it, and since the walk reads the unresolved
		// arm before the closed one, a close recorded here is never looked at: announcing it open
		// is what makes Left a dead press rather than a way out of the branch.
		it( 'does not report a row whose Subject is still in flight as expanded', async () => {
			const wrapper = mountWithServices(
				rootSubject,
				personSchema,
				[ nameSchema, eventSchema, timeSpanSchema, personSchema ],
				[],
			);
			await nextTick();

			const spouse = wrapper.get( `[data-mw-neowiki-subject-id="${ SPOUSE_ID }"]` );
			// No mark yet, which is what says the fetch has not failed: this is the loading row.
			expect( spouse.find( '.ext-neowiki-tree__mark' ).exists() ).toBe( false );
			expect( spouse.attributes( 'aria-expanded' ) ).toBe( 'false' );
			expect( spouse.attributes( 'aria-busy' ) ).toBe( 'true' );

			await spouse.trigger( 'keydown', { key: 'ArrowLeft' } );

			expect( rootTreeNode( wrapper ).attributes( 'tabindex' ) ).toBe( '0' );
		} );

		it( 'marks a row whose Subject it could not read', async () => {
			// Nothing seeded, so the Spouse target never resolves and the row keeps its raw id.
			const wrapper = mountWithServices(
				rootSubject,
				personSchema,
				[ nameSchema, eventSchema, timeSpanSchema, personSchema ],
				[],
			);
			await flushPromises();

			expect( markOn( wrapper, SPOUSE_ID ) ).toBe( 'unreadable' );
		} );

		// The mark says the row cannot be read; a control beside it would offer to open what could
		// not be read, and go on offering however often it was pressed.
		it( 'gives a row it could not read no control either', async () => {
			const wrapper = mountWithServices(
				rootSubject,
				personSchema,
				[ nameSchema, eventSchema, timeSpanSchema, personSchema ],
				[],
			);
			await flushPromises();

			const spouse = wrapper.get( `[data-mw-neowiki-subject-id="${ SPOUSE_ID }"]` );
			expect( markOn( wrapper, SPOUSE_ID ) ).toBe( 'unreadable' );
			expect( spouse.find( '.ext-neowiki-tree__twisty' ).exists() ).toBe( false );
			expect( spouse.attributes( 'aria-expanded' ) ).toBeUndefined();
		} );

		it( 'gives a row it cannot open no disclosure control', async () => {
			const wrapper = mountTreeWithCycle( { activeId: CYCLE_B_ID } );
			await flushPromises();

			const repeat = wrapper.findAll( `[data-mw-neowiki-subject-id="${ CYCLE_B_ID }"]` )[ 1 ];
			// Not `false` either: a tree pattern end node carries no aria-expanded at all, or it
			// promises children that pressing will never produce.
			expect( repeat.attributes( 'aria-expanded' ) ).toBeUndefined();
			expect( repeat.find( '.ext-neowiki-tree__twisty' ).exists() ).toBe( false );
		} );

	} );

	it( 'draws the root\'s own relations and no more until something is opened', async () => {
		const wrapper = mountDeepChain();
		await flushPromises();

		expect( targetNodeLabels( wrapper ) ).toEqual( [ 'Depth 1' ] );
	} );

	it( 'terminates on a cycle instead of recursing', async () => {
		const wrapper = mountTreeWithCycle( { activeId: CYCLE_B_ID } );
		await flushPromises();

		// B's "Colleague" targets itself, so depth 1 renders B and depth 2 renders the closing
		// second B. Exactly 2: 1 would mean the repeat visit was dropped rather than rendered
		// once per path, 3+ that the walk recursed through the repeat instead of stopping on it.
		expect( targetNodes( wrapper ).length ).toBe( 2 );
	} );

	it( 'terminates a two-hop cycle that closes back on the root, not just a direct self-loop', async () => {
		const wrapper = mountTreeWithTwoHopCycle( { activeId: TWOHOP_B_ID } );
		await flushPromises();

		// Two hops rather than a self-loop, so it is the path's visited set closing the cycle and
		// not a target that repeats immediately.
		expect( targetNodes( wrapper ).length ).toBe( 2 );
	} );

	// A node contains its whole subtree, so an unscoped find() inside one reaches its
	// descendants' dots: scoped to the row the accessible name is built from.
	function hasOwnUnsavedDot( wrapper: VueWrapper, subjectId: string ): boolean {
		const node = wrapper.get( `[data-mw-neowiki-subject-id="${ subjectId }"]` );
		const row = wrapper.get( `#${ node.attributes( 'id' ) }-name` );
		return row.find( '.ext-neowiki-unsaved-dot' ).exists();
	}

	it( 'shows the unsaved dot only on the subject with pending changes', async () => {
		const wrapper = mountTree( { unsavedIds: [ BIRTH_ID ] } );
		await flushPromises();

		expect( hasOwnUnsavedDot( wrapper, BIRTH_ID ) ).toBe( true );
		expect( hasOwnUnsavedDot( wrapper, SPOUSE_ID ) ).toBe( false );
	} );

	// The dot sits inside the element a treeitem takes its accessible name from, so without a
	// name of its own an edited subject announces exactly as a saved sibling does.
	it( 'gives the unsaved dot accessible text of its own', async () => {
		const wrapper = mountTree( { unsavedIds: [ BIRTH_ID ] } );
		await flushPromises();

		const dot = wrapper.get( '.ext-neowiki-unsaved-dot' );
		expect( dot.attributes( 'role' ) ).toBe( 'img' );
		expect( dot.attributes( 'aria-label' ) ).toBe( 'neowiki-subject-editor-unsaved' );
	} );

	// Time span sits inside Birth event's own treeitem.
	it( 'does not show an unsaved dot on a saved node whose descendant is unsaved', async () => {
		const wrapper = mountTree( { unsavedIds: [ TIMESPAN_ID ], activeId: TIMESPAN_ID } );
		await flushPromises();

		expect( hasOwnUnsavedDot( wrapper, TIMESPAN_ID ) ).toBe( true );
		expect( hasOwnUnsavedDot( wrapper, BIRTH_ID ) ).toBe( false );
	} );

	// The demo wiki has this shape: ACME's Amsterdam HQ is both an office and the headquarters.
	// The two occurrences live in different v-for lists, so Vue never warns about a collision and
	// no attribute carries a key — the only place the identity shows is Vue's own node key.
	it( 'renders each node under a key of its own, a subject reached by two paths included', async () => {
		const wrapper = mountDiamond();
		await flushPromises();

		// Both branches, because the rule opens the path to one occurrence of a Subject and this
		// is about there being two.
		await open( wrapper, DIAMOND_LEFT_ID );
		await open( wrapper, DIAMOND_RIGHT_ID );

		// Five nodes, and five distinct keys.
		// Matched by name: a generic SFC is not a component selector the wrapper's types accept.
		const nodeKeys = wrapper.findAllComponents( { name: 'NeoTreeNode' } )
			.map( ( node ) => node.vm.$.vnode.key )
			.filter( ( key ) => key !== null && key !== undefined );

		expect( nodeKeys ).toHaveLength( 5 );
		expect( new Set( nodeKeys ).size ).toBe( 5 );
	} );

	// Recorded against the pressed row's own key rather than left to the rule: the host reaches
	// the Subject asynchronously and one Subject can hold several rows, so waiting for activeId
	// to come back would leave the row the reader pressed shut — and could open another one.
	it( 'opens the row whose name was pressed, without waiting for the subject to arrive', async () => {
		const wrapper = mountTree();
		await flushPromises();

		await nameRow( wrapper.get( `[data-mw-neowiki-subject-id="${ BIRTH_ID }"]` ) ).trigger( 'click' );

		expect( wrapper.find( `[data-mw-neowiki-subject-id="${ TIMESPAN_ID }"]` ).exists() )
			.toBe( true );
	} );

	// The roving tab stop matches on key, so two nodes sharing one would both answer to it and
	// the tree would offer two tab stops instead of one.
	it( 'steps between the two occurrences of one subject one tab stop at a time', async () => {
		const wrapper = mountDiamond( { activeId: DIAMOND_SHARED_ID } );
		await flushPromises();

		// The rule opens one path down to the shared Subject; the other branch is opened by hand,
		// or there is only one occurrence and nothing for the keys to tell apart.
		await open( wrapper, DIAMOND_RIGHT_ID );
		expect( wrapper.findAll( `[data-mw-neowiki-subject-id="${ DIAMOND_SHARED_ID }"]` ) )
			.toHaveLength( 2 );

		const items = wrapper.findAll( '[role="treeitem"]' );
		const first = items.findIndex(
			( item ) => item.attributes( 'data-mw-neowiki-subject-id' ) === DIAMOND_SHARED_ID,
		);
		expect( first ).toBeGreaterThan( 0 );

		// Stepped ONTO the shared node, so the roving key is the shared node's. Stepping off it
		// would land on a node whose key is unique whatever the shared ones collapse to.
		await items[ first - 1 ].trigger( 'keydown', { key: 'ArrowDown' } );

		const tabbable = wrapper.findAll( '[role="treeitem"][tabindex="0"]' );
		expect( tabbable ).toHaveLength( 1 );
		expect( tabbable[ 0 ].element ).toBe( items[ first ].element );
	} );

	// A relation the user has picked but not yet saved lives only in the form, so a walk over
	// the stored data alone leaves it, and its unsaved dot, off the tree.
	describe( 'Edited copies', () => {
		function mountPersonTree(
			root: Subject,
			seeded: Subject[],
			overrides: MountOverrides,
		): VueWrapper {
			return mountWithServices(
				root,
				personSchema,
				[ nameSchema, eventSchema, timeSpanSchema, personSchema ],
				seeded,
				overrides,
			);
		}

		it( 'renders a node for a relation target held only in the edited copy', async () => {
			const wrapper = mountPersonTree(
				rootWithoutRelations,
				[ spouseSubject ],
				{ editedSubjects: new Map( [ [ ROOT_ID, rootWithSpouseOnly ] ] ) },
			);
			await flushPromises();

			expect( wrapper.find( `[data-mw-neowiki-subject-id="${ SPOUSE_ID }"]` ).exists() ).toBe( true );
		} );

		it( 'drops a node whose relation the edit removed', async () => {
			const wrapper = mountPersonTree(
				rootWithSpouseOnly,
				[ spouseSubject ],
				{ editedSubjects: new Map( [ [ ROOT_ID, rootWithoutRelations ] ] ) },
			);
			await flushPromises();

			expect( wrapper.find( `[data-mw-neowiki-subject-id="${ SPOUSE_ID }"]` ).exists() ).toBe( false );
		} );

		// Both copies have to be in play at once for a preference to be exercised, and mounting
		// with the edited copy already there does not put them there: the tree fetches only what
		// the walk cannot answer. The pane is opened after the stored copy has landed instead.
		it( 'prefers an open pane\'s edited copy over the fetched one for a descendant', async () => {
			const wrapper = mountPersonTree(
				rootSubject,
				[ spouseSubject, birthWithoutTimeSpan, timeSpanSubject ],
				{ activeId: TIMESPAN_ID },
			);
			await flushPromises();

			// The fetched Birth event, which has no Time span, is what the walk now holds.
			expect( wrapper.find( `[data-mw-neowiki-subject-id="${ TIMESPAN_ID }"]` ).exists() ).toBe( false );

			await wrapper.setProps( { editedSubjects: new Map( [ [ BIRTH_ID, birthSubject ] ] ) } );
			await flushPromises();

			const birthNode = wrapper.get( `[data-mw-neowiki-subject-id="${ BIRTH_ID }"]` );
			expect( birthNode.find( `[data-mw-neowiki-subject-id="${ TIMESPAN_ID }"]` ).exists() ).toBe( true );
		} );

		// A pane the user opened and left clean is as unreachable as a dirty one once the relation
		// that led to it is gone: a row missing here is an open Subject with no way back.
		it( 'renders a row for an open subject the walk cannot reach', async () => {
			const wrapper = mountTree( {
				openIds: [ ROOT_ID, STRAY_ID ],
				editedSubjects: new Map( [ [ STRAY_ID, straySubject ] ] ),
			} );
			await flushPromises();

			const node = wrapper.get( `[data-mw-neowiki-subject-id="${ STRAY_ID }"]` );
			expect( node.get( '.ext-neowiki-tree__node-label' ).text() ).toBe( 'Ada Lovelace' );
			expect( hasOwnUnsavedDot( wrapper, STRAY_ID ) ).toBe( false );
		} );

		it( 'renders an unsaved dot for a subject the walk cannot reach', async () => {
			const wrapper = mountTree( {
				openIds: [ ROOT_ID, STRAY_ID ],
				unsavedIds: [ STRAY_ID ],
				editedSubjects: new Map( [ [ STRAY_ID, straySubject ] ] ),
			} );
			await flushPromises();

			const node = wrapper.get( `[data-mw-neowiki-subject-id="${ STRAY_ID }"]` );
			// The label proves the row is built from the edited copy rather than the raw id.
			expect( node.get( '.ext-neowiki-tree__node-label' ).text() ).toBe( 'Ada Lovelace' );
			expect( hasOwnUnsavedDot( wrapper, STRAY_ID ) ).toBe( true );
		} );

		// The root carries every unreachable row, so it is a parent whenever there is one —
		// whatever its own relations say. A tree that says otherwise describes the root as an end
		// node while rendering a group beneath it, and answers neither arrow key on it.
		it( 'reports the root as expanded when its only children are unreachable subjects', async () => {
			const wrapper = mountWithServices(
				bareRootSubject,
				nameSchema,
				[ nameSchema ],
				[ straySubject ],
				{
					openIds: [ ROOT_ID, STRAY_ID ],
					editedSubjects: new Map( [ [ STRAY_ID, straySubject ] ] ),
				},
			);
			await flushPromises();

			expect( wrapper.find( `[data-mw-neowiki-subject-id="${ STRAY_ID }"]` ).exists() )
				.toBe( true );
			expect( rootTreeNode( wrapper ).attributes( 'aria-expanded' ) ).toBe( 'true' );
		} );

		// Without it the row is a name and nothing else, which is what a Subject with nothing
		// under it looks like — the one confusion this vocabulary exists to remove.
		it( 'counts what an unreachable subject holds rather than passing for a leaf', async () => {
			const wrapper = mountWithServices(
				bareRootSubject,
				nameSchema,
				[ nameSchema ],
				[ strayHoldingRelationSubject ],
				{
					openIds: [ ROOT_ID, STRAY_ID ],
					editedSubjects: new Map( [ [ STRAY_ID, strayHoldingRelationSubject ] ] ),
				},
			);
			await flushPromises();

			expect( countOn( wrapper, STRAY_ID ) ).toBe( '1' );
		} );

		// The count still says what the Subject holds; the control would promise to show it, and
		// no number of presses could. Reached from nowhere, there is no path to hang those rows on.
		it( 'offers no control on an unreachable subject that holds relations', async () => {
			const wrapper = mountWithServices(
				bareRootSubject,
				nameSchema,
				[ nameSchema ],
				[ strayHoldingRelationSubject ],
				{
					openIds: [ ROOT_ID, STRAY_ID ],
					editedSubjects: new Map( [ [ STRAY_ID, strayHoldingRelationSubject ] ] ),
				},
			);
			await flushPromises();

			const node = wrapper.get( `[data-mw-neowiki-subject-id="${ STRAY_ID }"]` );
			expect( node.find( '.ext-neowiki-tree__twisty' ).exists() ).toBe( false );
			expect( node.attributes( 'aria-expanded' ) ).toBeUndefined();
		} );

		// The other half of what "cannot reach" has to mean. Telling this apart from a row merely
		// out of sight is the whole job: one needs its row back, the other must not be relabelled.
		it( 'renders a row for an open subject whose link the schema no longer declares', async () => {
			const wrapper = mountWithServices(
				orphanRootSubject,
				nameSchema,
				[ nameSchema ],
				[ straySubject ],
				// Every open pane has a copy, so the row is named the way its form names it.
				{
					openIds: [ ROOT_ID, STRAY_ID ],
					editedSubjects: new Map( [ [ STRAY_ID, straySubject ] ] ),
				},
			);
			await flushPromises();

			const node = wrapper.get( `[data-mw-neowiki-subject-id="${ STRAY_ID }"]` );
			expect( node.get( '.ext-neowiki-tree__node-label' ).text() ).toBe( 'Ada Lovelace' );
			expect( relationNames( wrapper ) ).toContain( 'Not linked here' );
		} );

		// Closing a row hides what is under it; it does not unlink it. The relation is still there
		// and the pane still open, so the caption would be false — on the Subject being edited,
		// the one row the reader is certain to look for.
		it( 'does not caption a subject under a closed row as unlinked', async () => {
			const wrapper = mountTree( {
				activeId: TIMESPAN_ID,
				openIds: [ ROOT_ID, BIRTH_ID, TIMESPAN_ID ],
			} );
			await flushPromises();

			// The helper toggles, and the rule had opened this row: this press closes it.
			await open( wrapper, BIRTH_ID );

			expect( relationNames( wrapper ) ).not.toContain( 'Not linked here' );
			// Hidden under the closed row rather than relisted somewhere else in the tree.
			expect( wrapper.find( `[data-mw-neowiki-subject-id="${ TIMESPAN_ID }"]` ).exists() )
				.toBe( false );
		} );

		// A dirty root pane puts its own id in unsavedIds, and the root already has the tree's
		// first node: without the filter it would grow a second, phantom node under itself.
		it( 'does not list the root itself as unreachable', async () => {
			const wrapper = mountTree( { unsavedIds: [ ROOT_ID ] } );
			await flushPromises();

			expect( wrapper.findAll( `[data-mw-neowiki-subject-id="${ ROOT_ID }"]` ) ).toHaveLength( 1 );
			// A lone stray is named on its row rather than by a caption, so both are checked.
			expect( relationNames( wrapper ) ).not.toContain( 'Not linked here' );
		} );

		it( 'emits select for an unreachable subject\'s node', async () => {
			const wrapper = mountTree( {
				openIds: [ ROOT_ID, STRAY_ID ],
				unsavedIds: [ STRAY_ID ],
				editedSubjects: new Map( [ [ STRAY_ID, straySubject ] ] ),
			} );
			await flushPromises();

			await nameRow( wrapper.get( `[data-mw-neowiki-subject-id="${ STRAY_ID }"]` ) ).trigger( 'click' );

			const emitted = wrapper.emitted( 'select' );
			expect( emitted ).toHaveLength( 1 );
			expect( ( emitted![ 0 ][ 0 ] as SubjectId ).text ).toBe( STRAY_ID );
		} );
	} );

	// Each wave of the healthy branch's resolution re-runs the walk, which is what would
	// re-issue the broken branch's fetch without memoisation.
	function mountMemoTree( repository: InMemorySchemaRepository, activeId = MEMO_ROOT_ID ): void {
		const services = NeoWikiTestServices.getServices();
		services[ Service.SchemaRepository ] = repository;

		mount( SubjectTree, {
			props: {
				rootSubject: memoRootSubject,
				rootSchema: memoRootSchema,
				openIds: [ MEMO_ROOT_ID ],
				activeId,
				unsavedIds: [],
				editedSubjects: new Map<string, Subject>(),
			},
			global: {
				plugins: [ activePinia ],
				provide: services,
				mocks: {
					$i18n: createI18nMock(),
				},
			},
		} );
	}

	// A target whose own fetch fails, rather than the schema it names.
	it( 'attempts a permanently failing subject fetch at most once per mount', async () => {
		const subjectStore = useSubjectStore();
		subjectStore.setSubject( memoMidSubject );
		subjectStore.setSubject( memoEndSubject );

		const resolvable = subjectStore.getOrFetchSubject.bind( subjectStore );
		const getSubjectSpy = vi.spyOn( subjectStore, 'getOrFetchSubject' ).mockImplementation(
			async ( id: SubjectId ) => {
				if ( id.text === MEMO_BROKEN_ID ) {
					throw new Error( 'This subject is gone for good' );
				}
				return resolvable( id );
			},
		);

		mountMemoTree( new InMemorySchemaRepository( [ memoRootSchema, memoMidSchema, memoEndSchema ] ) );
		await flushPromises();

		const brokenCalls = getSubjectSpy.mock.calls.filter( ( args ) => args[ 0 ].text === MEMO_BROKEN_ID );
		expect( brokenCalls.length ).toBe( 1 );

		getSubjectSpy.mockRestore();
	} );

	// A Schema can only narrow what the Statements hold, so a Subject with no relation Statement
	// is a leaf under any of them, and asking for one would both fetch a Schema it cannot use and
	// draw a control until the answer landed. The Spouse's copy is passed in so that its own
	// fetch cannot be what the row is waiting on.
	it( 'neither reads a schema for a row with no relations nor offers to open it', async () => {
		const repository = new InMemorySchemaRepository(
			[ nameSchema, eventSchema, timeSpanSchema, personSchema ],
		);
		const held = repository.getSchema.bind( repository );
		// The Spouse's Schema never arrives, so a row that waits for one waits for the whole
		// test: without that, an in-memory repository answers within the first tick and a row
		// that should never have asked looks the same as one whose answer has landed.
		const getSchemaSpy = vi.spyOn( repository, 'getSchema' ).mockImplementation(
			async ( name: string ) => name === 'Name' ?
				new Promise<Schema>( () => {
					// Never settles, so a row waiting on this Schema waits for the whole test.
				} ) :
				held( name ),
		);

		const services = NeoWikiTestServices.getServices();
		services[ Service.SchemaRepository ] = repository;
		useSubjectStore().setSubject( birthSubject );

		const wrapper = mount( SubjectTree, {
			props: {
				rootSubject,
				rootSchema: personSchema,
				openIds: [ ROOT_ID ],
				// The Birth event, which does hold a relation, is what is being edited, so the walk
				// opens it and does need its Schema.
				activeId: BIRTH_ID,
				unsavedIds: [],
				editedSubjects: new Map( [ [ SPOUSE_ID, spouseSubject ] ] ),
			},
			global: {
				plugins: [ activePinia ],
				provide: services,
				mocks: { $i18n: createI18nMock() },
			},
		} );
		await nextTick();

		const spouse = wrapper.get( `[data-mw-neowiki-subject-id="${ SPOUSE_ID }"]` );
		expect( spouse.find( '.ext-neowiki-tree__twisty' ).exists() ).toBe( false );
		expect( spouse.attributes( 'aria-expanded' ) ).toBeUndefined();

		await flushPromises();

		const read = getSchemaSpy.mock.calls.map( ( args ) => args[ 0 ] );
		expect( read ).not.toContain( 'Name' );
		// The Schema of a row that does hold relations is still read, or this would pass by
		// reading no Schema at all.
		expect( read ).toContain( 'Event' );
	} );

	it( 'attempts a permanently failing schema fetch at most once per mount', async () => {
		const subjectStore = useSubjectStore();
		subjectStore.setSubject( memoMidSubject );
		subjectStore.setSubject( memoEndSubject );
		subjectStore.setSubject( memoBrokenTargetSubject );

		const repository = new InMemorySchemaRepository( [ memoRootSchema, memoMidSchema, memoEndSchema ] );
		const getSchemaSpy = vi.spyOn( repository, 'getSchema' );

		// Opened on the broken target, so the walk descends onto it and needs the Schema it
		// names. A closed row asks for nothing below it, its Schema included.
		mountMemoTree( repository, MEMO_BROKEN_ID );
		await flushPromises();

		const missingSchemaCalls = getSchemaSpy.mock.calls.filter( ( args ) => args[ 0 ] === 'MemoMissing' );
		expect( missingSchemaCalls.length ).toBe( 1 );
	} );
} );
