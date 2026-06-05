export interface ApiResponse<T> {
    data: T;
    meta?: any; // Optional meta data from the API
    links?: any; // Optional links data from the API
};