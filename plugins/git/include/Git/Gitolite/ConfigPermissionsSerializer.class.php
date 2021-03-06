<?php
/**
 * Copyright (c) Enalean, 2014 - 2016. All rights reserved
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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Tuleap. If not, see <http://www.gnu.org/licenses/
 */

use Tuleap\Git\Permissions\FineGrainedRetriever;
use Tuleap\Git\Permissions\FineGrainedPermissionFactory;
use Tuleap\Git\Permissions\FineGrainedPermission;
use Tuleap\Git\Permissions\RegexpFineGrainedRetriever;

class Git_Gitolite_ConfigPermissionsSerializer {

    /**
     * @var FineGrainedPermissionFactory
     */
    private $fine_grained_factory;

    /**
     * @var FineGrainedRetriever
     */
    private $fine_grained_retriever;

    const TEMPLATES_PATH    = 'gitolite';
    const REMOVE_PERMISSION = ' - ';

    /**
     * @var TemplateRenderer
     */
    private $template_renderer;

    /**
     * @var Git_Mirror_MirrorDataMapper
     */
    private $data_mapper;

    /**
     * @var Git_Driver_Gerrit_ProjectCreatorStatus
     */
    private $gerrit_status;

    private static $permissions_types = array(
        Git::PERM_READ   => ' R  ',
        Git::PERM_WRITE  => ' RW ',
        Git::PERM_WPLUS  => ' RW+'
    );
    /**
     * @var RegexpFineGrainedRetriever
     */
    private $regexp_retriever;

    public function __construct(
        Git_Mirror_MirrorDataMapper $data_mapper,
        Git_Driver_Gerrit_ProjectCreatorStatus $gerrit_status,
        $etc_templates_path,
        FineGrainedRetriever $fine_grained_retriever,
        FineGrainedPermissionFactory $fine_grained_factory,
        RegexpFineGrainedRetriever $regexp_retriever
    ) {
        $this->data_mapper   = $data_mapper;
        $this->gerrit_status = $gerrit_status;
        $template_dirs       = array();
        if (is_dir($etc_templates_path)) {
            $template_dirs[] = $etc_templates_path . '/' . self::TEMPLATES_PATH;
        }
        $template_dirs[]         = GIT_TEMPLATE_DIR . '/' . self::TEMPLATES_PATH;
        $this->template_renderer = TemplateRendererFactory::build()->getRenderer($template_dirs);

        $this->fine_grained_retriever = $fine_grained_retriever;
        $this->fine_grained_factory   = $fine_grained_factory;
        $this->regexp_retriever       = $regexp_retriever;
    }

    public function getGitoliteDotConf(array $project_names) {
        return $this->template_renderer->renderToString(
            'gitolite.conf',
            new Git_Gitolite_Presenter_GitoliteConfPresenter(
                $project_names,
                $this->data_mapper->fetchAll()
            )
        );
    }

    public function getGitoliteDotConfForHostname(array $project_names) {
        return $this->template_renderer->renderToString(
            'gitolite-with-hostname.conf',
            new Git_Gitolite_Presenter_GitoliteConfPresenter(
                $project_names,
                $this->data_mapper->fetchAll()
            )
        );
    }

    public function getAllIncludes(array $project_names) {
        return $this->template_renderer->renderToString(
            'gitolite-includes.conf',
            array(
                "project_names" => $project_names
            )
        );
    }

    public function getAllIncludesForHostname($hostname, array $project_names) {
        return $this->template_renderer->renderToString(
            'gitolite-includes-for-hostname.conf',
            array(
                "hostname"      => $hostname,
                "project_names" => $project_names
            )
        );
    }

    public function getForRepository(GitRepository $repository) {
        $project = $repository->getProject();
        $repo_config = '';
        $repo_config .= $this->fetchConfigPermissions($project, $repository, Git::PERM_READ);
        $repo_config .= $this->formatPermission(Git::PERM_READ, $this->getMirrorUserNames($repository));
        if ($this->isMigrationToGerritCompletedWithSuccess($repository)) {
            $key = new Git_RemoteServer_Gerrit_ReplicationSSHKey();
            $key->setGerritHostId($repository->getRemoteServerId());
            $repo_config .= $this->formatPermission(Git::PERM_WPLUS, array($key->getUserName()));
        } elseif (! $this->repositoryIsUsingFineGrainedPermissions($repository)) {
            $repo_config .= $this->fetchConfigPermissions($project, $repository, Git::PERM_WRITE);
            $repo_config .= $this->fetchConfigPermissions($project, $repository, Git::PERM_WPLUS);
        } else {
            $repo_config .= $this->getFineGrainedFormattedPermissions($repository);
        }
        return $repo_config;
    }

