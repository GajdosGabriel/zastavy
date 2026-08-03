import { defineStore } from "pinia";
import axiosInstance from "../axiosInstance";
import useErrors from "./StoreErrors";
import { PAGE_ATTRIBUTE, URL_BASE_API } from "../constants";

export interface AttributeValueRow {
    id: number;
    attribute_id: number;
    code: string;
    value: string;
    slug: string;
    color: string | null;
    sort_order: number;
}

export interface AttributeRow {
    id: number;
    code: string;
    name: string;
    unit: string | null;
    input_type: string;
    is_variant: boolean;
    is_filterable: boolean;
    is_public: boolean;
    sort_order: number;
    status?: any;
    values?: AttributeValueRow[];
    values_count?: number;
    endpoints?: Record<string, string>;
    permissions?: Record<string, any>;
}

export interface FacetValue {
    id: number;
    code: string;
    value: string;
    slug: string;
    color: string | null;
    count: number;
}

export interface Facet {
    code: string;
    name: string;
    unit: string | null;
    type: string;
    values: FacetValue[];
}

interface AttributesState {
    attributes: AttributeRow[];
    facets: Facet[];
    facetsLoaded: boolean;
}

export const emptyAttribute = (): Partial<AttributeRow> => ({
    name: '',
    unit: null,
    input_type: 'select',
    is_variant: true,
    is_filterable: true,
    is_public: true,
    sort_order: 0,
});

export const useAttributes = defineStore('attributes', {
    state: (): AttributesState => ({
        attributes: [],
        facets: [],
        facetsLoaded: false,
    }),

    getters: {
        getAttributes: (s): AttributeRow[] => s.attributes,
        // Iba vlastnosti, ktoré rozlišujú skladovú položku — z nich sa skladá variant.
        getVariantAttributes: (s): AttributeRow[] => s.attributes.filter((a) => a.is_variant),
        getFacets: (s): Facet[] => s.facets,
    },

    actions: {
        async fetchAttributes(): Promise<void> {
            try {
                const response = await axiosInstance.get(PAGE_ATTRIBUTE.URL);
                this.attributes = response.data.data;
            } catch (e) {
                useErrors().setErrors(e);
            }
        },

        async storeAttribute(payload: Partial<AttributeRow>): Promise<AttributeRow | null> {
            try {
                const response = await axiosInstance.post(PAGE_ATTRIBUTE.URL, payload);
                await this.fetchAttributes();
                return response.data.data ?? response.data;
            } catch (e) {
                useErrors().setErrors(e);
                return null;
            }
        },

        async updateAttribute(attribute: AttributeRow): Promise<void> {
            try {
                await axiosInstance.put(`${PAGE_ATTRIBUTE.URL}/${attribute.id}`, {
                    name: attribute.name,
                    code: attribute.code,
                    unit: attribute.unit,
                    input_type: attribute.input_type,
                    is_variant: attribute.is_variant,
                    is_filterable: attribute.is_filterable,
                    is_public: attribute.is_public,
                    sort_order: attribute.sort_order,
                });
                await this.fetchAttributes();
            } catch (e) {
                useErrors().setErrors(e);
            }
        },

        async destroyAttribute(attribute: AttributeRow): Promise<void> {
            if (!window.confirm(`Skutočne zmazať vlastnosť ${attribute.name}?`)) {
                return;
            }
            try {
                await axiosInstance.delete(`${PAGE_ATTRIBUTE.URL}/${attribute.id}`);
                await this.fetchAttributes();
            } catch (e) {
                useErrors().setErrors(e);
            }
        },

        async storeValue(attributeId: number, payload: Partial<AttributeValueRow>): Promise<void> {
            try {
                await axiosInstance.post(`${PAGE_ATTRIBUTE.URL}/${attributeId}/values`, payload);
                await this.fetchAttributes();
            } catch (e) {
                useErrors().setErrors(e);
            }
        },

        async updateValue(attributeId: number, value: AttributeValueRow): Promise<void> {
            try {
                await axiosInstance.put(`${PAGE_ATTRIBUTE.URL}/${attributeId}/values/${value.id}`, {
                    value: value.value,
                    color: value.color,
                    sort_order: value.sort_order,
                });
                await this.fetchAttributes();
            } catch (e) {
                useErrors().setErrors(e);
            }
        },

        async destroyValue(attributeId: number, value: AttributeValueRow): Promise<void> {
            if (!window.confirm(`Skutočne zmazať hodnotu ${value.value}?`)) {
                return;
            }
            try {
                await axiosInstance.delete(`${PAGE_ATTRIBUTE.URL}/${attributeId}/values/${value.id}`);
                await this.fetchAttributes();
            } catch (e) {
                useErrors().setErrors(e);
            }
        },

        /**
         * Fasety pre verejný katalóg — bez prihlásenia, načítajú sa raz.
         */
        async fetchFacets(force = false): Promise<void> {
            if (this.facetsLoaded && !force) {
                return;
            }
            try {
                const response = await axiosInstance.get(`${URL_BASE_API}/attribute-facets`);
                this.facets = response.data.data;
                this.facetsLoaded = true;
            } catch (e) {
                this.facets = [];
            }
        },
    },
});

export default useAttributes;
