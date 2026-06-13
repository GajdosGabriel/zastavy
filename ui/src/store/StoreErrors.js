import { reactive, readonly, computed } from "vue";

const defaultState = {
  errors: [
    // { name: "required", message: "This field is required." },
    // { name: "email", message: "Please enter a valid email address." },
  ],
  fieldErrors: {},
};

const state = reactive(defaultState);

const getters = {
  getErrors: computed(() => state.errors),
  getFieldErrors: computed(() => state.fieldErrors),
};

const actions = {
  setErrors: (error) => {
    actions.resetErrors();

    const fieldErrors = error?.response?.data?.errors ?? {};
    state.fieldErrors = fieldErrors;

    const fieldMessages = Object.values(fieldErrors).flat();

    if (fieldMessages.length > 0) {
      fieldMessages.forEach(msg => state.errors.push(msg));
    } else {
      const message = error?.response?.data?.message
        ?? error?.message
        ?? 'Nastala neočakávaná chyba.';
      state.errors.push(message);
    }
  },

  removeError: (index) => {
    state.errors = state.errors.filter((_, i) => i !== index);;
  },

  resetErrors: () => {
    state.errors = [];
    state.fieldErrors = {};
  },
};

export default () => ({
  state: readonly(state),
  ...getters,
  ...actions,
});