    private function getFineGrainedFormattedPermissions(GitRepository $repository)
    {
        $config = '';

        $all_permissions = $this->fine_grained_factory->getBranchesFineGrainedPermissionsForRepository($repository) +
            $this->fine_grained_factory->getTagsFineGrainedPermissionsForRepository($repository);

        foreach ($all_permissions as $permission) {
            $config .= $this->formatFineGrainedPermission($repository, $permission);
        }

        return $config;
    }

    private function getPatternInGitoliteFormat(FineGrainedPermission $permission, GitRepository $repository)
    {
        $formatted_pattern = str_replace('*', '.*', $permission->getPattern());
        $formatted_pattern = $this->addEndPatternCharacterWhenPermissionDoesntUseRegexp($repository, $formatted_pattern);

        return $formatted_pattern;
    }

    private function addEndPatternCharacterWhenPermissionDoesntUseRegexp(GitRepository $repository, $pattern)
    {
        if (! $this->regexp_retriever->areRegexpActivatedForRepository($repository)) {
            $pattern .= '$';
        }

        return $pattern;
    }

    private function formatFineGrainedPermission(GitRepository $repository, FineGrainedPermission $permission)
    {
        $pattern_config       = '';
        $pattern_for_gitolite = $this->getPatternInGitoliteFormat($permission, $repository);

        $pattern_config .= $this->getPatternConfiguration(
            $repository,
            $permission->getRewindersUgroup(),
            $pattern_for_gitolite,
            Git::PERM_WPLUS
        );

        $pattern_config .= $this->getPatternConfiguration(
            $repository,
            $permission->getWritersUgroup(),
            $pattern_for_gitolite,
            Git::PERM_WRITE
        );

        $pattern_config .= $this->removeAllUgroupForPattern($pattern_for_gitolite);

        return $pattern_config;
    }

    private function getPatternConfiguration(GitRepository $repository, array $ugroups, $pattern_for_gitolite, $type)
    {
        if ((count($ugroups) === 1 && $ugroups[0]->getId() == ProjectUGroup::NONE) ||
            count($ugroups) === 0
        ) {
            $pattern_config = '';
        } else {
            $pattern_config = $this->grantUgroupsForPattern(
                $repository->getProject(),
                $ugroups,
                $pattern_for_gitolite,
                $type
            );
        }

        return $pattern_config;
    }

    private function grantUgroupsForPattern(Project $project, array $ugroups, $pattern_for_gitolite, $permission_type)
    {
        $config             = '';
        $ugroup_literalizer = new UGroupLiteralizer();

        $ugroup_ids = array();
        foreach ($ugroups as $ugroup) {
            $ugroup_ids[] = $ugroup->getId();
        }

        $ugroup_names = $ugroup_literalizer->ugroupIdsToString($ugroup_ids, $project);

        $config .= rtrim(self::$permissions_types[$permission_type]) ." $pattern_for_gitolite = " . implode(' ', $ugroup_names) . PHP_EOL;

        return $config;
    }

    private function removeAllUgroupForPattern($pattern_for_gitolite)
    {
        return self::REMOVE_PERMISSION ."$pattern_for_gitolite = @all" . PHP_EOL;
    }

    private function repositoryIsUsingFineGrainedPermissions(GitRepository $repository)
    {
        return $this->fine_grained_retriever->doesRepositoryUseFineGrainedPermissions($repository);
    }

    private function isMigrationToGerritCompletedWithSuccess(GitRepository $repository) {
        return $repository->isMigratedToGerrit() &&
               $this->gerrit_status->getStatus($repository) === Git_Driver_Gerrit_ProjectCreatorStatus::DONE;
    }

    private function formatPermission($permission_type, array $granted) {
        if (count($granted)) {
            return self::$permissions_types[$permission_type] . ' = ' . implode(' ', $granted).PHP_EOL;
        }
        return '';
    }

    /**
     * Fetch the gitolite readable conf for permissions on a repository
     *
     * @return string
     */
    public function fetchConfigPermissions($project, $repository, $permission_type) {
        if (!isset(self::$permissions_types[$permission_type])) {
            return '';
        }
        $git_online_edit_conf_right = $this->getUserForOnlineEdition($repository);
        $ugroup_literalizer = new UGroupLiteralizer();
        $repository_groups  = $ugroup_literalizer->getUGroupsThatHaveGivenPermissionOnObject($project, $repository->getId(), $permission_type);
        if ($git_online_edit_conf_right) {
            $repository_groups[] = $git_online_edit_conf_right;
        }
        return $this->formatPermission($permission_type, $repository_groups);
    }

    private function getUserForOnlineEdition(GitRepository $repository) {
        if ($repository->hasOnlineEditEnabled()) {
            return 'id_rsa_gl-adm';
        }

        return '';
    }

    private function getMirrorUserNames(GitRepository $repository) {
        $names = array();
        foreach ($this->data_mapper->fetchAllRepositoryMirrors($repository) as $mirror) {
            $names[] = $mirror->owner->getUserName();
        }
        return $names;
    }
}
