import { Subject } from '@/domain/Subject';
import { Statement } from '@/domain/Statement';
import { StatementList, statementsToJson } from '@/domain/StatementList';
import { PropertyName, type PropertyDefinition } from '@/domain/PropertyDefinition';
import { Relation, RelationValue, ValueType, type Value } from '@/domain/Value';

export type Choice = 'theirs' | 'yours' | 'both';

interface Sides<T> {
	readonly base: T | undefined;
	readonly theirs: T | undefined;
	readonly yours: T | undefined;
}

interface ChangeOf<Kind extends string, T> extends Sides<T> {
	readonly kind: Kind;
	/** Names the change in the choices passed to `EditConflict.resolve()`. */
	readonly key: string;
	readonly subjectId: string;
}

export type LabelChange = ChangeOf<'label', string | null>;

export interface StatementChange extends ChangeOf<'statement', Statement> {
	readonly propertyName: string;
}

/** A Subject added or removed. */
export type SubjectChange = ChangeOf<'subject', Subject>;

export type Change = LabelChange | StatementChange | SubjectChange;

type Verdict = 'same' | 'theirs' | 'yours' | 'both';

interface Judged<C> {
	readonly change: C;
	readonly verdict: Verdict;
}

/** A Subject only one side holds is judged whole, one both hold field by field. */
type JudgedSubject = {
	readonly kind: 'whole';
	readonly sides: Sides<Subject>;
	readonly presence: Judged<SubjectChange>;
} | {
	readonly kind: 'fields';
	readonly theirs: Subject;
	readonly label: Judged<LabelChange>;
	readonly statements: readonly Judged<StatementChange>[];
};

type PropertyOf = ( subjectId: string, propertyName: string ) => PropertyDefinition | undefined;

/**
 * Your edit of a Subject and its Dependent Subjects, beside the edit someone else saved since you started from the
 * base. Each Subject list starts with the Host Subject.
 */
export class EditConflict {

	private constructor(
		private readonly subjects: ReadonlyMap<string, Sides<Subject>>,
		private readonly judged: ReadonlyMap<string, JudgedSubject>,
		public readonly hostId: string,
	) {
	}

	public static between( base: readonly Subject[], theirs: readonly Subject[], yours: readonly Subject[] ): EditConflict {
		const versions = { base: byId( base ), theirs: byId( theirs ), yours: byId( yours ) };
		const subjects = new Map( uniqueKeys( versions.yours, versions.theirs, versions.base ).map(
			( id ) => [ id, sidesOf( versions, id ) ],
		) );

		return new EditConflict(
			subjects,
			new Map( [ ...subjects ].map( ( [ id, sides ] ) => [ id, judgeSubject( id, sides ) ] ) ),
			yours[ 0 ].getId().text,
		);
	}

	/** Yours where you hold it, else theirs, else as you started from it. */
	public subjectFor( id: string ): Subject | undefined {
		const sides = this.subjects.get( id );
		return sides?.yours ?? sides?.theirs ?? sides?.base;
	}

	/** Each needs a choice before the edit can be saved. */
	public get bothChanged(): Change[] {
		return [ ...this.judged.values() ].flatMap( entriesOf )
			.filter( ( entry ) => entry.verdict === 'both' )
			.map( ( entry ) => entry.change );
	}

	/**
	 * The Subject and the property whose relation points at the Subject with this id, in your version where it does.
	 */
	public holderOf( id: string ): { subjectId: string; propertyName: string } | undefined {
		for ( const side of [ 'yours', 'theirs', 'base' ] as const ) {
			for ( const [ holderId, sides ] of this.subjects ) {
				const statement = [ ...sides[ side ]?.getStatements() ?? [] ].find(
					( candidate ) => relationTo( candidate, id ) !== undefined,
				);

				if ( statement !== undefined ) {
					return { subjectId: holderId, propertyName: statement.propertyName.toString() };
				}
			}
		}

		return undefined;
	}

