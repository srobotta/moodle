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

import Ajax from 'core/ajax';
import Notification from 'core/notification';

const validGradingStates = [
    'invalid',
    'complete',
    'finished',
    'gradedwrong',
    'gradedpartial',
    'gradedright',
    'manfinished',
    'mangrwrong',
    'mangrpartial',
    'mangrright',
];

const listOfElements = {};

const createEl = (tag, args) => {
    const el = document.createElement('div');
    if (tag === 'input') {
        el.innerHTML = `<input type="text" name="${args.name}" size="3" style="max-width: 3rem;"/>`;
    } else if (tag === 'mark') {
        el.innerHTML = `<div class="mark-wrapper">${args.html}</div>`;
    } else if (tag === 'spinner') {
        el.innerHTML = '<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>';
    }
    return el.firstChild;
};

const setInput = (element) => {
    const slot = element.dataset.slot;
    const attempt = element.dataset.attempt;
    const idx = `slot-${slot}-attempt-${attempt}`;
    listOfElements[idx] = element;
    const input = createEl('input', {name: idx});
    element.parentNode.replaceChild(input, element);
    input.focus();
    input.addEventListener('blur', (e) => leaveInput(e));
    input.addEventListener('keyup', (e) => leaveInput(e));
};

const leaveInput = (event) => {
    event.preventDefault();
    const input = event.target;
    if (event.type === 'keyup') {
        // User hit Enter, save value and remove input element.
        if (event.keyCode === 13) {
            const mark = input.value;
            if (mark.trim() != '') {
                const parts = input.name.split('-');
                saveGrade(input, parts[3], parts[1], mark);
                return;
            }
        }
        // User entered a value, no escape button. So remain on the input field.
        if (event.keyCode !== 27) {
            return;
        }
    }
    // Remove node without saving, so return to the original link.
    if (typeof(listOfElements[input.name]) !== 'undefined') {
        input.parentNode.replaceChild(listOfElements[input.name], input);
        return;
    }
    input.remove();
};

const saveGrade = async(input, attempt, slot, mark) => {
    const spinner = createEl('spinner');
    input.parentNode.replaceChild(spinner, input);
    const request = {
        methodname: 'mod_quiz_add_grade',
        args: {
            attempt: attempt,
            slot: slot,
            mark: mark
        }
    };
    Ajax.call([request])[0]
        .then((res) => {
            if (validGradingStates.includes(res.state)) {
                let p = spinner;
                while (p && p.nodeName !== 'TD') {
                    p = p.parentNode;
                }
                if (p) {
                    p.innerHTML = res.html;
                    return;
                }
            }
            spinner.parentNode.replaceChild(createEl('mark', {html: res.html}), spinner);
        })
        .fail((ex) => {
            Notification.exception(ex);
            spinner.parentNode.replaceChild(input, spinner);
        });
};

export const init = (selector) => {
    document.querySelectorAll(selector).forEach((element) => {
        element.addEventListener('click', (event) => {
            event.preventDefault();
            setInput(element);
        });
    });
};