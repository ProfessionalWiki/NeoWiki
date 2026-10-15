import type { Subject } from '@/domain/Subject';
import type { Schema } from '@/domain/Schema';
import type { SubjectNameFields } from '@/domain/HostSubject';
import { isNamedAfterHostSubject } from '@/domain/dependentSubjects';
import { resolveAllNonEmptyProperties } from '@/domain/resolveDisplayProperties';
import { valueText } from '@/presentation/valueText';

/**
 * The name to show for a Subject.
 *
 * A Subject nobody named is shown under its id, in brackets that say the string is a stand-in the
 * system supplied — the convention MediaWiki states in the documentation of `blanknamespace`,
 * "(Main)": "Surrounded by brackets to signal that it's only a symbolic label and not an actual
 * namespace prefix." They also set it apart from the bare id a relation shows for a target that
 * cannot be found. A Dependent Subject is named after its Schema and Host Subject instead, as in
 * "Birth of Pablo Picasso".
 *
 * It has to be in the string rather than in styling: several surfaces interpolate a display name
 * into plain text no CSS reaches, and colour alone would carry the meaning nowhere for anyone using
 * a screen reader.
 *
 * Not for the relation picker, whose selection becomes the value of a text input the user can then
 * edit and submit.
 */
export function subjectDisplayName( subject: Subject ): string {
	return displayNameOf( {
		id: subject.getId().text,
		schemaName: subject.getSchemaName(),
		displayName: subject.getDisplayName(),
		displayNameIsGenerated: subject.hasGeneratedDisplayName(),
		hostSubject: subject.getHostSubject(),
	} );
}

/**
 * As subjectDisplayName(), but a Dependent Subject also carries its first value that reads as text, in Schema order,
 * as in "Attendance of Rijksmuseum (Year: 2024)": named after the same Host Subject, those of one Schema share a name.
 * For where they are listed together, and where the user confirms which one to delete.
 */
export function identifyingSubjectName( subject: Subject, schema: Schema | undefined ): string {
	const name = subjectDisplayName( subject );

	if ( !isNamedAfterHostSubject( subject ) || schema === undefined ) {
		return name;
	}

	for ( const { propertyDefinition, value } of resolveAllNonEmptyProperties( schema, subject ) ) {
		const text = valueText( propertyDefinition, value );

		if ( text !== null ) {
			return mw.message( 'neowiki-dependent-subject-identified-name', name, propertyDefinition.name.toString(), text ).text();
		}
	}

	return name;
}

/**
 * As subjectDisplayName(), for a Subject known only by its name fields, such as a summary.
 */
export function displayNameOf( named: SubjectNameFields ): string {
	if ( named.hostSubject !== null ) {
		return mw.msg( 'neowiki-dependent-subject-name', named.schemaName, displayNameOf( named.hostSubject ) );
	}

	return named.displayNameIsGenerated ? generatedSubjectName( named.id ) : named.displayName;
}

/**
 * A Subject id presented as the stand-in it is. The frontend's one place that composes the marker;
 * the backend's is SubjectNameMessage.
 */
export function generatedSubjectName( subjectId: string ): string {
	return mw.msg( 'neowiki-subject-generated-name', subjectId );
}
