import type { SubjectId } from '@/domain/SubjectId';
import type { Layout } from '@/domain/Layout';

/**
 * Prop shape every View Type component must accept to render a View. Identifies
 * the Subject to render (subjectId) plus the per-View context (canEditSubject,
 * layoutName or layout) that NeoWiki supplies when it mounts the component for a
 * {{#view}} or Main Subject placeholder, or in the Layout editor's preview.
 *
 * Each element that shows one property's value carries a data-mw-neowiki-property
 * attribute holding the property's name, which the Layout editor's preview reads
 * to link the View to its list of properties.
 */
export interface ViewProps {
	subjectId: SubjectId;
	canEditSubject: boolean;
	/** Resolved from the layout store, which holds a page's Layouts by name. */
	layoutName?: string;
	/** Rendered in place of the Layout layoutName names, such as an unsaved one in the Layout editor. */
	layout?: Layout;
}
