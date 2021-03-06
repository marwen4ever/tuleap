<?php
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

namespace Tuleap\Tracker\Artifact;

require_once TRACKER_BASE_DIR . '/../tests/bootstrap.php';

use TuleapTestCase;

class PermissionsCacheTest extends TuleapTestCase
{
    public function itUsesCacheWhenPossible()
    {
        $artifact           = mock('Tracker_Artifact');
        $user               = mock('PFUser');
        $permission_checker = mock('Tracker_Permission_PermissionChecker');

        stub($permission_checker)->userCanView($user, $artifact)->returns(true);

        $permission_checker->expectOnce('userCanView');

        PermissionsCache::userCanView($artifact, $user, $permission_checker);
        PermissionsCache::userCanView($artifact, $user, $permission_checker);
    }
}
