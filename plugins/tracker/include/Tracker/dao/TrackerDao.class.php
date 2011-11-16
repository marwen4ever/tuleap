<?php
/**
 * Copyright (c) Xerox Corporation, Codendi Team, 2001-2009. All rights reserved
 *
 * This file is a part of Codendi.
 *
 * Codendi is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * Codendi is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Codendi. If not, see <http://www.gnu.org/licenses/>.
 */

require_once('common/dao/include/DataAccessObject.class.php');
require_once('common/dao/TrackerIdSharingDao.class.php');
class TrackerDao extends DataAccessObject {
    function __construct() {
        parent::__construct();
        $this->table_name = 'tracker';
    }
    
    function searchById($id) {
        $id      = $this->da->escapeInt($id);
        $sql = "SELECT *
                FROM $this->table_name
                WHERE id = $id ";
        return $this->retrieve($sql);
    }
    function searchByGroupId($group_id) {
        $group_id = $this->da->escapeInt($group_id);
        $sql = "SELECT *
                FROM $this->table_name
                WHERE group_id = $group_id 
                  AND deletion_date IS NULL
                ORDER BY name";
        return $this->retrieve($sql);
    }
    
    public function markAsDeleted($id) {
        $id = $this->da->escapeInt($id);
        $deletion_date = $this->da->escapeInt($_SERVER['REQUEST_TIME']);
        $sql = "UPDATE $this->table_name 
                SET deletion_date = $deletion_date
                WHERE id = $id";
        return $this->update($sql);
    }
    
    function duplicate($atid_template, $group_id, $name, $description, $item_name) {
        $atid_template = $this->da->escapeInt($atid_template);
        $group_id      = $this->da->escapeInt($group_id);
        $name          = $this->da->quoteSmart($name);
        $description   = $this->da->quoteSmart($description);
        $item_name     = $this->da->quoteSmart($item_name);
        
        $id_sharing = new TrackerIdSharingDao();
        if ($id = $id_sharing->generateTrackerId()) {
            $sql = "INSERT INTO $this->table_name 
                       (id,
                        group_id, 
                        name, 
                        description, 
                        item_name, 
                        allow_copy, 
                        submit_instructions, 
                        browse_instructions, 
                        status, 
                        instantiate_for_new_projects, 
                        stop_notification)
                    SELECT $id, $group_id, $name, $description, $item_name, 
                        allow_copy, 
                        submit_instructions, 
                        browse_instructions, 
                        status, 
                        instantiate_for_new_projects, 
                        stop_notification
                    FROM $this->table_name
                    WHERE id = $atid_template";
            return $this->updateAndGetLastId($sql);
        }
        return false;
    }
    
    function create($group_id, 
                    $name, 
                    $description, 
                    $item_name, 
                    $allow_copy, 
                    $submit_instructions, 
                    $browse_instructions, 
                    $status, 
                    $deletion_date, 
                    $instantiate_for_new_projects, 
                    $stop_notification) {
        $group_id                     = $this->da->escapeInt($group_id);
        $name                         = $this->da->quoteSmart($name);
        $description                  = $this->da->quoteSmart($description);
        $item_name                    = $this->da->quoteSmart($item_name);
        $allow_copy                   = $this->da->escapeInt($allow_copy);
        $submit_instructions          = $this->da->quoteSmart($submit_instructions);
        $browse_instructions          = $this->da->quoteSmart($browse_instructions);
        $status                       = $this->da->quoteSmart($status);
        $deletion_date                = $deletion_date ? $this->da->escapeInt($deletion_date) : 'NULL';
        $instantiate_for_new_projects = $this->da->quoteSmart($instantiate_for_new_projects);
        $stop_notification            = $this->da->escapeInt($stop_notification);
        
        $id_sharing = new TrackerIdSharingDao();
        if ($id = $id_sharing->generateTrackerId()) {
            
            $sql = "INSERT INTO $this->table_name 
                    (id,
                        group_id, 
                        name, 
                        description, 
                        item_name, 
                        allow_copy, 
                        submit_instructions, 
                        browse_instructions, 
                        status, 
                        deletion_date, 
                        instantiate_for_new_projects, 
                        stop_notification)
                    VALUES ($id,
                        $group_id, 
                        $name, 
                        $description, 
                        $item_name, 
                        $allow_copy, 
                        $submit_instructions, 
                        $browse_instructions, 
                        $status, 
                        $deletion_date, 
                        $instantiate_for_new_projects, 
                        $stop_notification)";
            return $this->updateAndGetLastId($sql);
        }
        return false;
    }
    
    function save($tracker) {
        $id                  = $this->da->escapeInt($tracker->id);
        $group_id                     = $this->da->escapeInt($tracker->group_id);
        $name                         = $this->da->quoteSmart($tracker->name);
        $description                  = $this->da->quoteSmart($tracker->description);
        $item_name                    = $this->da->quoteSmart($tracker->item_name);
        $allow_copy                   = $this->da->escapeInt($tracker->allow_copy);
        $submit_instructions          = $this->da->quoteSmart($tracker->submit_instructions);
        $browse_instructions          = $this->da->quoteSmart($tracker->browse_instructions);
        $status                       = $this->da->quoteSmart($tracker->status);
        $deletion_date                = $tracker->deletion_date ? $this->da->escapeInt($tracker->deletion_date) : 'NULL';
        $instantiate_for_new_projects = $this->da->quoteSmart($tracker->instantiate_for_new_projects);
        $stop_notification            = $this->da->escapeInt($tracker->stop_notification);
        $sql = "UPDATE $this->table_name SET 
                   group_id                     = $group_id,
                   name                         = $name,
                   description                  = $description,
                   item_name                    = $item_name,
                   allow_copy                   = $allow_copy,
                   submit_instructions          = $submit_instructions,
                   browse_instructions          = $browse_instructions,
                   status                       = $status,
                   deletion_date                = $deletion_date,
                   instantiate_for_new_projects = $instantiate_for_new_projects,
                   stop_notification            = $stop_notification
                WHERE id = $id ";
        return $this->update($sql);
    }
    
    function delete($id) {
        $sql = "DELETE FROM $this->table_name WHERE id = ". $this->da->escapeInt($id);
        return $this->update($sql);
    }
    
    public function updateItemName ($group_id, $oldItemname, $itemname) {
        $group_id = $this->da->quoteSmart($group_id);
        $itemname= $this->da->quoteSmart($itemname);
        $oldItemname= $this->da->quoteSmart($oldItemname);
        $sql = "UPDATE $this->table_name SET 
			item_name=$itemname
            WHERE item_name=$oldItemname AND group_id=$group_id";
        return $this->update($sql);
    }
}
?>
