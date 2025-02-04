import Vue from 'vue';
import findIndex from 'lodash/findIndex';

// State is simply an object that contains the properties that need to be shared within the application:
// The state must return a function to make the module reusable.
// See: https://vuex.vuejs.org/en/modules.html#module-reuse
const state = {
    maxFieldHandleLength: 64,
    maxFormHandleLength: 64,
    reservedHandles: [],
    emailTemplates: [],
    existingFields: [],
    existingNotifications: [],
    statuses: [],
};

// Mutations are functions responsible in directly mutating store state.
// In Vuex, mutations always have access to state as the first argument.
// In addition, Actions may or may not pass in a payload as the second argument:
const mutations = {
    SET_MAX_FIELD_HANDLE_LENGTH(state, config) {
        state.maxFieldHandleLength = config;
    },

    SET_MAX_FORM_HANDLE_LENGTH(state, config) {
        state.maxFormHandleLength = config;
    },

    SET_RESERVED_HANDLES(state, config) {
        for (const prop in config) {
            if (Object.hasOwnProperty.call(config, prop)) {
                Vue.set(state.reservedHandles, prop, config[prop]);
            }
        }
    },

    SET_EMAIL_TEMPLATES(state, config) {
        for (const prop in config) {
            if (Object.hasOwnProperty.call(config, prop)) {
                Vue.set(state.emailTemplates, prop, config[prop]);
            }
        }
    },

    SET_EXISTING_FIELDS(state, config) {
        for (const prop in config) {
            if (Object.hasOwnProperty.call(config, prop)) {
                Vue.set(state.existingFields, prop, config[prop]);
            }
        }
    },

    SET_EXISTING_NOTIFICATIONS(state, config) {
        for (const prop in config) {
            if (Object.hasOwnProperty.call(config, prop)) {
                Vue.set(state.existingNotifications, prop, config[prop]);
            }
        }
    },

    SET_STATUSES(state, config) {
        for (const prop in config) {
            if (Object.hasOwnProperty.call(config, prop)) {
                Vue.set(state.statuses, prop, config[prop]);
            }
        }
    },
};

// Actions exist to call mutations. Actions are also responsible in performing any
// or all asynchronous calls prior to committing to mutations.
// Actions have access to a context object that provides access to state (with context.state),
// to getters (with context.getters), and to the commit function (with context.commit).
const actions = {
    setMaxFieldHandleLength(context, config) {
        context.commit('SET_MAX_FIELD_HANDLE_LENGTH', config);
    },

    setMaxFormHandleLength(context, config) {
        context.commit('SET_MAX_FORM_HANDLE_LENGTH', config);
    },

    setReservedHandles(context, config) {
        context.commit('SET_RESERVED_HANDLES', config);
    },

    setEmailTemplates(context, config) {
        context.commit('SET_EMAIL_TEMPLATES', config);
    },

    setExistingFields(context, config) {
        context.commit('SET_EXISTING_FIELDS', config);
    },

    setExistingNotifications(context, config) {
        context.commit('SET_EXISTING_NOTIFICATIONS', config);
    },

    setStatuses(context, config) {
        context.commit('SET_STATUSES', config);
    },
};

// Getters are to a Vuex store what computed properties are to a Vue component.
// Getters are primarily used to perform some calculation/manipulation to store state
// before having that information accessible to components.
const getters = {
    maxFieldHandleLength: (state) => () => {
        return state.maxFieldHandleLength;
    },

    maxFormHandleLength: (state) => () => {
        return state.maxFormHandleLength;
    },

    reservedHandles: (state) => () => {
        return state.reservedHandles;
    },

    emailTemplates: (state) => () => {
        return state.emailTemplates;
    },

    existingFields: (state) => () => {
        return state.existingFields;
    },

    existingNotifications: (state) => () => {
        return state.existingNotifications;
    },

    statuses: (state) => () => {
        return state.statuses;
    },
};

export default {
    namespaced: true,
    state,
    mutations,
    actions,
    getters,
};
