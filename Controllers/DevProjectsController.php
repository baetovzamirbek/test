<?php

/**
 * Created by PhpStorm.
 * User: USER
 * Date: 09.10.2014
 * Time: 15:33
 */
class DevProjectsController extends AbstractController
{
  const PROJECTS_ROOT = '/mnt/www/';

  public function initialize()
  {
    $this->view->setLayout("dev-projects");
  }

  public function getBranch($project)
  {
    $projectsRoot = self::PROJECTS_ROOT;
    exec("cd $projectsRoot/$project && git branch | grep \*", $_branch, $return);
    return isset($_branch[0]) ? $_branch[0] : null;
  }

  public function getLastCommitMessage($project)
  {
    $projectsRoot = self::PROJECTS_ROOT;
    exec("cd $projectsRoot/$project && git log -1 --pretty=%B", $_message, $return);
    return trim(implode(' ', $_message));
  }

  public function getProjects()
  {
    $projectsRoot = self::PROJECTS_ROOT;
    exec("ls $projectsRoot | grep '.growave.io'", $_projects, $return);
    return $_projects;
  }

  public function indexAction()
  {
    $_projects = $this->getProjects();
    $projects = [];
    foreach ($_projects as $project) {
      $branch = $this->getBranch($project);
      if ($branch) {
        $projects[] = [
          "name" => $project,
          "branch" => $branch,
          "last_commit" => $this->getLastCommitMessage($project),
        ];
      }
    }
    $this->view->setVar('projects', $projects);
    //print_die($output);
  }

  public function gitResetAction()
  {
    if ($this->request->isPost()) {
      $projectsRoot = self::PROJECTS_ROOT;
      $_projects = $this->getProjects();
      $project_name = $this->request->getPost('project_name');
      $branch = $this->getBranch($project_name);
      $branch = trim(str_replace('*', '', $branch));
      if (in_array($project_name, $_projects)) {
        exec("cd $projectsRoot/$project_name && git clean -df; git fetch --all; git reset --hard origin/$branch;", $output, $return);
        exit(json_encode([
          "status" => true,
          "message" => implode("\n", $output)
        ]));
      }
    }
    exit(json_encode([
      "status" => false,
      "message" => "Bad request!"
    ]));
  }

  public function checkoutAction()
  {
    if ($this->request->isPost()) {
      $projectsRoot = self::PROJECTS_ROOT;
      $_projects = $this->getProjects();
      $project_name = $this->request->getPost('project_name');
      $branch = $this->request->getPost('branch');
      $branch = str_replace('remotes/origin/', '', $branch);
      if (in_array($project_name, $_projects)) {
        exec("cd $projectsRoot/$project_name && git reset --hard; git clean -df;");
        exec("cd $projectsRoot/$project_name && git checkout {$branch} -f && git pull", $output, $return);
        exit(json_encode([
          "status" => true,
          "message" => implode("\n", $output)
        ]));
      }
    }
    exit(json_encode([
      "status" => false,
      "message" => "Bad request!"
    ]));
  }

  public function loadBranchesAction()
  {
    if ($this->request->isPost()) {
      $projectsRoot = self::PROJECTS_ROOT;
      $_projects = $this->getProjects();
      $project_name = $this->request->getPost('project_name');
      if (in_array($project_name, $_projects)) {
        exec("cd $projectsRoot/$project_name && git fetch");
        exec("cd $projectsRoot/$project_name && git branch -a", $output, $return);
        $branches = [];
        foreach ($output as $item) {
          $branchName = trim($item);
          $isActive = false;
          if ($branchName[0] == '*') {
            $isActive = true;
            $branchName = ltrim($branchName, '*');
          }
          $branchName = trim($branchName);
          $branchName = explode(' ', $branchName)[0];
          $branches[] = [
            "name" => $branchName,
            "active" => $isActive
          ];
        }
        exit(json_encode([
          "status" => true,
          "branches" => $branches
        ]));
      }
    }
    exit(json_encode([
      "status" => false,
      "message" => "Bad request!"
    ]));
  }

  public function updateVendorAction()
  {
    if ($this->request->isPost()) {
      $projectsRoot = self::PROJECTS_ROOT;
      $_projects = $this->getProjects();
      $project_name = $this->request->getPost('project_name');
      if (in_array($project_name, $_projects)) {
        exec("cd $projectsRoot/$project_name && rm -r vendor && unzip -qq vendor.zip", $output, $return);

        exit(json_encode([
          "status" => true,
          "message" => "Successfully updated from vendor.zip"
        ]));
      }
    }

    exit(json_encode([
      "status" => false,
      "message" => "Bad request!"
    ]));
  }

  public function updateConfigsAction()
  {
    if ($this->request->isPost()) {
      exec("/mnt/www/deploy-config/py3env/bin/python34 /mnt/www/deploy-config/boto.py --ssw", $output, $var);

      exit(json_encode([
        "status" => true,
        "message" => implode("\n", $output)
      ]));
    }

    exit(json_encode([
      "status" => false,
      "message" => "Bad request!"
    ]));
  }
}