/**
 * Copyright (c) Enalean, 2016. All Rights Reserved.
 *
 * This file is a part of Tuleap.
 *
 * Tuleap is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * Tuleap is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Tuleap. If not, see <http://www.gnu.org/licenses/>.
 */

document.addEventListener('DOMContentLoaded', function () {
    var date_picker_elements = document.querySelectorAll('.tlp-input-date');

    [].forEach.call(date_picker_elements, function (element) {
        tlp.datePicker(element);
    });

    var project_selectors = document.querySelectorAll('.project-autocompleter');
    [].forEach.call(project_selectors, function (project_selector) {
        tuleap.autocomplete_projects_for_select2(
            project_selector,
            {
                include_private_projects: true
            }
        );
    });

    var user_selectors = document.querySelectorAll('.user-autocompleter');
    [].forEach.call(user_selectors, function (user_selector) {
        tuleap.autocomplete_users_for_select2(
            user_selector,
            {
                internal_users_only: true
            }
        );
    });

    var modal_buttons = document.querySelectorAll('[data-modal-id]');
    [].forEach.call(modal_buttons, function (button) {
        var modal_element = document.getElementById(button.dataset.modalId);
        if (! modal_element) {
            throw "Bad reference to an unknown modal element: '" + button.dataset.modalId +"'";
        }

        var modal = tlp.modal(modal_element);

        button.addEventListener('click', function () {
            modal.toggle();
        });
    });

    var data_export_contents = document.querySelectorAll('.siteadmin-export-data');
    var data_export_inputs   = document.querySelectorAll('input[name="data-export-content"]');
    [].forEach.call(data_export_inputs, function (data_export_input) {
        data_export_input.addEventListener('change', function () {
            var content_value = event.target.value;

            [].forEach.call(data_export_contents, function(content_to_disappear) {
                if (content_to_disappear.id !== content_value) {
                    content_to_disappear.classList.add('siteadmin-export-data-disappear');
                } else {
                    content_to_disappear.classList.remove('siteadmin-export-data-disappear');
                }
            });

            var inputs = document.querySelectorAll('input[value=' + content_value + ']');
            [].forEach.call(inputs, function(input) {
                input.checked = input.value === content_value;
            });
        });
    })
});
