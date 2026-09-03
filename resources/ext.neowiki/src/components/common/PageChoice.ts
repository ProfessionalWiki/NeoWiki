/**
 * A page the user picked: either one that exists, named by its id, or a title that does not exist
 * yet and has to be created before a Subject can be moved onto it.
 */
export interface PageChoice {
	pageId: number | null;
	title: string;
}