	/**
	 * The Host Subject first, then the Dependent Subjects it reaches: theirs with your changes and the choices made. A
	 * change both made without a choice keeps yours. A Dependent Subject only one side added or removed is kept where
	 * the resolved relations hold it; one kept against its removal joins a relation whose property takes several
	 * values, and replaces the value of any other.
	 */
	public resolve( choices: ReadonlyMap<string, Choice>, propertyOf: PropertyOf ): Subject[] {
		const resolved = new Map<string, Subject>();

		for ( const [ id, judged ] of this.judged ) {
			const subject = resolveSubject( judged, choices );

			if ( subject !== undefined ) {
				resolved.set( id, subject );
			}
		}

		const keptAgainstRemoval = [ ...this.judged.values() ]
			.flatMap( ( judged ) => judged.kind === 'whole' && judged.presence.verdict === 'both' ? [ judged.presence.change.subjectId ] : [] )
			.filter( ( id ) => resolved.has( id ) );
		const statementChanges = [ ...this.judged.values() ]
			.flatMap( ( judged ) => judged.kind === 'fields' ? judged.statements.map( ( entry ) => entry.change ) : [] );

		return reachedFromHost(
			withRelationsBack( resolved, keptAgainstRemoval, statementChanges, propertyOf ),
			this.hostId,
			this.subjects.keys(),
		);
	}

}

function entriesOf( judged: JudgedSubject ): Judged<Change>[] {
	return judged.kind === 'whole' ? [ judged.presence ] : [ judged.label, ...judged.statements ];
}

function byId( subjects: readonly Subject[] ): Map<string, Subject> {
	return new Map( subjects.map( ( subject ) => [ subject.getId().text, subject ] ) );
}

function uniqueKeys( ...maps: ReadonlyMap<string, unknown>[] ): string[] {
	return [ ...new Set( maps.flatMap( ( map ) => [ ...map.keys() ] ) ) ];
}

function sidesOf<T>( versions: { base: Map<string, T>; theirs: Map<string, T>; yours: Map<string, T> }, key: string ): Sides<T> {
	return { base: versions.base.get( key ), theirs: versions.theirs.get( key ), yours: versions.yours.get( key ) };
}

function judgeSubject( id: string, subjects: Sides<Subject> ): JudgedSubject {
	if ( subjects.theirs === undefined || subjects.yours === undefined ) {
		const change: SubjectChange = { kind: 'subject', key: `${ id } subject`, subjectId: id, ...subjects };
		return { kind: 'whole', sides: subjects, presence: { change, verdict: judge( subjects, sameSubject ) } };
	}

	const label: LabelChange = {
		kind: 'label',
		key: `${ id } label`,
		subjectId: id,
		base: subjects.base?.getLabel(),
		theirs: subjects.theirs.getLabel(),
		yours: subjects.yours.getLabel(),
	};

	const statements = {
		base: statementsByName( subjects.base ),
		theirs: statementsByName( subjects.theirs ),
		yours: statementsByName( subjects.yours ),
	};

	return {
		kind: 'fields',
		theirs: subjects.theirs,
		label: { change: label, verdict: judge( label, ( a, b ) => a === b ) },
		statements: uniqueKeys( statements.yours, statements.theirs, statements.base ).map( ( name ) => {
			const change: StatementChange = {
				kind: 'statement',
				key: `${ id } statement ${ name }`,
				subjectId: id,
				propertyName: name,
				...sidesOf( statements, name ),
			};
			return { change, verdict: judge( change, sameStatement ) };
		} ),
	};
}

function judge<T>( sides: Sides<T>, same: ( a: T, b: T ) => boolean ): Verdict {
	const equal = ( a: T | undefined, b: T | undefined ): boolean =>
		a === undefined || b === undefined ? a === b : same( a, b );

	if ( equal( sides.theirs, sides.yours ) ) {
		return 'same';
	}

	if ( equal( sides.base, sides.theirs ) ) {
		return 'yours';
	}

	return equal( sides.base, sides.yours ) ? 'theirs' : 'both';
}

function statementsByName( subject: Subject | undefined ): Map<string, Statement> {
	return new Map(
		[ ...subject?.getStatements().withNonEmptyValues() ?? [] ].map( ( statement ) => [ statement.propertyName.toString(), statement ] ),
	);
}

