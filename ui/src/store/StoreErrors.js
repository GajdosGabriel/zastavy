import { reactive, readonly, computed } from "vue";

const defaultState = {
  errors: [
    // { name: "required", message: "This field is required." },
    // { name: "email", message: "Please enter a valid email address." },
  ],
};

const state = reactive(defaultState);

const getters = {
  getErrors: computed(() => state.errors),
};

const actions = {
  setErrors: (error) => {
    actions.resetErrors();

    const message = error?.response?.data?.message
      ?? error?.message
      ?? "Nastala neočakávaná chyba.";

    state.errors.push(message)
  },

  removeError: (index) => {
    state.errors = state.errors.filter((_, i) => i !== index);;
  },

  resetErrors: () => {
    state.errors = [];
  },
};

export default () => ({
  state: readonly(state),
  ...getters,
  ...actions,
});
