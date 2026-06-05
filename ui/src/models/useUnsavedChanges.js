import { onBeforeRouteLeave } from 'vue-router';
import { ref } from 'vue';

export default function useUnsavedChanges(getCurrentData) {
    const originalData = ref(null);
    const isSaved = ref(false);

    function setOriginalData(data) {
        originalData.value = JSON.parse(JSON.stringify(data));
    }

    function markAsSaved() {
        isSaved.value = true;
    }

    function isFormChanged() {
        return JSON.stringify(originalData.value) !== JSON.stringify(getCurrentData());
    }

    onBeforeRouteLeave((to, from, next) => {
        if (!isSaved.value && isFormChanged()) {
            if (confirm('Máte neuložené zmeny. Naozaj chcete odísť?')) {
                next();
            } else {
                next(false);
            }
        } else {
            next();
        }
    });

    return {
        setOriginalData,
        markAsSaved,
        isFormChanged,
    };
}
