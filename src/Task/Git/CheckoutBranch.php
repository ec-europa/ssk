<?php

namespace EcEuropa\Toolkit\Task\Git;

use Gitonomy\Git\Repository;
use Robo\Contract\BuilderAwareInterface;
use Robo\ResultData;
use Robo\Task\BaseTask;
use Robo\TaskAccessor;

/**
 * Checkout local branch tracking its remote counterpart, if any.
 */
class CheckoutBranch extends BaseTask implements BuilderAwareInterface {

  use \Robo\Task\Vcs\loadTasks;
  use TaskAccessor;

  /**
   * Branch name.
   *
   * @var string
   */
  protected $branchName;

  /**
   * Remote name, default to 'origin'.
   *
   * @var string
   */
  protected $remote = 'origin';

  /**
   * Current working directory.
   *
   * @var string
   */
  protected $workingDir = '.';

  /**
   * When true fail task if local branch does not have a remote counterpart.
   *
   * @var bool
   */
  protected $strict = FALSE;

  /**
   * CheckoutBranch constructor.
   *
   * @param string $branchName
   *   Branch name.
   */
  public function __construct($branchName) {
    $this->branchName = $branchName;
  }

  /**
   * {@inheritdoc}
   */
  public function run() {
    $tasks = [];

    if ($this->strict) {
      $this->printTaskDebug('Running in strict mode.');
    }

    // Fail a command ran in strict mode if remote branch does not exist.
    if ($this->strict && !$this->hasRemoteBranch()) {
      $message = "Remote branch '$this->branchName' does not exists.";
      $this->printTaskError($message);
      return new ResultData(ResultData::EXITCODE_ERROR, $message);
    }

    $command = ['checkout', '-b', $this->branchName];
    if ($this->hasRemoteBranch()) {
      $this->printTaskDebug("Tracking remote branch: $this->remote/$this->branchName.");
      $command += ['--track', "$this->remote/$this->branchName"];
    }

    $tasks[] = $this->taskGitStack()
      ->stopOnFail()
      ->printOutput(FALSE)
      ->printMetadata(TRUE)
      ->dir($this->workingDir)
      ->exec($command);

    return $this->collectionBuilder()->addTaskList($tasks)->run();
  }

  /**
   * Set remote name, defaults to 'origin'.
   *
   * @param string $remote
   *   Remote name.
   *
   * @return CheckoutBranch
   *   Current task object.
   */
  public function remote(string $remote): CheckoutBranch {
    $this->remote = $remote;
    return $this;
  }

  /**
   * Set working directory.
   *
   * @param string $workingDir
   *   Working directory.
   *
   * @return CheckoutBranch
   *   Current task object.
   */
  public function workingDir(string $workingDir): CheckoutBranch {
    $this->workingDir = $workingDir;
    return $this;
  }

  /**
   * Enable strict mode.
   *
   * @param bool $strict
   *   Strict mode.
   *
   * @return \EcEuropa\Toolkit\Task\Git\CheckoutBranch
   *   Current task object.
   */
  public function strict(bool $strict): CheckoutBranch {
    $this->strict = $strict;
    return $this;
  }

  /**
   * Initialize and get Git repository.
   *
   * @return \Gitonomy\Git\Repository
   *   Git repository instance.
   */
  protected function getRepository(): Repository {
    return new Repository($this->workingDir, [
      'debug'  => TRUE,
      'logger' => $this->logger,
    ]);
  }

  /**
   * Check if remote branch exists.
   *
   * @return bool
   *   Check whereas related remote branch exists.
   */
  protected function hasRemoteBranch(): bool {
    return $this->getRepository()
      ->getReferences()
      ->hasRemoteBranch("{$this->remote}/{$this->branchName}");
  }

}
