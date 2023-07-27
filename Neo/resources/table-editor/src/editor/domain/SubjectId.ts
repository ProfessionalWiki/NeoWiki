import { Uuid } from '@/editor/infrastructure/Uuid';
import { InvalidArgumentError } from '@/editor/infrastructure/Exceptions/InvalidArgumentError';

export class SubjectId {
	public readonly text: string;

	public constructor( text: string ) {
		// if ( !Uuid.isValid( text ) ) {
		// 	throw new InvalidArgumentError( 'Subject ID has the wrong format. ID: ' + text );
		// }

		this.text = text;
	}
}
