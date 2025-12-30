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
 * TODO describe module searchform
 *
 * @module     local_lessonbank/searchform
 * @copyright  2025 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import Ajax from 'core/ajax';
import * as Notification from 'core/notification';
import Templates from 'core/templates';
import Log from 'core/log';
import loading from 'core/loadingicon';
import * as Str from 'core/str';

const component = 'local_lessonbank';

export const registerFilter = () => {
    const form = document.querySelector('#local_lessonbank_filters');
    const cardsContainer = document.querySelector('[data-region="cards-container"]');
    const gridlayoutbtn = document.querySelector('.gridlayoutbtn');
    const listlayoutbtn = document.querySelector('.listlayoutbtn');
    const countcontainer = document.querySelector('.countcontainer');
    const pagination = document.querySelector('[name="perpageselection"]');

    gridlayoutbtn?.addEventListener('click', e => {
        e.preventDefault();
        cardsContainer.classList.remove('listlayout');
        gridlayoutbtn.classList.add('active');
        listlayoutbtn.classList.remove('active');
        searchFilter(form);
    });
    listlayoutbtn?.addEventListener('click', e => {
        e.preventDefault();
        cardsContainer.classList.add('listlayout');
        listlayoutbtn.classList.add('active');
        gridlayoutbtn.classList.remove('active');
        searchFilter(form);
    });
    const searchFilter = form => {
        const args = {
            language: '',
            level: [],
            keywords: '',
            page: 1,
            perpage: 10
        };
        if (form.elements.language) {
            args.language = form.elements.language.value;
        }
        if (form.elements.search) {
            args.keywords = form.elements.search.value;
        }
        if (form.elements['level[]']) {
            const selectedOptions = form.elements['level[]'].selectedOptions;
            args.level = Array.from(selectedOptions).map(option => option.value);
        }
        if (form.elements.page) {
            args.page = form.elements.page.value;
        }
        if (form.elements.perpage) {
            args.perpage = form.elements.perpage.value;
        }

        Ajax.call([{
            methodname: `${component}_list_minilessons`,
            args
        }], true, false)[0]
        .then(items => {
            Log.debug(items);
            items.islistlayot = cardsContainer.classList.contains('listlayout') ? true : false;
            if (countcontainer) {
                Str.get_string('foundlessons', 'local_lessonbank', items.totalitems).then((langstr) => {
                    countcontainer.textContent = langstr;
                });
            }
            Templates.render(`${component}/lessonbankitems`, items)
            .then((html, js) => {
                Templates.replaceNodeContents(cardsContainer, html, js);
            });
            return null;
        })
        .catch(Notification.exception);
    };
    form?.addEventListener('submit', e => {
        e.preventDefault();
        form.elements.page.value = 1;
        searchFilter(form);
    });
    cardsContainer.addEventListener('click', e => {
        if (e.target.href) {
            return;
        }
        e.preventDefault();
        const dirbtn = e.target.closest('[data-action="previousbtn"],[data-action="nextbtn"]');
        if (dirbtn) {
            const pageno = dirbtn.getAttribute('data-page');
            const perpage = dirbtn.getAttribute('data-perpage');
            const pagevalue = parseInt(pageno, 10) + (dirbtn.dataset.action === 'previousbtn' ? -1: 1);
            if (form) {
                form.elements.page.value = pagevalue;
                form.elements.perpage.value = perpage;
                searchFilter(form);
            }
        }

        const downloadbtn = e.target.closest('[data-action="download"]');
        if (downloadbtn) {
            if (!downloadbtn.dataset.id) {
                return;
            }
            const promise = loading.addIconToContainerWithPromise(downloadbtn);
            Ajax.call([{
                methodname: `${component}_fetch_minilesson`,
                args: {
                    id: Number(downloadbtn.dataset.id)
                }
            }], true, false)[0]
            .then(data => {
                promise.resolve();
                const blob = new Blob([data.json], { type: 'application/json' });
                const url = URL.createObjectURL(blob);
                e.target.href = url;
                e.target.download = `${e.target.dataset.download}.json`;
                e.target.click();
                e.target.removeAttribute('href');
                URL.revokeObjectURL(url);
            }).catch(e => {
                promise.reject();
                Notification.exception(e);
            });
        }
        const showtextbtn = e.target.closest('[data-action="showtext"]');
        if (showtextbtn) {
            const wrapper = showtextbtn.parentElement;
            const titlehtml = wrapper.firstElementChild.cloneNode(true).outerHTML;
            wrapper.innerHTML = titlehtml + wrapper.dataset.text;
        }
    });
    if (pagination) {
        pagination.addEventListener('change', e => {
            const perpagevalue = e.target.value;
            if (form) {
                form.elements.page.value = 1;
                form.elements.perpage.value = perpagevalue;
                searchFilter(form);
            }
        });
    }
    if (form) {
        searchFilter(form);
        if (form.elements.submit) {
            form.elements.submit.parentElement.classList.add('d-none');
            form.elements.submit.classList.add('rounded-right');
            form.elements.search.insertAdjacentElement('afterend', form.elements.submit);
            form.elements.search.parentElement.classList.add('input-group');
        }
    }
};