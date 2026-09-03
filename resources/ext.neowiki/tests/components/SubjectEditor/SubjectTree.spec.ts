import { mount, flushPromises, DOMWrapper, VueWrapper } from '@vue/test-utils';
import { describe, it, expect, beforeEach, vi } from 'vitest';
import { createPinia, setActivePinia, type Pinia } from 'pinia';
import SubjectTree from '@/components/SubjectEditor/SubjectTree.vue';
import SchemaNameDisplay from '@/components/common/SchemaNameDisplay.vue';
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
const memoBrokenTargetSubject = newSubject( {
	id: MEMO_BROKEN_ID,
	label: 'Memo broken target',
	schemaName: 'MemoMissing',
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

// An unbranched chain five subjects long, so the cutoff falls inside the fixture: TREE_DEPTH=3
// renders three links and stops before the fourth. Every link resolves and none repeats, so
// neither a failed fetch nor the visited set can be what ends the walk.
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
// Reachable from Depth 3 and resolvable, so only the depth cap keeps it out.
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

function mountTreeWithCycle(): VueWrapper {
	return mountWithServices(
		cycleRootSubject,
		cycleSchema,
		[ cycleSchema ],
		[ cycleBSubject ],
	);
}

function mountTreeWithTwoHopCycle(): VueWrapper {
	return mountWithServices(
		twoHopASubject,
		cycleSchema,
		[ cycleSchema ],
		// The root is seeded too: B's "Colleague" targets A, and A must resolve, or a failed fetch
		// rather than the visited set could be what stops the walk.
		[ twoHopASubject, twoHopBSubject ],
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

function mountDeepChain(): VueWrapper {
	return mountWithServices(
		chainRootSubject,
		chainSchema,
		[ chainSchema ],
		[ chainOneSubject, chainTwoSubject, chainThreeSubject, chainFourSubject ],
	);
}

function mountDiamond(): VueWrapper {
	return mountWithServices(
		diamondRootSubject,
		diamondRootSchema,
		[ diamondRootSchema, diamondBranchSchema, diamondSharedSchema ],
		[ diamondLeftSubject, diamondRightSubject, diamondSharedSubject ],
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

	it( 'renders a node per relation target, labelled with the relation property', async () => {
		// person --Birth event--> birth --Time span--> timespan
		const wrapper = mountTree();
		await flushPromises();

		expect( targetNodeLabels( wrapper ) ).toContain( 'Birth of J. S. Bach' );
		expect( wrapper.findAll( '.ext-neowiki-tree__edge' ).map( ( n ) => n.text() ) )
			.toContain( 'Birth event' );
	} );

	it( 'names a relation once per group, not once per sibling node', async () => {
		const wrapper = mountTreeWithTwoTargets();
		await flushPromises();

		expect( targetNodes( wrapper ).length ).toBe( 2 );

		const siblingLabels = wrapper.findAll( '.ext-neowiki-tree__edge' )
			.map( ( n ) => n.text() )
			.filter( ( text ) => text === 'Sibling' );
		expect( siblingLabels.length ).toBe( 1 );
	} );

	// A declared relation earns a place in the navigator only once data fills it.
	it( 'gives a declared relation with no target neither a node nor a group caption', async () => {
		const wrapper = mountTree();
		await flushPromises();

		expect( wrapper.findAll( '.ext-neowiki-tree__edge' ).map( ( n ) => n.text() ) )
			.toEqual( [ 'Spouse', 'Birth event', 'Time span' ] );
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
	it( 'names a label-less target by its display name', async () => {
		const wrapper = mountWithServices(
			rootSubject,
			personSchema,
			[ nameSchema, eventSchema, timeSpanSchema, personSchema ],
			[ labellessSpouse, birthSubject, timeSpanSubject ],
		);
		await flushPromises();

		expect( targetNodeLabels( wrapper ) ).toContain( 'Name' );
		expect( targetNodeLabels( wrapper ) ).not.toContain( SPOUSE_ID );
	} );

	// ADR 31 names a label-less child after its Schema, so printing the Schema beside that
	// name would read "Name  Name".
	it( 'prints the schema of a label-less target only once', async () => {
		const wrapper = mountWithServices(
			rootSubject,
			personSchema,
			[ nameSchema, eventSchema, timeSpanSchema, personSchema ],
			[ labellessSpouse, birthSubject, timeSpanSubject ],
		);
		await flushPromises();

		expect( nameRow( wrapper.get( `[data-mw-neowiki-subject-id="${ SPOUSE_ID }"]` ) ).text() ).toBe( 'Name' );
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
			const wrapper = mountTree();
			await flushPromises();

			const birthNode = wrapper.find( `[data-mw-neowiki-subject-id="${ BIRTH_ID }"]` );
			expect( birthNode.find( `[data-mw-neowiki-subject-id="${ TIMESPAN_ID }"]` ).exists() ).toBe( true );
		} );

		// The other way to be a leaf: DiamondShared declares three relation properties and fills
		// none, where Spouse's Schema declares none at all. Left branch, which fills one, is the
		// contrast.
		it( 'announces a node whose declared relations are all empty as a leaf too', async () => {
			const wrapper = mountDiamond();
			await flushPromises();

			const shared = wrapper.findAll( `[data-mw-neowiki-subject-id="${ DIAMOND_SHARED_ID }"]` )[ 0 ];
			expect( shared.attributes( 'aria-expanded' ) ).toBeUndefined();

			const left = wrapper.get( `[data-mw-neowiki-subject-id="${ DIAMOND_LEFT_ID }"]` );
			expect( left.attributes( 'aria-expanded' ) ).toBe( 'true' );
		} );

		// The tree is eagerly expanded and offers no per-node toggle, so it draws no disclosure
		// glyph. Asserted as the row's whole content rather than as the absence of one class or
		// character, so a re-introduced glyph is caught whatever it is made of.
		it( 'renders nothing in a node\'s row but its name and its schema', async () => {
			const wrapper = mountTree();
			await flushPromises();

			const rows = wrapper.findAll( '.ext-neowiki-tree__node-name' );
			expect( rows.length ).toBe( 4 );

			for ( const row of rows ) {
				expect( [ ...row.element.children ].map( ( child ) => child.className ) )
					.toEqual( [ 'ext-neowiki-tree__node-label', 'ext-neowiki-tree__node-secondary' ] );
				// Text as well as elements: a bare glyph beside the two spans lands here.
				expect( row.text() ).toBe(
					row.get( '.ext-neowiki-tree__node-label' ).text() +
					row.get( '.ext-neowiki-tree__node-secondary' ).text(),
				);
			}
		} );

		it( 'shows a node\'s schema as the shared badge', async () => {
			const wrapper = mountTree();
			await flushPromises();

			const badges = wrapper.findAllComponents( SchemaNameDisplay );

			expect( badges.length ).toBe( 4 );
			expect( badges.map( ( badge ) => badge.props( 'schemaName' ) ) )
				.toEqual( [ 'Person', 'Name', 'Event', 'TimeSpan' ] );
		} );

		it( 'leaves the badge unlinked, so it cannot compete with the row it sits in', async () => {
			const wrapper = mountTree();
			await flushPromises();

			expect( wrapper.findAll( '.ext-neowiki-tree__node-secondary a' ) ).toHaveLength( 0 );
			expect( wrapper.findComponent( SchemaNameDisplay ).props( 'link' ) ).toBe( 'none' );
		} );

		it( 'marks the active node as selected', async () => {
			const wrapper = mountTree( { activeId: TIMESPAN_ID } );
			await flushPromises();

			const nodes = targetNodes( wrapper );
			expect( nodes[ 2 ].attributes( 'aria-selected' ) ).toBe( 'true' );
			expect( nodes[ 0 ].attributes( 'aria-selected' ) ).toBe( 'false' );
		} );

		// NeoTree's own Home test presses Home while the root is the active item, where roving
		// focus falls back to the root anyway, so a Home that moved no key would still look
		// right. Here the active Subject is the deep one Home is pressed from, so only a moved
		// key passes. Widget behaviour is asserted through this component for that reason.
		it( 'Home moves the roving key, not just DOM focus, while a deep node is active', async () => {
			const wrapper = mountTree( { activeId: TIMESPAN_ID } );
			await flushPromises();

			await targetNodes( wrapper )[ 2 ].trigger( 'keydown', { key: 'Home' } );

			expect( rootTreeNode( wrapper ).attributes( 'tabindex' ) ).toBe( '0' );
			expect( targetNodes( wrapper )[ 2 ].attributes( 'tabindex' ) ).toBe( '-1' );
		} );
	} );

	// Asserted from both sides: a raised cap pulls Depth 4 in, a lowered one drops Depth 3.
	it( 'walks three relation hops from the root and stops', async () => {
		const wrapper = mountDeepChain();
		await flushPromises();

		expect( targetNodeLabels( wrapper ) ).toEqual( [ 'Depth 1', 'Depth 2', 'Depth 3' ] );
	} );

	it( 'terminates on a cycle instead of recursing', async () => {
		const wrapper = mountTreeWithCycle();
		await flushPromises();

		// B's "Colleague" targets itself, so depth 1 renders B and depth 2 renders the closing
		// second B. Exactly 2: 1 would mean the repeat visit was dropped rather than rendered
		// once per path, 3+ that the depth cap stopped the walk instead of the cycle guard.
		expect( targetNodes( wrapper ).length ).toBe( 2 );
	} );

	it( 'terminates a two-hop cycle that closes back on the root, not just a direct self-loop', async () => {
		const wrapper = mountTreeWithTwoHopCycle();
		await flushPromises();

		// Depth 2 is still inside TREE_DEPTH=3, so the visited set stops this, not the depth cap.
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
		const wrapper = mountTree( { unsavedIds: [ TIMESPAN_ID ] } );
		await flushPromises();

		expect( hasOwnUnsavedDot( wrapper, TIMESPAN_ID ) ).toBe( true );
		expect( hasOwnUnsavedDot( wrapper, BIRTH_ID ) ).toBe( false );
	} );

	// The demo wiki has this shape: ACME's Amsterdam HQ is both an office and the headquarters,
	// so it renders under two relations and each occurrence needs a key of its own. The two live
	// in different v-for lists, so Vue never warns about a collision and no DOM attribute carries
	// a key: the only place the identity shows is the key Vue holds for each rendered node.
	it( 'renders each node under a key of its own, a subject reached by two paths included', async () => {
		const wrapper = mountDiamond();
		await flushPromises();

		// Five nodes, and five distinct keys.
		// Matched by name: a generic SFC is not a component selector the wrapper's types accept.
		const nodeKeys = wrapper.findAllComponents( { name: 'NeoTreeNode' } )
			.map( ( node ) => node.vm.$.vnode.key )
			.filter( ( key ) => key !== null && key !== undefined );

		expect( nodeKeys ).toHaveLength( 5 );
		expect( new Set( nodeKeys ).size ).toBe( 5 );
	} );

	// The roving tab stop matches on key, so two nodes sharing one would both answer to it and
	// the tree would offer two tab stops instead of one.
	it( 'steps between the two occurrences of one subject one tab stop at a time', async () => {
		const wrapper = mountDiamond();
		await flushPromises();

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
				{},
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

		// A dirty root pane puts its own id in unsavedIds, and the root already has the tree's
		// first node: without the filter it would grow a second, phantom node under itself.
		it( 'does not list the root itself as unreachable', async () => {
			const wrapper = mountTree( { unsavedIds: [ ROOT_ID ] } );
			await flushPromises();

			expect( wrapper.findAll( `[data-mw-neowiki-subject-id="${ ROOT_ID }"]` ) ).toHaveLength( 1 );
			expect( wrapper.findAll( '.ext-neowiki-tree__edge' ).map( ( n ) => n.text() ) )
				.not.toContain( 'Not linked here' );
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
	function mountMemoTree( repository: InMemorySchemaRepository ): void {
		const services = NeoWikiTestServices.getServices();
		services[ Service.SchemaRepository ] = repository;

		mount( SubjectTree, {
			props: {
				rootSubject: memoRootSubject,
				rootSchema: memoRootSchema,
				openIds: [ MEMO_ROOT_ID ],
				activeId: MEMO_ROOT_ID,
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

	it( 'attempts a permanently failing schema fetch at most once per mount', async () => {
		const subjectStore = useSubjectStore();
		subjectStore.setSubject( memoMidSubject );
		subjectStore.setSubject( memoEndSubject );
		subjectStore.setSubject( memoBrokenTargetSubject );

		const repository = new InMemorySchemaRepository( [ memoRootSchema, memoMidSchema, memoEndSchema ] );
		const getSchemaSpy = vi.spyOn( repository, 'getSchema' );

		mountMemoTree( repository );
		await flushPromises();

		const missingSchemaCalls = getSchemaSpy.mock.calls.filter( ( args ) => args[ 0 ] === 'MemoMissing' );
		expect( missingSchemaCalls.length ).toBe( 1 );
	} );
} );