function sameStatement( a: Statement, b: Statement ): boolean {
	return JSON.stringify( statementsToJson( new StatementList( [ a ] ) ) ) ===
		JSON.stringify( statementsToJson( new StatementList( [ b ] ) ) );
}

function sameSubject( a: Subject, b: Subject ): boolean {
	const statementsOfA = statementsByName( a );
	const statementsOfB = statementsByName( b );

	return a.getLabel() === b.getLabel() &&
		statementsOfA.size === statementsOfB.size &&
		[ ...statementsOfA ].every( ( [ name, statement ] ) => {
			const counterpart = statementsOfB.get( name );
			return counterpart !== undefined && sameStatement( statement, counterpart );
		} );
}

function resolveSubject( judged: JudgedSubject, choices: ReadonlyMap<string, Choice> ): Subject | undefined {
	if ( judged.kind === 'whole' ) {
		return judged.presence.verdict === 'both' ?
			pick( judged.presence, choices ) :
			judged.sides.yours ?? judged.sides.theirs;
	}

	const statements = judged.statements
		.map( ( entry ) => resolveStatement( entry, choices ) )
		.filter( ( statement ): statement is Statement => statement !== undefined );

	return judged.theirs
		.withLabel( pick( judged.label, choices ) ?? null )
		.withStatements( new StatementList( statements ) );
}

function resolveStatement( entry: Judged<StatementChange>, choices: ReadonlyMap<string, Choice> ): Statement | undefined {
	if ( entry.verdict === 'both' && choices.get( entry.change.key ) === 'both' ) {
		return bothListsOf( entry.change );
	}

	return pick( entry, choices );
}

function pick<T>( { change, verdict }: Judged<ChangeOf<string, T>>, choices: ReadonlyMap<string, Choice> ): T | undefined {
	if ( verdict === 'theirs' || verdict === 'both' && choices.get( change.key ) === 'theirs' ) {
		return change.theirs;
	}

	return change.yours;
}

function relationTo( statement: Statement | undefined, id: string ): Relation | undefined {
	return statement?.value instanceof RelationValue ?
		statement.value.relations.find( ( relation ) => relation.target.text === id ) :
		undefined;
}

/**
 * The ids of the Subjects the relations among these changes point at, on any side.
 */
export function relationTargetIdsOf( changes: readonly Change[] ): string[] {
	return [ ...new Set( changes.flatMap( ( change ) => change.kind !== 'statement' ? [] :
		[ change.base, change.theirs, change.yours ].flatMap( ( statement ) =>
			statement?.value instanceof RelationValue ? statement.value.targetIds.map( ( id ) => id.text ) : [],
		),
	) ) ];
}

/**
 * Whether both sides hold a list, so both lists' changes can be kept. Whether the property takes several values
 * is the caller's to check.
 */
export function holdsLists( change: Change ): boolean {
	return change.kind === 'statement' && isList( change.theirs?.value ) && isList( change.yours?.value );
}

// Text in several languages is left out: keeping both sides' parts can give a language two texts.
function isList( value: Value | undefined ): boolean {
	return value?.type === ValueType.String || value?.type === ValueType.Relation;
}

export function takesSeveralValues( property: PropertyDefinition | undefined ): boolean {
	return ( property as { multiple?: boolean } | undefined )?.multiple === true;
}

/**
 * Their list, without what you removed, followed by what you added.
 */
export function bothListsOf( change: StatementChange ): Statement | undefined {
	const items = ( statement: Statement | undefined ): Map<string, unknown> => new Map( itemsOf( statement?.value ) );
	const base = items( change.base );
	const yours = items( change.yours );
	const theirs = items( change.theirs );

	const kept = [ ...theirs ].filter( ( [ key ] ) => !base.has( key ) || yours.has( key ) );
	const added = [ ...yours ].filter( ( [ key ] ) => !base.has( key ) && !theirs.has( key ) );

	return change.theirs?.withValue( listValue( change.theirs.value, [ ...kept, ...added ].map( ( [ , item ] ) => item ) ) );
}

