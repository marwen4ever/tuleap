<?php
/**
 * Copyright (c) STMicroelectronics 2014. All rights reserved
 * Copyright (c) Enalean 2017. All rights reserved
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

namespace Tuleap\Statistics;

class ProjectsOverQuotaPresenter
{

    public $table_content;
    public $title;
    public $table_header;
    public $exceeding_projects;

    public function __construct(array $exceeding_projects)
    {
        $this->title              = $GLOBALS['Language']->getText('plugin_statistics', 'projects_over_quota_title');
        $this->table_header       = $this->getTableHeaderTitle();
        $this->exceeding_projects = $exceeding_projects;
    }

    private function getTableHeaderTitle()
    {
        $title   = array();
        $title[] = $GLOBALS['Language']->getText('plugin_statistics', 'project_name');
        $title[] = $GLOBALS['Language']->getText('plugin_statistics', 'current_size');
        $title[] = $GLOBALS['Language']->getText('plugin_statistics', 'quota');
        $title[] = $GLOBALS['Language']->getText('plugin_statistics', 'exceeding_size');
        $title[] = $GLOBALS['Language']->getText('plugin_statistics', 'warn_administrators');

        return $title;
    }
}
