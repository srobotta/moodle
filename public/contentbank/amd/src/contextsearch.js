// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Search and select content bank context using comboboxsearch.
 *
 * @module    core_contentbank/contextsearch
 * @copyright 2026 Stephan Robotta <stephan.robotta@bfh.ch>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import search_combobox from 'core/comboboxsearch/search_combobox';
import {renderForPromise, replaceNodeContents} from 'core/templates';
import Ajax from 'core/ajax';

export default class ContextSearch extends search_combobox {
    lastSearchTerm = null;

    constructor(component) {
        super();
        this.selectors = {
            ...this.selectors,
            placeholder: '.contentbank-context-search-dropdown [data-region="searchplaceholder"]',
        };
        this.component = component;
        this.instance = this.component.querySelector(this.selectors.instance).dataset.instance;
        this.searchInput.addEventListener('keydown', this.keyHandler.bind(this));
    }

    static init() {
        document.querySelectorAll('.contentbank-context-search').forEach((component) => {
            new ContextSearch(component);
        });
    }

    componentSelector() {
        return '.contentbank-context-search';
    }

    dropdownSelector() {
        return '.contentbank-context-search-dropdown';
    }

    async renderDropdown() {
        const categories = this.getMatchedResults().categories || [];
        const courses = this.getMatchedResults().courses || [];
        const {html, js} = await renderForPromise('contentbank/searchcontext/resultset', {
            hasresults: (categories.length > 0 || courses.length > 0),
            categories: categories,
            courses: courses,
            hascategories: categories.length > 0,
            hascourses: courses.length > 0,
            instance: this.instance,
            searchterm: this.getSearchTerm(),
        });
        replaceNodeContents(this.selectors.placeholder, html, js);
        this.searchInput.removeAttribute('aria-activedescendant');
    }

    async getDataset() {
        const searchTerm = this.getSearchTerm();
        if (searchTerm === '') {
            this.dataset = [];
            this.datasetSize = 0;
            this.lastSearchTerm = null;
            return this.dataset;
        }

        if (!this.dataset || this.lastSearchTerm !== searchTerm) {
            this.dataset = await this.fetchDataset(searchTerm);
            this.datasetSize = this.dataset.length;
            this.lastSearchTerm = searchTerm;
        }

        return this.dataset;
    }

    async fetchDataset(searchterm) {
        const request = {
            methodname: 'core_contentbank_search_contexts',
            args: {term: searchterm, contextid: M?.cfg?.contextid ?? 0},
        };

        const response = await Ajax.call([request])[0];
        if (Array.isArray(response.category?.contextid) && Array.isArray(response.course?.contextid)) {
            // Remap categories and courses.
            const categories = response.category.contextid.map((contextid, index) => ({
                id: contextid,
                name: response.category.name[index],
            }));
            const courses = response.course.contextid.map((contextid, index) => ({
                id: contextid,
                name: response.course.name[index],
            }));
            const res = {categories: categories, courses: courses};
            return res;
        }
        throw new Error('Failed to fetch content bank context search results');
    }

    async filterDataset(dataset) {
        return dataset;
    }

    async filterMatchDataset() {
        this.setMatchedResults(
            this.dataset || {categories: [], courses: []}
        );
    }

    async clickHandler(e) {
        if (e.target.closest(this.selectors.clearSearch)) {
            e.stopPropagation();
            this.searchInput.value = '';
            this.setSearchTerms('');
            this.searchInput.focus();
            this.clearSearchButton.classList.add('d-none');
            await this.filterrenderpipe();
            return;
        }

        const optionElement = e.target.closest('[role="option"]');
        if (optionElement) {
            e.preventDefault();
            if (optionElement.getAttribute('aria-disabled') === 'true') {
                return;
            }
            if (optionElement.dataset.value) {
                window.location = this.selectOneLink(optionElement.dataset.value);
                return;
            }
        }

        super.clickHandler(e);
    }

    changeHandler(e) {
        window.location = this.selectOneLink(e.target.value);
    }

    keyHandler(e) {
        if (e.key === 'Enter' && this.getMatchedResults().length > 0) {
            e.preventDefault();
            window.location = this.selectOneLink(this.getMatchedResults()[0].id);
        }
    }

    selectOneLink(contextid) {
        const url = new URL('/contentbank/index.php', window.location.origin);
        url.searchParams.set('contextid', contextid);
        return url.toString();
    }
}

export const init = () => {
    ContextSearch.init();
};
