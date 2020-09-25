<?php

declare(strict_types = 1);

namespace EcEuropa\Toolkit\TaskRunner\Commands;

use Composer\Semver\Semver;
use Symfony\Component\Console\Input\InputOption;
use OpenEuropa\TaskRunner\Commands\AbstractCommands;

/**
 * Generic tools.
 */
class ToolCommands extends AbstractCommands
{

    /**
     * {@inheritdoc}
     */
    public function getConfigurationFile()
    {
        return __DIR__ . '/../../../config/commands/tool.yml';
    }

    /**
     * Disable aggregation and clear cache.
     *
     * @command toolkit:disable-drupal-cache
     *
     * @return \Robo\Collection\CollectionBuilder
     *   Collection builder.
     */
    public function disableDrupalCache()
    {
        $tasks = [];

        $drush_bin = $this->getConfig()->get('runner.bin_dir') . '/drush';
        $tasks[] = $this->taskExecStack()
            ->stopOnFail()
            ->exec($drush_bin . ' -y config-set system.performance css.preprocess 0')
            ->exec($drush_bin . ' -y config-set system.performance js.preprocess 0')
            ->exec($drush_bin . ' cache:rebuild');

        // Build and return task collection.
        return $this->collectionBuilder()->addTaskList($tasks);
    }

    /**
     * Display toolkit notifications.
     *
     * @command toolkit:notifications
     *
     * @option endpoint The endpoint for the notifications
     */
    public function displayNotifications(array $options = [
        'endpoint' => InputOption::VALUE_OPTIONAL,
    ])
    {
        $endpointUrl = $options['endpoint'];

        if (isset($endpointUrl)) {
            $result = self::getQaEndpointContent($endpointUrl);
            $data = json_decode($result, true);
            foreach ($data as $notification) {
                $this->io()->warning($notification['title'] . PHP_EOL . $notification['notification']);
            }
        }//end if
    }

    /**
     * Check composer.json for components that are not whitelisted/blacklisted.
     *
     * @command toolkit:component-check
     *
     * @option endpoint The endpoint for the components whitelist/blacklist
     * @option blocker  Whether or not the command should exit with errorstatus
     */
    public function componentCheck(array $options = [
        'endpoint' => InputOption::VALUE_REQUIRED,
        'blocker' => InputOption::VALUE_REQUIRED,
        'test-command' => false,
    ])
    {
        // Currently undocumented in this class. Because I don't know how to
        // provide such a property to one single function other than naming the
        // failed property exaclty for this function.
        $this->componentCheckFailed = false;
        $blocker = $options['blocker'];
        $endpointUrl = $options['endpoint'];
        $basicAuth = getenv('QA_API_BASIC_AUTH') !== false ? getenv('QA_API_BASIC_AUTH') : '';
        $composerLock = file_get_contents('composer.lock') ? json_decode(file_get_contents('composer.lock'), true) : false;

        if (isset($endpointUrl) && isset($composerLock['packages'])) {
            $result = self::getQaEndpointContent($endpointUrl, $basicAuth);
            $data = json_decode($result, true);
            $modules = array_filter(array_combine(array_column($data, 'full_name'), $data));

            // To test this command execute it with the --test-command option:
            // ./vendor/bin/run toolkit:whitelist-components --test-command --endpoint-url="https://webgate.ec.europa.eu/fpfis/qa/api/v1/package-reviews?version=8.x"
            // Then we provide an array in the packages that fails on each type
            // of validation.
            if ($options['test-command']) {
                $composerLock['packages'] = [
                    // Lines below shoul trow a warning.
                    ['type' => 'drupal-module', 'version' => '1.0', 'name' => 'drupal/unreviewed'],
                    ['type' => 'drupal-module', 'version' => '1.0', 'name' => 'drupal/devel'],
                    ['type' => 'drupal-module', 'version' => '1.0-alpha1', 'name' => 'drupal/xmlsitemap'],
                    // Allowed for single project jrc-k4p, otherwise trows warning.
                    ['type' => 'drupal-module', 'version' => '1.0', 'name' => 'drupal/active_facet_pills'],
                    // Allowed dev version if the Drupal version meets the
                    // conflict version constraints.
                    [
                        'version' => 'dev-1.x',
                        'type' => 'drupal-module',
                        'name' => 'drupal/views_bulk_operations',
                        'extra' => [
                            'drupal' => [
                                'version' => '8.x-3.4+15-dev'
                            ]
                        ]
                    ]
                ];
            }

            // Loop over the require section.
            foreach ($composerLock['packages'] as $package) {
                // Check if it's a drupal package.
                // NOTE: Currently only supports drupal pagackages :(.
                if (substr($package['name'], 0, 7) === 'drupal/') {
                    $this->validateComponent($package, $modules);
                }
            }

            return $this->returnStatus($blocker);
        }//end if
    }

