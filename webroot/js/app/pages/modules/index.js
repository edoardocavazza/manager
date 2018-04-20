/**
 * Templates that uses this component (directly or indirectly)
 *  Template/Modules/index.twig
 *  - Element/Toolbar/filter.twig
 *  - Element/Toolbar/pagination.twig
 *
 *
 * <modules-index> component used for ModulesPage -> Index
 *
 */
Vue.component('modules-index', {

    /**
     * Component properties
     *
     * @type {Object} props properties
     */
    props: {
        ids: {
            type: String,
            default: () => [],
        },
    },

    /**
     * component properties
     *
     * @returns {Object}
     */
    data() {
        return {
            urlPagination: '',
            searchQuery: '',
            pageSize: '100',
            page: '',
            sort: '',
            isAllChecked: false,
            all: [],
            checked: [],
            exportids: [],
            statusids: [],
            trashids: [],
            restoreids: [], // used in trash
            status: '',
        };
    },

    /**
     * @inheritDoc
     */
    created() {
        // load url params when component initialized
        this.loadUrlParams();
        try {
            this.all = JSON.parse(this.ids);
            this.all = this.all.map(Number);
        } catch(error) {
            console.error(error);
        }
    },

    /**
     * watched vars handlers
     */
    watch: {

        /**
         * Checked checkboxes change handler.
         * If necessary, set isAllChecked.
         *
         * @return {void}
         */
        checked() {
            if (!this.isAllChecked && (this.all.length === this.checked.length)) {
                this.isAllChecked = true;
            } else if (this.isAllChecked && (this.all.length > this.checked.length)) {
                this.isAllChecked = false;
            }
            this.exportids = this.checked;
            this.statusids = this.checked;
            this.trashids = this.checked;
            this.restoreids = this.checked;
        },
    },

    /**
     * component methods
     */
    methods: {

        /**
         * extract params from page url
         *
         * @returns {void}
         */
        loadUrlParams() {
            // look for query string params in window url
            if (window.location.search) {
                const urlParams = window.location.search;

                // search for q='some string' both after ? and & tokens
                const queryStringExp = /[?&]q=([^&#]*)/g;
                let matches = urlParams.match(queryStringExp);
                if (matches && matches.length) {
                    matches = matches.map(e => e.replace(queryStringExp, '$1'));
                    this.searchQuery = matches[0];
                }

                // search for page_size='some string' both after ? and & tokens
                const pageSizeExp = /[?&]page_size=([^&#]*)/g;
                matches = urlParams.match(pageSizeExp);
                if (matches && matches.length) {
                    matches = matches.map(e => e.replace(pageSizeExp, '$1'));
                    this.pageSize = this.isNumeric(matches[0]) ? matches[0] : '';
                }

                // search for page='some string' both after ? and & tokens
                const pageExp = /[?&]page=([^&#]*)/g;
                matches = urlParams.match(pageExp);
                if (matches && matches.length) {
                    matches = matches.map(e => e.replace(pageExp, '$1'));
                    this.page = this.isNumeric(matches[0]) ? matches[0] : '';
                }

                // search for sort='some string' both after ? and & tokens
                const sortExp = /[?&]sort=([^&#]*)/g;
                matches = urlParams.match(sortExp);
                if (matches && matches.length) {
                    matches = matches.map(e => e.replace(sortExp, '$1'));
                    this.sort = matches[0];
                }
            }
        },


        /**
         * build coherent url based on these params:
         * - q= query string
         * - page_size
         *
         * @param {Object} params
         * @returns {String} url
         */
        buildUrlParams(params) {
            let url = `${window.location.origin}${window.location.pathname}`;
            let first = true;

            Object.keys(params).forEach((key) =>  {
                if (params[key] && params[key] !== '') {
                    url += `${first ? '?' : '&'}${key}=${params[key]}`;
                    first = false;
                }
            });

            return url;
        },


        /**
         * update pagination keeping searched string
         *
         * @returns {void}
         */
        updatePagination() {
            window.location.replace(this.urlPagination);
        },

        /**
         * search queryString keeping pagination options
         *
         * @returns {void}
         */
        search() {
            this.page = '';
            this.applyFilters();
        },


        /**
         * reset queryString in search keeping pagination options
         *
         * @returns {void}
         */
        resetResearch() {
            this.searchQuery = '';
            this.applyFilters();
        },

        /**
         * apply page filters such as query string or pagination
         *
         * @returns {void}
         */
        applyFilters() {
            let url = this.buildUrlParams({
                q: this.searchQuery,
                page_size: this.pageSize,
                page: this.page,
                sort: this.sort,
            });
            window.location.replace(url);
        },

        /**
         * Helper function
         *
         * @param {String|Number} num
         * @returns {Boolean}
         */
        isNumeric(num) {
            return !isNaN(num);
        },

        /**
         * Click con check/uncheck all
         *
         * @return {void}
         */
        checkAll() {
            this.isAllChecked = !this.isAllChecked;
            this.checked = (this.isAllChecked) ? this.all : [];
            this.exportids = this.checked;
        },

        /**
         * Submit bulk export form, if at least one item is checked
         *
         * @return {void}
         */
        exportSelected() {
            if (this.checked.length < 1) {
                return;
            }
            document.getElementById('form-export').submit();
        },

        /**
         * Submit bulk change status form, if at least one item is checked and status is not empty
         *
         * @return {void}
         */
        changeStatus() {
            if (this.statusids.length < 1 || !this.status) {
                return;
            }
            document.getElementById('form-status').submit();
        },

        /**
         * Submit bulk delete form, if at least one item is checked
         *
         * @return {void}
         */
        trash() {
            if (this.trashids.length < 1) {
                return;
            }
            document.getElementById('form-delete').submit();
        },

        /**
         * Submit bulk restore form, if at least one item is checked
         *
         * @return {void}
         */
        restore() {
            if (this.restoreids.length < 1) {
                return;
            }
            document.getElementById('form-restore').submit();
        },
    }
});


