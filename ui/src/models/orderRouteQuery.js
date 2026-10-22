// Filtre zoznamu objednávok (StoreQuery) ↔ parametre v adrese /objednavky?…
const ROUTE_PARAMS = { "status=": "status", "shippedAt=": "shippedAt", "bySearchInput=": "customer", "searchByProduct=": "product" };

export const ORDER_ROUTE_FIELDS = ["filter", "status", "shippedAt", "product", "customer", "marked"];

export const orderRouteQuery = (items) => {
    const query = {};
    for (const item of items) {
        const split = item.indexOf("=") + 1;
        const key = item.slice(0, split);
        if (key === "isMarked=") query.marked = 1;
        else if (ROUTE_PARAMS[key]) query[ROUTE_PARAMS[key]] = decodeURIComponent(item.slice(split));
        else query.filter = key.slice(0, -1);
    }
    return query;
};

// Porovnateľný odtlačok filtrov — strana a iné parametre sa ignorujú.
export const orderRouteSignature = (query) => ORDER_ROUTE_FIELDS.map((field) => String(query[field] ?? "")).join("|");