    /**
     * Helper function to return the status code.
     *
     * @param bool $blocker Whether or not to exit 1.
     *
     * @return bool
     */
    protected function returnStatus($blocker)
    {
        // If the validation failed and we have a blocker, then return 1.
        if ($this->componentCheckFailed && $blocker) {
            $this->io()->error('Failed the components whitelist check. Please contact the QA team.');
            return 1;
        }

        // If the validation failed and blocker was disabled, then return 0.
        if ($this->componentCheckFailed && !$blocker) {
            $this->io()->warning('Failed the components whitelist check. Please contact the QA team.');
            return 0;
        }
    }

    /**
     * Helper function to validate the component.
     *
     * @param array $package The package to validate.
     * @param array $modules The modules list.
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     *
     * @return void
     */
    protected function validateComponent($package, $modules)
    {
        $packageName = $package['name'];
        $hasBeenQaEd = isset($modules[$packageName]);
        $wasRejected = isset($modules[$packageName]['restricted_use']) && $modules[$packageName]['restricted_use'] !== '0';
        $wasNotRejected = isset($modules[$packageName]['restricted_use']) && $modules[$packageName]['restricted_use'] === '0';
        $packageVersion = isset($package['extra']['drupal']['version']) ? explode('+', str_replace('8.x-', '', $package['extra']['drupal']['version']))[0] : $package['version'];

        // Only validate module components for this time.
        if (isset($package['type']) && $package['type'] === 'drupal-module') {
            // If module was not reviewed yet.
            if (!$hasBeenQaEd) {
                $this->say("Package $packageName:$packageVersion has not been reviewed by QA.");
                $this->componentCheckFailed = true;
            }

            // If module was rejected.
            if ($hasBeenQaEd && $wasRejected) {
                $projectId = $this->getConfig()->get("toolkit.project_id");
                $allowedInProject = in_array($projectId, array_map('trim', explode(',', $modules[$packageName]['restricted_use'])));
                // If module was not allowed in project.
                if (!$allowedInProject) {
                    $this->say("Package $packageName:$packageVersion has been rejected by QA.");
                    $this->componentCheckFailed = true;
                }
            }

            if ($wasNotRejected) {
                # Once all projects are using Toolkit >=4.1.0, the 'version' key
                # may be removed from the endpoint: /api/v1/package-reviews.
                $constraints = [ 'whitelist' => false, 'blacklist' => true ];
                foreach ($constraints as $constraint => $result) {
                    $constraintValue = !empty($modules[$packageName][$constraint]) ? $modules[$packageName][$constraint] : null;

                    if (!is_null($constraintValue) && Semver::satisfies($packageVersion, $constraintValue) === $result) {
                        $this->say("Package $packageName:$packageVersion does not meet the $constraint version constraint: $constraintValue.");
                        $this->componentCheckFailed = true;
                    }
                }
            }
        }
    }

    /**
     * Curl function to access endpoint with or without authentication.
     *
     * This function is made publicly available as a static function for other
     * projects to call. Then we have to maintain less code.
     *
     * @SuppressWarnings(PHPMD.MissingImport)
     *
     * @param string $url The QA endpoint url.
     * @param string $basicAuth The basic auth.
     *
     * @return string
     */
    public static function getQaEndpointContent(string $url, string $basicAuth = ''): string
    {
        $content = '';
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        if ($basicAuth !== '') {
            $header = ['Authorization: Basic ' . $basicAuth];
            curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
        }
        $result = curl_exec($curl);

        if ($result !== false) {
            $statusCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            switch ($statusCode) {
                // Upon success set the content to be returned.
                case 200:
                    $content = $result;
                    break;
                // Upon other status codes.
                default:
                    if ($basicAuth === '') {
                        throw new \Exception(sprintf('Curl request to endpoint "%s" returned a %u.', $url, $statusCode));
                    }
                    // If we tried with authentication, retry without.
                    $content = self::getQaEndpointContent($url);
            }
        }
        if ($result === false) {
            throw new \Exception(sprintf('Curl request to endpoint "%s" failed.', $url));
        }
        curl_close($curl);

        return $content;
    }
}
