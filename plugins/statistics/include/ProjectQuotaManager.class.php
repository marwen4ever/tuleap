<?php
/**
 * Copyright (c) STMicroelectronics 2012. All rights reserved
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

require_once 'Statistics_ProjectQuotaDao.class.php';

/**
 * Management of custom quota by project
 */
class ProjectQuotaManager {

    /**
     * The Projects dao used to fetch data
     */
    protected $dao;

    /**
     * ProjectManager instance
     */
    protected $pm;

    /**
     * Statistics_DiskUsageManager instance
     */
    protected $diskUsageManager;

    /**
     * ProjectQuotaManager constructor
     */
    public function __construct() {
        $this->dao              = $this->getDao();
        $this->diskUsageManager = new Statistics_DiskUsageManager();
        $this->pm               = ProjectManager::instance();
    }

    /**
     * Retrieve the authorized disk quota for a project
     *
     * @param Integer $group_id The ID of the project we are looking for its quota
     *
     * @return String
     */
    private function getProjectAuthorizedQuota($group_id) {
        $quota = $this->getProjectCustomQuota($group_id);
        if (empty($quota)) {
            $quota = $this->getDefaultQuota();
        }
        return $quota;
    }

    /**
     * Convert a given quota size in bi to Gib
     *
     * @param Integer $size The quota size in bi
     *
     * @return Float
     */
    private function convertQuotaToGiB($size) {
        return $size * 1024 * 1024 * 1024;
    }

    /**
     * Check if a given project is overquota given it
     *
     * @param Integer $current_size The current disk size of the project in bi
     * @param Integer $allowed_size The allowed disk size of the project in bi
     *
     * @return Boolean
     */
    private function isProjectOverQuota($current_size, $allowed_size) {
        if (!empty($current_size) && ($current_size > $allowed_size)) {
            return True;
        }
        return False;
    }

    /**
     * Prepare disk occupation data for a given project over quota
     *
     * @param String  $unix_group_name The unix name of the project
     * @param Integer $group_id        The ID of the project
     * @param Integer $current_size    The current disk size of the project
     * @param Integer $allowed_size    The allowed disk size of the project
     *
     * @return Array
     */
    private function getProjectOverQuotaRow($unix_group_name, $group_id, $current_size, $allowed_size) {
        $usage_output          = new Statistics_DiskUsageOutput($this->diskUsageManager);
        $over_quota_disk_space = $current_size-$allowed_size;
        $exceed_percent        = round(($over_quota_disk_space/$allowed_size), 2) * 100;
        $projectRow            = array('project_name'       => $unix_group_name,
                                       'group_id'           => $group_id,
                                       'exceed'             => $exceed_percent.'%',
                                       'disk_quota'         => $usage_output->sizeReadable($allowed_size),
                                       'current_disk_space' => $usage_output->sizeReadable($current_size));
        return $projectRow;
    }

    /**
     * Retrieve the list of all projects exceeding their disk quota
     *
     * @return Array
     */
    public function getProjectsOverQuota() {
        $all_groups         = $this->fetchProjects();
        $exceeding_projects = array();
        foreach ($all_groups as $key => $group) {
            $quota = $this->getProjectAuthorizedQuota($group['group_id']);
            $current_size = $this->diskUsageManager->returnTotalProjectSize($group['group_id']);
            $allowed_size = $this->convertQuotaToGiB($quota);
            if ($this->isProjectOverQuota($current_size, $allowed_size)) {
                $exceeding_projects[$key] = $this->getProjectOverQuotaRow($group['unix_group_name'], $group['group_id'], $current_size, $allowed_size);
            }
        }
        return $exceeding_projects;
    }

    /**
     * Retrieve the project ID and the unix group name of all Tuleap projects
     *
     * @return DataAccessResult
     */
    private function fetchProjects() {
        return $this->diskUsageManager->_getDao()->searchAllGroups();
    }

