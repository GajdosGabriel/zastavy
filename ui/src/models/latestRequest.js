// Každá inštancia patrí jednej zobrazovanej množine dát.
export function createLatestRequest() {
    let version = 0;
    return {
        async run(request, commit, fail) {
            const current = ++version;
            try {
                const result = await request();
                if (current === version) commit(result);
            } catch (error) {
                if (current === version) fail(error);
            }
        },
    };
}
