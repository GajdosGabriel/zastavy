import { reactive, readonly, computed } from "vue";

const defaultState = {
  validations: [
    {name: "required", message: "This field is required."},
    {name: "email", message: "Please enter a valid email address."},
  ],
};

const state = reactive(defaultState);

const getters = {
  getValidations: computed(() => state.validations),
};

const actions = {
  resetValidations: () => {
    state.validations = [];
  },
};

export default () => ({
  state: readonly(state),
  ...getters,
  ...actions,
});
