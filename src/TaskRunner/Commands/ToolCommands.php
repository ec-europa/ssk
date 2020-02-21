<?php

declare(strict_types = 1);

namespace EcEuropa\Toolkit\TaskRunner\Commands;

use Symfony\Component\Console\Input\InputOption;
use OpenEuropa\TaskRunner\Commands\AbstractCommands;

/**
 * Generic tools.
 */
class ToolCommands extends AbstractCommands
{

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

        $tasks[] = $this->taskExecStack()
            ->stopOnFail()
            ->exec('./vendor/bin/drush -y config-set system.performance css.preprocess 0')
            ->exec('./vendor/bin/drush -y config-set system.performance js.preprocess 0')
            ->exec('./vendor/bin/drush cache:rebuild');

        // Build and return task collection.
        return $this->collectionBuilder()->addTaskList($tasks);
    }

    /**
     * Display toolkit notifications.
     *
     * @command toolkit:notifications
     *
     * @option endpoint-url The endpoint for the notifications
     */
    public function displayNotifications(array $options = [
        'endpoint-url' => InputOption::VALUE_OPTIONAL,
    ])
    {
        $endpointUrl = isset($options['endpoint-url']) ? $options['endpoint-url'] : $this->getConfig()->get("toolkit.notifications_endpoint");

        if (isset($endpointUrl)) {
            $result = $this->getQaEndpointContent($endpointUrl);
            $data = json_decode($result, true);
            foreach ($data as $notification) {
                $this->io()->warning($notification['title'] . PHP_EOL . $notification['notification']);
            }
        }//end if
    }

    /**
     * Check composer.json for components that are not whitelisted.
     *
     * @command toolkit:whitelist-components
     *
     * @option endpoint-url The endpoint for the notifications
     */
    public function whitelistComponents(array $options = [
        'endpoint-url' => InputOption::VALUE_OPTIONAL,
    ])
    {
        $endpointUrl = isset($options['endpoint-url']) ? $options['endpoint-url'] : $this->getConfig()->get("toolkit.whitelistings_endpoint");
        $basicAuth = getenv('QA_API_BASIC_AUTH') !== false ? getenv('QA_API_BASIC_AUTH') : '';
        $composerLock = file_get_contents('composer.lock') ? json_decode(file_get_contents('composer.lock'), true) : false;

        if (isset($endpointUrl) && isset($composerLock['packages'])) {
            $result = $this->getQaEndpointContent($endpointUrl, $basicAuth);
            $data = json_decode($result, true);
            $modules = array_filter(array_combine(array_column($data, 'name'), $data));

            // Loop over the require section.
            foreach ($composerLock['packages'] as $package) {
                // Check if it's a drupal package.
                // NOTE: Currently only supports drupal pagackages :(.
                $name = $package['name'];
                if (substr($name, 0, 7) === 'drupal/') {
                    $moduleName = str_replace('drupal/', '', $name);
                    if (!in_array($name, $modules)) {
                        $this->io()->warning('The package ' . $name . ' has not been approved by QA to be in your require section of composer.json.');
                    }
                }
            }
        }//end if
    }

    /**
     * Curl function to access endpoint with or without authentication.
     *
     * @SuppressWarnings(PHPMD.MissingImport)
     *
     * @param string $url The QA endpoint url.
     * @param string $basicAuth The basic auth.
     *
     * @return string
     */
    public function getQaEndpointContent(string $url, string $basicAuth = ''): string
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
                    $content = $this->getQaEndpointContent($url);
            }
        }
        if ($result === false) {
            throw new \Exception(sprintf('Curl request to endpoint "%s" failed.', $url));
        }
        curl_close($curl);

        return $content;
    }
}