    /**
     * Retrieve custom quota for a given project
     *
     * @param int $groupId ID of the project we want to retrieve its custom quota
     *
     * @return Integer
     */
    public function getProjectCustomQuota($groupId) {
        $allowedQuota = null;
        $res = $this->dao->getProjectCustomQuota($groupId);
        if ($res && !$res->isError() && $res->rowCount() == 1) {
            $row          = $res->getRow();
            $allowedQuota = $row[Statistics_ProjectQuotaDao::REQUEST_SIZE];
        }
        return $allowedQuota;
    }

    /**
     * Add custom quota for a project
     *
     * @param String  $project    Project for which quota will be customized
     * @param String  $requester  User that asked for the custom quota
     * @param Integer $quota      Quota to be set for the project
     * @param String  $motivation Why the custom quota was requested
     *
     * @return Void
     */
    public function addQuota($project, $requester, $quota, $motivation) {
        $maxQuota = $this->getMaximumQuota();
        if (empty($project)) {
            $GLOBALS['Response']->addFeedback('error', $GLOBALS['Language']->getText('plugin_statistics', 'invalid_project'));
        } elseif (empty($quota)) {
            $GLOBALS['Response']->addFeedback('error', $GLOBALS['Language']->getText('plugin_statistics', 'invalid_quota', $maxQuota));
        } else {
            $project = $this->pm->getProjectFromAutocompleter($project);
            if ($project) {
                $userId = null;
                $um     = UserManager::instance();
                $user   = $um->findUser($requester);
                if ($user) {
                    $userId = $user->getId();
                } else {
                    $user   = $um->getCurrentUser();
                    $userId = $user->getId();
                }
                if ($quota > $maxQuota) {
                    $GLOBALS['Response']->addFeedback('error', $GLOBALS['Language']->getText('plugin_statistics', 'invalid_quota', $maxQuota));
                } else {
                    if ($this->dao->addException($project->getGroupID(), $userId, $quota, $motivation)) {
                        $historyDao = new ProjectHistoryDao(CodendiDataAccess::instance());
                        $historyDao->groupAddHistory("add_custom_quota", $quota, $project->getGroupID());
                        $GLOBALS['Response']->addFeedback('info', $GLOBALS['Language']->getText('plugin_statistics', 'quota_added', array($project->getPublicName(), $quota)));
                    } else {
                        $GLOBALS['Response']->addFeedback('error', $GLOBALS['Language']->getText('plugin_statistics', 'add_error'));
                    }
                }
            } else {
                $GLOBALS['Response']->addFeedback('error', $GLOBALS['Language']->getText('plugin_statistics', 'no_project'));
            }
        }
    }

    /**
     * Get the default quota defined for the platform
     *
     * @return int
     */
    public function getDefaultQuota() {
        $quota = intval($this->diskUsageManager->getProperty('allowed_quota'));
        if (!$quota) {
            $quota = 5;
        }
        return $quota;
    }

    /**
     * Get the maximum quota defined for the platform
     *
     * @return int
     */
    public function getMaximumQuota() {
        $maxQuota = intval($this->diskUsageManager->getProperty('maximum_quota'));
        if (!$maxQuota) {
            $maxQuota = 50;
        }
        return $maxQuota;
    }

    public function deleteCustomQuota(Project $project) {
        $defaultQuota = $this->diskUsageManager->getProperty('allowed_quota');
        $historyDao   = new ProjectHistoryDao(CodendiDataAccess::instance());
        if ($this->dao->deleteCustomQuota($project->getId())) {
            $historyDao->groupAddHistory("restore_default_quota", intval($defaultQuota), $project->getId());
            $GLOBALS['Response']->addFeedback('info', $GLOBALS['Language']->getText('plugin_statistics', 'quota_deleted', $project->getUnconvertedPublicName()));
        } else {
            $GLOBALS['Response']->addFeedback('error', $GLOBALS['Language']->getText('plugin_statistics', 'delete_error'));
        }
    }

    /**
     * @return Statistics_ProjectQuotaDao
     */
    public function getDao() {
        if (!isset($this->dao)) {
            $this->dao = new Statistics_ProjectQuotaDao();
        }
        return $this->dao;
    }
}

?>