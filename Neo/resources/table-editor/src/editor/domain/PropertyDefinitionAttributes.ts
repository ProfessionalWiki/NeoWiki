export interface PropertyAttributes {
	description?: string;
	required?: boolean;
	default?: AttributeValue;
	[ key: string ]: AttributeValue;
}

export const baseAttribute: PropertyAttributes = {
	description: '',
	required: false
};

export type AttributeValue = string|number|boolean|undefined;