function itemsOf( value: Value | undefined ): [ string, unknown ][] {
	switch ( value?.type ) {
		case ValueType.String:
			return value.parts.map( ( part ) => [ part, part ] );
		case ValueType.Relation:
			return value.relations.map( ( relation ) => [ relation.id ?? relation.target.text, relation ] );
		default:
			return [];
	}
}

function listValue( value: Value | undefined, items: unknown[] ): Value | undefined {
	switch ( value?.type ) {
		case ValueType.String:
			return { type: ValueType.String, parts: items as string[] };
		case ValueType.Relation:
			return new RelationValue( items as Relation[] );
		default:
			return value;
	}
}

function withRelationsBack(
	resolved: Map<string, Subject>,
	keptAgainstRemoval: readonly string[],
	statementChanges: readonly StatementChange[],
	propertyOf: PropertyOf,
): Map<string, Subject> {
	let subjects = resolved;

	for ( const id of keptAgainstRemoval ) {
		if ( ![ ...subjects.values() ].some( ( subject ) => references( subject, id ) ) ) {
			subjects = withRelationBack( subjects, id, statementChanges, propertyOf );
		}
	}

	return subjects;
}

/**
 * The Host Subject and the Dependent Subjects its relations reach, without relations to the Subjects of the conflict
 * that resolved away.
 */
function reachedFromHost( subjects: Map<string, Subject>, hostId: string, conflictIds: Iterable<string> ): Subject[] {
	const reached = new Set( [ hostId ] );
	const toVisit = [ hostId ];

	while ( toVisit.length > 0 ) {
		const subject = subjects.get( toVisit.pop() as string );

		for ( const target of subject === undefined ? [] : subject.getStatements().getIdsOfReferencedSubjects() ) {
			if ( subjects.has( target.text ) && !reached.has( target.text ) ) {
				reached.add( target.text );
				toVisit.push( target.text );
			}
		}
	}

	const dropped = new Set( [ ...conflictIds ].filter( ( id ) => !reached.has( id ) ) );

	return [ ...subjects.values() ]
		.filter( ( subject ) => reached.has( subject.getId().text ) )
		.map( ( subject ) => withoutRelationsTo( subject, dropped ) );
}

function references( subject: Subject, id: string ): boolean {
	return [ ...subject.getStatements().getIdsOfReferencedSubjects() ].some( ( referenced ) => referenced.text === id );
}

function withoutRelationsTo( subject: Subject, dropped: Set<string> ): Subject {
	const statements = [ ...subject.getStatements() ].map( ( statement ) => statement.value instanceof RelationValue ?
		statement.withValue( new RelationValue(
			statement.value.relations.filter( ( relation ) => !dropped.has( relation.target.text ) ),
		) ) :
		statement );

	return subject.withStatements( new StatementList( statements ) );
}

function withRelationBack(
	subjects: Map<string, Subject>,
	dependentId: string,
	statementChanges: readonly StatementChange[],
	propertyOf: PropertyOf,
): Map<string, Subject> {
	const holder = statementChanges
		.flatMap( ( change ) => [ change.yours, change.theirs ].map( ( statement ) => ( { change, statement, relation: relationTo( statement, dependentId ) } ) ) )
		.find( ( candidate ) => candidate.relation !== undefined );
	const holderSubject = holder === undefined ? undefined : subjects.get( holder.change.subjectId );

	if ( holder?.statement === undefined || holder.relation === undefined || holderSubject === undefined ) {
		return subjects;
	}

	const { subjectId, propertyName } = holder.change;
	const name = new PropertyName( propertyName );
	const current = holderSubject.getStatements().has( name ) ? holderSubject.getStatements().get( name ) : undefined;
	const relations = current?.value instanceof RelationValue && takesSeveralValues( propertyOf( subjectId, propertyName ) ) ?
		current.value.relations :
		[];

	return new Map( subjects ).set( subjectId, holderSubject.withStatements( holderSubject.getStatements().withStatement(
		( current ?? holder.statement ).withValue( new RelationValue( [ ...relations, holder.relation ] ) ),
	) ) );
}
