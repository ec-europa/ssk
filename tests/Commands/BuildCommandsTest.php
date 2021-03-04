<?php

declare(strict_types = 1);

namespace EcEuropa\Toolkit\Tests\Commands;

use EcEuropa\Toolkit\Tests\AbstractTest;
use OpenEuropa\TaskRunner\TaskRunner;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Yaml\Yaml;

/**
 * Test Toolkit build commands.
 */
class BuildCommandsTest extends AbstractTest
{

    /**
     * Data provider for testBuild.
     *
     * @return array
     *   An array of test data arrays with assertations.
     */
    public function dataProvider()
    {
        return $this->getFixtureContent('commands/build.yml');
    }

    /**
     * Test "toolkit:build-*" commands.
     *
     * @param mixed $command
     *   A command.
     * @param array $config
     *   A configuration.
     * @param array $expected
     *   Tests expected.
     *
     * @dataProvider dataProvider
     */
    public function testBuild($command, array $config, array $expected)
    {
        // Setup test Task Runner configuration file.
        $configFile = $this->getSandboxFilepath('runner.yml');
        file_put_contents($configFile, Yaml::dump($config));

        // Run command.
        $input = new StringInput($command . ' --simulate');
        $output = new BufferedOutput();
        $runner = new TaskRunner($input, $output, $this->getClassLoader());
        $runner->run();

        // Assert expectations.
        $content = $output->fetch();
        foreach ($expected as $row) {
            $this->assertContainsNotContains($content, $row);
        }
    }
}
