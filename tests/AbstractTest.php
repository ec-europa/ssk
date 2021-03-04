<?php

declare(strict_types = 1);

namespace EcEuropa\Toolkit\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Yaml;

/**
 * Abstract test class for Toolkit commands.
 */
abstract class AbstractTest extends TestCase
{

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        if (!is_dir($this->getSandboxRoot())) {
            mkdir($this->getSandboxRoot());
        }
        $filesystem = new Filesystem();
        $filesystem->chmod($this->getSandboxRoot(), 0777, umask(), true);
        $filesystem->remove(glob($this->getSandboxRoot() . '/*'));
        $filesystem->copy($this->getFixtureFilepath('samples/sample-dump.sql'), $this->getSandboxFilepath('dump.sql'));
        $filesystem->copy($this->getFixtureFilepath('samples/sample-config.yml'), $this->getSandboxFilepath('config.yml'));
    }

    /**
     * Helper function to assert contain / not contain expectations.
     *
     * @param string $content
     *   Content to test.
     * @param array $expected
     *   Content expected.
     */
    protected function assertContainsNotContains($content, array $expected)
    {
        if (!empty($expected['contains'])) {
            $this->assertContains($this->trimEachLine($expected['contains']), [$this->trimEachLine($content)]);
            $this->assertEquals(substr_count($this->trimEachLine($content), $this->trimEachLine($expected['contains'])), 1, 'String found more than once.');
        }

        if (!empty($expected['not_contains'])) {
            $this->assertNotContains($this->trimEachLine($expected['not_contains']), $this->trimEachLine($content));
        }
    }

    /**
     * Trim each line of a blob of text, used when asserting on multiline strings.
     *
     * @param string $text
     *   Untrimmed text.
     *
     * @return string
     *   Trimmed text.
     */
    protected function trimEachLine($text)
    {
        return implode(PHP_EOL, array_map('trim', explode(PHP_EOL, $text)));
    }

    /**
     * Get class loader.
     *
     * @return \Composer\Autoload\ClassLoader
     *   Class loader of vendor.
     */
    protected function getClassLoader()
    {
        return require __DIR__ . '/../vendor/autoload.php';
    }

    /**
     * Get fixture root.
     *
     * @return mixed
     *   A set of test data.
     */
    protected function getFixtureRoot()
    {
        return __DIR__ . '/fixtures';
    }

    /**
     * Get fixture filepath.
     *
     * @param string $name
     *   A name of Sandbox.
     *
     * @return string
     *   The filepath of the sandbox.
     */
    protected function getFixtureFilepath($name)
    {
        return $this->getFixtureRoot() . '/' . $name;
    }

    /**
     * Get fixture content.
     *
     * @param string $filepath
     *   File path.
     *
     * @return mixed
     *   A set of test data.
     */
    protected function getFixtureContent($filepath)
    {
        return Yaml::parse(file_get_contents($this->getFixtureFilepath($filepath)));
    }

    /**
     * Get sandbox filepath.
     *
     * @param string $name
     *   A name of Sandbox.
     *
     * @return string
     *   The filepath of the sandbox.
     */
    protected function getSandboxFilepath($name)
    {
        return $this->getSandboxRoot() . '/' . $name;
    }

    /**
     * Get sandbox root path.
     *
     * @return string
     *   The filepath of sandbox.
     */
    protected function getSandboxRoot()
    {
        return __DIR__ . '/sandbox';
    }
}
